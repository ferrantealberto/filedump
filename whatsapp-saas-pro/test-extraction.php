<?php
/**
 * Test per la funzione di estrazione
 */
// Load WordPress
require_once('../../../wp-load.php');
echo "=== Test Extraction Function ===\n\n";
// Test 1: Test diretto della funzione
echo "1. Test estrazione diretta...\n";
$test_cases = array(
    array(
        'subject' => 'Test User, 393351234567@g.us',
        'body' => '2025-08-18 15:30:45: Messaggio di test, Test User, 393351234567@g.us',
        'expected_number' => '393351234567',
        'expected_name' => 'Test User'
    ),
    array(
        'subject' => 'Mario Rossi, +39 335 1234567',
        'body' => 'Messaggio ricevuto da Mario Rossi - +39 335 1234567',
        'expected_number' => '393351234567',
        'expected_name' => 'Mario Rossi'
    )
);
foreach ($test_cases as $test) {
    echo "\nTest: " . $test['subject'] . "\n";
    
    // Estrai numero
    $pattern = '/(\+?\d{10,15})|(\d{10,15}@g\.us)/';
    preg_match($pattern, $test['subject'] . ' ' . $test['body'], $matches);
    
    $extracted_number = '';
    if (!empty($matches[0])) {
        $extracted_number = preg_replace('/[^\d]/', '', $matches[0]);
    }
    
    // Estrai nome
    $extracted_name = '';
    if (preg_match('/([A-Za-z\s]+),/', $test['subject'], $name_matches)) {
        $extracted_name = trim($name_matches[1]);
    }
    
    echo "  Numero estratto: $extracted_number (atteso: {$test['expected_number']})\n";
    echo "  Nome estratto: $extracted_name (atteso: {$test['expected_name']})\n";
    
    if ($extracted_number == $test['expected_number']) {
        echo "  ✅ Test passato\n";
    } else {
        echo "  ❌ Test fallito\n";
    }
}
// Test 2: Verifica che la classe WSP_Test esista
echo "\n2. Verifica classe WSP_Test...\n";
if (class_exists('WSP_Test')) {
    echo "  ✅ Classe WSP_Test trovata\n";
    
    // Verifica che il metodo esista
    if (method_exists('WSP_Test', 'ajax_test_extraction')) {
        echo "  ✅ Metodo ajax_test_extraction esiste\n";
    } else {
        echo "  ❌ Metodo ajax_test_extraction NON trovato\n";
    }
} else {
    echo "  ❌ Classe WSP_Test NON trovata\n";
}
// Test 3: Test API Mail2Wa
echo "\n3. Test API Mail2Wa...\n";
$api_key = '1f06d5c8bd0cd19f7c99b660b504bb25';
$base_url = 'https://api.Mail2Wa.it';
echo "  API Key: $api_key\n";
echo "  Base URL: $base_url\n";
// Salva temporaneamente le impostazioni
update_option('wsp_mail2wa_api_key', $api_key);
update_option('wsp_mail2wa_base_url', $base_url);
echo "  ✅ Impostazioni salvate\n";
echo "\n=== Test completato ===\n";
echo "Ora prova di nuovo il pulsante 'Test API Extraction' nell'admin.\n";