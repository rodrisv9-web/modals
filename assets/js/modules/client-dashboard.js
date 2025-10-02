// assets/js/modules/client-dashboard.js
jQuery(document).ready(function($) {
    const form = $('#claim-pet-form');
    const feedback = $('#claim-feedback');
    const shareCodeInput = $('#claim-share-code');
    const submitButton = form.find('button[type="submit"]');

    form.on('submit', function(e) {
        e.preventDefault();
        const shareCode = shareCodeInput.val().trim().toUpperCase();
        if (!shareCode) {
            feedback.text('Por favor, introduce un código.').css('color', 'red');
            return;
        }

        feedback.text('Vinculando...').css('color', 'blue');
        submitButton.prop('disabled', true);

        $.ajax({
            url: VA_Client_Dashboard.rest_url + 'clients/claim-profile',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', VA_Client_Dashboard.nonce);
            },
            data: JSON.stringify({
                share_code: shareCode
            })
        }).done(function(response) {
            if (response.success) {
                feedback.text(response.message).css('color', 'green');
                alert(response.message);
                // Recargamos la página para que el servidor pueda mostrar las mascotas ya vinculadas.
                location.reload();
            }
        }).fail(function(jqXHR) {
            const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "Error al vincular. Revisa el código e inténtalo de nuevo.";
            feedback.text(errorMsg).css('color', 'red');
        }).always(function() {
            submitButton.prop('disabled', false);
        });
    });

    // Abrir el action sheet cuando se pulsa el botón de añadir mascota
    $(document).on('click', '.add-pet-button', function(e) {
        e.preventDefault();
        if (typeof window.showModal === 'function') {
            showModal('action-sheet-modal');
        } else {
            $('#action-sheet-modal').addClass('visible');
            document.body.style.overflow = 'hidden';
        }
    });

    // Opciones del action sheet
    $('#create-pet-option').on('click', function(e) {
        e.preventDefault();
        if (typeof window.hideModal === 'function') {
            hideModal('action-sheet-modal');
            showModal('pet-modal');
        } else {
            $('#action-sheet-modal').removeClass('visible');
            $('#pet-modal').addClass('visible');
            document.body.style.overflow = 'hidden';
        }
    });

    $('#import-patient-option').on('click', function(e) {
        e.preventDefault();
        if (typeof window.hideModal === 'function') {
            hideModal('action-sheet-modal');
            showModal('import-modal');
        } else {
            $('#action-sheet-modal').removeClass('visible');
            $('#import-modal').addClass('visible');
            document.body.style.overflow = 'hidden';
        }
    });

    // Cancelar action sheet
    $('#action-sheet-cancel').on('click', function(e) {
        e.preventDefault();
        if (typeof window.hideModal === 'function') {
            hideModal('action-sheet-modal');
        } else {
            $('#action-sheet-modal').removeClass('visible');
            document.body.style.overflow = '';
        }
    });

    // Cerrar modales con botones de cierre si existen
    $('#pet-modal-close').on('click', function() { if (typeof window.hideModal === 'function') { hideModal('pet-modal'); } else { $('#pet-modal').removeClass('visible'); document.body.style.overflow = ''; } });
    $('#import-modal-close').on('click', function() { if (typeof window.hideModal === 'function') { hideModal('import-modal'); } else { $('#import-modal').removeClass('visible'); document.body.style.overflow = ''; } });

    // Manejar el formulario de importación del modal
    $('#import-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Formulario de importación enviado');

        const importCode = $('#import-code').val().trim().toUpperCase();
        const importFeedback = $('#import-feedback');

        console.log('Código de importación:', importCode);

        if (!importCode) {
            console.log('Código vacío');
            if (importFeedback.length) {
                importFeedback.text('Por favor, introduce un código.').css('color', 'red');
            } else {
                alert('Por favor, introduce un código.');
            }
            return;
        }

        if (importFeedback.length) {
            importFeedback.text('Importando...').css('color', 'blue');
        }

        console.log('Enviando solicitud AJAX a:', VA_Client_Dashboard.rest_url + 'clients/claim-profile');

        $.ajax({
            url: VA_Client_Dashboard.rest_url + 'clients/claim-profile',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', VA_Client_Dashboard.nonce);
                console.log('Nonce enviado:', VA_Client_Dashboard.nonce);
            },
            data: JSON.stringify({
                share_code: importCode
            })
        }).done(function(response) {
            console.log('Respuesta exitosa:', response);
            if (response.success) {
                if (importFeedback.length) {
                    importFeedback.text(response.message).css('color', 'green');
                } else {
                    alert(response.message);
                }
                // Cerrar el modal
                if (typeof window.hideModal === 'function') {
                    hideModal('import-modal');
                } else {
                    $('#import-modal').removeClass('visible');
                    document.body.style.overflow = '';
                }
                // Recargar la página para mostrar la mascota importada
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Error en la solicitud AJAX:', {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText,
                responseJSON: jqXHR.responseJSON
            });

            const errorMsg = jqXHR.responseJSON && jqXHR.responseJSON.message
                ? jqXHR.responseJSON.message
                : "Error al importar. Revisa el código e inténtalo de nuevo.";

            if (importFeedback.length) {
                importFeedback.text(errorMsg).css('color', 'red');
            } else {
                alert(errorMsg);
            }
        });
    });

    // Cancelar importación
    $('#import-form-cancel').on('click', function(e) {
        e.preventDefault();
        if (typeof window.hideModal === 'function') {
            hideModal('import-modal');
        } else {
            $('#import-modal').removeClass('visible');
            document.body.style.overflow = '';
        }
    });
});
