<?php
/**
 * Paytm Business, paid by scanning a UPI QR.
 *
 * Paytm Business collects into a UPI id, so there is nothing to type: the
 * customer is shown a QR built from the UPI intent - payee, amount and our
 * own order code in the `tr` field - and pays it in whichever UPI app is on
 * their phone.
 *
 * That `tr` value is what ties the money back to the order. Paytm records it
 * as its own ORDERID, so the order-status endpoint can be asked about exactly
 * that id and the answer needs no matching by hand. A success for the full
 * amount marks the order paid by itself; anything else leaves the order where
 * it is with what Paytm said written into its log, because the alternative is
 * finishing an order nobody paid for.
 *
 * Needs the MID and the business UPI id, both from the Paytm dashboard. There
 * is no merchant key here and nothing is signed: this reads a status, it never
 * asks Paytm to move money.
 */
require_once APP_PATH . '/helpers/currency.php';

/**
 * Where the status of one order is read from.
 *
 * A variable rather than a literal inside the closures so the two that need
 * it cannot drift apart.
 */
$statusEndpoint = 'https://securegw.paytm.in/order/status';

/**
 * What to ask for, in rupees.
 *
 * UPI settles in INR and nothing else, so a shop priced in anything else has
 * to convert - and with no rate on record there is no honest figure to put in
 * the QR. That is the one case this refuses outright rather than guessing.
 *
 * @return array{ok: bool, inr: float, rate: float, message: string}
 */
$rupees = static function (array $order): array {
    $price = (float) $order['price'];

    if (strtoupper((string) base_currency()['code']) === 'INR') {
        return ['ok' => true, 'inr' => round($price, 2), 'rate' => 1.0, 'message' => ''];
    }

    // rate_to_base says how many of ours one rupee is worth, so dividing is
    // what turns our price back into rupees.
    $rate = currency_rate('INR');

    if ($rate <= 0) {
        return ['ok' => false, 'inr' => 0.0, 'rate' => 0.0,
                'message' => 'Paytm collects in rupees and there is no INR rate on record. '
                           . 'Add INR under Currencies, then try again.'];
    }

    return ['ok' => true, 'inr' => round($price / $rate, 2), 'rate' => $rate, 'message' => ''];
};

/**
 * The order code with nothing in it a UPI `tr` field might refuse.
 *
 * Codes read GK-8F42KD; the letters and digits alone are still unique, and
 * both the QR and the status lookup use this same spelling, or the lookup is
 * about an id Paytm never saw.
 */
$reference = static fn (array $order): string
    => (string) preg_replace('/[^A-Za-z0-9]/', '', (string) $order['code']);

return [
    'name'  => 'Paytm Business (UPI QR)',
    'blurb' => 'Shows a UPI QR for the exact amount and watches Paytm for the payment. '
             . 'Confirms the order itself when the money lands - no transaction id to type.',
    'kind'  => 'redirect',

    'fields' => [
        'merchant_id' => [
            'label'    => 'Merchant ID (MID)',
            'required' => true,
            'hint'     => 'From your Paytm Business dashboard. This is what the payment is '
                        . 'looked up against, so a wrong one means nothing ever confirms.',
        ],
        'upi_id' => [
            'label'       => 'Business UPI ID',
            'required'    => true,
            'placeholder' => 'yourbusiness@paytm',
            'hint'        => 'The UPI id the money goes to. It is printed into the QR.',
        ],
        'payee_name' => [
            'label'       => 'Name shown in the UPI app',
            'placeholder' => 'Your shop name',
            'hint'        => 'Left empty, the site name is used.',
        ],
    ],

    'instructions' => function (array $method, array $order) use ($rupees): string {
        $want = $rupees($order);

        return $want['ok']
            ? 'Scan the Paytm QR with any UPI app and pay ' . money_in($want['inr'], 'INR')
            . '. The order confirms itself the moment Paytm reports the payment - there is '
            . 'nothing to type and nothing to send us.'
            : $want['message'];
    },

    // Our own page, not Paytm's: the QR is drawn there and the waiting is done
    // there, so the customer never leaves the shop.
    'start' => function (array $method, array $order): array {
        return ['redirect' => url('order/' . $order['code'] . '/qr')];
    },

    /**
     * What the hosted QR page draws.
     *
     * `tn` is left out on purpose - several UPI apps refuse a note with
     * punctuation in it, and the order code is already in `tr`.
     */
    'qr' => function (array $method, array $order) use ($rupees, $reference): array {
        $config = payment_config($method);
        $upi    = trim((string) ($config['upi_id'] ?? ''));

        if ($upi === '') {
            return ['error' => 'Paytm is not configured yet - no UPI id was saved.'];
        }

        // A UPI id is name@handle out of a narrow alphabet. Checked because
        // this one string ends up inside a URI the page hands to a phone, and
        // because a mistyped one would otherwise collect nothing in silence.
        if (!preg_match('/^[A-Za-z0-9.\-_]{2,60}@[A-Za-z0-9.\-]{2,40}$/', $upi)) {
            return ['error' => 'The saved Paytm UPI id does not look like one '
                             . '(it should read something like yourbusiness@paytm).'];
        }

        $want = $rupees($order);
        if (!$want['ok']) {
            return ['error' => $want['message']];
        }

        // A long shop name is the only part of this that can grow, and the QR
        // encoder tops out at 271 bytes, so it is the part that gets clipped.
        $payee = trim((string) ($config['payee_name'] ?? ''));
        if ($payee === '') {
            $payee = (string) setting('site_name', 'SMM Panel');
        }

        // Built by hand rather than with http_build_query: that escapes the @
        // in the UPI id to %40, and a handful of UPI apps take the parameter
        // literally and look for a payee nobody has. Only the shop name can
        // hold a space or an ampersand, so only it is encoded; the rest are
        // already down to digits, letters and the @.
        $query = [
            'pa' => $upi,
            'pn' => rawurlencode(mb_substr($payee, 0, 24)),
            'am' => number_format($want['inr'], 2, '.', ''),
            'cu' => 'INR',
            'tr' => $reference($order),
        ];

        // `mc` is nominally a merchant category code, and what goes in it here
        // is the MID - which is what Paytm Business QR integrations in the
        // wild send and what the money is attributed by. Drop this line if a
        // UPI app ever refuses the intent over it: nothing else depends on it,
        // because the payee is `pa` and the order is matched on `tr`.
        $merchant = preg_replace('/[^A-Za-z0-9]/', '', (string) ($config['merchant_id'] ?? ''));
        if ($merchant !== '') {
            $query['mc'] = $merchant;
        }

        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return [
            'text'      => 'upi://pay?' . implode('&', $pairs),
            'amount'    => money_in($want['inr'], 'INR'),
            'reference' => $reference($order),
            'caption'   => 'Scan with any UPI app - Paytm, PhonePe, Google Pay or your bank app.',
        ];
    },

    /**
     * Ask Paytm what happened to this order.
     *
     * The amount is checked as well as the status: a success for less than was
     * asked for is somebody paying a different figure into the same QR, and
     * quietly treating that as payment in full is how a shop delivers for
     * nothing. It is left for a person, with both figures in the order log.
     */
    'poll' => function (array $method, array $order) use ($rupees, $reference, $statusEndpoint): array {
        $config   = payment_config($method);
        $merchant = trim((string) ($config['merchant_id'] ?? ''));

        if ($merchant === '') {
            return ['state' => 'waiting',
                    'message' => 'Paytm has no merchant id saved, so nothing can be looked up.'];
        }

        $want = $rupees($order);
        if (!$want['ok']) {
            return ['state' => 'waiting', 'message' => $want['message']];
        }

        $url = $statusEndpoint . '?' . http_build_query([
            'JsonData' => json_encode(['MID' => $merchant, 'ORDERID' => $reference($order)]),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Verification stays on. A payment status read over a connection
            // nobody checked is a payment status anybody in the way can write.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'SMMPanel/1.0',
        ]);

        $body  = curl_exec($ch);
        $errNo = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Unreachable is not unpaid: say so and let the next check decide.
        if ($errNo !== 0) {
            return ['state' => 'waiting', 'message' => 'Could not reach Paytm: ' . $error];
        }
        if ($code >= 400) {
            return ['state' => 'waiting', 'message' => 'Paytm returned HTTP ' . $code . '.'];
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return ['state' => 'waiting',
                    'message' => 'Paytm sent a reply we could not read: '
                               . mb_substr((string) $body, 0, 180)];
        }

        $status = strtoupper(trim((string) ($data['STATUS'] ?? '')));
        $paid   = round((float) ($data['TXNAMOUNT'] ?? 0), 2);
        $txn    = trim((string) ($data['TXNID'] ?? ''));

        if ($status === 'TXN_SUCCESS') {
            if ($paid + 0.01 < $want['inr']) {
                return [
                    'state'   => 'waiting',
                    'message' => 'Paytm reports ' . money_in($paid, 'INR') . ' against the '
                               . money_in($want['inr'], 'INR') . ' this order asks for, so it '
                               . 'has been left for us to check.',
                    'summary' => 'Paytm: paid ' . money_in($paid, 'INR') . ', expected '
                               . money_in($want['inr'], 'INR')
                               . ($txn !== '' ? ', transaction ' . $txn : ''),
                ];
            }

            return [
                'state'     => 'paid',
                'reference' => $txn !== '' ? $txn : $reference($order),
                // Our books are kept in the base currency, so the rupees come
                // back out at the rate the figure was worked out with.
                'amount'    => round($paid * $want['rate'], 2),
                'summary'   => 'Paytm confirmed ' . money_in($paid, 'INR')
                             . ($txn !== '' ? ', transaction ' . $txn : ''),
                'message'   => 'Payment received. Your order is on its way.',
            ];
        }

        if ($status === 'TXN_FAILURE') {
            return [
                'state'   => 'failed',
                'message' => trim((string) ($data['RESPMSG'] ?? '')) !== ''
                    ? (string) $data['RESPMSG']
                    : 'Paytm says that payment did not go through.',
            ];
        }

        // No STATUS at all is Paytm complaining about the question rather than
        // answering it - a wrong MID reads exactly like this. Said once into
        // the order log, because otherwise a shop that will never confirm
        // anything looks identical to a shop nobody has paid yet.
        if ($status === '' && trim((string) ($data['RESPMSG'] ?? '')) !== '') {
            return [
                'state'   => 'waiting',
                'message' => '',
                'summary' => 'Paytm would not answer about this order: ' . $data['RESPMSG']
                           . ' (code ' . (string) ($data['RESPCODE'] ?? '?') . '). '
                           . 'Check the merchant id on the payment method.',
            ];
        }

        // PENDING, or an id Paytm has never seen because nobody has scanned
        // the QR yet. Both mean the same thing here: keep waiting.
        return ['state' => 'waiting', 'message' => ''];
    },
];
