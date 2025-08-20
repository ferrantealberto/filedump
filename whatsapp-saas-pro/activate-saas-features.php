<?php
/**
 * Script per attivare le funzionalità SaaS
 * 
 * Questo file aggiunge le funzionalità SaaS al plugin esistente
 */

// Carica WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

echo '<h1>Attivazione Funzionalità SaaS</h1>';

// 1. Trova quale versione del plugin è attiva
$active_plugins = get_option('active_plugins');
$plugin_v3 = 'whatsapp-saas-pro/whatsapp-saas-plugin.php';
$plugin_v4 = 'whatsapp-saas-pro/whatsapp-saas-plugin-v4.php';

$active_plugin = null;
if (in_array($plugin_v3, $active_plugins)) {
    $active_plugin = $plugin_v3;
    echo '<p>✅ Trovato plugin v3 attivo: ' . $plugin_v3 . '</p>';
} elseif (in_array($plugin_v4, $active_plugins)) {
    $active_plugin = $plugin_v4;
    echo '<p>✅ Trovato plugin v4 attivo: ' . $plugin_v4 . '</p>';
}

if (!$active_plugin) {
    echo '<p>❌ Nessuna versione del plugin WhatsApp SaaS Pro è attiva!</p>';
    echo '<p><a href="' . admin_url('plugins.php') . '">Vai ai Plugin</a></p>';
    exit;
}

// 2. Modifica il file del plugin attivo per includere le classi SaaS
$plugin_file = WP_PLUGIN_DIR . '/' . $active_plugin;
$plugin_content = file_get_contents($plugin_file);

// Verifica se le classi SaaS sono già incluse
if (strpos($plugin_content, 'class-wsp-database-saas.php') !== false) {
    echo '<p>✅ Le classi SaaS sono già incluse nel plugin!</p>';
} else {
    echo '<p>⚠️ Le classi SaaS NON sono incluse. Aggiunta in corso...</p>';
    
    // Trova dove aggiungere le inclusioni
    $search = "require_once WSP_PLUGIN_DIR . 'includes/class-wsp-database.php';";
    
    $saas_includes = "
        // Classi SaaS Multi-Tenant
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-database-saas.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-customers.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-reports.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-integrations.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-activity-log.php';";
    
    $replacement = $search . $saas_includes;
    
    $new_content = str_replace($search, $replacement, $plugin_content);
    
    // Aggiungi anche l'inizializzazione della test suite
    $admin_search = "if (is_admin()) {
            new WSP_Admin();
        }";
    
    $admin_replacement = "if (is_admin()) {
            new WSP_Admin();
            
            // Test Suite SaaS
            if (file_exists(WSP_PLUGIN_DIR . 'admin/class-wsp-admin-test-saas.php')) {
                require_once WSP_PLUGIN_DIR . 'admin/class-wsp-admin-test-saas.php';
                new WSP_Admin_Test_SaaS();
            }
        }";
    
    $new_content = str_replace($admin_search, $admin_replacement, $new_content);
    
    // Salva il file modificato
    if (file_put_contents($plugin_file, $new_content)) {
        echo '<p class="success">✅ Plugin modificato con successo!</p>';
    } else {
        echo '<p class="error">❌ Errore nel salvare il file del plugin</p>';
    }
}

// 3. Crea le tabelle del database
echo '<h2>Creazione Tabelle Database</h2>';

// Carica la classe database SaaS
$db_saas_file = WP_PLUGIN_DIR . '/whatsapp-saas-pro/includes/class-wsp-database-saas.php';
if (file_exists($db_saas_file)) {
    require_once($db_saas_file);
    
    if (class_exists('WSP_Database_SaaS')) {
        WSP_Database_SaaS::create_tables();
        echo '<p class="success">✅ Tabelle database create/aggiornate!</p>';
        
        // Verifica tabelle create
        global $wpdb;
        $tables = array(
            'wsp_customers',
            'wsp_subscription_plans',
            'wsp_customer_subscriptions'
        );
        
        echo '<ul>';
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo '<li>✅ ' . $table_name . ' (' . $count . ' record)</li>';
            } else {
                echo '<li>❌ ' . $table_name . ' non creata</li>';
            }
        }
        echo '</ul>';
    } else {
        echo '<p class="error">❌ Classe WSP_Database_SaaS non trovata</p>';
    }
} else {
    echo '<p class="error">❌ File class-wsp-database-saas.php non trovato</p>';
}

// 4. Ricarica plugin
echo '<h2>Ricaricamento Plugin</h2>';
echo '<p>Per applicare le modifiche, il plugin deve essere ricaricato.</p>';

?>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
        background: #f0f0f1;
    }
    h1, h2 {
        color: #23282d;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
    }
    .button {
        display: inline-block;
        padding: 10px 20px;
        background: #0073aa;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        margin: 10px 5px;
    }
    .button:hover {
        background: #005a87;
    }
    ul {
        background: white;
        padding: 15px 30px;
        border-radius: 3px;
        border: 1px solid #ccd0d4;
    }
</style>

<div style="margin-top: 30px; padding: 20px; background: white; border: 1px solid #ccd0d4; border-radius: 3px;">
    <h3>Prossimi Passi:</h3>
    <ol>
        <li>Disattiva e riattiva il plugin per ricaricare le modifiche</li>
        <li>Oppure clicca sui pulsanti sotto:</li>
    </ol>
    
    <a href="<?php echo admin_url('plugins.php?action=deactivate&plugin=' . urlencode($active_plugin) . '&_wpnonce=' . wp_create_nonce('deactivate-plugin_' . $active_plugin)); ?>" class="button">
        1. Disattiva Plugin
    </a>
    
    <a href="<?php echo admin_url('plugins.php?action=activate&plugin=' . urlencode($active_plugin) . '&_wpnonce=' . wp_create_nonce('activate-plugin_' . $active_plugin)); ?>" class="button">
        2. Riattiva Plugin
    </a>
    
    <a href="<?php echo admin_url('admin.php?page=whatsapp-saas'); ?>" class="button">
        3. Vai al Plugin
    </a>
    
    <a href="saas-diagnostic.php" class="button" style="background: #f0ad4e;">
        🔍 Esegui Diagnostica
    </a>
</div>