<?php
/**
 * Front-site markup that uses a class the active theme never defines.
 *
 *   php tools/check-theme-css.php
 *
 * The admin has had this check since the Themes screen shipped as an
 * unstyled column; the front site needs it more, because a theme only ships
 * the views whose structure differs and inherits the rest. Every view a
 * theme inherits arrives with the default theme's class names, so its
 * stylesheet has to cover a vocabulary it never wrote - and a class with no
 * rule behind it renders as nothing at all.
 *
 * Checked per theme, against exactly the files that theme would render:
 * its own views first, then the default theme's, which is the order
 * render() resolves in.
 *
 * Exits non-zero when something is missing, so it can gate a release.
 */
declare(strict_types=1);

$root   = dirname(__DIR__);
$themes = glob($root . '/themes/*', GLOB_ONLYDIR) ?: [];
$faults = 0;

/** Every view this theme would render, keyed by the path it is reached at. */
function theme_views(string $root, string $theme): array
{
    $views = [];
    foreach ([$theme, $root . '/themes/default'] as $folder) {
        foreach ((glob($folder . '/views/*.php') ?: []) as $file) {
            $views[basename($file)] ??= $file;
        }
        foreach ((glob($folder . '/views/*/*.php') ?: []) as $file) {
            $views[basename(dirname($file)) . '/' . basename($file)] ??= $file;
        }
    }
    return $views;
}

foreach ($themes as $theme) {
    $css = $theme . '/assets/app.css';
    if (!is_file($css)) {
        $css = $root . '/themes/default/assets/app.css';   // inherited as well
    }

    $views  = theme_views($root, $theme);
    $styles = (string) file_get_contents($css);
    foreach ($views as $view) {
        if (preg_match_all('~<style[^>]*>(.*?)</style>~s', (string) file_get_contents($view), $blocks)) {
            $styles .= implode("\n", $blocks[1]);
        }
    }
    $styles = preg_replace('~/\*.*?\*/~s', '', $styles);

    preg_match_all('/\.([a-zA-Z][\w-]*)/', (string) $styles, $found);
    $defined = array_flip($found[1]);

    $ignore  = ['icon' => true];     // drawn by the sprite, not by a rule
    $missing = [];

    foreach ($views as $where => $view) {
        $markup = (string) file_get_contents($view);
        if (!preg_match_all('/class="([^"]*)"/', $markup, $attributes)) {
            continue;
        }
        foreach ($attributes[1] as $attribute) {
            // Drop PHP interpolation but keep the literal words around it, so
            // a class built as "st st-" plus an echoed status still checks
            // "st" and the prefix "st-".
            $attribute = preg_replace('~<\?.*?\?>~s', ' ', $attribute);

            foreach (preg_split('/\s+/', (string) $attribute) as $name) {
                if ($name === '' || !preg_match('/^[a-zA-Z][\w-]*$/', $name)) {
                    continue;
                }
                if (isset($defined[$name]) || isset($ignore[$name])) {
                    continue;
                }
                // A trailing dash came from interpolation: satisfied by any
                // rule that starts with it.
                if (str_ends_with($name, '-')) {
                    foreach ($defined as $known => $_) {
                        if (str_starts_with((string) $known, $name)) {
                            continue 2;
                        }
                    }
                }
                $missing[$name][$where] = true;
            }
        }
    }

    ksort($missing);
    echo basename($theme), ":\n";
    foreach ($missing as $name => $where) {
        printf("  .%-22s %s\n", $name, implode(', ', array_keys($where)));
    }
    echo $missing
        ? '  ' . count($missing) . " class(es) with no rule in " . basename(dirname(dirname($css)))
          . "/assets/app.css\n"
        : "  every class it renders has a rule behind it\n";

    $faults += count($missing);
}

exit($faults ? 1 : 0);
