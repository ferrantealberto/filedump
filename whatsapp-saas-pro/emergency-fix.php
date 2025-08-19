<?php
/**
 * SCRIPT DI EMERGENZA - Fix completo database WhatsApp SaaS Pro
 * Esegui questo script se hai errori "Unknown column" o altri problemi database
 * 
 * USO: php emergency-fix.php
 */
// Load WordPress
$wp_load_paths = array(
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../../../../../wp-load.php',
    '/var/www/html/wp-load.php',
    '/home/user/public_html/wp-load.php'
);
$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}
if (!$wp_loaded) {
    die("Errore: Impossibile trovare wp-load.php. Esegui questo script dalla directory del plugin.\n");
}
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     WhatsApp SaaS Pro - EMERGENCY DATABASE FIX           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";
global $wpdb;
// Definisci tutte le tabelle
$tables = array(
    'numbers' => $wpdb->prefix . 'wsp_whatsapp_numbers',
    'messages' => $wpdb->prefix . 'wsp_messages',
    'campaigns' => $wpdb->prefix . 'wsp_campaigns',
    'credits' => $wpdb->prefix . 'wsp_credits_transactions',
    'logs' => $wpdb->prefix . 'wsp_activity_log'
);
echo "🔍 STEP 1: Verifica tabelle esistenti\n";
echo "=====================================\n";
foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "✅ Tabella $name ($table) esiste\n";
    } else {
        echo "❌ Tabella $name ($table) NON esiste - verrà creata\n";
    }
}
echo "\n🔧 STEP 2: Fix struttura tabelle\n";
echo "================================\n";
// Fix tabella wsp_whatsapp_numbers
echo "\n→ Fixing tabella NUMBERS...\n";
$table = $tables['numbers'];
// Lista colonne richieste per tabella numbers
$required_columns = array(
    'id' => 'bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY',
    'sender_number' => 'varchar(20) NOT NULL',
    'sender_name' => 'varchar(255) DEFAULT NULL',
    'sender_formatted' => 'varchar(20) DEFAULT NULL',
    'sender_email' => 'varchar(255) DEFAULT NULL',
    'recipient_number' => 'varchar(20) DEFAULT NULL',
    'recipient_name' => 'varchar(255) DEFAULT NULL',
    'recipient_email' => 'varchar(255) DEFAULT NULL',
    'email_subject' => 'text',
    'source' => "varchar(50) DEFAULT 'manual'",
    'campaign_id' => "varchar(100) DEFAULT ''",
    'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP'
);
// Verifica e aggiungi colonne mancanti
$existing_columns = array();
$result = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach ($result as $col) {
    $existing_columns[] = $col->Field;
}
foreach ($required_columns as $col_name => $col_type) {
    if (!in_array($col_name, $existing_columns)) {
        if ($col_name == 'id') continue; // Skip primary key
        
        echo "  + Aggiunta colonna '$col_name'...\n";
        $sql = "ALTER TABLE $table ADD COLUMN $col_name $col_type";
        $wpdb->query($sql);
        
        if ($wpdb->last_error) {
            echo "    ⚠️ Errore: " . $wpdb->last_error . "\n";
        } else {
            echo "    ✅ OK\n";
        }
    }
}
// Fix tabella wsp_campaigns
echo "\n→ Fixing tabella CAMPAIGNS...\n";
$table = $tables['campaigns'];
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$exists) {
    echo "  Creazione tabella campaigns...\n";
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        campaign_id varchar(100) NOT NULL,
        campaign_name varchar(255) NOT NULL,
        campaign_type varchar(50) DEFAULT 'qr',
        qr_code_url text,
        landing_page_url text,
        welcome_message text,
        total_scans int(11) DEFAULT 0,
        total_registrations int(11) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_campaign_id (campaign_id),
        KEY idx_active (is_active)
    ) $charset_collate";
    
    $wpdb->query($sql);
    echo "  ✅ Tabella campaigns creata\n";
} else {
    // Verifica colonna campaign_id
    $has_campaign_id = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'campaign_id'");
    if (!$has_campaign_id) {
        echo "  + Aggiunta colonna 'campaign_id' a campaigns...\n";
        $wpdb->query("ALTER TABLE $table ADD COLUMN campaign_id varchar(100) NOT NULL AFTER id");
        $wpdb->query("ALTER TABLE $table ADD UNIQUE KEY idx_campaign_id (campaign_id)");
        echo "    ✅ OK\n";
    }
}
// Aggiungi indici se mancanti
echo "\n🔗 STEP 3: Verifica indici\n";
echo "==========================\n";
$table = $tables['numbers'];
$indices = $wpdb->get_results("SHOW INDEX FROM $table");
$has_campaign_index = false;
foreach ($indices as $index) {
    if ($index->Key_name == 'idx_campaign') {
        $has_campaign_index = true;
    }
}
if (!$has_campaign_index) {
    echo "  + Aggiunta indice idx_campaign...\n";
    $wpdb->query("ALTER TABLE $table ADD INDEX idx_campaign (campaign_id)");
    echo "    ✅ OK\n";
} else {
    echo "  ✓ Indice idx_campaign già presente\n";
}
// Test finale
echo "\n✨ STEP 4: Test finale\n";
echo "======================\n";
// Test query problematica
$table_campaigns = $tables['campaigns'];
$table_numbers = $tables['numbers'];
$test_query = "SELECT c.*, 
               COALESCE((SELECT COUNT(*) FROM $table_numbers n WHERE n.campaign_id = c.campaign_id), 0) as registrations
               FROM $table_campaigns c 
               ORDER BY c.created_at DESC LIMIT 1";
$wpdb->suppress_errors(true);
$result = $wpdb->get_results($test_query);
$wpdb->suppress_errors(false);
if ($wpdb->last_error) {
    echo "❌ La query delle campagne ancora non funziona:\n";
    echo "   Errore: " . $wpdb->last_error . "\n";
    echo "\n🚨 Provo fix alternativo...\n";
    
    // Drop e ricrea tabelle
    foreach ($tables as $name => $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Ricrea usando la classe del plugin
    require_once('includes/class-wsp-database.php');
    WSP_Database::create_tables();
    
    echo "✅ Tabelle ricreate da zero\n";
} else {
    echo "✅ Tutte le query funzionano correttamente!\n";
}
// Inserisci dati di esempio se le tabelle sono vuote
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['numbers']}");
if ($count == 0) {
    echo "\n📝 Inserimento dati di esempio...\n";
    require_once('includes/class-wsp-sample-data.php');
    WSP_Sample_Data::insert_sample_data();
    echo "✅ Dati di esempio inseriti\n";
}
echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    FIX COMPLETATO!                        ║\n";
echo "║                                                           ║\n";
echo "║  1. Torna al pannello WordPress                          ║\n";
echo "║  2. Ricarica la pagina Campagne                          ║\n";
echo "║  3. Se hai ancora problemi, vai a:                       ║\n";
echo "║     WhatsApp SaaS > Impostazioni > Fix Database          ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n";
--------------------------------------------------------------------------------