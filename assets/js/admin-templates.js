// assets/js/admin-templates.js
jQuery(document).ready(function($) {
    const adminPage = $('.wrap');

    // --- MANEJO DEL MODAL DE CREACIÓN ---
    $('#va-add-new-template-btn').on('click', function(e) {
        e.preventDefault();
        $('#va-template-modal').remove(); // Limpiar por si acaso

        const modalHtml = `
            <div id="va-template-modal" class="va-modal">
                <div class="va-modal-content">
                    <div class="va-modal-header"><h3>Crear Nueva Plantilla</h3><span class="va-modal-close">&times;</span></div>
                    <form id="va-new-template-form">
                        <div class="va-modal-body">
                            <div class="va-form-group"><label for="va-template-name">Nombre de la Plantilla</label><input type="text" id="va-template-name" name="template_name" required placeholder="Ej: Veterinario General"></div>
                            <div class="va-form-group"><label for="va-professional-type">Tipo de Profesional</label><input type="text" id="va-professional-type" name="professional_type" required placeholder="Ej: veterinario, paseador"></div>
                            <div class="va-form-group"><label for="va-template-description">Descripción</label><textarea id="va-template-description" name="description"></textarea></div>
                            <div class="va-form-group"><label><input type="checkbox" name="is_active" checked> Activa</label></div>
                        </div>
                        <div class="va-modal-footer">
                            <button type="submit" class="va-btn-primary">Guardar Plantilla</button>
                            <button type="button" class="va-btn-secondary va-modal-close">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>`;
        $('body').append(modalHtml);
    });

    $('body').on('click', '#va-template-modal .va-modal-close', function() { $('#va-template-modal').remove(); });

    $('body').on('submit', '#va-new-template-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalBtnText = $submitBtn.text();
        $submitBtn.text('Guardando...').prop('disabled', true);

        const formData = {
            action: 'va_save_template',
            _wpnonce: va_ajax_object.nonce, // Asegurarnos que se usa el estándar '_wpnonce'
            template_name: $form.find('[name="template_name"]').val(),
            professional_type: $form.find('[name="professional_type"]').val(),
            description: $form.find('[name="description"]').val(),
            is_active: $form.find('[name="is_active"]').is(':checked') ? 1 : 0,
        };

        // --- INICIO: Mensajes de Depuración ---
        console.group("🔵 DEBUG: Enviando Solicitud AJAX para Guardar Plantilla");
        console.log("URL del AJAX:", va_ajax_object.ajax_url);
        console.log("Nonce enviado:", va_ajax_object.nonce);
        console.log("Datos completos enviados (formData):", JSON.parse(JSON.stringify(formData)));
        console.groupEnd();
        // --- FIN: Mensajes de Depuración ---

        $.post(va_ajax_object.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    console.log("✅ ÉXITO: Plantilla guardada. Respuesta:", response);
                    alert('Plantilla guardada con éxito.');
                    location.reload();
                } else {
                    console.error("❌ ERROR: El servidor respondió con un error. Respuesta:", response);
                    alert('Error: ' + response.data.message);
                }
            })
            .fail(function(jqXHR) {
                console.error("🔥 FALLO GRAVE: La solicitud AJAX falló. Status:", jqXHR.status, jqXHR.statusText);
                console.error("Respuesta completa del servidor:", jqXHR.responseText);
                alert('Ocurrió un error de conexión. Revisa la consola para más detalles (F12).');
            })
            .always(function() {
                $submitBtn.text(originalBtnText).prop('disabled', false);
                $('#va-template-modal').remove();
            });
    });

    // --- INICIO: LÓGICA DEL EDITOR DE PLANTILLAS ---

    // 1. Cargar el editor al hacer clic en el nombre de una plantilla
    adminPage.on('click', '#the-list tr a', function(e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const templateId = $row.data('template-id');

        // Si el editor para esta plantilla ya está abierto, lo cerramos
        if ($row.next().find('.va-template-editor-container').length) {
            $row.next().remove();
            return;
        }

        // Cerrar cualquier otro editor que esté abierto
        $('.va-editor-row').remove();

        // Añadir un placeholder de "cargando..."
        const $loadingRow = $('<tr class="va-editor-row"><td colspan="4">Cargando editor...</td></tr>');
        $row.after($loadingRow);

        const ajaxData = {
            action: 'va_get_template_details',
            // <<-- CAMBIO CLAVE -->>
            // Se estandariza el nombre del campo a '_wpnonce' para ser consistente.
            _wpnonce: va_ajax_object.nonce,
            template_id: templateId
        };

        console.log('Solicitando detalles para la plantilla ID:', templateId);

        $.post(va_ajax_object.ajax_url, ajaxData)
            .done(function(response) {
                if(response.success) {
                    $loadingRow.find('td').html(response.data.html);
                } else {
                    $loadingRow.find('td').html('Error al cargar la plantilla: ' + response.data.message);
                }
            })
            .fail(function() {
                $loadingRow.find('td').html('Error de conexión al cargar la plantilla.');
            });
    });

    // --- INICIO: AÑADIR CATEGORÍA A PLANTILLA ---
    adminPage.on('submit', '.va-add-category-to-template-form form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const templateId = $form.find('[name="template_id"]').val();
        const categoryName = $form.find('[name="category_name"]').val();

        if (!categoryName) {
            alert('El nombre de la categoría no puede estar vacío.');
            return;
        }

        const originalBtnText = $button.text();
        $button.text('Añadiendo...').prop('disabled', true);

        const ajaxData = {
            action: 'va_add_category_to_template',
            _wpnonce: va_ajax_object.nonce,
            template_id: templateId,
            category_name: categoryName
        };

        console.log('Enviando nueva categoría:', ajaxData);

        $.post(va_ajax_object.ajax_url, ajaxData)
            .done(function(response) {
                if (response.success) {
                    // Si es la primera categoría, elimina el mensaje de "no hay items"
                    $('#va-template-editor-' + templateId).find('.va-no-items-message').remove();
                    
                    // --- INICIO DE CAMBIOS ---
                    // Simplemente usamos el HTML que nos devuelve el servidor
                    $('#va-template-categories-container').append(response.data.html);
                    // --- FIN DE CAMBIOS ---
                    
                    // Limpia el campo del formulario
                    $form.find('[name="category_name"]').val('');

                } else {
                    alert('Error: ' + response.data.message);
                }
            })
            .fail(function() {
                alert('Ocurrió un error de conexión.');
            })
            .always(function() {
                $button.text(originalBtnText).prop('disabled', false);
            });
    });
    // --- FIN: AÑADIR CATEGORÍA A PLANTILLA ---

    // --- INICIO: AÑADIR SERVICIO A PLANTILLA ---
    adminPage.on('submit', '.va-add-service-to-template-form form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $categoryItem = $form.closest('.va-template-category-item');

        const serviceData = {
            action: 'va_add_service_to_template',
            _wpnonce: va_ajax_object.nonce,
            template_category_id: $form.find('[name="template_category_id"]').val(),
            service_name: $form.find('[name="service_name"]').val(),
            suggested_duration: $form.find('[name="suggested_duration"]').val(),
            suggested_price: $form.find('[name="suggested_price"]').val()
        };

        const originalBtnText = $button.text();
        $button.text('Añadiendo...').prop('disabled', true);

        $.post(va_ajax_object.ajax_url, serviceData)
            .done(function(response) {
                if (response.success) {
                    const service = response.data.service;
                    const $serviceListContainer = $categoryItem.find('.va-template-services-list');
                    
                    // Si es el primer servicio, quita el mensaje "No hay servicios"
                    if ($serviceListContainer.find('p').length) {
                        $serviceListContainer.html('<ul></ul>');
                    }

                    // Añade el nuevo servicio a la lista
                    const newServiceHtml = `<li>${service.service_name} (${service.suggested_duration} min, $${service.suggested_price})</li>`;
                    $serviceListContainer.find('ul').append(newServiceHtml);
                    
                    // Limpia el formulario
                    $form.trigger('reset');

                } else {
                    alert('Error: ' + response.data.message);
                }
            })
            .fail(function() {
                alert('Ocurrió un error de conexión.');
            })
            .always(function() {
                $button.text(originalBtnText).prop('disabled', false);
            });
    });
    // --- FIN: AÑADIR SERVICIO A PLANTILLA ---

});