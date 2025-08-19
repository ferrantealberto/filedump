<?php
/**
 * Script to fix database structure and insert sample data
 * Run this from the plugin directory
 */
// Load WordPress
require_once('../../../wp-load.php');
// Load plugin classes
require_once('includes/class-wsp-database.php');
require_once('includes/class-wsp-sample-data.php');
echo "=== WhatsApp SaaS Pro - Database Fix Script ===\n\n";
// Step 1: Create/Update tables
echo "Step 1: Creating/Updating database tables...\n";
// Drop and recreate tables if they have issues
global $wpdb;
$tables_to_check = array(
    $wpdb->prefix . 'wsp_whatsapp_numbers',
    $wpdb->prefix . 'wsp_messages',
    $wpdb->prefix . 'wsp_campaigns',
    $wpdb->prefix . 'wsp_credits_transactions',
    $wpdb->prefix . 'wsp_activity_log'
);
// Check for SQL errors and recreate if needed
foreach ($tables_to_check as $table) {
    $check_sql = "SELECT COUNT(*) FROM $table LIMIT 1";
    $wpdb->suppress_errors(true);
    $result = $wpdb->get_var($check_sql);
    $wpdb->suppress_errors(false);
    
    if ($wpdb->last_error) {
        echo "Found error in table $table: " . $wpdb->last_error . "\n";
        echo "Dropping and recreating table...\n";
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}
WSP_Database::create_tables();
WSP_Database::update_table_structure();
echo "✅ Tables created/updated\n\n";
// Step 2: Check table structure
global $wpdb;
$table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
echo "Step 2: Checking table structure...\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_numbers");
echo "Columns in $table_numbers:\n";
foreach ($columns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}
echo "\n";
// Step 3: Check existing data
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
echo "Step 3: Checking existing data...\n";
echo "Current record count: $count\n\n";
// Step 4: Insert sample data if needed
if ($count == 0) {
    echo "Step 4: Inserting sample data...\n";
    WSP_Sample_Data::insert_sample_data();
    $new_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
    echo "✅ Sample data inserted. New record count: $new_count\n\n";
} else {
    echo "Step 4: Sample data already exists. Skipping...\n\n";
}
// Step 5: Verify data
echo "Step 5: Verifying data...\n";
$sample = $wpdb->get_row("SELECT * FROM $table_numbers LIMIT 1");
if ($sample) {
    echo "Sample record:\n";
    foreach ($sample as $key => $value) {
        echo "  $key: $value\n";
    }
} else {
    echo "❌ No data found in table\n";
}
// Step 6: Fix credits if needed
$credits = get_option('wsp_credits_balance', 0);
if ($credits == 0) {
    update_option('wsp_credits_balance', 100);
    echo "\n✅ Credits balance set to 100\n";
}
echo "\n=== Database fix completed ===\n";
echo "Please refresh your WordPress admin page to see the changes.\n";