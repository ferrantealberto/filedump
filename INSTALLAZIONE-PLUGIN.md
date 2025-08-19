# 📦 WhatsApp SaaS Pro v4.0.0 - Guida Installazione

## File ZIP Creato
✅ **whatsapp-saas-pro-v4.0.0.zip** (128 KB)

## 🚀 Installazione Rapida

### Metodo 1: Upload da WordPress Admin

1. **Download del file ZIP**
   - Il file `whatsapp-saas-pro-v4.0.0.zip` è pronto per il download

2. **Installazione in WordPress**
   ```
   WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin
   → Seleziona whatsapp-saas-pro-v4.0.0.zip → Installa ora → Attiva
   ```

3. **Configurazione iniziale automatica**
   - Le tabelle vengono create automaticamente
   - I 3 piani (Standard, Avanzato, Plus) vengono inseriti
   - Le opzioni predefinite vengono configurate

### Metodo 2: Upload via FTP

1. **Estrai il file ZIP**
   ```bash
   unzip whatsapp-saas-pro-v4.0.0.zip
   ```

2. **Carica via FTP**
   ```
   Carica la cartella whatsapp-saas-pro/ in:
   /wp-content/plugins/
   ```

3. **Attiva da WordPress Admin**
   ```
   Plugin → WhatsApp SaaS Pro - Multi-Tenant → Attiva
   ```

## ⚙️ Configurazione Post-Installazione

### 1. Configurazione API Mail2Wa
```
WordPress Admin → WhatsApp SaaS → Impostazioni

API URL: https://api.Mail2Wa.it
API Key: 1f06d5c8bd0cd19f7c99b660b504bb25
```

### 2. Configurazione Webhook N8N (Opzionale)
```
WordPress Admin → WhatsApp SaaS → Impostazioni → Integrazioni

N8N Webhook URL: https://your-n8n-instance.com/webhook/wsp-report
```

### 3. Test Funzionalità
```
WordPress Admin → WhatsApp SaaS → Test

✓ Test connessione database
✓ Test API Mail2Wa
✓ Test estrazione numero
✓ Test invio messaggio
```

## 📋 Contenuto del Plugin

### File Principali
- `whatsapp-saas-plugin-v4.php` - File principale del plugin
- `README-SAAS.md` - Documentazione completa
- `install/database-schema.sql` - Schema database per installazione manuale

### Nuove Classi SaaS
- `class-wsp-database-saas.php` - Gestione database multi-tenant
- `class-wsp-customers.php` - Gestione clienti/destinatari
- `class-wsp-reports.php` - Sistema report e comandi WhatsApp
- `class-wsp-integrations.php` - Form registrazione e integrazioni
- `class-wsp-activity-log.php` - Log attività sistema

### Workflow N8N
- `n8n-workflows/wsp-report-workflow.json` - Workflow per invio report

## 🔧 Primo Cliente di Test

Dopo l'installazione, puoi creare un cliente di test:

### Via Codice PHP
```php
$test_customer = WSP_Customers::register_customer(array(
    'business_name' => 'Test Azienda SRL',
    'contact_name' => 'Mario Rossi',
    'email' => 'test@azienda.com',
    'whatsapp_number' => '+391234567890',
    'plan_id' => 1 // Piano Standard
));

echo "Cliente creato!";
echo "Codice: " . $test_customer['customer_code'];
echo "API Key: " . $test_customer['api_key'];
echo "QR Code: " . $test_customer['qr_code_url'];
```

### Via Form Registrazione
Aggiungi questo shortcode a una pagina:
```
[wsp_registration_form show_plans="yes" button_text="Registrati Ora"]
```

## 📱 Test Comandi WhatsApp

Una volta configurato un cliente, puoi testare i comandi inviando questi messaggi al numero WhatsApp del sistema:

- `REPORT NUMERI` - Ricevi report numeri raccolti
- `SALDO` - Verifica crediti disponibili
- `HELP` - Lista comandi disponibili

## 🆘 Troubleshooting

### Se le tabelle non vengono create
Esegui manualmente il file SQL:
```bash
mysql -u username -p database_name < install/database-schema.sql
```

### Se l'email di conferma non arriva
Verifica configurazione SMTP:
```php
// Test invio email
wp_mail('test@email.com', 'Test', 'Test message');
```

### Se il QR code non funziona
Verifica che l'URL sia accessibile:
```
https://qr.wapower.it/?email=test@email.com
```

## 📊 Verifiche Post-Installazione

### 1. Verifica Tabelle Create
```sql
SHOW TABLES LIKE '%wsp_%';
```
Dovresti vedere 11 tabelle:
- wp_wsp_customers
- wp_wsp_subscription_plans
- wp_wsp_customer_subscriptions
- wp_wsp_whatsapp_numbers
- wp_wsp_campaigns
- wp_wsp_messages
- wp_wsp_credits_transactions
- wp_wsp_reports
- wp_wsp_activity_log
- wp_wsp_webhook_events
- wp_wsp_integrations

### 2. Verifica Piani Inseriti
```sql
SELECT * FROM wp_wsp_subscription_plans;
```
Dovresti vedere 3 piani: Standard, Avanzato, Plus

### 3. Verifica Cron Jobs
```php
// In WordPress Admin → Strumenti → Cron Events
// Dovresti vedere:
- wsp_process_emails (ogni 5 minuti)
- wsp_send_daily_reports (giornaliero)
- wsp_check_subscriptions (ogni ora)
- wsp_cleanup_logs (settimanale)
```

## ✅ Checklist Installazione Completata

- [ ] Plugin attivato con successo
- [ ] Tabelle database create (11 tabelle)
- [ ] Piani abbonamento inseriti (3 piani)
- [ ] API Mail2Wa configurata
- [ ] Primo cliente di test creato
- [ ] QR code generato e funzionante
- [ ] Test invio messaggio riuscito
- [ ] Comando REPORT NUMERI testato
- [ ] Email conferma ricevuta
- [ ] Cron jobs schedulati

## 🎉 Installazione Completata!

Il plugin WhatsApp SaaS Pro v4.0.0 è ora pronto per l'uso come piattaforma multi-tenant per la gestione di campagne WhatsApp.

Per supporto: support@wapower.it