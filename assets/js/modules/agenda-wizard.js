// assets/js/modules/agenda-wizard.js (Versión Final Fase 1)

const AgendaWizard = (function ($) {
    // Estado del wizard
    let state = {
        professionalId: null,
        currentStep: 1,
        selectedClientId: null,
        selectedPetId: null,
        selectedClientName: null,
        selectedClientEmail: null,
        selectedClientPhone: null,
        currentClientPets: [],
        // <-- Propiedades nuevas para el agendamiento -->
        servicesAndCategories: [],
        selectedService: { id: null, duration: null, name: null },
        selectedDate: null,
        selectedSlot: null,
    };

    // Elementos del DOM
    let dom = {};
    let initialized = false;

    function cacheDOM() {
        const modal = $('#agenda-booking-wizard-modal');
        dom.modal = modal;
        dom.title = modal.find('#wizard-title');
        dom.steps = modal.find('.wizard-step');
        dom.progressSteps = modal.find('.progress-step');
        dom.closeBtn = modal.find('#wizard-close-btn');
        dom.clientSearchInput = modal.find('#wizard-client-search');
        dom.searchResultsContainer = modal.find('#wizard-search-results');
        dom.categorySelect = modal.find('#wiz-category');
        dom.serviceSelect = modal.find('#wiz-service');
        dom.dateInput = modal.find('#wiz-date');
        dom.slotsWrapper = modal.find('#wiz-slots');
        dom.slotsWrapperContainer = modal.find('#wiz-slots-wrapper');
        dom.slotsPagination = modal.find('#wiz-slots-pagination');
        dom.confirmBtn = modal.find('#wizard-confirm-appointment-btn');
        console.log('🔧 DOM cacheado:', {
            modal: dom.modal.length,
            steps: dom.steps.length
        });
    }

    function bindEvents() {
        // Limpiar handlers anteriores para evitar duplicados cuando el modal
        // es eliminado e inyectado de nuevo en el DOM.
        if (dom.modal && dom.modal.length) {
            dom.modal.off();
        }

        dom.closeBtn.on('click', close);

        let searchTimeout;
        dom.clientSearchInput.on('keyup', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { handleClientSearch(); }, 300);
        });

        dom.modal.on('click', '.result-item', function() {
            const clientId = $(this).data('client-id');
            handleClientSelection(clientId);
        });

        dom.modal.on('click', '.unlock-btn', function() {
            const petId = $(this).data('pet-id');
            handleUnlockPet(petId);
        });

        dom.modal.on('click', '.pet-item.selectable', function() {
            const petId = $(this).data('pet-id');
            handlePetSelection(petId);
        });
        
        // <-- INICIO DE NUEVOS EVENTOS: Proyecto Chocovainilla - Paso 1.5/1.6 -->
        dom.modal.on('change', '#wiz-category', function() {
            handleCategorySelection($(this).val());
        });

        dom.modal.on('change', '#wiz-service', function() {
            const selectedOption = $(this).find('option:selected');
            const serviceId = selectedOption.val();
            if (serviceId) {
                const duration = selectedOption.data('duration');
                const name = selectedOption.data('service-name') || selectedOption.text();
                handleServiceSelection(serviceId, duration, name);
            } else {
                state.selectedService = { id: null, duration: null, name: null };
                state.selectedSlot = null;
                updateSlotsMessage('Selecciona un servicio para ver los horarios disponibles.');
                dom.confirmBtn.prop('disabled', true);
            }
        });

        dom.modal.on('change', '#wiz-date', function() {
            const selectedDate = $(this).val();
            state.selectedDate = selectedDate || null;
            console.log('📅 Wizard: Fecha seleccionada', state.selectedDate);
            if (state.selectedService.id && state.selectedDate) {
                fetchAndRenderSlots(state.selectedDate);
            } else {
                dom.confirmBtn.prop('disabled', true);
                updateSlotsMessage('Selecciona un servicio y fecha para ver horarios.');
            }
        });

        dom.modal.on('click', '#wiz-slots .time-slot', function() {
            state.selectedSlot = $(this).data('time');
            dom.slotsWrapper.find('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            dom.confirmBtn.prop('disabled', false);
        });

        dom.confirmBtn.on('click', finalizeAppointment);
        
        // <-- NUEVOS EVENTOS PARA PASOS INTEGRADOS -->
        // Evento para ir al paso de crear cliente nuevo
        dom.modal.on('click', '#wizard-new-client-btn', function() {
            showStep(1.5);
        });

        // Eventos de navegación del paso 1.5 (crear cliente)
        dom.modal.on('click', '#wizard-back-to-search-inline', function() {
            showStep(1);
        });

        dom.modal.on('submit', '#wizard-client-form-inline', function(e) {
            handleInlineClientFormSubmit(e);
        });

        // Evento para ir al paso de crear mascota nueva
        dom.modal.on('click', '#wizard-new-pet-btn', function() {
            $('#wizard-selected-client-name-pet').text(state.selectedClientName || 'Cliente Seleccionado');
            showStep(2.5);
        });

        // Eventos de navegación del paso 2.5 (crear mascota)
        dom.modal.on('click', '#wizard-back-to-pets-inline', function() {
            showStep(2);
        });

        dom.modal.on('submit', '#wizard-pet-form-inline', function(e) {
            handleInlinePetFormSubmit(e);
        });

        // Regenerar share code
        dom.modal.on('click', '#wizard-regenerate-code-inline', function() {
            regenerateInlineShareCode();
        });

        // Auto-generar share code cuando cambie el nombre
        dom.modal.on('input', '#wizard-pet-name-inline', function() {
            autoGenerateInlineShareCode(this.value);
        });
        // <-- FIN DE NUEVOS EVENTOS PARA PASOS INTEGRADOS -->
        
        // <-- FIN DE NUEVOS EVENTOS: Proyecto Chocovainilla - Paso 1.5/1.6 -->
    }
    
    // --- Lógica de Pasos (sin cambios) ---
    function open(professionalId) {
        console.group("🚀 WIZARD: Abriendo modal para el profesional ID:", professionalId);

        // Obtener referencia actual del modal en el DOM
        const currentModal = jQuery('#agenda-booking-wizard-modal');

        // Si no está inicializado o el modal no existe aún, intentar inicializar
        if (!initialized || currentModal.length === 0) {
            init();
            cacheDOM();
        }

        // Si el modal fue reinyectado, actualizar el cache y reemparejar eventos
        if (currentModal.length > 0 && (!dom.modal || dom.modal.length === 0 || dom.modal[0] !== currentModal[0])) {
            cacheDOM();
            bindEvents();
            console.log('🔁 WIZARD: Modal re-detectado en DOM. Re-cacheado y enlazados eventos.');
        }

        if (!jQuery('#agenda-booking-wizard-modal').length) {
            console.error("❌ WIZARD: No se encontró #agenda-booking-wizard-modal en el DOM. Aborto.");
            console.groupEnd();
            return;
        }

        console.log("   - Objeto del modal:", dom.modal);
        console.log("   - Clases ANTES de modificar:", typeof dom.modal.attr === 'function' ? dom.modal.attr('class') : dom.modal[0]?.className);
        state.professionalId = professionalId;
        resetWizard();
        showStep(1);
        dom.modal.removeClass('hidden');
        setTimeout(() => {
            dom.modal.addClass('visible');
            dom.modal.find('.modal-content').addClass('show');
            console.log("   - Clases DESPUÉS de modificar:", typeof dom.modal.attr === 'function' ? dom.modal.attr('class') : dom.modal[0]?.className);
            console.log("   - Modal ahora visible.");
        }, 10);
    }

    function close() {
        dom.modal.find('.modal-content').removeClass('show');
        dom.modal.removeClass('visible');
        setTimeout(() => {
            dom.modal.addClass('hidden');
            console.log("   - Modal cerrado.");
            console.groupEnd();
        }, 300);
    }

    function showStep(stepNumber) {
        state.currentStep = stepNumber;
        dom.steps.hide().removeClass('active');
        dom.steps.filter(`[data-step="${stepNumber}"]`).show().addClass('active');
        console.log(`🔷 Chocovainilla Wizard: Mostrando paso ${stepNumber}`);

        const mainStep = Math.floor(stepNumber);
        if (dom.progressSteps && dom.progressSteps.length) {
            dom.progressSteps.removeClass('active');
            dom.progressSteps.each(function() {
                const step = parseInt($(this).data('progress-step'), 10);
                if (step <= mainStep) {
                    $(this).addClass('active');
                }
            });
        }

        // Lógica específica para cada paso
        if (stepNumber === 1.5) {
            // Limpiar formulario de cliente y enfocar
            const clientForm = $('#wizard-client-form-inline');
            if (clientForm.length > 0) {
                clientForm[0].reset();
            }
            setTimeout(() => {
                $('#wizard-client-name-inline').focus();
            }, 100);
        } else if (stepNumber === 2.5) {
            // Limpiar formulario de mascota y generar share code
            const petForm = $('#wizard-pet-form-inline');
            if (petForm.length > 0) {
                petForm[0].reset();
            }
            $('#wizard-pet-share-code-inline').val(generateWizardShareCode('MASCOTA'));
            setTimeout(() => {
                $('#wizard-pet-name-inline').focus();
            }, 100);
        } else if (stepNumber === 3) {
            loadServicesAndCategories();
        }
    }
    
    // <-- FUNCIÓN NUEVA: Resetea el estado para una nueva apertura -->
    function resetWizard() {
        state.selectedClientId = null;
        state.selectedClientName = null;
        state.selectedClientEmail = null;
        state.selectedClientPhone = null;
        state.currentClientPets = [];
        state.servicesAndCategories = [];
        state.selectedPetId = null;
        state.selectedService = { id: null, duration: null, name: null };
        const today = new Date();
        const isoToday = today.toISOString().split('T')[0];
        state.selectedDate = isoToday;
        state.selectedSlot = null;
        dom.clientSearchInput.val('');
        dom.searchResultsContainer.html('<p class="text-center text-gray-500">Introduce al menos 3 caracteres para buscar.</p>');
        if (dom.categorySelect && dom.categorySelect.length) {
            dom.categorySelect.val('').prop('disabled', true);
        }
        if (dom.serviceSelect && dom.serviceSelect.length) {
            dom.serviceSelect.html('<option value="">Selecciona una categoría primero</option>').prop('disabled', true);
        }
        if (dom.dateInput && dom.dateInput.length) {
            dom.dateInput.attr('min', isoToday).val(isoToday);
        }
        updateSlotsMessage('Selecciona un servicio para ver los horarios disponibles.');
        dom.confirmBtn.prop('disabled', true).text('Confirmar cita');
    }

    // --- Lógica de Búsqueda y Vinculación (sin cambios) ---
    function handleClientSearch() {
        const term = dom.clientSearchInput.val();
        if (term.length < 3) {
            dom.searchResultsContainer.html('<p class="text-center text-gray-500">Introduce al menos 3 caracteres para buscar.</p>');
            return;
        }

        console.log(`🔍 Chocovainilla Wizard: Buscando clientes con el término "${term}"...`);
        dom.searchResultsContainer.html('<p class="text-center text-gray-500">Buscando...</p>');

        $.ajax({
            url: VA_REST.api_url + 'clients/search',
            data: {
                term: term,
                professional_id: state.professionalId
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce);
            }
        }).done(function(response) {
            if (response.success) {
                console.log("📊 Chocovainilla Wizard: Resultados recibidos de la API", response.data);
                renderSearchResults(response.data);
            }
        });
    }

    function renderSearchResults(results) {
        state.lastSearchResults = results;
        if (results.length === 0) {
            dom.searchResultsContainer.html('<p class="text-center text-gray-500">No se encontraron clientes.</p>');
            return;
        }

        const resultsHtml = results.map(client => {
            const hasAccess = parseInt(client.has_access) === 1;
            const availabilityText = hasAccess ? '✅ Mis Pacientes' : '🔒 Red Veterinalia';
            const availabilityClass = hasAccess ? 'tag-mine' : 'tag-network';

            return `
                <div class="result-item" data-client-id="${client.client_id}" data-client-name="${client.name || ''}" data-client-email="${client.email || ''}">
                    <div class="result-item-header">
                        <span class="result-item-tag ${availabilityClass}">${availabilityText}</span>
                    </div>
                    <div class="result-item-body">
                        <strong>${client.name}</strong>
                    </div>
                    <div class="result-item-footer">
                        <small>${client.email}</small>
                    </div>
                </div>
            `;
        }).join('');

        dom.searchResultsContainer.html(resultsHtml);
    }

    function handleClientSelection(clientId, options = {}) {
        state.selectedClientId = clientId;
        console.log(`👤 Chocovainilla Wizard: Cliente seleccionado ID: ${clientId}. Buscando sus mascotas...`);
        
        // Buscar el cliente en los últimos resultados de búsqueda o tomarlo del atributo data
        const clientFromState = (state.lastSearchResults || []).find(c => String(c.client_id) === String(clientId));
        const clickedItem = dom.modal.find(`.result-item[data-client-id="${clientId}"]`);
        const clientName = clientFromState?.name || clickedItem.data('client-name') || '';
        const clientEmail = clientFromState?.email || clickedItem.data('client-email') || '';
        state.selectedClientName = clientName || null;
        state.selectedClientEmail = clientEmail || null;
        if (clientName) {
            $('#wizard-selected-client-name').text(clientName);
        } else {
            $('#wizard-selected-client-name').text(`ID ${clientId}`);
        }
        
        // Llamada a la API para obtener las mascotas de este cliente
        console.log(`📡 Chocovainilla Wizard: Solicitando mascotas de client_id=${clientId} con professional_id=${state.professionalId}`);
        $.ajax({
            url: VA_REST.api_url + `patients/clients/${clientId}/pets`,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce);
            }
        , data: { professional_id: state.professionalId }
        }).done(function(response) {
            if (response.success) {
                const petsArray = Array.isArray(response.data) ? response.data : [];
                const accessCount = petsArray.filter(p => parseInt(p.has_access) === 1).length;
                console.log(`🐶 Chocovainilla Wizard: Mascotas encontradas para el cliente ${clientId}: ${petsArray.length} (con acceso: ${accessCount})`, response.data);
                state.currentClientPets = Array.isArray(response.data) ? response.data : [];
                renderPetSelection(response.data);
                if (options.autoSelectPetId) {
                    // Auto-seleccionar la mascota recién desbloqueada
                    console.log(`➡️ Chocovainilla Wizard: Auto-seleccionando mascota ${options.autoSelectPetId} y avanzando a Paso 3.`);
                    handlePetSelection(options.autoSelectPetId);
                } else {
                    showStep(2);
                }
            } else {
                alert("No se pudieron cargar las mascotas de este cliente.");
            }
        });
    }

    function renderPetSelection(pets) {
        const container = $('#wizard-pet-selection');
        if (pets.length === 0) {
            container.html("<p>Este cliente no tiene mascotas registradas.</p>");
            return;
        }

        const professionalAccess = simDB.pet_access // Simulación, en real esto se verificaría en el backend
            .filter(access => access.professional_id === state.professionalId)
            .map(access => access.pet_id);
        
        const petsArray = Array.isArray(pets) ? pets : [];
        const debugPets = petsArray.map(p => ({ id: p.pet_id, name: p.name, has_access: (typeof p.has_access !== 'undefined') ? parseInt(p.has_access) : (professionalAccess.includes(p.pet_id) ? 1 : 0) }));
        console.log("🧩 Chocovainilla Wizard: Renderizando selección de mascotas:", debugPets);

        const petsHtml = petsArray.map(pet => {
            const hasAccess = (typeof pet.has_access !== 'undefined') ? (parseInt(pet.has_access) === 1) : professionalAccess.includes(pet.pet_id);
            const petName = pet && pet.name ? pet.name : `Mascota ${pet.pet_id}`;
            const petSpecies = pet && pet.species ? pet.species : 'Especie no especificada';
            return `
                <div class="pet-item ${hasAccess ? 'selectable' : 'locked'}" data-pet-id="${pet.pet_id}">
                    <div class="pet-item-main">
                        <div class="pet-item-avatar" aria-hidden="true">🐾</div>
                        <div class="pet-item-info">
                            <div class="pet-item-name">${petName}</div>
                            <div class="pet-item-meta">${petSpecies}</div>
                        </div>
                        ${hasAccess ? '<i class="fas fa-chevron-right" aria-hidden="true"></i>' : ''}
                    </div>
                    ${!hasAccess ?
                        `<div class="unlock-section">
                            <input type="text" id="share-code-${pet.pet_id}" class="form-input" placeholder="Share-Code">
                            <button type="button" class="btn btn-secondary unlock-btn" data-pet-id="${pet.pet_id}">Desbloquear</button>
                        </div>` :
                        ''
                    }
                </div>
            `;
        }).join('');
        container.html(petsHtml);
    }

    function handleUnlockPet(petId) {
        const shareCode = $(`#share-code-${petId}`).val().toUpperCase();
        if (!shareCode) {
            alert("Por favor, introduce el código de compartir.");
            return;
        }
        console.log(`🔑 Chocovainilla Wizard: Intentando desbloquear mascota ${petId} con código ${shareCode}`);

        $.ajax({
            url: VA_REST.api_url + 'pets/grant-access',
            method: 'POST',
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce);
            },
            data: JSON.stringify({
                professional_id: state.professionalId,
                pet_id: petId,
                share_code: shareCode
            })
        }).done(function(response) {
            if (response.success) {
                alert(`¡Mascota desbloqueada y añadida a tu cartera!`);
                // Recargar mascotas y auto-seleccionar la recién desbloqueada para avanzar al Paso 3
                console.log(`✅ Chocovainilla Wizard: Desbloqueo exitoso. Recargando mascotas del cliente ${state.selectedClientId} y seleccionando pet_id=${petId}.`);
                handleClientSelection(state.selectedClientId, { autoSelectPetId: petId });
            }
        }).fail(function(jqXHR) {
            const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "Error desconocido.";
            alert(`Error al desbloquear: ${errorMsg}`);
        });
    }

    function handlePetSelection(petId) {
        state.selectedPetId = petId;
        console.log(`✅ Chocovainilla Wizard: Mascota seleccionada ID: ${petId}. Pasando a agendamiento.`);
        // Usar datos reales de la última respuesta
        const pet = (state.currentClientPets || []).find(p => String(p.pet_id) === String(petId));
        const labelClientName = state.selectedClientName || '';
        const labelPetName = pet && pet.name ? pet.name : `Mascota ${petId}`;
        const subtitle = labelClientName ? `${labelPetName} · ${labelClientName}` : `${labelPetName}`;
        $('#wizard-selected-pet-name').text(`Agendando para ${subtitle}`);
        showStep(3);
    }

    // <-- INICIO DE NUEVAS FUNCIONES: Proyecto Chocovainilla - Paso 1.5/1.6 -->
    function loadServicesAndCategories() {
        console.log('🔄 Chocovainilla Wizard: Cargando servicios y categorías...');
        if (dom.categorySelect && dom.categorySelect.length) {
            dom.categorySelect.prop('disabled', true).html('<option value="">Cargando categorías...</option>');
        }
        if (dom.serviceSelect && dom.serviceSelect.length) {
            dom.serviceSelect.prop('disabled', true).html('<option value="">Selecciona una categoría primero</option>');
        }
        updateSlotsMessage('Selecciona un servicio para ver los horarios disponibles.');

        $.ajax({
            url: VA_REST.api_url + `professionals/${state.professionalId}/services-and-categories`,
            beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce); }
        }).done(function(response) {
            if (response.success) {
                state.servicesAndCategories = response.data;
                console.log('✅ Chocovainilla Wizard: Servicios cargados:', state.servicesAndCategories);
                renderServicesInterface();
            } else {
                updateSlotsMessage('No fue posible cargar los servicios disponibles.');
            }
        }).fail(function() {
            updateSlotsMessage('Ocurrió un error al consultar los servicios.');
        });
    }

    function renderServicesInterface() {
        if (!dom.categorySelect || !dom.categorySelect.length) {
            return;
        }

        if (!state.servicesAndCategories || state.servicesAndCategories.length === 0) {
            dom.categorySelect.html('<option value="">No hay categorías disponibles</option>').prop('disabled', true);
            dom.serviceSelect.html('<option value="">Sin servicios disponibles</option>').prop('disabled', true);
            updateSlotsMessage('No hay servicios configurados para este profesional.');
            return;
        }

        const options = ['<option value="">Selecciona una categoría</option>'];
        state.servicesAndCategories.forEach(cat => {
            options.push(`<option value="${cat.category_id}">${cat.name}</option>`);
        });

        dom.categorySelect.html(options.join('')).prop('disabled', false);
        dom.serviceSelect.html('<option value="">Selecciona una categoría primero</option>').prop('disabled', true);
    }

    function handleCategorySelection(categoryId) {
        if (!dom.serviceSelect || !dom.serviceSelect.length) return;

        state.selectedService = { id: null, duration: null, name: null };
        state.selectedSlot = null;
        dom.confirmBtn.prop('disabled', true);

        if (!categoryId) {
            dom.serviceSelect.html('<option value="">Selecciona una categoría primero</option>').prop('disabled', true);
            updateSlotsMessage('Selecciona un servicio para ver los horarios disponibles.');
            return;
        }

        const selectedCategory = state.servicesAndCategories.find(cat => String(cat.category_id) === String(categoryId));
        if (!selectedCategory || !Array.isArray(selectedCategory.services) || selectedCategory.services.length === 0) {
            dom.serviceSelect.html('<option value="">No hay servicios en esta categoría</option>').prop('disabled', true);
            updateSlotsMessage('No hay horarios disponibles para esta categoría.');
            return;
        }

        const serviceOptions = ['<option value="">Selecciona un servicio</option>'];
        selectedCategory.services.forEach(service => {
            serviceOptions.push(`<option value="${service.service_id}" data-duration="${service.duration}" data-service-name="${service.name}">${service.name}</option>`);
        });

        dom.serviceSelect.html(serviceOptions.join('')).prop('disabled', false);
        updateSlotsMessage('Selecciona un servicio para ver los horarios disponibles.');
    }

    function handleServiceSelection(serviceId, duration, name) {
        state.selectedService = { id: serviceId, duration: duration, name: name };
        console.log(`🔧 Chocovainilla Wizard: Servicio seleccionado ID ${serviceId}, Duración ${duration} min.`);
        state.selectedSlot = null;
        dom.confirmBtn.prop('disabled', true);

        if (state.selectedDate) {
            fetchAndRenderSlots(state.selectedDate);
        } else {
            updateSlotsMessage('Selecciona una fecha para ver los horarios disponibles.');
        }
    }

    function updateSlotsMessage(message) {
        if (dom.slotsWrapper && dom.slotsWrapper.length) {
            dom.slotsWrapper.html(`<div class="slots-message">${message}</div>`);
        }
        if (dom.slotsPagination && dom.slotsPagination.length) {
            dom.slotsPagination.empty();
        }
    }

    function renderSlotsPagination(totalSlots) {
        if (!dom.slotsPagination || !dom.slotsPagination.length) return;
        dom.slotsPagination.empty();
        if (!totalSlots || totalSlots <= 4) {
            return;
        }
        const groups = Math.ceil(totalSlots / 4);
        let dotsHtml = '';
        for (let i = 0; i < groups; i++) {
            dotsHtml += `<span class="dot ${i === 0 ? 'active' : ''}"></span>`;
        }
        dom.slotsPagination.html(dotsHtml);
    }

    function fetchAndRenderSlots(date) {
        console.log('⏰ Chocovainilla Wizard: consultando horarios para', date, 'y servicio', state.selectedService.id);
        updateSlotsMessage('Cargando horarios disponibles...');
        dom.confirmBtn.prop('disabled', true);
        state.selectedSlot = null;

        VAApi.getAvailableSlots(state.professionalId, state.selectedService.id, date)
            .done(function(response) {
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    const slotsHtml = response.data.map(slot => `<button type="button" class="time-slot" data-time="${slot}">${slot}</button>`).join('');
                    dom.slotsWrapper.html(slotsHtml);
                    renderSlotsPagination(response.data.length);
                } else {
                    updateSlotsMessage('No hay horarios disponibles para esta fecha.');
                }
            })
            .fail(function() {
                updateSlotsMessage('Ocurrió un error al cargar los horarios.');
            });
    }

    function finalizeAppointment() {
        console.log("📦 Chocovainilla Wizard: Finalizando agendamiento con el siguiente estado:", state);
        if (!state.selectedClientId || !state.selectedPetId || !state.selectedService.id || !state.selectedDate || !state.selectedSlot) {
            alert("Error: Faltan datos para agendar la cita. Por favor, reinicia el proceso.");
            return;
        }
        
        dom.confirmBtn.prop('disabled', true).text('Agendando...');

        // Construir datos desde el estado real (sin simDB)
        const pet = (state.currentClientPets || []).find(p => String(p.pet_id) === String(state.selectedPetId));

        // Debug: Verificar que la mascota se encontró correctamente
        console.log('🐾 Chocovainilla Wizard: Mascota encontrada para finalizar:', pet);

        const appointmentData = {
            professional_id: state.professionalId,
            service_id: state.selectedService.id,
            date: state.selectedDate,
            time: state.selectedSlot,
            client_name: state.selectedClientName || '',
            pet_name: pet && pet.name ? pet.name : '',
            pet_species: pet && pet.species ? pet.species : '',
            pet_breed: pet && pet.breed ? pet.breed : '',
            pet_gender: pet && pet.gender ? pet.gender : 'unknown',
            client_email: state.selectedClientEmail || '',
            client_phone: state.selectedClientPhone || '',
            notes: '',
            pet_id: state.selectedPetId,
            client_id: state.selectedClientId,
        };

        // Log detallado de cada campo para debugging
        console.log("🔍 Chocovainilla Wizard: Verificación de campos:");
        console.log("  - pet_species:", appointmentData.pet_species || "❌ VACÍO");
        console.log("  - pet_breed:", appointmentData.pet_breed || "❌ VACÍO");
        console.log("  - pet_gender:", appointmentData.pet_gender || "❌ VACÍO");
        console.log("  - client_email:", appointmentData.client_email || "❌ VACÍO");
        console.log("  - client_phone:", appointmentData.client_phone || "❌ VACÍO");

        console.log("🧾 Chocovainilla Wizard: Enviando datos completos de cita:", appointmentData);

        // Usamos la función de VAApi que ya existe para crear la cita
        VAApi.createAppointment(appointmentData)
            .done(function(response) {
                if(response.success) {
                    alert("¡Cita agendada con éxito!");
                    close();
                    // TODO: Llamar a la función de refresco de la agenda principal.
                    // Por ejemplo: VeterinaliaAgendaModule.reloadDataFromAJAX();
                    // Refrescar la agenda sin recargar toda la página
                    if (window.VA_AgendaModule && typeof window.VA_AgendaModule.reloadDataFromAJAX === 'function') {
                        window.VA_AgendaModule.reloadDataFromAJAX();
                    }
                    dom.confirmBtn.prop('disabled', false).text('Confirmar cita');
                    return;
                    location.reload(); // Solución simple por ahora
                } else {
                    throw new Error(response.data.message || 'No se pudo crear la cita.');
                }
            })
            .fail(function(jqXHR) {
                const msg = jqXHR?.responseJSON?.message || jqXHR?.responseText || 'Error desconocido al crear la cita';
                alert('Error: ' + msg);
                dom.confirmBtn.prop('disabled', false).text('Confirmar cita');
            });
    }
    // <-- FIN DE NUEVAS FUNCIONES: Proyecto Chocovainilla - Paso 1.5/1.6 -->

    // =====================================================================
    // FUNCIONES PARA MANEJO DE PASOS INTEGRADOS (CLIENTES Y MASCOTAS)
    // =====================================================================

    // --- FUNCIONES PARA CLIENTE NUEVO (PASO 1.5) ---
    async function handleInlineClientFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = $('#wizard-create-client-inline');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Creando...');
        
        try {
            const formData = new FormData(e.target);
            // Obtener la lada y el número local
            const phoneCode = formData.get('client-phone-code') || $('#wizard-client-phone-code-inline').val() || '+52';
            const phoneNumber = formData.get('client-phone') || $('#wizard-client-phone-inline').val() || '';

            // Combinar lada con número local
            const fullPhone = phoneNumber ? `${phoneCode} ${phoneNumber}`.trim() : '';

            const clientData = {
                name: formData.get('client-name') || $('#wizard-client-name-inline').val(),
                email: formData.get('client-email') || $('#wizard-client-email-inline').val(),
                phone: fullPhone,
                professional_id: state.professionalId
            };

            console.log('🔄 Creando cliente nuevo (inline):', clientData);

            // Usar el mismo endpoint que patients-module.js
            const response = await $.ajax({
                url: VA_REST.api_url + 'patients/clients',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce);
                },
                data: JSON.stringify(clientData)
            });

            if (response.success) {
                const newClient = response.data;
                console.log(`✅ Cliente creado exitosamente: ${newClient.name} (ID: ${newClient.client_id})`);
                
                // Auto-seleccionar el cliente recién creado
                state.selectedClientId = newClient.client_id;
                state.selectedClientName = newClient.name;
                state.selectedClientEmail = newClient.email;
                state.selectedClientPhone = newClient.phone;
                
                // Mostrar notificación
                showClientCreatedNotification(newClient);
                
                // Actualizar la UI en el paso 1 para mostrar el cliente seleccionado
                dom.clientSearchInput.val(newClient.name);
                dom.searchResultsContainer.html(`
                    <div class="result-item selected" data-client-id="${newClient.client_id}">
                        <div>
                            <strong>${newClient.name}</strong><br>
                            <small>${newClient.email || 'Sin email'}</small>
                        </div>
                        <span class="result-item-tag tag-mine">✅ Cliente Nuevo</span>
                    </div>
                `);
                
                // Como es cliente nuevo, no tendrá mascotas, ir directo al paso 2
                renderPetSelection([]); // Array vacío
                showStep(2);
                
            } else {
                throw new Error(response.message || 'Error al crear el cliente');
            }
            
        } catch (error) {
            console.error('❌ Error creating client:', error);
            showWizardNotification('Error al crear el cliente: ' + error.message, 'error');
        } finally {
            submitBtn.prop('disabled', false).text(originalText);
        }
    }

    function showClientCreatedNotification(client) {
        const hasEmail = client.email && client.email.trim() !== '';
        
        const notification = hasEmail ? 
            `✅ Cliente "${client.name}" creado exitosamente.\n🔔 Se enviará una invitación automática cuando registres su primera mascota.` :
            `✅ Cliente "${client.name}" creado exitosamente.\n⚠️ Sin email: No se podrá enviar invitación automática.`;
        
        showWizardNotification(notification, 'success');
        console.log(`📧 Cliente creado - Email disponible: ${hasEmail ? 'SÍ' : 'NO'}`);
    }

    // --- FUNCIONES PARA MASCOTA NUEVA (PASO 2.5) ---
    async function handleInlinePetFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = $('#wizard-create-pet-inline');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Creando...');
        
        try {
            const formData = new FormData(e.target);
            const petData = {
                client_id: state.selectedClientId,
                name: formData.get('pet-name') || $('#wizard-pet-name-inline').val(),
                species: formData.get('pet-species') || $('#wizard-pet-species-inline').val(),
                breed: formData.get('pet-breed') || $('#wizard-pet-breed-inline').val() || '',
                gender: formData.get('pet-gender') || $('#wizard-pet-gender-inline').val() || 'unknown',
                share_code: formData.get('pet-share-code') || $('#wizard-pet-share-code-inline').val(),
                professional_id: state.professionalId
            };

            console.log('🔄 Creando mascota nueva (inline):', petData);

            // Usar el endpoint que automáticamente envía el email
            const response = await $.ajax({
                url: VA_REST.api_url + 'patients/pets',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', VA_REST.api_nonce);
                },
                data: JSON.stringify(petData)
            });

            if (response.success) {
                const newPet = response.data;
                console.log(`✅ Mascota creada exitosamente: ${newPet.name} (ID: ${newPet.pet_id})`);
                
                // Notificación especial: Email enviado
                showPetCreatedWithEmailNotification(newPet, state.selectedClientEmail);
                
                // Auto-seleccionar la mascota y continuar al paso 3
                state.selectedPetId = newPet.pet_id;

                // Agregar la nueva mascota al estado para que esté disponible en finalizeAppointment
                if (!state.currentClientPets) {
                    state.currentClientPets = [];
                }
                state.currentClientPets.push(newPet);

                const labelClientName = state.selectedClientName || '';
                const labelPetName = newPet.name || `Mascota ${newPet.pet_id}`;
                dom.modal.find('#wizard-selected-pet-name').text(labelClientName ? `${labelPetName} (${labelClientName})` : `${labelPetName}`);

                showStep(3);
                
            } else {
                throw new Error(response.message || 'Error al crear la mascota');
            }
            
        } catch (error) {
            console.error('❌ Error creating pet:', error);
            showWizardNotification('Error al crear la mascota: ' + error.message, 'error');
        } finally {
            submitBtn.prop('disabled', false).text(originalText);
        }
    }

    function showPetCreatedWithEmailNotification(pet, clientEmail) {
        const hasEmail = clientEmail && clientEmail.trim() !== '';
        
        if (hasEmail) {
            const message = `🎉 ¡Mascota "${pet.name}" registrada exitosamente!
            
📧 Se ha enviado automáticamente una invitación a ${clientEmail} con:
• Enlace para registrarse en la plataforma
• Share code: ${pet.share_code}
• Instrucciones para vincular su expediente

El cliente podrá acceder a todo el historial médico de su mascota una vez se registre.`;
            
            showWizardNotification(message, 'success');
        } else {
            showWizardNotification(`⚠️ Mascota "${pet.name}" creada, pero no se pudo enviar invitación (sin email).`, 'warning');
        }
    }

    // --- FUNCIONES AUXILIARES ---
    function generateWizardShareCode(name = 'MASCOTA') {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = '';
        for (let i = 0; i < 4; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return `${name.substring(0, 6).toUpperCase()}-${code}`;
    }

    function regenerateInlineShareCode() {
        const petName = $('#wizard-pet-name-inline').val() || 'MASCOTA';
        $('#wizard-pet-share-code-inline').val(generateWizardShareCode(petName));
        console.log('🔄 Share code regenerado (inline)');
    }

    function autoGenerateInlineShareCode(petName) {
        if (petName && petName.trim() !== '') {
            $('#wizard-pet-share-code-inline').val(generateWizardShareCode(petName.trim()));
        }
    }

    function showWizardNotification(message, type = 'info') {
        // Crear un elemento temporal para mostrar la notificación
        const notification = $(`
            <div class="wizard-notification ${type}" style="white-space: pre-line;">
                ${message}
            </div>
        `);
        
        dom.modal.append(notification);
        
        // Auto-remover después de 8 segundos para mensajes largos
        setTimeout(() => {
            notification.fadeOut(500, () => notification.remove());
        }, 8000);
        
        console.log(`📢 Notificación (${type}): ${message}`);
    }

    // =====================================================================
    // FIN DE FUNCIONES PARA MANEJO DE CLIENTES Y MASCOTAS NUEVOS
    // =====================================================================

    function init() {
        cacheDOM();
        if (!dom.modal || dom.modal.length === 0) {
            console.warn("⚠️ Chocovainilla Wizard: Modal no encontrado aún. Difiriendo init.");
            return;
        }
        bindEvents();
        initialized = true;
        console.log("✅ Chocovainilla Wizard: Módulo inicializado y listo.");
    }

    return { init: init, open: open, close: close };

})(jQuery);

// Base de datos simulada para desarrollo
const simDB = {
    clients: [
        { client_id: 1, name: 'Ana García Martínez', email: 'ana.garcia@ejemplo.com' },
        { client_id: 2, name: 'Carlos López Ruiz', email: 'carlos.lopez@ejemplo.com' },
        { client_id: 3, name: 'María Fernández Silva', email: 'maria.fernandez@ejemplo.com' }
    ],
    pets: [
        { pet_id: 1, client_id: 1, name: 'Luna', species: 'dog', share_code: 'LUNA-G7K4' },
        { pet_id: 2, client_id: 1, name: 'Max', species: 'cat', share_code: 'MAX-H2L9' },
        { pet_id: 3, client_id: 2, name: 'Rocky', species: 'dog', share_code: 'ROCKY-A1B3' },
        { pet_id: 4, client_id: 3, name: 'Mimi', species: 'cat', share_code: 'MIMI-X9Y8' }
    ],
    pet_access: [
        { access_id: 1, pet_id: 1, professional_id: 1, access_level: 'full' },
        { access_id: 2, pet_id: 2, professional_id: 1, access_level: 'full' }
    ]
};

jQuery(document).ready(function() {
    if (document.getElementById('agenda-booking-wizard-modal')) {
        AgendaWizard.init();
    }
});

// Re-inicializa el wizard cuando el modal es inyectado dinámicamente
(function observeWizardMount(){
    try {
        const target = document.body;
        if (!target || typeof MutationObserver === 'undefined') return;
        const obs = new MutationObserver((mutations)=>{
            for (const m of mutations){
                for (const node of m.addedNodes){
                    if (node && node.nodeType === 1 && node.id === 'agenda-booking-wizard-modal'){
                        if (typeof AgendaWizard !== 'undefined' && AgendaWizard.init){
                            AgendaWizard.init();
                            console.log('�o. WIZARD: Modal detectado en DOM. Inicializado nuevamente.');
                        }
                    }
                }
            }
        });
        obs.observe(target, { childList: true, subtree: true });
    } catch (e) {
        // noop
    }
})();
