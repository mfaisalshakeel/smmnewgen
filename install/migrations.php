<?php
/**
 * Schema changes applied after the first install.
 *
 * The installer runs these straight after schema.sql, and an existing site
 * picks them up from Settings -> Update database. Each entry is applied once
 * and recorded in the `migrations` table.
 *
 * Statements are written per driver because MySQL and SQLite disagree about
 * ALTER TABLE. Anything that fails because it is already there is skipped -
 * SQLite has no ADD COLUMN IF NOT EXISTS, so that is how we get the same
 * behaviour on both.
 */

return [

    // -----------------------------------------------------------------------
    '2026_10_currencies' => [
        'label'  => 'Currencies, provider currency and service markup',
        'mysql'  => [
            "CREATE TABLE IF NOT EXISTS `currencies` (
               `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
               `code` VARCHAR(10) NOT NULL,
               `name` VARCHAR(60) NOT NULL,
               `symbol` VARCHAR(10) NOT NULL DEFAULT '',
               `rate_to_base` DECIMAL(18,8) NOT NULL DEFAULT 1,
               `is_base` TINYINT(1) NOT NULL DEFAULT 0,
               `is_active` TINYINT(1) NOT NULL DEFAULT 1,
               `sort_order` INT NOT NULL DEFAULT 0,
               `updated_at` DATETIME NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_currencies_code` (`code`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "ALTER TABLE `providers` ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT ''",
            "ALTER TABLE `providers` ADD COLUMN `supports_multi_status` TINYINT(1) NULL",
            "ALTER TABLE `providers` ADD COLUMN `auto_sync` TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `providers` ADD COLUMN `last_sync_at` DATETIME NULL",
            "ALTER TABLE `providers` ADD COLUMN `last_error` VARCHAR(500) NOT NULL DEFAULT ''",

            "ALTER TABLE `services` ADD COLUMN `provider_rate` DECIMAL(18,6) NOT NULL DEFAULT 0",
            "ALTER TABLE `services` ADD COLUMN `markup_percent` DECIMAL(8,2) NOT NULL DEFAULT 0",
            "ALTER TABLE `services` ADD COLUMN `auto_sync` TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `services` ADD COLUMN `last_synced_at` DATETIME NULL",
            "ALTER TABLE `services` ADD COLUMN `supports_dripfeed` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `services` ADD COLUMN `missing_at_provider` TINYINT(1) NOT NULL DEFAULT 0",
        ],
        'sqlite' => [
            'CREATE TABLE IF NOT EXISTS "currencies" (
               "id" INTEGER PRIMARY KEY AUTOINCREMENT,
               "code" TEXT NOT NULL UNIQUE,
               "name" TEXT NOT NULL,
               "symbol" TEXT NOT NULL DEFAULT \'\',
               "rate_to_base" REAL NOT NULL DEFAULT 1,
               "is_base" INTEGER NOT NULL DEFAULT 0,
               "is_active" INTEGER NOT NULL DEFAULT 1,
               "sort_order" INTEGER NOT NULL DEFAULT 0,
               "updated_at" TEXT
             )',

            'ALTER TABLE "providers" ADD COLUMN "currency" TEXT NOT NULL DEFAULT \'\'',
            'ALTER TABLE "providers" ADD COLUMN "supports_multi_status" INTEGER',
            'ALTER TABLE "providers" ADD COLUMN "auto_sync" INTEGER NOT NULL DEFAULT 1',
            'ALTER TABLE "providers" ADD COLUMN "last_sync_at" TEXT',
            'ALTER TABLE "providers" ADD COLUMN "last_error" TEXT NOT NULL DEFAULT \'\'',

            'ALTER TABLE "services" ADD COLUMN "provider_rate" REAL NOT NULL DEFAULT 0',
            'ALTER TABLE "services" ADD COLUMN "markup_percent" REAL NOT NULL DEFAULT 0',
            'ALTER TABLE "services" ADD COLUMN "auto_sync" INTEGER NOT NULL DEFAULT 1',
            'ALTER TABLE "services" ADD COLUMN "last_synced_at" TEXT',
            'ALTER TABLE "services" ADD COLUMN "supports_dripfeed" INTEGER NOT NULL DEFAULT 0',
            'ALTER TABLE "services" ADD COLUMN "missing_at_provider" INTEGER NOT NULL DEFAULT 0',
        ],
        'both' => [
            "INSERT INTO currencies (code, name, symbol, rate_to_base, is_base, is_active, sort_order, updated_at)
               VALUES ('PKR', 'Pakistani Rupee', 'Rs ', 1, 1, 1, 1, '2026-01-01 00:00:00')",
            "INSERT INTO currencies (code, name, symbol, rate_to_base, is_base, is_active, sort_order, updated_at)
               VALUES ('USD', 'US Dollar', '$', 0.0036, 0, 1, 2, '2026-01-01 00:00:00')",
            "INSERT INTO settings (`k`, `v`) VALUES ('base_currency', 'PKR')",
            "INSERT INTO settings (`k`, `v`) VALUES ('currency_rates_updated_at', '')",
            "INSERT INTO settings (`k`, `v`) VALUES ('currency_auto_update', '1')",
        ],
    ],

    // -----------------------------------------------------------------------
    '2026_10_cron_runs' => [
        'label' => 'Cron run history and cron-job.org settings',
        'mysql' => [
            "CREATE TABLE IF NOT EXISTS `cron_runs` (
               `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
               `task` VARCHAR(40) NOT NULL,
               `source` VARCHAR(20) NOT NULL DEFAULT 'cron',
               `ok` TINYINT(1) NOT NULL DEFAULT 1,
               `summary` VARCHAR(500) NOT NULL DEFAULT '',
               `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
               `created_at` DATETIME NOT NULL,
               PRIMARY KEY (`id`),
               KEY `ix_cron_runs_task` (`task`, `created_at`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],
        'sqlite' => [
            'CREATE TABLE IF NOT EXISTS "cron_runs" (
               "id" INTEGER PRIMARY KEY AUTOINCREMENT,
               "task" TEXT NOT NULL,
               "source" TEXT NOT NULL DEFAULT \'cron\',
               "ok" INTEGER NOT NULL DEFAULT 1,
               "summary" TEXT NOT NULL DEFAULT \'\',
               "duration_ms" INTEGER NOT NULL DEFAULT 0,
               "created_at" TEXT NOT NULL
             )',
            'CREATE INDEX IF NOT EXISTS "ix_cron_runs_task" ON "cron_runs" ("task", "created_at")',
        ],
        'both' => [
            "INSERT INTO settings (`k`, `v`) VALUES ('cronjob_org_key', '')",
            "INSERT INTO settings (`k`, `v`) VALUES ('cronjob_org_job_id', '')",
            "INSERT INTO settings (`k`, `v`) VALUES ('sync_services_on_cron', '1')",
            "INSERT INTO settings (`k`, `v`) VALUES ('active_theme', 'default')",
        ],
    ],

    // -----------------------------------------------------------------------
    '2026_10_payment_drivers' => [
        'label' => 'Pluggable payment gateways',
        'mysql' => [
            "ALTER TABLE `payment_methods` ADD COLUMN `driver` VARCHAR(40) NOT NULL DEFAULT 'manual'",
            "ALTER TABLE `payment_methods` ADD COLUMN `config` TEXT NULL",
            "UPDATE `payment_methods` SET `driver` = 'manual' WHERE `driver` = ''",
        ],
        'sqlite' => [
            'ALTER TABLE "payment_methods" ADD COLUMN "driver" TEXT NOT NULL DEFAULT \'manual\'',
            'ALTER TABLE "payment_methods" ADD COLUMN "config" TEXT',
            'UPDATE "payment_methods" SET "driver" = \'manual\' WHERE "driver" = \'\'',
        ],
    ],
];
