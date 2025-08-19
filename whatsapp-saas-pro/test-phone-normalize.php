<?php
/**
 * Test per verificare che il metodo normalize_phone sia accessibile
 */
// Load WordPress
require_once('../../../wp-load.php');
echo "=== Test Normalize Phone Method ===\n\n";
// Test 1: Verifica che la classe esista
echo "1. Verifica classe WSP_Mail2Wa...\n";
if (class_exists('WSP_Mail2Wa')) {
    echo "  ✅ Classe WSP_Mail2Wa trovata\n";
} else {
    echo "  ❌ Classe WSP_Mail2Wa NON trovata\n";
    exit;
}
// Test 2: Crea istanza
echo "\n2. Creazione istanza...\n";
try {
    $mail2wa = new WSP_Mail2Wa();
    echo "  ✅ Istanza creata\n";
} catch (Exception $e) {
    echo "  ❌ Errore: " . $e->getMessage() . "\n";
    exit;
}
// Test 3: Verifica metodo normalize_phone
echo "\n3. Test metodo normalize_phone...\n";
$test_numbers = array(
    '3351234567' => '+393351234567',
    '+393351234567' => '+393351234567',
    '393351234567' => '+393351234567',
    '0039 335 123 4567' => '+393351234567',
    '335-123-4567' => '+393351234567'
);
foreach ($test_numbers as $input => $expected) {
    try {
        $result = $mail2wa->normalize_phone($input);
        if ($result == $expected) {
            echo "  ✅ '$input' -> '$result' (corretto)\n";
        } else {
            echo "  ⚠️ '$input' -> '$result' (atteso: $expected)\n";
        }
    } catch (Error $e) {
        echo "  ❌ Errore con '$input': " . $e->getMessage() . "\n";
        
        // Se il metodo è privato, otterremo questo errore
        if (strpos($e->getMessage(), 'Call to private method') !== false) {
            echo "\n❌ IL METODO È ANCORA PRIVATO!\n";
            echo "Devi cambiare 'private function normalize_phone' in 'public function normalize_phone'\n";
            echo "Nel file: includes/class-wsp-mail2wa.php\n";
            exit;
        }
    }
}
// Test 4: Test chiamata webhook simulata
echo "\n4. Test simulazione webhook (come fa WSP_API)...\n";
try {
    // Simula quello che fa WSP_API::process_numbers
    $phone = '+393351234567';
    $normalized = $mail2wa->normalize_phone($phone);
    echo "  ✅ Normalizzazione da WSP_API funziona: $phone -> $normalized\n";
} catch (Error $e) {
    echo "  ❌ Errore: " . $e->getMessage() . "\n";
}
echo "\n=== Test completato ===\n";
echo "Se tutti i test sono passati, il webhook dovrebbe funzionare!\n";