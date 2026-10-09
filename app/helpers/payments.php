<?php
/**
 * Payment gateways.
 *
 * A gateway is one file in app/payments/ that returns a manifest. Dropping a
 * file in registers it - nothing here keeps a list, so adding a gateway never
 * means editing a file that already exists.
 *
 * A manifest looks like this:
 *
 *   return [
 *     'name'    => 'Paytm',
 *     'blurb'   => 'One line for the admin.',
 *     'kind'    => 'manual' | 'redirect',
 *     'fields'  => [                       // what the admin fills in
 *        'merchant_id' => ['label' => 'Merchant ID', 'required' => true],
 *        'secret'      => ['label' => 'Secret', 'type' => 'password'],
 *     ],
 *     'instructions' => fn(array $method, array $order): string => '...',
 *     'start'        => fn(array $method, array $order): array  => ['redirect' => $url],
 *     'verify'       => fn(array $method, array $order, array $input): array
 *                        => ['ok' => true, 'reference' => '...', 'message' => '...'],
 *   ];
 *
 * 'manual' gateways are the bank-transfer kind: show account details, take a
 * transaction id, a human confirms it. 'redirect' gateways send the customer
 * away to pay and are confirmed when they come back.
 *
 * Only 'name' is required. Anything a gateway leaves out falls back to the
 * manual behaviour, which is why the simplest gateway file is three lines.
 */

/** Every gateway, keyed by its file name. */
function payment_gateways(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }

    $cache = [];

    foreach (glob(APP_PATH . '/payments/*.php') ?: [] as $file) {
        $key = basename($file, '.php');

        // The key ends up in a form value and a database column.
        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            continue;
        }

        $manifest = require $file;
        if (!is_array($manifest) || empty($manifest['name'])) {
            continue;
        }

        $cache[$key] = $manifest + [
            'key'    => $key,
            'blurb'  => '',
            'kind'   => 'manual',
            'fields' => [],
        ];
    }

    ksort($cache);
    return $cache;
}

/** One gateway, or null when the file has been removed. */
function payment_gateway(string $key): ?array
{
    return payment_gateways()[$key] ?? null;
}

/** [key => name] for a select. */
function payment_gateway_options(): array
{
    $options = [];
    foreach (payment_gateways() as $key => $gateway) {
        $options[$key] = $gateway['name'];
    }
    return $options;
}

/** The saved settings for one payment method, as an array. */
function payment_config(array $method): array
{
    $config = json_decode((string) ($method['config'] ?? ''), true);
    return is_array($config) ? $config : [];
}

/**
 * What the customer should be told, for a method that takes payment by hand.
 *
 * A gateway can replace this wholesale; most do not need to.
 */
function payment_instructions(array $method, array $order): string
{
    $gateway = payment_gateway((string) ($method['driver'] ?? 'manual'));

    if ($gateway && isset($gateway['instructions']) && is_callable($gateway['instructions'])) {
        return (string) ($gateway['instructions'])($method, $order);
    }

    return (string) ($method['instructions'] ?? '');
}

/**
 * Begin a payment.
 *
 * Returns ['redirect' => url] to send the customer away, or an empty array to
 * stay on the order page and take a transaction id as usual.
 */
function payment_start(array $method, array $order): array
{
    $gateway = payment_gateway((string) ($method['driver'] ?? 'manual'));

    if ($gateway && isset($gateway['start']) && is_callable($gateway['start'])) {
        $result = ($gateway['start'])($method, $order);
        return is_array($result) ? $result : [];
    }

    return [];
}

/**
 * Check what the customer submitted.
 *
 * The default is the manual flow: any non-empty reference is accepted here and
 * a human confirms it in the admin. A gateway that can really verify a payment
 * says so by returning ok => true with a reference.
 *
 * @return array{ok: bool, reference: string, message: string, confirmed: bool}
 */
function payment_verify(array $method, array $order, array $input): array
{
    $gateway = payment_gateway((string) ($method['driver'] ?? 'manual'));

    if ($gateway && isset($gateway['verify']) && is_callable($gateway['verify'])) {
        $result = ($gateway['verify'])($method, $order, $input);
        return is_array($result) ? $result + [
            'ok' => false, 'reference' => '', 'message' => '', 'confirmed' => false,
        ] : ['ok' => false, 'reference' => '', 'message' => 'The gateway said nothing.', 'confirmed' => false];
    }

    $reference = trim((string) ($input['trx_id'] ?? ''));

    if (setting('require_trx_id', '1') === '1' && mb_strlen($reference) < 4) {
        return ['ok' => false, 'reference' => '', 'confirmed' => false,
                'message' => 'Please enter the transaction id from your payment receipt.'];
    }

    return ['ok' => true, 'reference' => $reference, 'confirmed' => false, 'message' => ''];
}

/**
 * The one gateway we can send a customer straight to, if there is exactly one.
 *
 * A redirect gateway takes the payment itself, so there is nothing for the
 * customer to read first. With several, picking for them would be wrong; with
 * none, the order page is where the account numbers and the reference box
 * live. Either way the answer is "show the page".
 */
function sole_redirect_method(): ?array
{
    $found = null;

    foreach (all('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, id') as $method) {
        $gateway = payment_gateway((string) ($method['driver'] ?? 'manual'));
        if (($gateway['kind'] ?? 'manual') !== 'redirect') {
            continue;
        }
        if ($found !== null) {
            return null;    // more than one: the customer chooses
        }
        $found = $method;
    }

    return $found;
}
