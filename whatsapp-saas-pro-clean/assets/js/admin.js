/**
 * WhatsApp SaaS Pro Admin JavaScript
 * Version: 3.0.0
 */
jQuery(document).ready(function($) {
    
    // Initialize Select2 if available
    if ($.fn.select2) {
        $('.wsp-select2').select2({
            width: '100%',
            placeholder: 'Seleziona un\'opzione'
        });
    }
    
    // Export functions to global scope
    window.wspExportToday = function() {
        window.location.href = wsp_ajax.ajax_url + '?action=wsp_export_csv&nonce=' + wsp_ajax.nonce + '&period=today';
    };
    
    window.wspSendDailyReport = function() {
        if (!confirm('Inviare il report giornaliero ora?')) {
            return;
        }
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_send_daily_report',
            nonce: wsp_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Report inviato con successo!');
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
            }
        });
    };
    
    window.wspTestAPI = function() {
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_test_api',
            nonce: wsp_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('API Test: ' + response.data.message);
            } else {
                alert('API Test fallito: ' + (response.data.message || 'Errore sconosciuto'));
            }
        });
    };
    
    // Handle welcome message sending
    $('.wsp-send-welcome').on('click', function() {
        var $btn = $(this);
        var numberId = $btn.data('number-id');
        
        if (!confirm(wsp_ajax.strings.confirm_send)) {
            return;
        }
        
        $btn.prop('disabled', true).text(wsp_ajax.strings.loading);
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_send_welcome',
            nonce: wsp_ajax.nonce,
            number_id: numberId
        }, function(response) {
            if (response.success) {
                $btn.text('✅ Inviato');
                setTimeout(function() {
                    $btn.prop('disabled', false).text('Invia Benvenuto');
                }, 3000);
            } else {
                alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
                $btn.prop('disabled', false).text('Invia Benvenuto');
            }
        });
    });
    
    // Handle bulk send
    $('#wsp-bulk-send-form').on('submit', function(e) {
        e.preventDefault();
        
        var recipients = $('#wsp-recipients').val();
        var message = $('#wsp-message').val();
        
        if (!recipients || recipients.length === 0) {
            alert('Seleziona almeno un destinatario');
            return;
        }
        
        if (!message) {
            alert('Inserisci un messaggio');
            return;
        }
        
        if (!confirm('Inviare il messaggio a ' + recipients.length + ' destinatari?')) {
            return;
        }
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        $submitBtn.prop('disabled', true).html('<span class="wsp-spinner"></span> Invio in corso...');
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_send_bulk',
            nonce: wsp_ajax.nonce,
            recipients: recipients,
            message: message
        }, function(response) {
            if (response.success) {
                alert('Invio completato!\nInviati: ' + response.data.sent + '\nFalliti: ' + response.data.failed);
                $form[0].reset();
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
            }
            $submitBtn.prop('disabled', false).text('Invia Messaggi');
        });
    });
    
    // Load recipients for select2
    if ($('#wsp-recipients').length) {
        $('#wsp-recipients').select2({
            ajax: {
                url: wsp_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wsp_get_recipients',
                        nonce: wsp_ajax.nonce,
                        search: params.term
                    };
                },
                processResults: function(response) {
                    if (response.success) {
                        return {
                            results: $.map(response.data, function(item) {
                                return {
                                    id: item.sender_number,
                                    text: item.sender_name ? 
                                        item.sender_name + ' (' + item.sender_formatted + ')' : 
                                        item.sender_formatted
                                };
                            })
                        };
                    }
                    return { results: [] };
                }
            },
            placeholder: 'Seleziona destinatari',
            multiple: true,
            width: '100%'
        });
    }
    
    // Campaign QR Code generation
    $('#wsp-create-campaign').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var formData = $form.serialize();
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_create_campaign',
            nonce: wsp_ajax.nonce,
            name: $form.find('[name="campaign_name"]').val(),
            type: $form.find('[name="campaign_type"]').val(),
            message: $form.find('[name="welcome_message"]').val()
        }, function(response) {
            if (response.success) {
                alert('Campagna creata con successo!');
                
                // Show QR code
                var qrUrl = response.data.qr_code_url;
                $('#campaign-qr-preview').html(
                    '<img src="' + qrUrl + '" alt="QR Code" style="max-width: 300px;">' +
                    '<p>Landing Page: <a href="' + response.data.landing_page_url + '" target="_blank">' + 
                    response.data.landing_page_url + '</a></p>'
                );
                
                // Reset form
                $form[0].reset();
                
                // Reload campaigns list
                loadCampaigns();
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
            }
        });
    });
    
    // Load campaigns list
    function loadCampaigns() {
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_get_campaigns',
            nonce: wsp_ajax.nonce
        }, function(response) {
            if (response.success && response.data.length > 0) {
                var html = '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Nome</th>';
                html += '<th>ID</th>';
                html += '<th>Registrazioni</th>';
                html += '<th>Creata</th>';
                html += '<th>Azioni</th>';
                html += '</tr></thead><tbody>';
                
                $.each(response.data, function(i, campaign) {
                    html += '<tr>';
                    html += '<td>' + campaign.campaign_name + '</td>';
                    html += '<td><code>' + campaign.campaign_id + '</code></td>';
                    html += '<td>' + campaign.total_registrations + '</td>';
                    html += '<td>' + campaign.created_at + '</td>';
                    html += '<td>';
                    html += '<button class="button button-small wsp-view-qr" data-id="' + campaign.campaign_id + '">QR Code</button> ';
                    html += '<button class="button button-small wsp-delete-campaign" data-id="' + campaign.campaign_id + '">Elimina</button>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#campaigns-list').html(html);
            } else {
                $('#campaigns-list').html('<p>Nessuna campagna trovata.</p>');
            }
        });
    }
    
    // Delete campaign
    $(document).on('click', '.wsp-delete-campaign', function() {
        var campaignId = $(this).data('id');
        
        if (!confirm('Eliminare questa campagna?')) {
            return;
        }
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_delete_campaign',
            nonce: wsp_ajax.nonce,
            campaign_id: campaignId
        }, function(response) {
            if (response.success) {
                alert('Campagna eliminata');
                loadCampaigns();
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
            }
        });
    });
    
    // View QR Code
    $(document).on('click', '.wsp-view-qr', function() {
        var campaignId = $(this).data('id');
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_get_campaign_stats',
            nonce: wsp_ajax.nonce,
            campaign_id: campaignId
        }, function(response) {
            if (response.success) {
                var campaign = response.data.campaign;
                var modal = '<div class="wsp-modal">';
                modal += '<div class="wsp-modal-content">';
                modal += '<h2>' + campaign.campaign_name + '</h2>';
                modal += '<img src="' + campaign.qr_code_url + '" style="max-width: 300px;">';
                modal += '<p>Registrazioni: ' + response.data.registrations + '</p>';
                modal += '<p>Landing: <a href="' + campaign.landing_page_url + '" target="_blank">' + campaign.landing_page_url + '</a></p>';
                modal += '<button class="button wsp-modal-close">Chiudi</button>';
                modal += '</div></div>';
                
                $('body').append(modal);
            }
        });
    });
    
    // Close modal
    $(document).on('click', '.wsp-modal-close, .wsp-modal', function(e) {
        if (e.target === this) {
            $('.wsp-modal').remove();
        }
    });
    
    // Load initial data
    if ($('#campaigns-list').length) {
        loadCampaigns();
    }
    
    // Auto-refresh stats
    if ($('.wsp-stats-grid').length) {
        setInterval(function() {
            $.post(wsp_ajax.ajax_url, {
                action: 'wsp_get_stats',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    // Update stats display
                    console.log('Stats updated', response.data);
                }
            });
        }, 60000); // Every minute
    }
});
// Modal styles
jQuery(document).ready(function($) {
    var modalStyles = '<style>' +
        '.wsp-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 99999; }' +
        '.wsp-modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 500px; text-align: center; }' +
        '.wsp-modal-content img { margin: 20px 0; }' +
        '#wsp-add-number-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 99999; }' +
        '#wsp-add-number-modal .wsp-modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; }' +
        '</style>';
    $('head').append(modalStyles);
});
// Funzioni aggiuntive per pagine complete
function wspShowAddNumber() {
    jQuery('#wsp-add-number-modal').fadeIn();
}
function wspCloseModal() {
    jQuery('#wsp-add-number-modal').fadeOut();
}
function wspSendMessageTo(phone, name) {
    var message = prompt('Messaggio per ' + (name || phone) + ':');
    if (message) {
        jQuery.post(wsp_ajax.ajax_url, {
            action: 'wsp_test_mail2wa_send',
            nonce: wsp_ajax.nonce,
            phone: phone,
            message: message
        }, function(response) {
            if (response.success) {
                alert('Messaggio inviato con successo!');
            } else {
                alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
            }
        });
    }
}
function wspLoadTemplate(template) {
    var templates = {
        welcome: '🎉 Ciao {nome}! Benvenuto nel nostro servizio WhatsApp. Il tuo numero {numero} è stato registrato con successo!',
        promo: '🔥 Ciao {nome}! Abbiamo una promozione speciale per te! Scopri le nostre offerte esclusive.',
        reminder: '⏰ Ciao {nome}, questo è un promemoria per il tuo appuntamento. Ti aspettiamo!',
        thanks: '🙏 Ciao {nome}, grazie per averci scelto! Il tuo supporto è importante per noi.'
    };
    
    if (templates[template]) {
        jQuery('textarea[name="message"]').val(templates[template]);
    }
}
// Form handlers
jQuery(document).ready(function($) {
    // Form aggiungi numero
    $('#wsp-add-number-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_add_number',
            nonce: wsp_ajax.nonce,
            number: $(this).find('[name="number"]').val(),
            name: $(this).find('[name="name"]').val(),
            email: $(this).find('[name="email"]').val(),
            campaign: $(this).find('[name="campaign"]').val()
        }, function(response) {
            if (response.success) {
                alert('Numero aggiunto con successo!');
                location.reload();
            } else {
                alert('Errore: ' + (response.data || 'Errore sconosciuto'));
            }
        });
    });
    
    // Form invio messaggio
    $('#wsp-send-message-form').on('submit', function(e) {
        e.preventDefault();
        
        var recipients = $('#wsp-message-recipients').val();
        var message = $(this).find('[name="message"]').val();
        
        if (!recipients || recipients.length === 0) {
            alert('Seleziona almeno un destinatario');
            return;
        }
        
        if (!message) {
            alert('Inserisci un messaggio');
            return;
        }
        
        $('#wsp-send-status').html('<span class="wsp-spinner"></span> Invio in corso...');
        
        $.post(wsp_ajax.ajax_url, {
            action: 'wsp_send_bulk',
            nonce: wsp_ajax.nonce,
            recipients: recipients,
            message: message
        }, function(response) {
            if (response.success) {
                $('#wsp-send-status').html('✅ Invio completato! Inviati: ' + response.data.sent + ', Falliti: ' + response.data.failed);
                $('#wsp-send-message-form')[0].reset();
            } else {
                $('#wsp-send-status').html('❌ Errore: ' + (response.data || 'Errore sconosciuto'));
            }
        });
    });
    
    // Carica destinatari per select2
    if ($('#wsp-message-recipients').length) {
        $('#wsp-message-recipients').select2({
            ajax: {
                url: wsp_ajax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wsp_get_recipients',
                        nonce: wsp_ajax.nonce,
                        search: params.term
                    };
                },
                processResults: function(response) {
                    if (response.success) {
                        return {
                            results: $.map(response.data, function(item) {
                                return {
                                    id: item.sender_formatted || item.sender_number,
                                    text: item.sender_name ? 
                                        item.sender_name + ' (' + (item.sender_formatted || item.sender_number) + ')' : 
                                        (item.sender_formatted || item.sender_number)
                                };
                            })
                        };
                    }
                    return { results: [] };
                }
            },
            placeholder: 'Seleziona destinatari',
            multiple: true,
            width: '100%'
        });
    }
});
--------------------------------------------------------------------------------