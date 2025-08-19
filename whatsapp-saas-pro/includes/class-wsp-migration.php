<?php
/**
 * Migration e aggiornamento database
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Migration {
    
    public static function check_and_migrate() {
        $current_version = get_option('wsp_db_version', '1.0.0');
        
        if (version_compare($current_version, '3.0.0', '<')) {
            self::migrate_to_3_0_0();
        }
    }
    
    private static function migrate_to_3_0_0() {
        // Crea/aggiorna tabelle
        WSP_Database::create_tables();
        
        // Aggiorna versione
        update_option('wsp_db_version', '3.0.0');
        
        // Log migrazione
        WSP_Database::log_activity('migration', 'Database migrato alla versione 3.0.0');
    }
}
--------------------------------------------------------------------------------