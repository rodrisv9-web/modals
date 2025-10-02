/**
 * api-client.js
 * Módulo centralizado para todas las llamadas AJAX del plugin Veterinalia Appointment.
 */
var VAApi = (function ($) {

    /**
     * Realiza una petición AJAX genérica y maneja el estado de carga y los errores.
     * @param {string} action - La acción de WordPress AJAX a ejecutar.
     * @param {object} data - Los datos a enviar, sin incluir 'action' y 'nonce'.
     * @param {jQuery} [$button] - Opcional. El botón que inició la acción para desactivarlo.
     * @returns {Promise} - Una promesa de jQuery que se resuelve o rechaza con la respuesta del servidor.
     */
    function request(action, data, $button) {
        var originalButtonText;
        if ($button && $button.length) {
            originalButtonText = $button.text();
            $button.prop('disabled', true).text('Procesando...');
        }

        // Usamos va_ajax_object.nonce que definimos con wp_localize_script
        var ajaxData = $.extend({
            action: action,
            nonce: va_ajax_object.nonce 
        }, data);

        return $.post(va_ajax_object.ajax_url, ajaxData)
            .fail(function() {
                alert('Error de conexión. Por favor, revisa tu conexión a internet e inténtalo de nuevo.');
            })
            .always(function() {
                if ($button && $button.length) {
                    $button.prop('disabled', false).text(originalButtonText);
                }
            });
    }

    // --- MÉTODOS PÚBLICOS ---
    return {
        // Exponer la función request para uso general
        request: request,

        // -- Métodos de Horarios --
        saveSchedule: function(profId, scheduleData, $button) {
            return request('va_save_professional_schedule', {
                professional_id: profId,
                schedule_data: JSON.stringify(scheduleData)
            }, $button);
        },
        getSchedule: function(profId) {
            return request('va_get_professional_schedule', {
                professional_id: profId
            });
        },

        // -- Métodos de Citas --
        getAppointments: function(profId) {
            return request('va_get_professional_appointments', {
                professional_id: profId
            });
        },
        updateAppointmentStatus: function(appointmentId, newStatus, profId, $button) {
            return request('va_update_appointment_status', {
                appointment_id: appointmentId,
                new_status: newStatus,
                professional_id: profId
            }, $button);
        },

        // -- Métodos de Reserva de Cliente --
        getAvailabilityForRange: function(profId, startDate, endDate, duration) {
            // Nótese que aquí el nonce se pasa como 'nonce' y no '_wpnonce',
            // ajustándose a lo que espera check_ajax_referer('va_appointment_nonce', 'nonce').
            // La función 'request' ya se encarga de esto.
            return request('va_get_availability_for_range', {
                professional_id: profId,
                start_date: startDate,
                end_date: endDate,
                duration: duration
            });
        },
        bookAppointment: function(bookingData, $button) {
            return request('va_book_appointment', {
                booking_data: JSON.stringify(bookingData)
            }, $button);
        },

        // -- Métodos de Servicios y Categorías --
        getServicesForCategory: function(categoryId) {
            // Este es un GET, por lo que no necesita nonce, pero lo incluimos por consistencia.
            // La llamada se hace a la nueva ruta REST que creamos.
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}categories/${categoryId}/services`;
            
            return jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        saveCategory: function(profId, categoryName, $button) {
            return request('va_save_category', {
                professional_id: profId,
                category_name: categoryName
            }, $button);
        },
        editCategory: function(categoryId, newName) {
            return request('va_edit_category', {
                category_id: categoryId,
                name: newName
            });
        },
        deleteCategory: function(profId, categoryId) {
            return request('va_delete_category', {
                professional_id: profId,
                category_id: categoryId
            });
        },
        saveService: function(serviceData, $button) {
            return request('va_save_service', serviceData, $button);
        },
        editService: function(serviceId, serviceData) {
            return request('va_edit_service', $.extend({ service_id: serviceId }, serviceData));
        },
        deleteService: function(profId, serviceId) {
            return request('va_delete_service', {
                professional_id: profId,
                service_id: serviceId
            });
        },
        getAvailableSlots: function(professionalId, serviceId, date) {
            console.log('[API Client] Solicitando slots disponibles:', {
                professional_id: professionalId,
                service_id: serviceId,
                date: date
            });

            return $.ajax({
                url: va_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_available_slots',
                    professional_id: professionalId,
                    service_id: serviceId,
                    date: date,
                    nonce: va_ajax_object.nonce
                },
                dataType: 'json'
            }).done(function(response) {
                console.log('[API Client] Slots disponibles recibidos:', response);
            }).fail(function(xhr, status, error) {
                console.log('[API Client] Error obteniendo slots:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            });
        },

        createAppointment: function(data) {
            data.action = 'create_appointment';
            data.nonce = va_ajax_object.nonce;

            console.log('[API Client] Enviando createAppointment:', data);
            console.log('[API Client] URL AJAX:', va_ajax_object.ajax_url);

            return $.ajax({
                url: va_ajax_object.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json'
            }).done(function(response) {
                console.log('[API Client] Respuesta exitosa:', response);
            }).fail(function(xhr, status, error) {
                console.log('[API Client] Error en la petición:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
            });
        },

        // -- Métodos de Importación y Plantillas --
        getListingData: function(profId) {
            return request('va_get_listing_categories_services', {
                professional_id: profId
            });
        },
        inheritFromListing: function(targetId, inheritanceData, $button) {
            return request('va_inherit_categories_services', {
                professional_id: targetId,
                inheritance_data: JSON.stringify(inheritanceData)
            }, $button);
        },
        getServiceTemplates: function() {
             // Para el admin, el nonce esperado es '_wpnonce' con la acción 'va_admin_ajax_nonce'.
             // Hacemos una llamada AJAX especial para el admin.
            return $.post(va_ajax_object.ajax_url, {
                action: 'va_get_service_templates',
                nonce: va_ajax_object.nonce // Usamos el nonce del frontend, que es va_appointment_nonce
            });
        },
        importTemplate: function(profId, templateId, $button) {
            return request('va_import_template', {
                professional_id: profId,
                template_id: templateId
            }, $button);
        },

        // -- Métodos de Tipos de Entrada --
        getEntryTypes: function() {
            // Esta es una llamada a la API REST, no a admin-ajax
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}entry-types`;
            
            return jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        getFormFields: function(entryTypeId) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}forms/entry-type/${entryTypeId}`;
            return jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        createPetLog: function(logData) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}pet-logs`;
            return jQuery.ajax({
                url: url,
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(logData),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        getProductsByProfessional: function(professionalId) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}products/professional/${professionalId}`;
            return jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        getProductsFullByProfessional: function(professionalId) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}products-full/professional/${professionalId}`;
            return jQuery.ajax({
                url: url,
                method: 'GET',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        saveProduct: function(productData) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}products`;
            return jQuery.ajax({
                url: url,
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(productData),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        updateProduct: function(productId, productData) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}products/${productId}`;
            return jQuery.ajax({
                url: url,
                method: 'PUT',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(productData),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
        deleteProduct: function(productId, professionalId) {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const url = `${api.api_url}products/${productId}`;
            return jQuery.ajax({
                url: url,
                method: 'DELETE',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify({ professional_id: professionalId }),
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', api.api_nonce);
                }
            });
        },
    };

})(jQuery);