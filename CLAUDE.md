# CLAUDE.md

Conventions for this codebase. Written from the code that exists, so it
describes what is actually there rather than an intention.

## What this is

A social-media-growth storefront plus admin panel, both complete. Plain PHP 8,
MySQL or SQLite, no framework, no Composer, no build step. It has to run on ordinary cPanel shared
hosting, so: no CLI requirement beyond an optional cron job, no writable paths
outside `config/`, `storage/` and `uploads/`.

## Shape

```
index.php              the only web entry point
cron.php               CLI entry point
app/core/bootstrap.php config, errors, session, security headers
app/core/resolver.php  URL -> controller file
app/helpers/           the function library (see below)
controllers/           one file per URL
views/                 one file per screen
assets/                css + js, no build step
install/               installer, schema, seed
```

## Routing

File-based, no route table.

| URL | runs |
|---|---|
| `/` | `controllers/home.php` |
| `/track` | `controllers/track.php` |
| `/admin` | `controllers/admin/index.php` |
| `/admin/services` | `controllers/admin/services.php` |
| `/admin/services/edit/7` | same file, `$params = ['edit', '7']` |
| `/instagram`, `/instagram/followers`, `/refund-policy` | `controllers/_fallback.php` |
| `/order`, `/order/GK-8F42KD`, `/order/GK-8F42KD/pay` | `controllers/order.php` |
| `/api/track` | `controllers/api.php` |
| `/sitemap.xml`, `/robots.txt` | named in the resolver, since a dot fails the slug rule |
| `/admin/update`, `/admin/update/plan`, `/admin/update/step` | `controllers/admin/update.php` |
| `/install` | `install/index.php`, before any routing exists |
| anything else | `controllers/_404.php` |

Rules:

- **No URL contains `.php`.** `/install` is a directory with an `index.php`, so
  every server resolves it without a rewrite rule; `/install/index.php` 301s to
  `/install`.
- A URL segment must match `^[A-Za-z0-9_-]+$` or the request 404s. This is what
  stops path traversal — there is no other place to get it wrong.
- `current_path()` works out the mount point from the configured `base_url`,
  then from `DOCUMENT_ROOT` vs `BASE_PATH`, and only falls back to
  `SCRIPT_NAME`. Servers disagree about `SCRIPT_NAME` — PHP's built-in server
  reports the directory index it resolved, Apache reports the rewritten front
  controller — and taking its dirname eats a real URL segment.
- Files starting with `_` are includes, never routes.
- Every `controllers/admin/*` request runs `controllers/admin/_middleware.php`
  first. That is where the login check and the CSRF check live, so individual
  admin controllers never repeat them.
- Controllers are plain PHP files, not classes. They see `$params` and the
  helpers. They end by calling `view()` or `redirect()`.

## Helpers

`app/helpers/functions.php` is loaded for every request and holds the whole
general-purpose set. Add to it rather than writing a one-off inline:

- config / db — `cfg`, `db`, `q`, `one`, `all`, `col`, `insert_row`, `update_row`, `delete_row`
- settings — `setting`, `set_setting`, `settings_all`
- output — `e`, `money`, `qty_fmt`, `excerpt`, `when`
- urls — `base_url`, `url`, `asset`, `redirect`, `current_path`, `is_https`
- views — `view`, `render`, `partial`
- forms — `csrf_token`, `csrf_field`, `csrf_verify`, `old`, `keep_old`, `flash`, `flashes`
- auth — `admin_user`, `is_admin`, `require_admin`
- misc — `slugify`, `random_code`, `client_ip`, `rate_limit`, `rate_limit_hit`, `log_line`

Loaded on demand by the controllers that need them:

- `crud.php` — the shared admin CRUD (`crud_handle`, `options_from`)
- `SmmApi.php` — the only class in the codebase
- `detect.php` — platform/category guessing for the import screen
- `orders.php` — `send_order_to_provider`, `sync_order_statuses`, `map_provider_status`
- `cron.php` — `run_cron_tasks`, shared by `cron.php` and `controllers/cron.php`
- `migrate.php` — `migrations_pending`, `migration_apply`, `migrations_run`
- `update.php` — `update_available`, `update_steps`, `update_run_step`

## Database

- **Two drivers.** `db_driver` in the config is `mysql` or `sqlite`; `db()`
  builds the right connection, and SQLite gets `foreign_keys`, WAL and a busy
  timeout turned on. A config with no `db_driver` means MySQL.
- **Write SQL both understand.** No `NOW()`, no `INTERVAL`, no
  `ON DUPLICATE KEY UPDATE` — bind a `date('Y-m-d H:i:s')` instead of asking the
  database for the time, and spell an upsert out. The schema exists twice,
  `install/schema.sql` and `install/schema.sqlite.sql`; keep them in step.
- Every query goes through `q`/`one`/`all`/`col`, which prepare and bind. There
  is no string interpolation of values into SQL anywhere. Where a column or
  table name is dynamic (the CRUD engine) it comes from a spec written in PHP,
  never from the request.
- `settings` is a key/value table read once per request.
- Order status is an enum: `pending, paid, processing, completed, partial,
  cancelled, refunded, api_error`.
- Foreign keys use `ON DELETE SET NULL` where history matters (an order keeps
  working when its service is deleted) and `CASCADE` where it does not.

## Shared admin CRUD

Simple admin screens describe their table once and `crud_handle()` runs the
list / new / edit / save / delete / toggle cycle against
`views/admin/crud/list.php` and `form.php`. Providers, platforms, categories,
payment methods, pages and FAQs all work this way.

Write a screen by hand only when it needs more than the spec can say — services
(bulk actions), import, orders (detail with actions), settings, messages.

Two spec keys are easy to confuse:

- `empty` is only the **label on a select's blank choice**. It says nothing
  about the column.
- `nullable` is what stores NULL when the field comes back blank. Without it a
  blank field falls back to `default`, then to `''`.

Getting that backwards is how "Detect from the balance reply" tried to write
NULL into `providers.currency`, which is `NOT NULL`.

## Service import

`controllers/admin/import.php`.

- The provider catalogue is fetched once and cached in `storage/cache/` for 30
  minutes. "Refresh from API" forces a refetch. If the provider is unreachable
  the cached copy is still shown, with a warning.
- Auto-detect matches the **service name first, the provider's category name
  second** — the name is more specific. Word lists live at the top of
  `detect.php`; matching is whole-word and allows a plural, because providers
  write "Followers" not "Follower".
- Markup sets the price: `cost × (1 + markup/100)`.
- A service already in the catalogue is matched on
  `(provider_id, provider_service_id)`. Re-importing with "update existing"
  refreshes cost, price and limits but keeps the name and description, because
  an admin may have rewritten them.

## Themes

- A theme is a folder under `themes/` with `theme.php` beside `views/` and
  `assets/`. Nothing keeps a list — `themes_available()` scans the directory —
  so adding one never means editing a file that already exists.
- `render()` looks in the active theme's views, then the **default theme's**,
  then `app/views`. A theme therefore ships only the views whose structure
  differs and inherits the rest; `viralborn` ships four files and gets the
  order page, the tracking page and the modal from `default`.
- Because inherited views come with the default theme's class names, a theme's
  stylesheet has to cover that whole vocabulary. Both shipped themes are
  written in the same order so they read side by side.
- `theme_asset()` falls back the same way, so a theme with no `app.js` of its
  own still gets one — which is why a theme must keep the ids the script
  needs: `#platStrip`, `#catTabs`, `#serviceCards`, `#heroTitle` with its
  `.grad` span, `#heroCta`, `#svcTitle`, and the card's `data-` attributes.
- A manifest may declare `brand`, the colour the theme was drawn around.
  Activating a theme adopts it — but only while the colour on record is still
  the **previous theme's** own, so a colour the admin chose is never
  overwritten.

## Service packages

- **A service with no packages still shows quantity cards.** `service_tiers()`
  falls back to a ladder of round numbers (`QUANTITY_LADDER`), trimmed to what
  the provider accepts and spread across the range, priced at the service's own
  rate. This is what makes a fresh install look like the reference without an
  admin hand-entering five rows per service; `auto_packages` in Settings turns
  it off. A generated tier carries **no id**, so the order posts a quantity
  alone and the server prices it from the rate — the same number the card
  showed. *Generate from the service* on the Packages screen writes the ladder
  out as real rows so the prices can then be edited.
- What tells a fixed-quantity card from a typed one in the script is whether
  it **has a quantity input**, not whether it has a package id — a generated
  tier is fixed and has no package behind it.
- A service can be sold in preset packages — "500 followers for 165" — as well
  as by quantity. A package carries **its own price**, not a rate, which is the
  whole point: a bigger package can genuinely cost less per thousand, and the
  admin list shows the per-thousand figure so that discount is visible.
- `bonus_quantity` is delivered free on top. The order's quantity is
  `quantity + bonus_quantity`, because that is what the provider is asked for;
  the cost is still the rate times that, so margin stays honest.
- **`catalogue_mode` decides what a category sells.** `services` (the default)
  shows every service in the category as its own card. `single` shows only the
  service the category names in `categories.service_id`, so the customer picks
  a quantity rather than a service — the way the big shops do it. A category
  that names none falls back to its first active service rather than rendering
  an empty tab.
- The front cards come from `partials/cards.php`: one card per package for a
  service that has them, the ordinary quantity card for a service that does
  not, so nothing ever disappears from the grid.
- The packaged services then share **one** custom-quantity panel underneath,
  with a service picker when there is more than one. A "custom" card per
  service sitting among the tiers gave no way to tell which service it
  belonged to. The panel is an ordinary `.card`, so choosing a service
  rewrites its `data-` attributes rather than re-rendering: the price maths
  and the order modal keep working on it unchanged.
- `order.php` reads the price **back from the package row**. The quantity the
  browser sends for a package order is not even looked at, and a package id
  that does not belong to the service is refused.

## The storefront

- `controllers/home.php` renders `/`, `/{platform}` and `/{platform}/{category}`
  — `_fallback.php` looks the rows up and leaves them in `$GLOBALS`.
- Only platforms, categories and services that are active are shown, and a
  category with no active service is left out of the tabs entirely.
- The platform strip and the category tabs are **real links**, so the site works
  and is crawlable without JavaScript. `assets/js/app.js` only adds the live
  price and the order modal; the order form posts normally when it is blocked.
- Meta title and description come from the category, then the platform, then a
  sensible generated line. Every page sets a canonical URL.
- The theme colour from Settings is written into `--brand` in the layout.

## Order flow

`pending → paid → processing → completed`, with `api_error` parked to one side.

- `send_order_to_provider()` is the only place an order is handed to an API. A
  failure stores the provider's own words in `api_error` and leaves the order
  where it is, so it can be retried.
- A service with no provider is manual: the order moves to `processing` and a
  person delivers it. No API call is made.
- `map_provider_status()` turns provider wording into our enum. **An unknown
  status maps to `processing`, never to `completed`** — never silently finish an
  order we are not sure about.
- `sync_order_statuses()` groups open orders per provider and uses the
  multi-status call, so a hundred open orders is a handful of requests.
- Whether a provider answers a batch is read from the **shape** of its reply,
  not its contents: keyed by order id means yes, a flat status object means it
  read `order` and ignored `orders`. `multi_status_probe()` therefore needs no
  real orders — it asks about ids that will not exist — so a provider can be
  checked the moment it is added. The one reply it cannot read is a flat error
  for a batch of unknown ids, which is also what a batching provider says; that
  answers "cannot tell yet" and records nothing rather than guessing. Providers
  has its own **Multi-status** button, and Check asks as well.
- Every state change writes an `order_logs` row.

## Versions and updating

- The release version lives in **one place**, `app/core/version.php`.
  `bootstrap.php` turns it into `APP_VERSION`; the installer reads the same
  file without booting the app. Bump it in the same commit as the migration
  that needs it.
- The database carries its own copy in the `app_version` setting. When the two
  differ, or a migration in `install/migrations.php` has not run, the admin
  shows **Update available** — a banner on every screen and a dot on the
  sidebar item.
- `/admin/update` does the work **one step per request**: the browser asks for
  the plan, then posts each step and draws the progress bar. A shared host
  cuts a request off at thirty seconds and a migration that rewrites a table
  is exactly what gets cut. `/admin/update/run` is the same list in one POST,
  for a browser that cannot run the stepper.
- The installer works the same way, for the same reason: `?step=2&task=plan`
  returns the task list, `?step=2&task=<key>` runs one. Submitting the form
  without JavaScript runs the identical list in one request.
- A step that fails because the thing it adds is already there is **skipped,
  not an error** — SQLite has no `ADD COLUMN IF NOT EXISTS`, and that is how
  both drivers end up behaving the same way. `migration_already_done()` holds
  the list of phrases; MySQL and SQLite word them differently, so both
  wordings have to be in it.

## The dashboard

- The figures live in `app/helpers/stats.php`, not the controller, because each
  one is a decision about what counts and they have to agree with each other.
  The rule throughout: an order is money only once it is paid, so `pending`,
  `cancelled` and `refunded` are out of every revenue, cost and profit figure
  while still counting as orders.
- **Never put the status list in a statement twice.** Placeholders bind by
  position, so a second `status IN (?, ?, …)` in the same query silently shifts
  every later binding and the answer comes back zero — no error, just wrong.
  Two queries, one clause each.
- Dates are worked out in PHP and bound. MySQL and SQLite disagree about
  `INTERVAL`.
- Charts are inline SVG from `app/helpers/charts.php`; there is no build step
  and a dashboard is not worth breaking that for. **One measure per chart, so
  one colour per chart** — orders and revenue are two charts, never one plot
  with two y-scales, because the alignment between two scales is arbitrary and
  invents a relationship the data does not have.
- Every chart folds the same numbers underneath as a table. A value you can
  only reach by hovering is a value some readers cannot reach at all.
- Bars for named things are one hue. Shading each bar by its own length would
  spend the only free channel restating the length.

## Admin components

Three pieces of chrome are drawn by us rather than by the browser, and all
three work the same way: **the native control stays in the DOM and stays the
thing that submits.** The enhancement is drawn over it, so a browser that
never runs the script gets the plain control, styled.

- **`admin/_upload`** — an image field. The file input is moved out of sight
  and driven by its label, which is what a file input is built to allow. Adds
  a drop target and a preview.
- **The searchable select** — every `<select>` in the admin gets a button and
  a popup; the search box inside appears once there are enough options to
  scroll, or where the markup says `data-search` (a CRUD field spec asks with
  `'search' => true`). Choosing writes back to the real select and dispatches
  `change`, so a filter form still submits itself.
- **Tabs** — panels are only hidden once the script runs, so without it the
  page is the long column it used to be. Every field stays in the DOM
  whichever tab is showing: hiding a panel must never mean dropping what is
  in it.

`[hidden]` is declared `display:none !important` in both the admin and the
theme stylesheets, because a rule that sets `display` otherwise wins and the
attribute silently does nothing.

## Security

These are the rules the code already follows; keep them.

- **Output:** everything printed in a view goes through `e()`. The two
  exceptions are deliberate and commented: page content and `head_code`, both
  written by an admin.
- **SQL:** prepared statements only, via the helpers.
- **CSRF:** every non-GET admin request is checked in `_middleware.php`. Forms
  use `csrf_field()`. A bad token ends the request with 419.
- **Passwords:** `password_hash` / `password_verify`, rehashed on login when the
  algorithm moves on.
- **Sessions:** `session_regenerate_id(true)` on login and on password change.
  Cookies are `httponly`, `samesite=Lax`, and `secure` over HTTPS.
- **Login:** throttled per IP, 8 failures per 10 minutes. Successful logins do
  not count against the budget. The failure message never says which field was
  wrong.
- **Uploads:** accepted only if `getimagesize()` can read them, saved with an
  extension we chose, and `uploads/.htaccess` turns off the PHP engine.
- **Installer:** refuses to run once `install/install.lock` exists.
- **Cron URL:** `/cron/{cron_key}` compared with `hash_equals`; a wrong key is a
  plain 404, so the endpoint cannot be probed for.
- **Paths:** `app/`, `config/`, `storage/` and `install/*.sql` are denied in
  `.htaccess`; the nginx equivalent is in the README.
- **Money:** the order price is recalculated from the service row on the server
  every time. A price posted by the browser is ignored.
- **Links:** when a platform sets `url_prefix`, an order's link must be on that
  host, so an Instagram service cannot be ordered with a TikTok link.
- **Spam:** the order and contact forms each carry a hidden honeypot field and
  a per-IP throttle.

## Style

- Comments explain **why**, not what. No comment that restates the line below it.
- Function and variable names are words, not abbreviations.
- Views contain presentation only; anything that needs a decision belongs in the
  controller or a helper.
- Keep the design tokens in `assets/css/admin.css` (`:root`) — do not hard-code
  colours in markup.
- British or American spelling, but match the file you are in.

## Design

The admin follows direction A in `mockups/admin-a.html` (light sidebar, indigo
accent). The front site will follow one of `mockups/front-*.html`. The mockups
stay in the repository as the visual reference; they ship with no PHP and are
not part of the deployed site.

## Releasing

`bash tools/build-release.sh` stages only what the live site needs and zips it
into `build/`. `mockups/` and `tools/` stay in the repository but never ship -
`tools/mock-provider.php` must not reach a real server. The script fails loudly
if anything on that list sneaks in.

## Testing

No test framework. The checks that matter:

```bash
# syntax
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;

# a real run
php -S localhost:8000 tools/dev-router.php     # the app
php -S localhost:8001 tools/mock-provider.php  # a fake provider
```

Use the router script. Without it `php -S` answers `/sitemap.xml` and
`/robots.txt` from disk, finds no such file and returns 404, while Apache
rewrites both to the front controller — so the two servers disagree about
exactly the URLs the resolver names specially.

Run the install-to-completed pass **on both drivers**. The two bugs that only
one of them showed were `NOW()` left in the installer's admin INSERT, which
SQLite has no function for, and `current_path()` trusting `SCRIPT_NAME`, which
only broke once a router script changed what the server reported.

Then: install from an empty database, add the mock provider, import, place an
order, mark it paid, run `php cron.php` a few times and watch it complete. An
order whose link contains `fail-me` exercises the `api_error` path.

Before a release, do that run against the **zip**, not the working copy -
unpack `build/*.zip` somewhere clean and install it from zero. The one bug that
only showed up that way was a CRUD form field missing from a POST becoming NULL
in a NOT NULL column, which the working copy never hit because its forms always
carried every field.
