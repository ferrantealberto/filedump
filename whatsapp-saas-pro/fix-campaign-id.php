<?php
/**
 * Fix specifico per l'errore "Unknown column 'campaign_id'"
 * Esegui questo script se vedi l'errore nella pagina Campagne
 */
// Load WordPress
require_once('../../../wp-load.php');
echo "=== WhatsApp SaaS Pro - Fix Campaign ID ===\n\n";
global $wpdb;
// Tabella numeri
$table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
echo "Controllo tabella: $table_numbers\n\n";
// Verifica se la colonna campaign_id esiste
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_numbers");
$has_campaign_id = false;
echo "Colonne attuali nella tabella:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
    if ($column->Field === 'campaign_id') {
        $has_campaign_id = true;
    }
}
echo "\n";
if (!$has_campaign_id) {
    echo "❌ La colonna 'campaign_id' NON esiste. Aggiunta in corso...\n";
    
    // Aggiungi la colonna campaign_id
    $sql = "ALTER TABLE $table_numbers ADD COLUMN campaign_id varchar(100) DEFAULT '' AFTER source";
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ Colonna 'campaign_id' aggiunta con successo!\n";
        
        // Aggiungi anche l'indice
        $sql_index = "ALTER TABLE $table_numbers ADD INDEX idx_campaign (campaign_id)";
        $wpdb->query($sql_index);
        echo "✅ Indice 'idx_campaign' aggiunto!\n";
    } else {
        echo "❌ Errore nell'aggiunta della colonna: " . $wpdb->last_error . "\n";
        
        // Prova metodo alternativo
        echo "\nProvo metodo alternativo...\n";
        $sql = "ALTER TABLE $table_numbers ADD campaign_id varchar(100) DEFAULT ''";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo "✅ Colonna aggiunta con metodo alternativo!\n";
        } else {
            echo "❌ Anche il metodo alternativo ha fallito: " . $wpdb->last_error . "\n";
        }
    }
} else {
    echo "✅ La colonna 'campaign_id' esiste già.\n";
}
// Verifica anche altre colonne che potrebbero mancare
echo "\nControllo altre colonne importanti...\n";
$columns_to_check = array(
    'source' => "ALTER TABLE $table_numbers ADD COLUMN source varchar(50) DEFAULT 'manual'",
    'sender_formatted' => "ALTER TABLE $table_numbers ADD COLUMN sender_formatted varchar(20) DEFAULT ''",
    'recipient_number' => "ALTER TABLE $table_numbers ADD COLUMN recipient_number varchar(20) DEFAULT ''",
    'recipient_name' => "ALTER TABLE $table_numbers ADD COLUMN recipient_name varchar(255) DEFAULT ''",
    'recipient_email' => "ALTER TABLE $table_numbers ADD COLUMN recipient_email varchar(255) DEFAULT ''",
    'email_subject' => "ALTER TABLE $table_numbers ADD COLUMN email_subject text",
    'updated_at' => "ALTER TABLE $table_numbers ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP"
);
foreach ($columns_to_check as $column_name => $sql) {
    $exists = false;
    foreach ($columns as $column) {
        if ($column->Field === $column_name) {
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        echo "  - Aggiunta colonna '$column_name'...\n";
        $wpdb->query($sql);
    } else {
        echo "  ✓ Colonna '$column_name' già presente\n";
    }
}
// Test finale
echo "\n=== Test Finale ===\n";
$test_query = "SELECT campaign_id FROM $table_numbers LIMIT 1";
$wpdb->suppress_errors(true);
$result = $wpdb->get_var($test_query);
$wpdb->suppress_errors(false);
if ($wpdb->last_error) {
    echo "❌ ERRORE: La colonna campaign_id ancora non funziona!\n";
    echo "Errore: " . $wpdb->last_error . "\n";
} else {
    echo "✅ SUCCESSO: La colonna campaign_id ora funziona correttamente!\n";
}
echo "\n=== Fix completato ===\n";
echo "Torna alla pagina Campagne e ricarica la pagina.\n";
--------------------------------------------------------------------------------