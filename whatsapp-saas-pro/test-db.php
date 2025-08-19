<?php
// Load WordPress
require_once('../../../wp-load.php');
global $wpdb;
// Check tables exist
$table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
$table_messages = $wpdb->prefix . 'wsp_messages';
$table_campaigns = $wpdb->prefix . 'wsp_campaigns';
echo "=== DATABASE CHECK ===\n\n";
// Check if tables exist
$tables = $wpdb->get_results("SHOW TABLES LIKE '%wsp_%'");
echo "Tables found:\n";
foreach ($tables as $table) {
    $table_name = array_values(get_object_vars($table))[0];
    echo "- $table_name\n";
}
echo "\n=== NUMBERS TABLE ===\n";
// Check numbers table structure
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_numbers");
echo "Columns in $table_numbers:\n";
foreach ($columns as $col) {
    echo "- {$col->Field} ({$col->Type})\n";
}
echo "\n=== DATA IN NUMBERS TABLE ===\n";
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
echo "Total records: $count\n\n";
if ($count > 0) {
    $numbers = $wpdb->get_results("SELECT * FROM $table_numbers LIMIT 5");
    echo "Sample records:\n";
    foreach ($numbers as $number) {
        echo "ID: {$number->id}\n";
        echo "  sender_number: {$number->sender_number}\n";
        echo "  sender_formatted: {$number->sender_formatted}\n";
        echo "  sender_name: {$number->sender_name}\n";
        echo "  sender_email: {$number->sender_email}\n";
        echo "  campaign_id: {$number->campaign_id}\n";
        echo "  source: {$number->source}\n";
        echo "  created_at: {$number->created_at}\n";
        echo "---\n";
    }
} else {
    echo "No data found. Attempting to insert sample data...\n";
    
    // Try to insert sample data
    require_once('includes/class-wsp-sample-data.php');
    $result = WSP_Sample_Data::insert_sample_data();
    
    if ($result) {
        echo "Sample data inserted successfully!\n";
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
        echo "New record count: $new_count\n";
    } else {
        echo "Failed to insert sample data.\n";
    }
}
// Check the query used in the admin page
echo "\n=== TESTING ADMIN QUERY ===\n";
$per_page = 20;
$offset = 0;
$where = '';
$query = "SELECT * FROM $table_numbers $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
echo "Query: $query\n";
$results = $wpdb->get_results($query);
echo "Results count: " . count($results) . "\n";
if (count($results) > 0) {
    echo "First result:\n";
    print_r($results[0]);
}