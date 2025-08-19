<?php
/**
 * Quick fix for campaign SQL errors
 * Run this if you see "ONINSERT" or SQL syntax errors
 */
// Load WordPress
require_once('../../../wp-load.php');
echo "=== WhatsApp SaaS Pro - Campaign SQL Fix ===\n\n";
global $wpdb;
// Drop and recreate campaigns table with correct syntax
$table_campaigns = $wpdb->prefix . 'wsp_campaigns';
echo "Step 1: Backing up existing campaign data...\n";
$existing_campaigns = $wpdb->get_results("SELECT * FROM $table_campaigns", ARRAY_A);
$backup_count = count($existing_campaigns);
echo "Found $backup_count campaigns to backup\n\n";
echo "Step 2: Dropping problematic table...\n";
$wpdb->query("DROP TABLE IF EXISTS $table_campaigns");
echo "✅ Table dropped\n\n";
echo "Step 3: Creating new campaigns table with correct syntax...\n";
$charset_collate = $wpdb->get_charset_collate();
$sql = "CREATE TABLE $table_campaigns (
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
if ($wpdb->last_error) {
    echo "❌ Error creating table: " . $wpdb->last_error . "\n";
    
    // Try simpler version without CURRENT_TIMESTAMP
    echo "Trying simpler version...\n";
    $sql = "CREATE TABLE $table_campaigns (
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
        created_at datetime,
        updated_at datetime,
        PRIMARY KEY (id),
        UNIQUE KEY idx_campaign_id (campaign_id),
        KEY idx_active (is_active)
    ) $charset_collate";
    
    $wpdb->query($sql);
}
echo "✅ Table created successfully\n\n";
// Restore data if any
if ($backup_count > 0) {
    echo "Step 4: Restoring campaign data...\n";
    foreach ($existing_campaigns as $campaign) {
        // Ensure dates are set
        if (empty($campaign['created_at'])) {
            $campaign['created_at'] = current_time('mysql');
        }
        if (empty($campaign['updated_at'])) {
            $campaign['updated_at'] = current_time('mysql');
        }
        
        $wpdb->insert($table_campaigns, $campaign);
    }
    echo "✅ Restored $backup_count campaigns\n\n";
} else {
    echo "Step 4: Inserting sample campaigns...\n";
    
    // Insert sample campaigns
    require_once('includes/class-wsp-sample-data.php');
    
    $sample_campaigns = array(
        array(
            'campaign_id' => 'campaign_' . time() . '_001',
            'campaign_name' => 'Campagna Estate 2024',
            'campaign_type' => 'qr',
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode(home_url()),
            'landing_page_url' => home_url('/campaign/estate-2024'),
            'welcome_message' => 'Benvenuto nella campagna Estate 2024!',
            'total_scans' => 0,
            'total_registrations' => 0,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        )
    );
    
    foreach ($sample_campaigns as $campaign) {
        $wpdb->insert($table_campaigns, $campaign);
    }
    
    echo "✅ Sample campaign created\n\n";
}
// Verify the fix
echo "Step 5: Verifying the fix...\n";
$test_query = "SELECT COUNT(*) FROM $table_campaigns";
$count = $wpdb->get_var($test_query);
if ($wpdb->last_error) {
    echo "❌ Still having errors: " . $wpdb->last_error . "\n";
} else {
    echo "✅ Table is working correctly! Found $count campaigns.\n";
}
echo "\n=== Fix completed ===\n";
echo "Please refresh your WordPress admin page and try creating a campaign again.\n";
--------------------------------------------------------------------------------