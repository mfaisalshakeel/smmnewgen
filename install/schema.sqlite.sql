-- ===========================================================================
-- SMM Panel - SQLite schema and seed data
--
-- The MySQL file (schema.sql) is the reference; this one says the same thing
-- in SQLite's dialect:
--   INTEGER PRIMARY KEY AUTOINCREMENT instead of INT AUTO_INCREMENT
--   TEXT with a CHECK instead of ENUM
--   no ENGINE / CHARSET / COLLATE
--   INSERT OR IGNORE instead of ON DUPLICATE KEY UPDATE
-- Keep the two in step when either changes.
-- ===========================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS "settings" (
  "k" TEXT NOT NULL PRIMARY KEY,
  "v" TEXT
);

CREATE TABLE IF NOT EXISTS "admins" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "username" TEXT NOT NULL UNIQUE,
  "email" TEXT NOT NULL UNIQUE,
  "password_hash" TEXT NOT NULL,
  "last_login_at" TEXT,
  "created_at" TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS "providers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "api_url" TEXT NOT NULL,
  "api_key" TEXT NOT NULL,
  "balance" REAL NOT NULL DEFAULT 0,
  "balance_currency" TEXT NOT NULL DEFAULT '',
  "balance_checked_at" TEXT,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "ix_providers_active" ON "providers" ("is_active");

CREATE TABLE IF NOT EXISTS "platforms" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL UNIQUE,
  "name" TEXT NOT NULL,
  "icon" TEXT NOT NULL DEFAULT '',
  "color" TEXT NOT NULL DEFAULT '',
  "meta_title" TEXT NOT NULL DEFAULT '',
  "meta_description" TEXT NOT NULL DEFAULT '',
  "url_prefix" TEXT NOT NULL DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS "ix_platforms_active" ON "platforms" ("is_active", "sort_order");

CREATE TABLE IF NOT EXISTS "categories" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "platform_id" INTEGER NOT NULL,
  "slug" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "meta_title" TEXT NOT NULL DEFAULT '',
  "meta_description" TEXT NOT NULL DEFAULT '',
  "service_id" INTEGER,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  UNIQUE ("platform_id", "slug"),
  FOREIGN KEY ("platform_id") REFERENCES "platforms" ("id") ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_categories_active" ON "categories" ("is_active", "sort_order");

CREATE TABLE IF NOT EXISTS "services" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "provider_id" INTEGER,
  "provider_service_id" TEXT NOT NULL DEFAULT '',
  "platform_id" INTEGER,
  "category_id" INTEGER,
  "name" TEXT NOT NULL,
  "description" TEXT,
  "badge" TEXT NOT NULL DEFAULT '',
  "features" TEXT,
  "cost_per_1000" REAL NOT NULL DEFAULT 0,
  "price_per_1000" REAL NOT NULL DEFAULT 0,
  "min_qty" INTEGER NOT NULL DEFAULT 100,
  "max_qty" INTEGER NOT NULL DEFAULT 100000,
  "delivery_time" TEXT NOT NULL DEFAULT '',
  "supports_refill" INTEGER NOT NULL DEFAULT 0,
  "supports_cancel" INTEGER NOT NULL DEFAULT 0,
  "is_featured" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL,
  "updated_at" TEXT,
  UNIQUE ("provider_id", "provider_service_id"),
  FOREIGN KEY ("provider_id") REFERENCES "providers" ("id") ON DELETE SET NULL,
  FOREIGN KEY ("platform_id") REFERENCES "platforms" ("id") ON DELETE SET NULL,
  FOREIGN KEY ("category_id") REFERENCES "categories" ("id") ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "ix_services_listing"
  ON "services" ("is_active", "platform_id", "category_id", "sort_order");

CREATE TABLE IF NOT EXISTS "payment_methods" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "driver" TEXT NOT NULL DEFAULT 'manual',
  "config" TEXT,
  "short_name" TEXT NOT NULL DEFAULT '',
  "account_title" TEXT NOT NULL DEFAULT '',
  "account_number" TEXT NOT NULL DEFAULT '',
  "extra_label" TEXT NOT NULL DEFAULT '',
  "extra_value" TEXT NOT NULL DEFAULT '',
  "instructions" TEXT,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS "ix_payment_active" ON "payment_methods" ("is_active", "sort_order");

CREATE TABLE IF NOT EXISTS "orders" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "code" TEXT NOT NULL UNIQUE,
  "service_id" INTEGER,
  "platform_id" INTEGER,
  "service_name" TEXT NOT NULL DEFAULT '',
  "quantity" INTEGER NOT NULL,
  "link" TEXT NOT NULL,
  "whatsapp" TEXT NOT NULL DEFAULT '',
  "price" REAL NOT NULL DEFAULT 0,
  "cost" REAL NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'pending'
     CHECK ("status" IN ('pending','paid','processing','completed','partial',
                         'cancelled','refunded','api_error')),
  "provider_id" INTEGER,
  "provider_order_id" TEXT NOT NULL DEFAULT '',
  "provider_status" TEXT NOT NULL DEFAULT '',
  "start_count" INTEGER,
  "remains" INTEGER,
  "api_error" TEXT NOT NULL DEFAULT '',
  "payment_method_id" INTEGER,
  "trx_id" TEXT NOT NULL DEFAULT '',
  "paid_amount" REAL,
  "paid_at" TEXT,
  "synced_at" TEXT,
  "ip" TEXT NOT NULL DEFAULT '',
  "created_at" TEXT NOT NULL,
  "updated_at" TEXT,
  FOREIGN KEY ("service_id") REFERENCES "services" ("id") ON DELETE SET NULL,
  FOREIGN KEY ("platform_id") REFERENCES "platforms" ("id") ON DELETE SET NULL,
  FOREIGN KEY ("payment_method_id") REFERENCES "payment_methods" ("id") ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS "ix_orders_status" ON "orders" ("status", "created_at");
CREATE INDEX IF NOT EXISTS "ix_orders_platform" ON "orders" ("platform_id");
CREATE INDEX IF NOT EXISTS "ix_orders_provider" ON "orders" ("provider_id", "provider_order_id");

CREATE TABLE IF NOT EXISTS "service_packages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "service_id" INTEGER NOT NULL REFERENCES "services" ("id") ON DELETE CASCADE,
  "quantity" INTEGER NOT NULL,
  "bonus_quantity" INTEGER NOT NULL DEFAULT 0,
  "price" REAL NOT NULL DEFAULT 0,
  "badge" TEXT NOT NULL DEFAULT '',
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT
);
CREATE INDEX IF NOT EXISTS "ix_service_packages_service"
  ON "service_packages" ("service_id", "sort_order");

CREATE TABLE IF NOT EXISTS "order_logs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "order_id" INTEGER NOT NULL,
  "message" TEXT NOT NULL,
  "is_internal" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT NOT NULL,
  FOREIGN KEY ("order_id") REFERENCES "orders" ("id") ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_order_logs_order" ON "order_logs" ("order_id", "created_at");

CREATE TABLE IF NOT EXISTS "pages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "slug" TEXT NOT NULL UNIQUE,
  "title" TEXT NOT NULL,
  "content" TEXT,
  "meta_title" TEXT NOT NULL DEFAULT '',
  "meta_description" TEXT NOT NULL DEFAULT '',
  "show_in_footer" INTEGER NOT NULL DEFAULT 1,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS "faqs" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "platform_id" INTEGER,
  "question" TEXT NOT NULL,
  "answer" TEXT NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY ("platform_id") REFERENCES "platforms" ("id") ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "ix_faqs_active" ON "faqs" ("is_active", "sort_order");

CREATE TABLE IF NOT EXISTS "messages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "name" TEXT NOT NULL,
  "email" TEXT NOT NULL DEFAULT '',
  "whatsapp" TEXT NOT NULL DEFAULT '',
  "body" TEXT NOT NULL,
  "is_read" INTEGER NOT NULL DEFAULT 0,
  "ip" TEXT NOT NULL DEFAULT '',
  "created_at" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "ix_messages_read" ON "messages" ("is_read", "created_at");

CREATE TABLE IF NOT EXISTS "rate_limits" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "action" TEXT NOT NULL,
  "ip" TEXT NOT NULL,
  "created_at" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "ix_rate_action_ip" ON "rate_limits" ("action", "ip", "created_at");

-- ===========================================================================
-- Seed data
-- ===========================================================================

INSERT OR IGNORE INTO "settings" ("k", "v") VALUES
  ('site_name',            'GrowKit'),
  ('site_tagline',         'Buy social media growth, safely'),
  ('currency_symbol',      'Rs '),
  ('default_platform',     'instagram'),
  ('theme_color',          '#6c4df6'),
  ('whatsapp_number',      ''),
  ('support_email',        ''),
  ('logo_path',            ''),
  ('favicon_path',         ''),
  ('head_code',            ''),
  ('default_markup',       '35'),
  ('auto_send_orders',     '1'),
  ('auto_sync_statuses',   '1'),
  ('require_trx_id',       '1'),
  ('allow_manual_services','1'),
  ('order_prefix',         'GK'),
  ('active_theme',         'default'),
  ('auto_packages',        '1'),
  ('app_version',          '');

INSERT OR IGNORE INTO "platforms" ("slug", "name", "icon", "color", "url_prefix", "sort_order", "is_active") VALUES
  ('instagram', 'Instagram', 'i-instagram', '#e1306c', 'instagram.com',  1, 1),
  ('tiktok',    'TikTok',    'i-tiktok',    '#111827', 'tiktok.com',     2, 1),
  ('youtube',   'YouTube',   'i-youtube',   '#ef4444', 'youtube.com',    3, 1),
  ('facebook',  'Facebook',  'i-facebook',  '#1877f2', 'facebook.com',   4, 1),
  ('x',         'X',         'i-x',         '#0f172a', 'x.com',          5, 1),
  ('telegram',  'Telegram',  'i-telegram',  '#2aabee', 't.me',           6, 1);

INSERT OR IGNORE INTO "categories" ("platform_id", "slug", "name", "sort_order", "is_active")
SELECT p."id", c."slug", c."name", c."sort_order", 1
FROM "platforms" p
CROSS JOIN (
  SELECT 'followers' AS "slug", 'Followers' AS "name", 1 AS "sort_order"
  UNION ALL SELECT 'likes', 'Likes', 2
  UNION ALL SELECT 'views', 'Views', 3
) c;

INSERT OR IGNORE INTO "payment_methods"
  ("name", "short_name", "account_title", "account_number", "sort_order", "is_active") VALUES
  ('JazzCash',      'JC', 'Your business name', '', 1, 0),
  ('EasyPaisa',     'EP', 'Your business name', '', 2, 0),
  ('Bank Transfer', 'BK', 'Your business name', '', 3, 0);

INSERT OR IGNORE INTO "pages" ("slug", "title", "content", "show_in_footer", "sort_order", "is_active") VALUES
  ('about-us',        'About Us',        '<p>Tell your customers who you are.</p>', 1, 1, 1),
  ('contact-us',      'Contact Us',      '<p>How people can reach you.</p>',        1, 2, 1),
  ('privacy-policy',  'Privacy Policy',  '<p>Replace with your privacy policy.</p>',1, 3, 1),
  ('refund-policy',   'Refund Policy',   '<p>Replace with your refund policy.</p>', 1, 4, 1),
  ('terms-of-service','Terms of Service','<p>Replace with your terms.</p>',         1, 5, 1);

INSERT OR IGNORE INTO "faqs" ("question", "answer", "sort_order", "is_active") VALUES
  ('Do you need my password?',
   'Never. We only need your public profile or post link. Anyone asking for your password is not us.', 1, 1),
  ('How fast does delivery start?',
   'Most orders start within 2-15 minutes of payment confirmation. Larger orders are delivered gradually so growth looks natural.', 2, 1),
  ('Is it safe for my account?',
   'Yes. Delivery is gradual and stays inside normal platform limits.', 3, 1),
  ('What if followers drop?',
   'Services with a refill guarantee are refilled free of charge. Send us your order code on WhatsApp.', 4, 1),
  ('Which payment methods do you accept?',
   'The methods listed on the payment page. After paying you submit the transaction ID and we confirm it.', 5, 1),
  ('Can I order for a private account?',
   'No. Your account must be public while the order is running.', 6, 1);
