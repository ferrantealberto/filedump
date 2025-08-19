-- WhatsApp SaaS Pro - Database Schema v4.0.0
-- Installazione manuale tabelle database

-- IMPORTANTE: Sostituire 'wp_' con il proprio prefisso tabelle WordPress

-- 1. Tabella Clienti/Destinatari
CREATE TABLE IF NOT EXISTS `wp_wsp_customers` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_code` varchar(50) NOT NULL,
    `business_name` varchar(255) NOT NULL,
    `contact_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `phone` varchar(20) DEFAULT '',
    `whatsapp_number` varchar(20) DEFAULT '',
    `vat_number` varchar(50) DEFAULT '',
    `address` text,
    `city` varchar(100) DEFAULT '',
    `postal_code` varchar(20) DEFAULT '',
    `country` varchar(100) DEFAULT 'Italia',
    `plan_id` bigint(20) DEFAULT NULL,
    `credits_balance` int(11) DEFAULT 0,
    `qr_code_url` text,
    `api_key` varchar(255) DEFAULT '',
    `webhook_url` text,
    `is_active` tinyint(1) DEFAULT 1,
    `activation_date` datetime DEFAULT NULL,
    `expiration_date` datetime DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_customer_code` (`customer_code`),
    UNIQUE KEY `idx_email` (`email`),
    KEY `idx_plan` (`plan_id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_whatsapp` (`whatsapp_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabella Piani Abbonamento
CREATE TABLE IF NOT EXISTS `wp_wsp_subscription_plans` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `plan_name` varchar(100) NOT NULL,
    `plan_type` enum('standard','avanzato','plus','custom') DEFAULT 'standard',
    `billing_type` enum('mensile','crediti','entrambi') DEFAULT 'mensile',
    `monthly_price` decimal(10,2) DEFAULT 0.00,
    `credits_included` int(11) DEFAULT 0,
    `extra_credit_price` decimal(10,2) DEFAULT 0.00,
    `max_campaigns` int(11) DEFAULT -1,
    `max_messages_per_month` int(11) DEFAULT -1,
    `features` text,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`plan_type`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabella Sottoscrizioni
CREATE TABLE IF NOT EXISTS `wp_wsp_customer_subscriptions` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `plan_id` bigint(20) NOT NULL,
    `subscription_status` enum('active','suspended','cancelled','expired') DEFAULT 'active',
    `start_date` datetime NOT NULL,
    `end_date` datetime DEFAULT NULL,
    `renewal_date` datetime DEFAULT NULL,
    `credits_remaining` int(11) DEFAULT 0,
    `messages_sent_this_period` int(11) DEFAULT 0,
    `auto_renew` tinyint(1) DEFAULT 1,
    `payment_method` varchar(50) DEFAULT '',
    `last_payment_date` datetime DEFAULT NULL,
    `next_payment_date` datetime DEFAULT NULL,
    `notes` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_plan` (`plan_id`),
    KEY `idx_status` (`subscription_status`),
    KEY `idx_renewal` (`renewal_date`),
    CONSTRAINT `fk_subscription_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subscription_plan` FOREIGN KEY (`plan_id`) REFERENCES `wp_wsp_subscription_plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabella Numeri WhatsApp
CREATE TABLE IF NOT EXISTS `wp_wsp_whatsapp_numbers` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `sender_number` varchar(20) NOT NULL,
    `sender_name` varchar(255) DEFAULT '',
    `sender_formatted` varchar(20) DEFAULT '',
    `sender_email` varchar(255) DEFAULT '',
    `recipient_number` varchar(20) DEFAULT '',
    `recipient_name` varchar(255) DEFAULT '',
    `recipient_email` varchar(255) DEFAULT '',
    `email_subject` text,
    `source` varchar(50) DEFAULT 'manual',
    `campaign_id` varchar(100) DEFAULT '',
    `opt_in_date` datetime DEFAULT NULL,
    `opt_out_date` datetime DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `tags` text,
    `custom_fields` longtext,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_sender_number` (`sender_number`),
    KEY `idx_recipient_number` (`recipient_number`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_numbers_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabella Campagne
CREATE TABLE IF NOT EXISTS `wp_wsp_campaigns` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `campaign_id` varchar(100) NOT NULL,
    `campaign_name` varchar(255) NOT NULL,
    `campaign_type` varchar(50) DEFAULT 'qr',
    `qr_code_url` text,
    `landing_page_url` text,
    `welcome_message` text,
    `total_scans` int(11) DEFAULT 0,
    `total_registrations` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `start_date` datetime DEFAULT NULL,
    `end_date` datetime DEFAULT NULL,
    `budget` decimal(10,2) DEFAULT 0.00,
    `spent` decimal(10,2) DEFAULT 0.00,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_campaign_id` (`campaign_id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_campaigns_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabella Messaggi
CREATE TABLE IF NOT EXISTS `wp_wsp_messages` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `whatsapp_number_id` bigint(20) DEFAULT NULL,
    `recipient_number` varchar(20) NOT NULL,
    `message_content` text NOT NULL,
    `message_type` varchar(50) DEFAULT 'manual',
    `delivery_status` varchar(50) DEFAULT 'pending',
    `api_response` text,
    `credits_used` int(11) DEFAULT 1,
    `campaign_id` varchar(100) DEFAULT '',
    `scheduled_at` datetime DEFAULT NULL,
    `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `delivered_at` datetime DEFAULT NULL,
    `read_at` datetime DEFAULT NULL,
    `error_message` text,
    `retry_count` int(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_number_id` (`whatsapp_number_id`),
    KEY `idx_recipient` (`recipient_number`),
    KEY `idx_status` (`delivery_status`),
    KEY `idx_sent` (`sent_at`),
    KEY `idx_campaign` (`campaign_id`),
    CONSTRAINT `fk_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabella Transazioni Crediti
CREATE TABLE IF NOT EXISTS `wp_wsp_credits_transactions` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `transaction_type` enum('purchase','usage','refund','bonus','expired') NOT NULL,
    `amount` int(11) NOT NULL,
    `balance_before` int(11) NOT NULL,
    `balance_after` int(11) NOT NULL,
    `description` text,
    `reference_id` varchar(100) DEFAULT '',
    `payment_id` varchar(100) DEFAULT '',
    `invoice_number` varchar(50) DEFAULT '',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_type` (`transaction_type`),
    KEY `idx_created` (`created_at`),
    KEY `idx_reference` (`reference_id`),
    CONSTRAINT `fk_credits_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabella Report
CREATE TABLE IF NOT EXISTS `wp_wsp_reports` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `report_type` varchar(50) NOT NULL,
    `request_source` varchar(50) DEFAULT 'whatsapp',
    `request_message` text,
    `report_data` longtext,
    `report_url` text,
    `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
    `requested_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `completed_at` datetime DEFAULT NULL,
    `sent_via` varchar(50) DEFAULT '',
    `error_message` text,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_requested` (`requested_at`),
    CONSTRAINT `fk_reports_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabella Activity Log
CREATE TABLE IF NOT EXISTS `wp_wsp_activity_log` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) DEFAULT NULL,
    `log_type` varchar(50) NOT NULL,
    `log_message` text NOT NULL,
    `log_data` longtext,
    `user_id` bigint(20) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT '',
    `user_agent` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_type` (`log_type`),
    KEY `idx_user` (`user_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Tabella Webhook Events
CREATE TABLE IF NOT EXISTS `wp_wsp_webhook_events` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) DEFAULT NULL,
    `event_type` varchar(100) NOT NULL,
    `payload` longtext,
    `response` longtext,
    `status` enum('pending','success','failed') DEFAULT 'pending',
    `attempts` int(11) DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `processed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_type` (`event_type`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Tabella Integrazioni
CREATE TABLE IF NOT EXISTS `wp_wsp_integrations` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `customer_id` bigint(20) NOT NULL,
    `integration_type` varchar(50) NOT NULL,
    `integration_name` varchar(255) NOT NULL,
    `api_endpoint` text,
    `api_key` text,
    `api_secret` text,
    `settings` longtext,
    `is_active` tinyint(1) DEFAULT 1,
    `last_sync` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_type` (`integration_type`),
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_integrations_customer` FOREIGN KEY (`customer_id`) REFERENCES `wp_wsp_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento Piani Predefiniti
INSERT INTO `wp_wsp_subscription_plans` (`plan_name`, `plan_type`, `billing_type`, `monthly_price`, `credits_included`, `extra_credit_price`, `max_campaigns`, `max_messages_per_month`, `features`, `is_active`) VALUES
('Piano Standard', 'standard', 'mensile', 29.00, 1000, 0.03, 5, 1000, '{"qr_codes":true,"welcome_messages":true,"basic_reports":true,"email_support":true,"api_access":false,"custom_branding":false,"priority_support":false}', 1),
('Piano Avanzato', 'avanzato', 'entrambi', 79.00, 5000, 0.025, 20, 5000, '{"qr_codes":true,"welcome_messages":true,"advanced_reports":true,"email_support":true,"phone_support":true,"api_access":true,"custom_branding":false,"priority_support":true,"webhooks":true,"bulk_import":true}', 1),
('Piano Plus', 'plus', 'entrambi', 199.00, 20000, 0.02, -1, -1, '{"qr_codes":true,"welcome_messages":true,"advanced_reports":true,"realtime_analytics":true,"email_support":true,"phone_support":true,"dedicated_support":true,"api_access":true,"custom_branding":true,"white_label":true,"priority_support":true,"webhooks":true,"bulk_import":true,"custom_integrations":true,"sla_guarantee":true}', 1);