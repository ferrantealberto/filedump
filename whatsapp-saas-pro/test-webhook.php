<?php
/**
 * Script per testare il webhook n8n
 * Esegui questo script per verificare che il webhook funzioni
 */
// Load WordPress
require_once('../../../wp-load.php');
echo "=== Test Webhook n8n ===\n\n";
// 1. Verifica tabelle
echo "1. Verifica tabelle database...\n";
global $wpdb;
$tables = array(
    'wsp_whatsapp_numbers',
    'wsp_messages', 
    'wsp_campaigns',
    'wsp_activity_log'
);
$all_ok = true;
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    if ($exists) {
        echo "  ✅ Tabella $table esiste\n";
    } else {
        echo "  ❌ Tabella $table NON esiste\n";
        $all_ok = false;
    }
}
if (!$all_ok) {
    echo "\n⚠️ Alcune tabelle mancano. Creazione in corso...\n";
    require_once('includes/class-wsp-database.php');
    WSP_Database::create_tables();
    echo "✅ Tabelle create\n";
}
// 2. Verifica API Key
echo "\n2. Verifica API Key...\n";
$api_key = get_option('wsp_api_key', 'demo-api-key-9lz721sv0xTjFNVA');
echo "  API Key: $api_key\n";
// 3. Test chiamata diretta al webhook
echo "\n3. Test chiamata webhook...\n";
$webhook_url = rest_url('wsp/v1/webhook');
echo "  URL Webhook: $webhook_url\n";
// Dati di test
$test_data = array(
    'numbers' => array(
        array(
            'phone' => '+393351234567',
            'name' => 'Test User',
            'email' => 'test@example.com'
        )
    ),
    'campaign' => 'test_' . time(),
    'source' => 'test_script'
);
echo "\n  Invio dati di test...\n";
// Chiamata al webhook
$response = wp_remote_post($webhook_url, array(
    'headers' => array(
        'Content-Type' => 'application/json',
        'X-API-Key' => $api_key
    ),
    'body' => json_encode($test_data),
    'timeout' => 30,
    'sslverify' => false
));
if (is_wp_error($response)) {
    echo "  ❌ Errore: " . $response->get_error_message() . "\n";
} else {
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "  Status Code: $status\n";
    
    if ($status >= 200 && $status < 300) {
        echo "  ✅ Webhook funziona correttamente!\n";
        echo "  Risposta: " . print_r(json_decode($body, true), true) . "\n";
    } else {
        echo "  ❌ Errore HTTP $status\n";
        echo "  Risposta: $body\n";
    }
}
// 4. Verifica dati inseriti
echo "\n4. Verifica dati inseriti nel database...\n";
$table = $wpdb->prefix . 'wsp_whatsapp_numbers';
$last_number = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");
if ($last_number) {
    echo "  Ultimo numero inserito:\n";
    echo "    - Numero: " . $last_number->sender_formatted . "\n";
    echo "    - Nome: " . $last_number->sender_name . "\n";
    echo "    - Email: " . $last_number->sender_email . "\n";
    echo "    - Sorgente: " . $last_number->source . "\n";
    echo "    - Data: " . $last_number->created_at . "\n";
} else {
    echo "  Nessun numero nel database\n";
}
echo "\n=== Test completato ===\n";
--------------------------------------------------------------------------------