# SMM Panel

A small social-media-growth storefront with an admin panel, written in plain PHP
with no framework and no Composer. It runs on ordinary shared hosting: upload,
open the installer, done.

**Status:** the admin panel is complete and tested. The public storefront is a
placeholder for now — the front site is the next phase.

---

## What it does

| | |
|---|---|
| **Admin** | Dashboard, orders, services, provider import, platforms, categories, pages, FAQs, messages, payment methods, settings |
| **Providers** | Any SMM API v2 provider. Import a provider's catalogue, mark it up, and resell |
| **Orders** | Mark paid → send to provider → statuses come back on cron → completed |
| **Manual services** | A service with no provider is never sent to an API; you deliver it yourself |
| **Design** | Light sidebar admin (direction A from `mockups/`) |

Requirements: PHP 8.0+, MySQL 5.7+ / MariaDB 10.3+, and the `pdo_mysql`,
`mbstring`, `json` and `curl` extensions.

---

## Installing on cPanel

1. **Create the database.** In cPanel → *MySQL Databases*, create a database and
   a user, and give the user *All Privileges* on it. Note the three values —
   cPanel prefixes them, so they look like `myacct_smm`, `myacct_smmuser`.

2. **Upload the files.** Put everything in `public_html` (or a subfolder if the
   panel is not on the main domain). `mockups/` and `tools/` are development
   aids and do not need to go up.

3. **Make three folders writable** (755 is usually enough, 775 if your host is
   strict): `config/`, `storage/logs/`, `uploads/branding/`.

4. **Open `https://yourdomain.com/install/install.php`.** It checks the server,
   asks for the database details, creates the tables and seed data, then asks
   for your admin username and password. It writes `config/config.php` with a
   random app key and cron key, and locks itself afterwards.

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

## Testing without a real provider

```bash
php -S 127.0.0.1:8001 tools/mock-provider.php
```

Add a provider with API URL `http://127.0.0.1:8001/` and any key. It returns 40
realistic services, accepts orders, and walks each order forward on every status
call, so you can watch the whole flow complete. An order whose link contains
`fail-me` is refused, which is a quick way to see the `api_error` path.
