<?php
/**
 * Guessing a platform and a category from a provider's free-text service name.
 *
 * Providers name things however they like ("Instagram Followers | Non Drop |
 * Max 100K"), so the import screen runs these over the service name and the
 * provider's own category name to pre-fill the two dropdowns.
 *
 * The word lists live at the top on purpose: adding a platform or a synonym
 * is a one-line change, no logic to follow.
 */

/** platform slug => words that mean that platform */
const DETECT_PLATFORMS = [
    'instagram' => ['instagram', 'insta', 'ig'],
    'tiktok'    => ['tiktok', 'tik tok', 'tt'],
    'youtube'   => ['youtube', 'yt'],
    'facebook'  => ['facebook', 'fb'],
    'x'         => ['twitter', 'x.com', ' x '],
    'telegram'  => ['telegram', 'tg'],
];

/** category slug => words that mean that category */
const DETECT_CATEGORIES = [
    'followers' => ['follower', 'subscriber', 'member', 'sub', 'connection'],
    'likes'     => ['like', 'reaction', 'heart', 'favorite', 'favourite'],
    'views'     => ['view', 'play', 'impression', 'watch', 'reach'],
];

/** Words that suggest the service refills drops. */
const DETECT_REFILL = ['refill', 'guarantee', 'non drop', 'non-drop', 'nondrop', 'lifetime'];

/**
 * Find the first list whose words appear in the text.
 *
 * Matching is whole-word, so short entries like "ig" and "fb" cannot match
 * inside "big" or "fbi" - but a trailing plural is allowed, because provider
 * names are written in the plural far more often than the singular
 * ("Followers", "Views", "Members", "Reactions").
 */
function detect_match(string $text, array $table): ?string
{
    $normalise = static fn(string $s): string =>
        trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));

    $haystack = ' ' . $normalise($text) . ' ';

    foreach ($table as $slug => $words) {
        foreach ($words as $word) {
            $needle = $normalise($word);
            if ($needle === '') {
                continue;
            }
            // (?<= ) and (?= ) are the word boundaries; (?:e?s)? allows the plural.
            if (preg_match('/(?<= )' . preg_quote($needle, '/') . '(?:e?s)?(?= )/', $haystack)) {
                return $slug;
            }
        }
    }
    return null;
}

/**
 * Work out the platform and category for a provider service.
 *
 * The service's own name is checked before the provider's category name,
 * because the name is the more specific of the two: "Telegram Reactions"
 * commonly sits inside a provider category called "Telegram Members".
 *
 * @return array{platform: ?string, category: ?string, refill: bool}
 */
function detect_service(string $name, string $providerCategory = ''): array
{
    return [
        'platform' => detect_match($name, DETECT_PLATFORMS)
                   ?? detect_match($providerCategory, DETECT_PLATFORMS),
        'category' => detect_match($name, DETECT_CATEGORIES)
                   ?? detect_match($providerCategory, DETECT_CATEGORIES),
        'refill'   => detect_match($name . ' ' . $providerCategory, ['yes' => DETECT_REFILL]) !== null,
    ];
}

/** Platform id for a slug, or null. Cached for the length of the request. */
function platform_id_for(?string $slug): ?int
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (all('SELECT id, slug FROM platforms') as $row) {
            $map[$row['slug']] = (int) $row['id'];
        }
    }
    return $slug !== null && isset($map[$slug]) ? $map[$slug] : null;
}

/** Category id for a platform + category slug pair, or null. */
function category_id_for(?int $platformId, ?string $slug): ?int
{
    if ($platformId === null || $slug === null) {
        return null;
    }
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (all('SELECT id, platform_id, slug FROM categories') as $row) {
            $map[$row['platform_id'] . ':' . $row['slug']] = (int) $row['id'];
        }
    }
    return $map[$platformId . ':' . $slug] ?? null;
}
