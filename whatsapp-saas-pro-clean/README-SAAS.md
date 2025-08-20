# WhatsApp SaaS Pro - Sistema Multi-Tenant

## 📋 Panoramica

WhatsApp SaaS Pro è una piattaforma completa per la gestione multi-tenant di campagne WhatsApp. Il sistema permette a più aziende (destinatari) di utilizzare il servizio per raccogliere numeri WhatsApp dei propri clienti attraverso QR code e gestire comunicazioni automatizzate.

## 🚀 Caratteristiche Principali

### Sistema Multi-Tenant
- **Gestione Clienti/Destinatari**: Ogni azienda ha il proprio account isolato
- **Dashboard Personalizzata**: Ogni cliente accede solo ai propri dati
- **API Key Univoca**: Ogni cliente riceve una API key per integrazioni
- **Isolamento Dati**: Completa separazione dei dati tra clienti

### Piani di Abbonamento
1. **Piano Standard** (€29/mese)
   - 1.000 crediti inclusi
   - 5 campagne max
   - Report base
   - Supporto email

2. **Piano Avanzato** (€79/mese)
   - 5.000 crediti inclusi
   - 20 campagne max
   - Report avanzati
   - API access
   - Supporto prioritario

3. **Piano Plus** (€199/mese)
   - 20.000 crediti inclusi
   - Campagne illimitate
   - White label
   - Custom branding
   - Supporto dedicato

### Funzionalità Core
- **Estrazione Numeri WhatsApp**: Da email Gmail e form
- **QR Code Personalizzati**: Generazione automatica per ogni cliente
- **Messaggi di Benvenuto**: Automatici alla scansione QR
- **Report Automatici**: Inviati via WhatsApp su richiesta
- **Gestione Crediti**: Sistema di crediti prepagati
- **Webhook & API**: Integrazioni con sistemi esterni

## 📦 Installazione

### Requisiti
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- Estensione cURL PHP
- SSL certificato (per webhook)

### Procedura di Installazione

1. **Upload Plugin**
```bash
# Carica la cartella del plugin in wp-content/plugins/
cp -r whatsapp-saas-pro/ /path/to/wordpress/wp-content/plugins/
```

2. **Attivazione**
- Vai su WordPress Admin > Plugin
- Trova "WhatsApp SaaS Pro - Multi-Tenant"
- Clicca su "Attiva"

3. **Configurazione Iniziale**
- Il plugin creerà automaticamente tutte le tabelle necessarie
- I piani predefiniti verranno inseriti automaticamente
- Configurare le impostazioni API

## ⚙️ Configurazione

### 1. Configurazione Mail2Wa
```php
// In wp-admin > WhatsApp SaaS > Impostazioni
API URL: https://api.Mail2Wa.it
API Key: [inserire API key]
```

### 2. Configurazione N8N Webhook
```php
// URL webhook per report automatici
N8N Webhook URL: https://your-n8n-instance.com/webhook/wsp-report
```

### 3. Form di Registrazione

#### Metodo 1: Form Esterno
Utilizzare il form su: `https://upgradeservizi.eu/external_add_user/?id_res=1327`

#### Metodo 2: Shortcode WordPress
```php
[wsp_registration_form 
    show_plans="yes" 
    default_plan="1" 
    button_text="Registrati Ora"
    terms_url="https://tuosito.com/terms"
    privacy_url="https://tuosito.com/privacy"]
```

## 🔧 Utilizzo

### Per gli Amministratori

#### Dashboard Admin
- **Clienti**: Gestione completa dei clienti registrati
- **Piani**: Modifica piani e prezzi
- **Report**: Visualizzazione report globali
- **Crediti**: Gestione transazioni crediti
- **Log**: Monitoraggio attività sistema

#### Gestione Clienti
```php
// Attivare un cliente
WSP_Customers::activate_customer($customer_id);

// Cambiare piano
WSP_Customers::update_customer_plan($customer_id, $new_plan_id);

// Generare report
WSP_Reports::generate_numbers_report($customer_id);
```

### Per i Clienti/Destinatari

#### Comandi WhatsApp
I clienti possono inviare questi comandi al numero WhatsApp del sistema:

- **REPORT NUMERI**: Riceve report dei numeri raccolti
- **SALDO**: Verifica crediti disponibili
- **STATO**: Stato account e servizi
- **PIANO**: Dettagli piano attivo
- **STATISTICHE**: Statistiche di utilizzo
- **HELP**: Lista comandi disponibili

#### Processo di Registrazione

1. **Compilazione Form**
   - Il cliente compila il form di registrazione
   - Seleziona il piano desiderato
   - Inserisce dati aziendali e WhatsApp Business

2. **Ricezione Email Conferma**
   ```
   Oggetto: Conferma registrazione servizio WaPower
   
   Gentile cliente,
   il tuo profilo del servizio WaPower è stato creato.
   Per completare la procedura, inquadra il QR code: 
   qr.wapower.it/?email=cliente@email.com
   ```

3. **Scansione QR Code**
   - Il cliente scansiona il QR code dal proprio WhatsApp Business
   - Il numero viene collegato al sistema
   - L'account viene attivato

4. **Utilizzo del Servizio**
   - Creazione campagne QR code
   - Ricezione numeri WhatsApp clienti
   - Invio messaggi automatizzati
   - Richiesta report via WhatsApp

## 🔌 API REST

### Autenticazione
Tutte le richieste API richiedono l'header:
```
X-API-Key: wsp_xxxxxxxxxxxxxxxxxxxxx
```

### Endpoints Disponibili

#### Estrai Numero
```http
POST /wp-json/wsp/v1/extract
Content-Type: application/json
X-API-Key: {api_key}

{
    "email_content": "...",
    "campaign_id": "CAMP001"
}
```

#### Invia Messaggio
```http
POST /wp-json/wsp/v1/send-message
Content-Type: application/json
X-API-Key: {api_key}

{
    "recipient": "+39123456789",
    "message": "Testo messaggio",
    "campaign_id": "CAMP001"
}
```

#### Statistiche Cliente
```http
GET /wp-json/wsp/v1/customer/stats
X-API-Key: {api_key}
```

#### Lista Numeri
```http
GET /wp-json/wsp/v1/customer/numbers
X-API-Key: {api_key}
```

## 📊 Database Schema

### Tabelle Principali

#### wsp_customers
- Informazioni aziende/destinatari
- API keys
- Piani associati

#### wsp_subscription_plans
- Definizione piani
- Prezzi e features
- Limiti crediti

#### wsp_customer_subscriptions
- Sottoscrizioni attive
- Date rinnovo
- Crediti rimanenti

#### wsp_whatsapp_numbers
- Numeri raccolti per cliente
- Associazione campagne
- Stato opt-in/opt-out

#### wsp_campaigns
- Campagne per cliente
- QR codes
- Messaggi benvenuto

#### wsp_messages
- Log messaggi inviati
- Stati consegna
- Crediti utilizzati

#### wsp_reports
- Report generati
- Stati elaborazione
- URL download

## 🔄 Workflow N8N

### Installazione Workflow

1. Importa il file `n8n-workflows/wsp-report-workflow.json` in N8N
2. Configura le credenziali Mail2Wa
3. Imposta l'URL callback WordPress
4. Attiva il workflow

### Flusso Report Automatico

1. Cliente invia "REPORT NUMERI" via WhatsApp
2. Sistema genera report CSV
3. Trigger webhook N8N
4. N8N scarica il report
5. Invia email a Mail2Wa
6. Mail2Wa consegna su WhatsApp
7. Callback conferma consegna

## 🐛 Troubleshooting

### Problemi Comuni

#### Tabelle non create
```sql
-- Verifica esistenza tabelle
SHOW TABLES LIKE '%wsp_%';

-- Ricrea manualmente se necessario
-- Esegui il contenuto di class-wsp-database-saas.php
```

#### Crediti non aggiornati
```php
// Ricalcola manualmente
WSP_Credits::recalculate_balance($customer_id);
```

#### Email non inviate
```php
// Verifica configurazione
$mail_result = wp_mail('test@example.com', 'Test', 'Test message');
var_dump($mail_result);
```

#### Report non generati
```php
// Test manuale generazione
$result = WSP_Reports::generate_numbers_report($customer_id);
print_r($result);
```

## 📈 Monitoraggio

### Metriche Chiave
- Numero clienti attivi
- Messaggi inviati/giorno
- Crediti consumati
- Tasso conversione QR
- Report generati

### Log Attività
```php
// Visualizza ultimi log
$logs = WSP_Activity_Log::get_customer_logs($customer_id, 50);
```

## 🔐 Sicurezza

### Best Practices
- API keys criptate in database
- Nonce verification su tutti i form
- Sanitizzazione input utente
- Rate limiting su API
- SSL obbligatorio per webhook

### Backup
```bash
# Backup database
mysqldump -u user -p database_name \
  --tables wp_wsp_customers wp_wsp_whatsapp_numbers \
  > backup_wsp_$(date +%Y%m%d).sql
```

## 📝 Changelog

### Version 4.0.0 (Current)
- ✅ Sistema multi-tenant completo
- ✅ Gestione piani abbonamento
- ✅ Report automatici via WhatsApp
- ✅ Integrazione form esterni
- ✅ Workflow N8N
- ✅ API REST completa
- ✅ Sistema crediti
- ✅ Activity logging

### Version 3.0.0
- Sistema single-tenant base
- Estrazione numeri da Gmail
- Integrazione Mail2Wa
- Campagne QR code

## 🤝 Supporto

Per supporto tecnico:
- Email: support@wapower.it
- WhatsApp: +39 XXX XXX XXXX
- Documentazione: https://docs.wapower.it

## 📄 Licenza

GPL v2 o successiva

---

## 🚀 Quick Start

1. Installa il plugin
2. Configura Mail2Wa API
3. Crea primo cliente di test
4. Genera QR code
5. Testa invio messaggio
6. Richiedi report via WhatsApp

```php
// Test rapido funzionalità
$test_customer = WSP_Customers::register_customer(array(
    'business_name' => 'Test Azienda',
    'contact_name' => 'Mario Rossi',
    'email' => 'test@example.com',
    'whatsapp_number' => '+391234567890',
    'plan_id' => 1
));

if ($test_customer['success']) {
    echo "Cliente creato: " . $test_customer['customer_code'];
    echo "QR Code: " . $test_customer['qr_code_url'];
}
```

## 🎯 Prossimi Sviluppi

- [ ] Dashboard cliente frontend
- [ ] App mobile companion
- [ ] Integrazione CRM popolari
- [ ] Automazioni avanzate
- [ ] Analytics in tempo reale
- [ ] Chatbot AI integrato
- [ ] Marketplace template messaggi
- [ ] Sistema affiliazione