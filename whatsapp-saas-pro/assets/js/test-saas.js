/**
 * JavaScript per Test SaaS
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

jQuery(document).ready(function($) {
    
    // Gestione click sui bottoni test
    $('.test-btn').on('click', function() {
        var $btn = $(this);
        var test = $btn.data('test');
        var $result = $('#result-' + test.replace('_', '-'));
        
        // Reset risultato precedente
        $result.removeClass('success error info').html('').hide();
        
        // Disabilita bottone
        $btn.prop('disabled', true).text('⏳ Test in corso...');
        
        // Prepara dati
        var data = {
            action: 'wsp_test_' + test,
            nonce: wsp_test_saas.nonce
        };
        
        // Aggiungi parametri specifici per ogni test
        switch(test) {
            case 'activate_customer':
                data.customer_id = $('#customer-to-activate').val();
                if (!data.customer_id) {
                    showResult($result, 'error', 'Seleziona un cliente da attivare');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'generate_report':
                data.customer_id = $('#customer-for-report').val();
                if (!data.customer_id) {
                    showResult($result, 'error', 'Seleziona un cliente');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'process_command':
                data.from_number = $('#whatsapp-from').val();
                data.command = $('#whatsapp-command').val();
                if (!data.from_number) {
                    showResult($result, 'error', 'Inserisci numero WhatsApp mittente');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'change_plan':
                data.customer_id = $('#customer-for-plan').val();
                data.plan_id = $('#new-plan').val();
                if (!data.customer_id || !data.plan_id) {
                    showResult($result, 'error', 'Seleziona cliente e piano');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'add_credits':
                data.customer_id = $('#customer-for-credits').val();
                data.amount = $('#credits-amount').val();
                if (!data.customer_id || !data.amount) {
                    showResult($result, 'error', 'Seleziona cliente e inserisci crediti');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'verify_api':
                data.api_key = $('#api-key-test').val();
                if (!data.api_key) {
                    showResult($result, 'error', 'Inserisci API key da verificare');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'send_welcome_email':
                data.customer_id = $('#customer-for-email').val();
                if (!data.customer_id) {
                    showResult($result, 'error', 'Seleziona un cliente');
                    resetButton($btn, test);
                    return;
                }
                break;
                
            case 'webhook':
                data.webhook_url = $('#webhook-url').val();
                if (!data.webhook_url) {
                    showResult($result, 'error', 'Inserisci URL webhook');
                    resetButton($btn, test);
                    return;
                }
                break;
        }
        
        // Esegui chiamata AJAX
        $.post(wsp_test_saas.ajax_url, data, function(response) {
            if (response.success) {
                var html = '<strong>✅ Successo!</strong><br>';
                
                if (response.data) {
                    if (response.data.message) {
                        html += response.data.message;
                    }
                    
                    // Mostra dati aggiuntivi se presenti
                    if (response.data.data) {
                        html += '<pre>' + JSON.stringify(response.data.data, null, 2) + '</pre>';
                    }
                    
                    if (response.data.response) {
                        html += '<div style="margin-top:10px;padding:10px;background:#f0f0f0;border-radius:4px;">';
                        html += '<strong>Risposta:</strong><br>';
                        html += response.data.response.replace(/\n/g, '<br>');
                        html += '</div>';
                    }
                    
                    if (response.data.customer) {
                        html += '<pre>' + JSON.stringify(response.data.customer, null, 2) + '</pre>';
                    }
                    
                    if (response.data.tests_passed) {
                        html += '<br><strong>Test Superati:</strong><ul>';
                        response.data.tests_passed.forEach(function(test) {
                            html += '<li>✅ ' + test + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    if (response.data.issues) {
                        html += '<br><strong>Problemi:</strong><ul>';
                        response.data.issues.forEach(function(issue) {
                            html += '<li>⚠️ ' + issue + '</li>';
                        });
                        html += '</ul>';
                    }
                } else {
                    html += response.data || 'Operazione completata';
                }
                
                showResult($result, 'success', html);
                
                // Aggiorna select se necessario
                if (test === 'create_customer') {
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                }
                
            } else {
                var errorMsg = '<strong>❌ Errore!</strong><br>';
                
                if (response.data) {
                    if (response.data.message) {
                        errorMsg += response.data.message;
                    }
                    if (response.data.error) {
                        errorMsg += '<br><code>' + response.data.error + '</code>';
                    }
                    if (response.data.issues) {
                        errorMsg += '<br><strong>Problemi rilevati:</strong><ul>';
                        response.data.issues.forEach(function(issue) {
                            errorMsg += '<li>' + issue + '</li>';
                        });
                        errorMsg += '</ul>';
                    }
                } else {
                    errorMsg += response.data || 'Errore sconosciuto';
                }
                
                showResult($result, 'error', errorMsg);
            }
            
        }).fail(function(xhr, status, error) {
            showResult($result, 'error', 'Errore AJAX: ' + error);
            
        }).always(function() {
            resetButton($btn, test);
        });
    });
    
    // Reset dati test
    $('#reset-test-data').on('click', function() {
        if (!confirm('⚠️ Sei sicuro di voler eliminare TUTTI i clienti di test?\n\nQuesta azione eliminerà permanentemente:\n- Tutti i clienti con email @test.com\n- Tutti i loro numeri WhatsApp\n- Tutti i messaggi\n- Tutti i report\n- Tutte le transazioni crediti\n\nL\'azione non può essere annullata!')) {
            return;
        }
        
        var $btn = $(this);
        var $result = $('#result-reset');
        
        $btn.prop('disabled', true).text('⏳ Eliminazione in corso...');
        $result.removeClass('success error info').html('').hide();
        
        $.post(wsp_test_saas.ajax_url, {
            action: 'wsp_reset_test_data',
            nonce: wsp_test_saas.nonce
        }, function(response) {
            if (response.success) {
                showResult($result, 'success', '✅ ' + response.data);
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showResult($result, 'error', '❌ ' + response.data);
            }
        }).always(function() {
            $btn.prop('disabled', false).text('🗑️ Elimina Dati Test');
        });
    });
    
    // Helper: Mostra risultato
    function showResult($element, type, message) {
        $element.html(message).addClass(type).fadeIn();
        
        // Auto-scroll to result
        $('html, body').animate({
            scrollTop: $element.offset().top - 100
        }, 500);
    }
    
    // Helper: Reset bottone
    function resetButton($btn, test) {
        var originalText = getOriginalButtonText(test);
        $btn.prop('disabled', false).text(originalText);
    }
    
    // Helper: Ottieni testo originale bottone
    function getOriginalButtonText(test) {
        var texts = {
            'create_customer': '➕ Crea Cliente Test',
            'activate_customer': '✅ Attiva Cliente',
            'generate_report': '📊 Genera Report',
            'process_command': '💬 Invia Comando',
            'change_plan': '🔄 Cambia Piano',
            'add_credits': '💰 Aggiungi Crediti',
            'verify_api': '🔑 Verifica API',
            'send_welcome_email': '📧 Invia Email',
            'webhook': '🔗 Test Webhook',
            'database_integrity': '🗄️ Verifica Database',
            'multi_tenant': '🏢 Test Multi-Tenant'
        };
        
        return texts[test] || 'Test';
    }
    
    // Auto-refresh statistiche ogni 30 secondi
    setInterval(function() {
        refreshStats();
    }, 30000);
    
    function refreshStats() {
        $.get(wsp_test_saas.ajax_url, {
            action: 'wsp_get_stats',
            nonce: wsp_test_saas.nonce
        }, function(response) {
            if (response.success && response.data) {
                $('.stat-value').each(function() {
                    var $stat = $(this);
                    var label = $stat.prev('.stat-label').text().toLowerCase();
                    
                    if (label.includes('clienti totali')) {
                        $stat.text(response.data.total_customers);
                    } else if (label.includes('clienti attivi')) {
                        $stat.text(response.data.active_customers);
                    } else if (label.includes('numeri')) {
                        $stat.text(response.data.total_numbers);
                    } else if (label.includes('messaggi')) {
                        $stat.text(response.data.total_messages);
                    } else if (label.includes('report')) {
                        $stat.text(response.data.total_reports);
                    }
                });
            }
        });
    }
    
    // Copia API key al click
    $(document).on('click', 'pre', function() {
        var $pre = $(this);
        var text = $pre.text();
        
        // Cerca API key nel testo
        var apiKeyMatch = text.match(/wsp_[a-f0-9]{64}/);
        if (apiKeyMatch) {
            copyToClipboard(apiKeyMatch[0]);
            
            // Feedback visivo
            var originalBg = $pre.css('background-color');
            $pre.css('background-color', '#d4edda');
            $pre.append('<span style="float:right;color:#155724;">📋 Copiato!</span>');
            
            setTimeout(function() {
                $pre.css('background-color', originalBg);
                $pre.find('span').remove();
            }, 2000);
        }
    });
    
    // Helper: Copia negli appunti
    function copyToClipboard(text) {
        var $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
    }
    
    // Evidenzia codice JSON
    $(document).on('mouseenter', 'pre', function() {
        var $pre = $(this);
        if (!$pre.hasClass('highlighted')) {
            try {
                var json = JSON.parse($pre.text());
                $pre.html(syntaxHighlight(JSON.stringify(json, null, 2)));
                $pre.addClass('highlighted');
            } catch(e) {
                // Non è JSON valido, lascia così
            }
        }
    });
    
    // Helper: Syntax highlighting per JSON
    function syntaxHighlight(json) {
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) {
                    cls = 'key';
                } else {
                    cls = 'string';
                }
            } else if (/true|false/.test(match)) {
                cls = 'boolean';
            } else if (/null/.test(match)) {
                cls = 'null';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }
    
    // Aggiungi stili per syntax highlighting
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            pre.highlighted .string { color: #008000; }
            pre.highlighted .number { color: #0000ff; }
            pre.highlighted .boolean { color: #b22222; }
            pre.highlighted .null { color: #808080; }
            pre.highlighted .key { color: #a020f0; font-weight: bold; }
        `)
        .appendTo('head');
});