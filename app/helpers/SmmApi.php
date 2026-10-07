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

    public function services(): array      { return $this->call(['action' => 'services']); }
    public function balance(): array       { return $this->call(['action' => 'balance']); }
    public function status(string $o): array { return $this->call(['action' => 'status', 'order' => $o]); }
    public function refill(string $o): array { return $this->call(['action' => 'refill', 'order' => $o]); }

    public function add(string $service, string $link, int $quantity, array $extra = []): array
    {
        return $this->call($extra + [
            'action'   => 'add',
            'service'  => $service,
            'link'     => $link,
            'quantity' => $quantity,
        ]);
    }

    /** Status for several orders in one request. */
    public function multiStatus(array $orderIds): array
    {
        return $this->call(['action' => 'status', 'orders' => implode(',', $orderIds)]);
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
