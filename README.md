# SMM Panel

A small social-media-growth storefront with an admin panel, written in plain PHP
with no framework and no Composer. It runs on ordinary shared hosting: upload,
open the installer, done.

**Status:** complete and tested — storefront, ordering, payment, tracking and
the full admin panel.

---

## What it does

| | |
|---|---|
| **Storefront** | Platform switcher, category tabs, live pricing, order modal, FAQ, content pages, contact form, sitemap |
| **Ordering** | Order → payment details → transaction id → tracking by code, plus a JSON status endpoint |
| **Admin** | Dashboard, orders, services, provider import, platforms, categories, pages, FAQs, messages, payment methods, settings |
| **Providers** | Any SMM API v2 provider. Import a provider's catalogue, mark it up, and resell |
| **Orders** | Mark paid → send to provider → statuses come back on cron → completed |
| **Manual services** | A service with no provider is never sent to an API; you deliver it yourself |
| **Design** | Direction A from `mockups/` — clean light front, light sidebar admin |

Requirements: PHP 8.0+, the `mbstring`, `json` and `curl` extensions, and a
database — either **MySQL 5.7+ / MariaDB 10.3+** (`pdo_mysql`) or **SQLite**
(`pdo_sqlite`). SQLite needs no database server at all: the whole thing lives
in one file under `storage/`, which is already blocked from the web.

---

## Installing on cPanel

1. **Create the database** — only if you want MySQL. In cPanel → *MySQL
   Databases*, create a database and a user, and give the user *All Privileges*
   on it. cPanel prefixes the names, so they look like `myacct_smm`,
   `myacct_smmuser`.

   Picking **SQLite** in the installer instead skips this step entirely.

2. **Upload the files.** Put everything in `public_html` (or a subfolder if the
   panel is not on the main domain). `mockups/` and `tools/` are development
   aids and do not need to go up.

3. **Make three folders writable** (755 is usually enough, 775 if your host is
   strict): `config/`, `storage/logs/`, `uploads/branding/`.

4. **Open `https://yourdomain.com/install`.** It checks the server, lets you
   choose MySQL or SQLite, creates the tables and seed data, then asks for your
   admin username and password. It writes `config/config.php` with a random app
   key and cron key, and locks itself afterwards.

   Visiting any other page before this redirects here, so you cannot miss it.

5. **Delete the `install/` folder.** Everything keeps working without it.

6. **Add the cron job** (cPanel → *Cron Jobs*, every 5 minutes):

   ```
   php /home/youraccount/public_html/cron.php
   ```

   If your host does not allow CLI cron, use the URL form instead — the full
   address is shown in *Settings → Cron*:

   ```
   https://yourdomain.com/cron/your-cron-key
   ```

Log in at `https://yourdomain.com/admin`.

---

## Updating to a newer release

The database is the only thing an update has to touch, and the panel does that
part itself.

1. **Back up the database.** An update only adds tables, columns and settings,
   but a backup costs a minute and a restore does not.

2. **Upload the new files over the old ones.** Leave `config/`, `storage/` and
   `uploads/` alone — those are yours, and nothing in a release overwrites
   them.

3. **Open *Update* in the admin sidebar.** It compares the version in the files
   (`app/core/version.php`) with the version recorded in the database, lists
   the changes that are outstanding, and applies them one at a time with a
   progress bar.

   Every screen shows a banner while an update is outstanding, so there is no
   way to upload a release and forget this step.

Each change runs as its own request. A migration that rewrites a large table is
exactly the kind of thing a shared host cuts off at thirty seconds, and running
them one per request keeps each one well inside the limit. A change that has
already been applied is recorded and never runs twice, so pressing the button
again is safe.

---

## Themes

Two ship: **Clean Light** (soft gradients, light palette) and **Warm Gradient**
(pink-to-coral hero with the platform chooser sitting across it). Switch in
*Admin → Themes*; the new theme's own colour is applied unless you have picked
one yourself in Settings.

To add a third, copy a folder under `themes/`, change `theme.php` and edit what
you want. A theme only ships the views it changes — anything it leaves out comes
from the default theme — and no existing file needs touching for it to appear in
the admin.

---

## Nginx

There is no `.htaccess` on nginx, so the rewrite and the deny rules have to go
in the server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/smm;
    index index.php;

    # Everything that is not a real file goes to the front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Application internals are never served
    location ~ ^/(app|config|storage|install)/ { deny all; return 404; }

    # Nothing in uploads/ is ever executed
    location ^~ /uploads/ {
        location ~ \.(php|phtml|phar)$ { deny all; return 404; }
    }

    location ~ /\.          { deny all; }
    location ~ \.(sql|log)$ { deny all; }
}
```

---

## Adding a provider and importing services

1. **Admin → Providers → Add provider.** Give it a name you will recognise, the
   provider's API endpoint (usually something ending in `/api/v2`) and your API
   key. Save, then press **Check balances** to confirm the key works — the
   balance appears in the sidebar.

2. **Admin → Import Services.** Pick the provider. The catalogue is fetched once
   and cached for 30 minutes; **Refresh from API** forces a new fetch.

3. **Filter down to what you want.** Search by name, or narrow by the provider's
   own category.

4. **Check the two settings above the table:**
   - *Auto-detect platform & category* reads each service name and fills in the
     platform and category for you. It reads the service name first and the
     provider's category name second, because the name is more specific —
     "Telegram Reactions" sits in a provider category called "Telegram Members"
     but belongs under Likes.
   - *Markup %* sets your selling price: `cost × (1 + markup/100)`.

5. **Tick the rows and press Import.** Services you already have are shown as
   *have it* and unticked by default. Importing them again with *update existing*
   on refreshes cost, price and limits but keeps any name or description you
   edited.

6. **Admin → Services** to tidy up: rename, write a description and feature
   ticks, set which are *most popular*, or use the bulk bar to activate, apply a
   markup, re-sync prices or deactivate in one go.

To sell something you fulfil by hand, add a service and leave **Buy from** set to
*Manual*. Those orders move to *processing* on payment and wait for you.

---

## How an order moves

```
pending ──mark paid──► paid ──send──► processing ──cron──► completed
   │                     │                │
   │                     └── api_error ◄──┘ (provider refused; the reason is kept)
   └── cancelled / refunded
```

With *auto-send orders* on (Settings), marking an order paid sends it straight
to the provider. Cron then refreshes statuses, pulls back start count and
remains, and marks orders completed. Nothing is ever silently completed — an
unknown provider status stays *processing*.

---

## Layout

```
index.php              front controller; every URL lands here
cron.php               scheduled work (CLI)
app/core/              bootstrap and the resolver
app/helpers/           functions.php, crud.php, SmmApi.php, detect.php, orders.php, cron.php
controllers/           one file per URL; controllers/admin/ is the admin
views/                 one file per screen, plus layouts/ and admin/crud/
assets/                css and js
install/               installer and schema (delete after installing)
mockups/               the HTML design mockups this was built from
tools/                 mock-provider.php, for testing without a real API
```

Routing is file-based: `/admin/services` runs `controllers/admin/services.php`.
No route table to keep in sync.

---

## Running it locally

```bash
php -S localhost:8000                         # the site
php -S localhost:8001 tools/mock-provider.php # a fake provider
```

That is enough: every page a person clicks works, including `/install`.

The one gap is `/sitemap.xml` and `/robots.txt`. PHP's built-in server answers
any URL with a file extension straight from disk and never reaches `index.php`,
so those two 404 locally — they are fine on Apache and nginx, and `/sitemap`
and `/robots` serve the same thing everywhere. If you want the dotted versions
locally too:

```bash
php -S localhost:8000 tools/dev-router.php
```

which makes the built-in server behave like Apache with the shipped
`.htaccess`. It is development only and is not in the release zip.

## Testing without a real provider

```bash
php -S 127.0.0.1:8001 tools/mock-provider.php
```

Add a provider with API URL `http://127.0.0.1:8001/` and any key. It returns 40
realistic services, accepts orders, and walks each order forward on every status
call, so you can watch the whole flow complete. An order whose link contains
`fail-me` is refused, which is a quick way to see the `api_error` path.


---

## Building the upload zip

```bash
bash tools/build-release.sh
```

Writes `build/smm-panel-YYYY-MM-DD.zip` with only what the live site needs. The
mockups and the development tools are deliberately left out — the mock provider
must never reach a real server.

---

## What a customer sees

```
/                        the default platform
/instagram               that platform
/instagram/followers     that platform and category
/faq  /contact  /track   content and tracking
/install                 the installer, until it locks itself
/order/GK-8F42KD         their order: summary, payment details, live status
/api/track?code=...      the same status as JSON
/sitemap.xml /robots.txt for search engines (also at /sitemap and /robots)
```

No URL anywhere has `.php` in it. The platform strip and the category tabs are
real links, so the site works — and is crawlable — with JavaScript blocked. The script only adds the live price
and the order modal; without it the same form posts normally.

The price is recalculated from the service row on every order. Nothing the
browser says about money is trusted.


---

## MySQL or SQLite?

Both are first-class; the installer asks once and writes the choice into
`config/config.php` as `db_driver`.

| | MySQL / MariaDB | SQLite |
|---|---|---|
| Setup | create a database and user first | nothing to set up |
| Where the data lives | on the database server | `storage/database.sqlite` |
| Backups | export through phpMyAdmin or mysqldump | copy the one file |
| Good for | any size, several sites sharing a server | a single shop; simplest possible install |

The SQLite file sits in `storage/`, which `.htaccess` and the nginx config
already deny, so it is not reachable over the web. Back it up the way you would
back up a database — it *is* the database.

To move between them, install fresh on the other driver and re-import your
services; there is no converter.
