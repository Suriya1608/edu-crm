-- ============================================================
--  EDU-CRM — Live Server Database Migration Queries
--  Generated : 2026-03-12
--  Run these queries in order on the live server MySQL DB.
--  All ALTER TABLE statements use IF NOT EXISTS / IF EXISTS
--  guards so they are safe to re-run without errors.
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- BLOCK 1 · users table — Online Presence
-- Migration: 2026_02_25_102000_add_online_presence_to_users_table
-- ────────────────────────────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_online`    TINYINT(1)  NOT NULL DEFAULT 0   AFTER `status`,
    ADD COLUMN IF NOT EXISTS `last_seen_at` TIMESTAMP   NULL     DEFAULT NULL AFTER `is_online`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 2 · call_logs table — Telecaller Mapping Fields
-- Migration: 2026_02_25_100000_add_last_telecaller_mapping_fields_to_call_logs_table
-- Note: telecaller_id and twilio_call_sid added here but dropped in Block 3.
--       Skip this block if you are running all blocks together (net result = no change).
--       Included for completeness/rollback reference only.
-- ────────────────────────────────────────────────────────────
ALTER TABLE `call_logs`
    ADD COLUMN IF NOT EXISTS `customer_number` VARCHAR(255) NULL DEFAULT NULL AFTER `lead_id`,
    ADD COLUMN IF NOT EXISTS `direction`       ENUM('outbound','inbound') NULL DEFAULT NULL AFTER `provider`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 3 · call_logs table — Drop Duplicate Columns
-- Migration: 2026_02_25_111000_drop_duplicate_calllog_columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE `call_logs`
    DROP COLUMN IF EXISTS `twilio_call_sid`,
    DROP COLUMN IF EXISTS `telecaller_id`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 4 · notifications table — Create (Laravel standard)
-- Migration: 2026_02_26_100000_create_notifications_table_if_missing
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`              CHAR(36)     NOT NULL,
    `type`            VARCHAR(255) NOT NULL,
    `notifiable_type` VARCHAR(255) NOT NULL,
    `notifiable_id`   BIGINT UNSIGNED NOT NULL,
    `data`            TEXT         NOT NULL,
    `read_at`         TIMESTAMP    NULL DEFAULT NULL,
    `created_at`      TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 5 · followups table — escalated_at, completed_at, reminder_notified_at
-- Migrations: 2026_02_26_101000 / 2026_02_26_120000 / 2026_02_26_160000
-- ────────────────────────────────────────────────────────────
ALTER TABLE `followups`
    ADD COLUMN IF NOT EXISTS `escalated_at`         TIMESTAMP NULL DEFAULT NULL AFTER `next_followup`,
    ADD COLUMN IF NOT EXISTS `completed_at`          TIMESTAMP NULL DEFAULT NULL AFTER `next_followup`,
    ADD COLUMN IF NOT EXISTS `reminder_notified_at`  TIMESTAMP NULL DEFAULT NULL AFTER `escalated_at`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 6 · leads table — SLA escalation + duplicate tracking
-- Migrations: 2026_02_26_160000 / 2026_03_03_100000
-- ────────────────────────────────────────────────────────────
ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `sla_escalated_at`      TIMESTAMP    NULL DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `is_duplicate`          TINYINT(1)   NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `merged_into_lead_id`   BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_duplicate`;

-- Foreign key for merged_into_lead_id (skip if already exists)
-- Run this separately if the ALTER above succeeded:
-- ALTER TABLE `leads` ADD CONSTRAINT `leads_merged_into_lead_id_foreign`
--     FOREIGN KEY (`merged_into_lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;


-- ────────────────────────────────────────────────────────────
-- BLOCK 7 · whatsapp_messages table — Status & Media columns
-- Migrations: 2026_02_27_100000 / 2026_03_05_100000
-- ────────────────────────────────────────────────────────────
ALTER TABLE `whatsapp_messages`
    ADD COLUMN IF NOT EXISTS `provider_message_id` VARCHAR(255) NULL DEFAULT NULL AFTER `direction`,
    ADD COLUMN IF NOT EXISTS `sent_at`             TIMESTAMP   NULL DEFAULT NULL AFTER `provider_message_id`,
    ADD COLUMN IF NOT EXISTS `meta_data`           JSON        NULL DEFAULT NULL AFTER `sent_at`,
    ADD COLUMN IF NOT EXISTS `is_read`             TINYINT(1)  NOT NULL DEFAULT 0 AFTER `meta_data`,
    ADD COLUMN IF NOT EXISTS `media_type`          VARCHAR(50) NULL DEFAULT NULL AFTER `meta_data`,
    ADD COLUMN IF NOT EXISTS `media_url`           TEXT        NULL DEFAULT NULL AFTER `media_type`,
    ADD COLUMN IF NOT EXISTS `media_filename`      VARCHAR(255) NULL DEFAULT NULL AFTER `media_url`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 8 · call_logs table — outcome column + index
-- Migration: 2026_03_03_100100 / 2026_03_03_100300
-- ────────────────────────────────────────────────────────────
ALTER TABLE `call_logs`
    ADD COLUMN IF NOT EXISTS `outcome` ENUM(
        'interested',
        'not_interested',
        'wrong_number',
        'call_back_later',
        'switched_off'
    ) NULL DEFAULT NULL AFTER `duration`;

CREATE INDEX IF NOT EXISTS `call_logs_outcome_index` ON `call_logs` (`outcome`);


-- ────────────────────────────────────────────────────────────
-- BLOCK 9 · audit_logs table — Create
-- Migration: 2026_03_03_100200_create_audit_logs_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `action`     VARCHAR(100)    NOT NULL,
    `model`      VARCHAR(100)    NULL DEFAULT NULL,
    `model_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `old_values` JSON            NULL DEFAULT NULL,
    `new_values` JSON            NULL DEFAULT NULL,
    `ip_address` VARCHAR(45)     NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `audit_logs_action_index`   (`action`),
    INDEX `audit_logs_model_index`    (`model`),
    INDEX `audit_logs_model_id_index` (`model_id`),
    INDEX `audit_logs_created_at_index` (`created_at`),
    CONSTRAINT `audit_logs_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 10 · leads table — Performance indexes
-- Migration: 2026_03_03_100300_add_performance_indexes
-- ────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS `leads_course_index`              ON `leads` (`course`);
CREATE INDEX IF NOT EXISTS `leads_created_at_index`          ON `leads` (`created_at`);
CREATE INDEX IF NOT EXISTS `leads_phone_index`               ON `leads` (`phone`);
CREATE INDEX IF NOT EXISTS `leads_assigned_by_status_index`  ON `leads` (`assigned_by`, `status`);
CREATE INDEX IF NOT EXISTS `leads_assigned_to_status_index`  ON `leads` (`assigned_to`, `status`);


-- ────────────────────────────────────────────────────────────
-- BLOCK 11 · campaigns table — Create
-- Migration: 2026_03_09_200000_create_campaigns_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)    NOT NULL,
    `description` TEXT            NULL DEFAULT NULL,
    `status`      ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'active',
    `created_by`  BIGINT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `campaigns_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 12 · campaign_contacts table — Create
-- Migration: 2026_03_09_200100_create_campaign_contacts_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaign_contacts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`  BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(255)    NOT NULL,
    `phone`        VARCHAR(255)    NOT NULL,
    `email`        VARCHAR(255)    NULL DEFAULT NULL,
    `course`       VARCHAR(255)    NULL DEFAULT NULL,
    `city`         VARCHAR(255)    NULL DEFAULT NULL,
    `status`       ENUM('pending','called','interested','not_interested','no_answer','callback','converted')
                   NOT NULL DEFAULT 'pending',
    `assigned_to`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `next_followup` DATE           NULL DEFAULT NULL,
    `call_count`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `campaign_contacts_campaign_id_assigned_to_index` (`campaign_id`, `assigned_to`),
    INDEX `campaign_contacts_phone_campaign_id_index` (`phone`, `campaign_id`),
    CONSTRAINT `campaign_contacts_campaign_id_foreign`
        FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campaign_contacts_assigned_to_foreign`
        FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 13 · campaign_activities table — Create
-- Migration: 2026_03_09_200200_create_campaign_activities_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaign_activities` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_contact_id`  BIGINT UNSIGNED NOT NULL,
    `type`                 ENUM('call','whatsapp','note','status_change','followup_set') NOT NULL,
    `description`          TEXT            NULL DEFAULT NULL,
    `meta`                 JSON            NULL DEFAULT NULL,
    `created_by`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`           TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `campaign_activities_campaign_contact_id_type_index` (`campaign_contact_id`, `type`),
    CONSTRAINT `campaign_activities_campaign_contact_id_foreign`
        FOREIGN KEY (`campaign_contact_id`) REFERENCES `campaign_contacts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `campaign_activities_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 14 · followups + campaign_contacts — followup_time column
-- Migration: 2026_03_09_300000_add_followup_time_to_followups_and_campaign_contacts
-- ────────────────────────────────────────────────────────────
ALTER TABLE `followups`
    ADD COLUMN IF NOT EXISTS `followup_time` TIME NULL DEFAULT NULL AFTER `next_followup`;

ALTER TABLE `campaign_contacts`
    ADD COLUMN IF NOT EXISTS `followup_time` TIME NULL DEFAULT NULL AFTER `next_followup`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 15 · whatsapp_messages — campaign_contact_id column
-- Migration: 2026_03_09_400000_add_campaign_contact_id_to_whatsapp_messages
-- ────────────────────────────────────────────────────────────
ALTER TABLE `whatsapp_messages`
    ADD COLUMN IF NOT EXISTS `campaign_contact_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `lead_id`;

CREATE INDEX IF NOT EXISTS `whatsapp_messages_campaign_contact_id_index`
    ON `whatsapp_messages` (`campaign_contact_id`);


-- ────────────────────────────────────────────────────────────
-- BLOCK 16 · users table — Security columns (brute-force protection)
-- Migration: 2026_03_10_100000_add_security_to_users_table
-- ────────────────────────────────────────────────────────────
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `locked_until`          TIMESTAMP        NULL DEFAULT NULL AFTER `failed_login_attempts`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 17 · user_sessions table — Device info + Location columns
-- Migrations: 2026_03_10_110000 / 2026_03_10_120000
-- ────────────────────────────────────────────────────────────
ALTER TABLE `user_sessions`
    ADD COLUMN IF NOT EXISTS `ip_address`       VARCHAR(45)  NULL DEFAULT NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `user_agent`       VARCHAR(500) NULL DEFAULT NULL AFTER `ip_address`,
    ADD COLUMN IF NOT EXISTS `device_type`      VARCHAR(30)  NULL DEFAULT NULL AFTER `user_agent`,
    ADD COLUMN IF NOT EXISTS `browser`          VARCHAR(80)  NULL DEFAULT NULL AFTER `device_type`,
    ADD COLUMN IF NOT EXISTS `platform`         VARCHAR(80)  NULL DEFAULT NULL AFTER `browser`,
    ADD COLUMN IF NOT EXISTS `location_area`    VARCHAR(100) NULL DEFAULT NULL AFTER `platform`,
    ADD COLUMN IF NOT EXISTS `location_city`    VARCHAR(100) NULL DEFAULT NULL AFTER `location_area`,
    ADD COLUMN IF NOT EXISTS `location_state`   VARCHAR(100) NULL DEFAULT NULL AFTER `location_city`,
    ADD COLUMN IF NOT EXISTS `location_country` VARCHAR(100) NULL DEFAULT NULL AFTER `location_state`;


-- ────────────────────────────────────────────────────────────
-- BLOCK 18 · documents table — Create (Document Management Module)
-- Migration: 2026_03_10_200000_create_documents_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255)    NOT NULL,
    `file_path`   VARCHAR(255)    NOT NULL,
    `file_name`   VARCHAR(255)    NOT NULL,
    `file_type`   VARCHAR(255)    NULL DEFAULT NULL,
    `file_size`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by` BIGINT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `documents_uploaded_by_foreign`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 19 · instagram_accounts table — Create
-- Migration: 2026_03_12_200000_create_instagram_accounts_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `instagram_accounts` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id`             VARCHAR(255)    NOT NULL,
    `instagram_user_id`   VARCHAR(255)    NULL DEFAULT NULL,
    `name`                VARCHAR(255)    NOT NULL,
    `access_token`        TEXT            NOT NULL,  -- Laravel encrypted value
    `app_secret`          TEXT            NULL DEFAULT NULL, -- Laravel encrypted value
    `verify_token`        VARCHAR(128)    NOT NULL,
    `is_active`           TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `instagram_accounts_page_id_unique` (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 20 · instagram_conversations table — Create
-- Migration: 2026_03_12_200100_create_instagram_conversations_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `instagram_conversations` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `instagram_account_id`  BIGINT UNSIGNED NOT NULL,
    `sender_id`             VARCHAR(255)    NOT NULL,
    `sender_name`           VARCHAR(255)    NULL DEFAULT NULL,
    `sender_username`       VARCHAR(255)    NULL DEFAULT NULL,
    `last_message_preview`  TEXT            NULL DEFAULT NULL,
    `last_message_at`       TIMESTAMP       NULL DEFAULT NULL,
    `unread_count`          INT UNSIGNED    NOT NULL DEFAULT 0,
    `assigned_to`           BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ig_conv_account_sender_unique` (`instagram_account_id`, `sender_id`),
    INDEX `instagram_conversations_last_message_at_index` (`last_message_at`),
    CONSTRAINT `ig_conv_account_foreign`
        FOREIGN KEY (`instagram_account_id`) REFERENCES `instagram_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ig_conv_assigned_to_foreign`
        FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- BLOCK 21 · instagram_messages table — Create
-- Migration: 2026_03_12_200200_create_instagram_messages_table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `instagram_messages` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `mid`             VARCHAR(255)    NOT NULL,
    `direction`       ENUM('inbound','outbound') NOT NULL,
    `body`            TEXT            NOT NULL,
    `sent_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `is_read`         TINYINT(1)      NOT NULL DEFAULT 0,
    `sent_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `instagram_messages_mid_unique` (`mid`),
    INDEX `instagram_messages_conversation_sent_at_index` (`conversation_id`, `sent_at`),
    CONSTRAINT `ig_msg_conversation_foreign`
        FOREIGN KEY (`conversation_id`) REFERENCES `instagram_conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ig_msg_sent_by_foreign`
        FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Email Campaign Module (2026-03-13)
-- ============================================================

CREATE TABLE IF NOT EXISTS `email_campaigns` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(255)    NOT NULL,
    `description`      TEXT            NULL DEFAULT NULL,
    `template_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `template_name`    VARCHAR(255)    NOT NULL,
    `template_subject` VARCHAR(255)    NOT NULL,
    `template_body`    LONGTEXT        NOT NULL,
    `course_filter`    VARCHAR(255)    NULL DEFAULT NULL,
    `scheduled_at`     TIMESTAMP       NULL DEFAULT NULL,
    `sent_at`          TIMESTAMP       NULL DEFAULT NULL,
    `status`           ENUM('draft','scheduled','sending','completed','failed') NOT NULL DEFAULT 'draft',
    `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `recipients_count` INT UNSIGNED    NOT NULL DEFAULT 0,
    `sent_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `failed_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `opened_count`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `bounced_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `email_campaigns_template_id_foreign`
        FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `email_campaigns_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_campaign_recipients` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email_campaign_id` BIGINT UNSIGNED NOT NULL,
    `email`             VARCHAR(255)    NOT NULL,
    `name`              VARCHAR(255)    NULL DEFAULT NULL,
    `tracking_token`    VARCHAR(64)     NOT NULL,
    `status`            ENUM('pending','sent','failed','bounced') NOT NULL DEFAULT 'pending',
    `sent_at`           TIMESTAMP       NULL DEFAULT NULL,
    `opened_at`         TIMESTAMP       NULL DEFAULT NULL,
    `error_message`     TEXT            NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ecr_tracking_token_unique` (`tracking_token`),
    INDEX `ecr_email_campaign_id_index` (`email_campaign_id`),
    INDEX `ecr_status_index` (`status`),
    CONSTRAINT `ecr_email_campaign_id_foreign`
        FOREIGN KEY (`email_campaign_id`) REFERENCES `email_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Course Management (2026-03-13)
-- ============================================================

CREATE TABLE IF NOT EXISTS `courses` (
    `id`          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)      NOT NULL,
    `code`        VARCHAR(50)       NULL DEFAULT NULL,
    `description` TEXT              NULL DEFAULT NULL,
    `is_active`   TINYINT(1)        NOT NULL DEFAULT 1,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP         NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP         NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- END OF MIGRATIONS
-- ============================================================
-- TIP: On the live server you can also just run:
--      php artisan migrate
-- from the project root if SSH/shell access is available.
-- These raw SQL queries are provided as a fallback for
-- cPanel phpMyAdmin or direct DB access without shell.
-- ============================================================
