<?php
/**
 * Not selling at a loss.
 *
 * Two numbers travel together on every order: what the customer pays and what
 * the provider charges us. Nothing used to compare them, and there are three
 * ordinary ways they drift apart:
 *
 *   - a provider raises its rate and the catalogue sync has not run, or is
 *     switched off;
 *   - a package carries its own fixed price, which no sync ever touches, so
 *     a rate rise means every package sells at the old price forever;
 *   - the exchange rate moves, and our cost is in the provider's currency.
 *
 * In all three the shop keeps taking orders and losing money on each one,
 * quietly, until somebody adds up the month. This says no instead.
 */

/**
 * What an order is worth to us.
 *
 * @return array{profit: float, percent: float, below: bool, loss: bool}
 *         `percent` is margin on the sale price, which is what a shop means
 *         by margin; `below` is the admin's own floor, `loss` is real money.
 */
function order_margin(float $price, float $cost): array
{
    $profit  = round($price - $cost, 2);
    $percent = $price > 0 ? ($profit / $price) * 100 : ($cost > 0 ? -100.0 : 0.0);
    $floor   = (float) setting('min_margin_percent', 0);

    return [
        'profit'  => $profit,
        'percent' => round($percent, 2),
        'below'   => $price > 0 && $percent < $floor,
        'loss'    => $profit < 0,
    ];
}

/**
 * May this order be taken?
 *
 * `margin_guard` decides how strict to be:
 *   off   - take everything, which is what the panel did before
 *   loss  - refuse only when it actually costs us money (the default)
 *   floor - refuse anything under min_margin_percent
 *
 * A refusal is always recorded and raised, because an order the shop turned
 * away is something the admin has to know about within minutes: it means the
 * catalogue is stale, and every other service from that provider is probably
 * stale too.
 *
 * @return array{ok: bool, reason: string, margin: array}
 */
function margin_allows(float $price, float $cost, array $about = []): array
{
    $margin = order_margin($price, $cost);
    $guard  = (string) setting('margin_guard', 'loss');

    $refuse = match ($guard) {
        'off'   => false,
        'floor' => $margin['below'] || $margin['loss'],
        default => $margin['loss'],
    };

    if (!$refuse) {
        // Still worth a line when it is thin but allowed - that is the shape
        // of a problem about to become one.
        if ($margin['below'] || $margin['loss']) {
            margin_report('margin.thin', $price, $cost, $margin, $about, 'warn');
        }
        return ['ok' => true, 'reason' => '', 'margin' => $margin];
    }

    margin_report('margin.refused', $price, $cost, $margin, $about, 'alert');

    return [
        'ok'     => false,
        'reason' => $margin['loss']
            ? 'costs ' . money($cost) . ' and sells for ' . money($price)
            : 'margin is ' . $margin['percent'] . '%, under the '
              . setting('min_margin_percent', 0) . '% floor',
        'margin' => $margin,
    ];
}

/** Write it down and, where it is bad enough, tell the admin. */
function margin_report(string $action, float $price, float $cost, array $margin,
                       array $about, string $severity): void
{
    require_once APP_PATH . '/helpers/audit.php';
    require_once APP_PATH . '/helpers/notify.php';

    $service = (string) ($about['service'] ?? 'a service');
    $summary = $service . ': cost ' . money($cost) . ', price ' . money($price)
             . ' (' . $margin['percent'] . '%)';

    audit($action, [
        'entity'    => 'service',
        'entity_id' => (int) ($about['service_id'] ?? 0),
        'summary'   => $summary,
        'severity'  => $severity,
        'after'     => ['price' => $price, 'cost' => $cost,
                        'profit' => $margin['profit'], 'percent' => $margin['percent']],
    ]);

    if ($severity === 'alert') {
        notify('admin_margin', [
            'service'    => $service,
            'price'      => money($price),
            'cost'       => money($cost),
            'percent'    => (string) $margin['percent'],
            'admin_url'  => url('admin/services'),
            'admin_link' => 'admin/services',
        ]);
    }
}

/**
 * What a service costs us now, per thousand, read fresh.
 *
 * Used where a stored cost is too old to trust - between an order being
 * placed and it being handed to the provider, which can be days.
 */
function service_cost_now(int $serviceId): ?float
{
    $cost = col('SELECT cost_per_1000 FROM services WHERE id = ?', [$serviceId], null);
    return $cost === null ? null : (float) $cost;
}

/**
 * Every service currently selling at or under the floor.
 *
 * The Services screen shows this as a banner; it is the list an admin needs
 * after a provider moves its prices.
 */
function services_below_margin(int $limit = 200): array
{
    $floor = (float) setting('min_margin_percent', 0);
    $rows  = all(
        'SELECT s.id, s.name, s.price_per_1000, s.cost_per_1000, p.name AS provider_name
           FROM services s
      LEFT JOIN providers p ON p.id = s.provider_id
          WHERE s.is_active = 1 AND s.cost_per_1000 > 0
       ORDER BY s.id
          LIMIT ' . max(1, min(500, $limit))
    );

    $found = [];
    foreach ($rows as $row) {
        $margin = order_margin((float) $row['price_per_1000'], (float) $row['cost_per_1000']);
        if ($margin['loss'] || $margin['percent'] < $floor) {
            $found[] = $row + ['margin' => $margin];
        }
    }
    return $found;
}

/**
 * Packages whose own price no longer covers the service they belong to.
 *
 * Checked separately because a package price is a fixed number an admin typed
 * and no sync rewrites it - which makes this the slowest leak of the three
 * and the one nothing else would catch.
 */
function packages_below_margin(int $limit = 200): array
{
    $floor = (float) setting('min_margin_percent', 0);
    $rows  = all(
        'SELECT sp.id, sp.quantity, sp.bonus_quantity, sp.price, s.id AS service_id,
                s.name AS service_name, s.cost_per_1000
           FROM service_packages sp
           JOIN services s ON s.id = sp.service_id
          WHERE sp.is_active = 1 AND s.is_active = 1 AND s.cost_per_1000 > 0
       ORDER BY sp.id
          LIMIT ' . max(1, min(500, $limit))
    );

    $found = [];
    foreach ($rows as $row) {
        // Bonus quantity is delivered too, so it is bought too.
        $delivered = (int) $row['quantity'] + (int) $row['bonus_quantity'];
        $cost      = round(((float) $row['cost_per_1000'] / 1000) * $delivered, 2);
        $margin    = order_margin((float) $row['price'], $cost);

        if ($margin['loss'] || $margin['percent'] < $floor) {
            $found[] = $row + ['cost' => $cost, 'delivered' => $delivered, 'margin' => $margin];
        }
    }
    return $found;
}
