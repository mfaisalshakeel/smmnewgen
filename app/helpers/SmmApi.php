<?php
/**
 * The one class in the codebase.
 *
 * Nearly every SMM provider speaks the same "API v2" dialect: a single POST
 * endpoint, a `key` field, an `action` field, and JSON back. This wraps it.
 *
 *   $api = new SmmApi($provider['api_url'], $provider['api_key']);
 *   $api->services();          // catalogue
 *   $api->add($id, $link, $q); // place an order  -> ['order' => 123]
 *   $api->status($orderId);    // one order
 *   $api->multiStatus([1,2]);  // several at once
 *   $api->balance();
 *   $api->refill($orderId);
 *   $api->cancel([$orderId]);
 *   $api->refillStatus($refillId);
 *
 * add() takes a fourth argument for the extras the standard allows:
 * runs + interval (drip-feed), comments, hashtag, usernames, answer_number.
 *
 * Every call returns a decoded array. Transport and provider errors both land
 * in the ['error' => '...'] key so callers only check one thing.
 */
final class SmmApi
{
    public function __construct(
        private string $url,
        private string $key,
        private int $timeout = 45
    ) {}

    public function services(): array        { return $this->call(['action' => 'services']); }
    public function balance(): array         { return $this->call(['action' => 'balance']); }
    public function status(string $o): array { return $this->call(['action' => 'status', 'order' => $o]); }
    public function refill(string $o): array { return $this->call(['action' => 'refill', 'order' => $o]); }

    /** Refill several orders at once, where the provider allows it. */
    public function refillMany(array $orderIds): array
    {
        return $this->call(['action' => 'refill', 'orders' => implode(',', $orderIds)]);
    }

    public function refillStatus(string $refillId): array
    {
        return $this->call(['action' => 'refill_status', 'refill' => $refillId]);
    }

    public function refillStatusMany(array $refillIds): array
    {
        return $this->call(['action' => 'refill_status', 'refills' => implode(',', $refillIds)]);
    }

    public function add(string $service, string $link, int $quantity, array $extra = []): array
    {
        return $this->call($extra + [
            'action'   => 'add',
            'service'  => $service,
            'link'     => $link,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Status for several orders in one request.
     *
     * Not every provider implements this. The reply should be an object keyed
     * by order id; one that does not support it answers with an error, or
     * with a single flat status object. provider_supports_multi_status() in
     * orders.php decides which, and callers fall back to one request per
     * order when the answer is no. The standard caps a batch at 100.
     */
    public function multiStatus(array $orderIds): array
    {
        return $this->call(['action' => 'status', 'orders' => implode(',', array_slice($orderIds, 0, 100))]);
    }

    public function cancel(array $orderIds): array
    {
        return $this->call(['action' => 'cancel', 'orders' => implode(',', $orderIds)]);
    }

    /** POST the fields plus the key, decode the JSON, normalise errors. */
    private function call(array $fields): array
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields + ['key' => $this->key]),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'SMMPanel/1.0',
        ]);

        $body   = curl_exec($ch);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            return ['error' => 'Connection failed: ' . $errMsg];
        }
        if ($code >= 400) {
            return ['error' => 'Provider returned HTTP ' . $code];
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            return ['error' => 'Provider sent a reply we could not read: ' . mb_substr((string) $body, 0, 180)];
        }

        // Some providers answer a list request with a bare array - keep it as is.
        return $data;
    }
}
