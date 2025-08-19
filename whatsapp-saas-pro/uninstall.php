<?php
/**
 * Uninstall WhatsApp SaaS Pro
 * 
 * Rimuove tutte le tabelle e opzioni del plugin
 */
// Se uninstall non è chiamato da WordPress, esci
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
global $wpdb;
// Rimuovi tabelle
$tables = array(
    $wpdb->prefix . 'wsp_whatsapp_numbers',
    $wpdb->prefix . 'wsp_messages',
    $wpdb->prefix . 'wsp_campaigns',
    $wpdb->prefix . 'wsp_credits_transactions',
    $wpdb->prefix . 'wsp_activity_log'
);
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}
// Rimuovi opzioni
$options = array(
    'wsp_version',
    'wsp_db_version',
    'wsp_api_key',
    'wsp_credits_balance',
    'wsp_welcome_message',
    'wsp_mail2wa_api_key',
    'wsp_mail2wa_base_url',
    'wsp_mail2wa_endpoint_path',
    'wsp_mail2wa_method',
    'wsp_mail2wa_content_type',
    'wsp_mail2wa_auth_method',
    'wsp_mail2wa_phone_param',
    'wsp_mail2wa_message_param',
    'wsp_mail2wa_api_key_param',
    'wsp_mail2wa_extra_params',
    'wsp_mail2wa_email_fallback',
    'wsp_mail2wa_timeout',
    'wsp_report_email',
    'wsp_report_time',
    'wsp_report_enabled',
    'wsp_gmail_email',
    'wsp_gmail_password',
    'wsp_gmail_from_filter',
    'wsp_send_welcome_on_new'
);
foreach ($options as $option) {
    delete_option($option);
}
// Rimuovi cron jobs
wp_clear_scheduled_hook('wsp_process_emails');
wp_clear_scheduled_hook('wsp_daily_report');
// Pulisci cache
flush_rewrite_rules();