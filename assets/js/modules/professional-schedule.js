// veterinalia-appointment/assets/js/modules/professional-schedule.js
/**
 * Módulo para manejar el formulario de configuración de horarios del profesional.
 * REFACTORIZADO para usar VAApi y modales.
 */
var VA_Professional_Schedule = (function($) {

    var $professionalScheduleForm,
        $scheduleEntries,
        $noScheduleMessage,
        $scheduleFeedback,
        professionalId,
        // Nuevas variables para los modales
        $scheduleActionSheetModal,
        $scheduleTemplateImportModalContainer,
        $showAddScheduleModalBtn,
        $addManualScheduleBtn,
        $importTemplateScheduleBtn,
        $cancelScheduleActionBtn,
        $modalBackdrops,
        // Nuevas variables para la nueva UI
        $moduleHeader,
        $backBtn,
        $addBtn,
        $moduleTitle,
        $daysView,
        $slotsView,
        $daysList,
        currentView,
        selectedDayId,
        selectedDayName,
        existingScheduleData,
        // Variables para autoguardado
        autoSaveTimeout,
        autoSaveDelay = 2000, // 2 segundos de delay
        isAutoSaving = false;

    function init() {
        // Buscar contenedor principal (ahora solo va-schedule-content)
        $professionalScheduleForm = $('.va-schedule-content');
        if (!$professionalScheduleForm.length) {
            return; // No estamos en la página correcta, no hacer nada.
        }

        // Detectar si usar nueva UI
        var useNewUI = $professionalScheduleForm.hasClass('va-schedule-new-ui');

        // Inicialización de variables del DOM
        $scheduleEntries = $('#va-schedule-entries');
        $noScheduleMessage = $('#va-no-schedule-message');
        $scheduleFeedback = $('#va-schedule-feedback');
        professionalId = $professionalScheduleForm.data('professional-id');

        // Inicialización de modales (solo si existen)
        $scheduleActionSheetModal = $('#schedule-action-sheet-modal');
        $scheduleTemplateImportModalContainer = $('#schedule-template-import-modal-container');
        $showAddScheduleModalBtn = $('#show-add-schedule-modal-btn');
        $addManualScheduleBtn = $('#add-manual-schedule-btn');
        $importTemplateScheduleBtn = $('#import-template-schedule-btn');
        $cancelScheduleActionBtn = $('#cancel-schedule-action-btn');
        $modalBackdrops = $('.va-modal-backdrop');

        if (useNewUI) {
            // Inicialización de elementos de nueva UI usando el header existente
            $backBtn = $('#va-schedule-back-btn');
            $addBtn = $('#va-schedule-add-btn');
            $moduleTitle = $('#va-schedule-title');
            $daysView = $('#va-days-view');
            $slotsView = $('#va-slots-view');
            $daysList = $('.va-days-list');

            // Estado inicial
            currentView = 'days';
            selectedDayId = null;
            selectedDayName = null;

            // Registrar eventos de nueva UI
            if ($backBtn.length) $backBtn.on('click', handleBackNavigation);
            if ($addBtn.length) $addBtn.on('click', handleAddButtonClick);
            if ($daysList.length) $daysList.on('click', '.va-day-item', handleDaySelection);

            // Registrar eventos de autoguardado
            setupAutoSave();
        }

        // Cargar horarios existentes
        loadInitialSchedule();

        // Registrar eventos comunes
        $('#va-professional-listing').on('change', handleListingChange);
        if ($scheduleEntries.length) {
            $scheduleEntries.on('click', '.va-remove-schedule-block', handleRemoveBlock);
        }
        $('#va-schedule-form').on('submit', handleFormSubmit);

        // Eventos para los modales (solo si existen)
        if ($addBtn.length) $addBtn.on('click', showActionSheetModal);
        if ($addManualScheduleBtn.length) {
            $addManualScheduleBtn.on('click', function() {
                addScheduleBlock();
                hideModals();
            });
        }
        if ($importTemplateScheduleBtn.length) {
            $importTemplateScheduleBtn.on('click', function() {
                hideModals(); // Ocultar el action sheet primero
                showTemplateImportModal();
            });
        }
        if ($cancelScheduleActionBtn.length) $cancelScheduleActionBtn.on('click', hideModals);

        // Eventos para cerrar modales
        $(document).on('click', '.modal-overlay', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                hideModals();
            }
        });
        $(document).on('click', '.modal-close-btn', hideModals);

        $(document).on('click', '#schedule-template-import-modal-container .va-template-list-item', handleTemplateSelection);

        console.log("Módulo Professional Schedule cargado" + (useNewUI ? " (Nueva UI)" : " (UI Clásica)") + ".");
    }

    // Función para mostrar el modal de action sheet
    function showActionSheetModal() {
        $scheduleActionSheetModal.addClass('visible');
    }

    // Función para ocultar todos los modales
    function hideModals() {
        $('.modal-overlay.visible').addClass('is-closing');
        setTimeout(function() {
            $('.modal-overlay.is-closing').removeClass('visible', 'is-closing');
            $scheduleTemplateImportModalContainer.find('.modal-content').empty(); // Limpiar contenido del modal de importación
        }, 300); // Coincidir con la duración de la animación CSS
    }

    // Función para mostrar el modal de importación de plantillas
    function showTemplateImportModal() {
        $scheduleTemplateImportModalContainer.addClass('visible');
        loadScheduleTemplates();
    }

    function loadScheduleTemplates() {
        var $modalContent = $scheduleTemplateImportModalContainer.find('.modal-content');
        $modalContent.empty().append(`
            <div class="modal-header">
                <h3>Importar desde Plantilla</h3>
                <button class="modal-close-btn" id="schedule-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p>Cargando plantillas...</p>
            </div>
        `);
        
        VAApi.request('va_get_schedule_templates', {})
            .done(function(response) {
                $modalContent.empty(); // Limpiar el mensaje de carga
                $modalContent.append(`
                    <div class="modal-header">
                        <h3>Importar desde Plantilla</h3>
                        <button class="modal-close-btn" id="schedule-modal-close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                `);

                if (response.success && response.data.length > 0) {
                    var $templatesList = $('<div class="va-template-list"></div>');
                    $.each(response.data, function(index, template) {
                        console.log('Template data:', template);
                        console.log('Bloques de la plantilla:', template.bloques); // Añadido para depuración de bloques
                        var $templateItem = $(
                            '<div class="va-template-list-item" data-template=\'' + JSON.stringify(template.bloques) + '\'>' +
                            '<h4>' + template.nombre + '</h4>' +
                            '<p>' + (template.descripcion ? template.descripcion : 'Sin descripción') + '</p>' +
                            '</div>'
                        );
                        $templatesList.append($templateItem);
                    });
                    $modalContent.find('.modal-body').append($templatesList);
                } else {
                    $modalContent.find('.modal-body').append('<p>No hay plantillas disponibles.</p>');
                }
                
                $modalContent.append('</div>');
            })
            .fail(function() {
                $modalContent.empty().append(`
                    <div class="modal-header">
                        <h3>Importar desde Plantilla</h3>
                        <button class="modal-close-btn" id="schedule-modal-close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Error al cargar las plantillas.</p>
                    </div>
                `);
            });
    }

    function handleTemplateSelection() {
        var bloques = $(this).data('template');
        if (bloques && bloques.length > 0) {
            var useNewUI = $professionalScheduleForm.hasClass('va-schedule-new-ui');

            if (useNewUI && currentView === 'days') {
                // Nueva UI: Aplicar plantilla a todos los días
                if (confirm('¿Estás seguro de que quieres importar esta plantilla? Esto aplicará franjas horarias a todos los días configurados en la plantilla.')) {
                    // Simular guardar cada bloque de la plantilla
                    var templateData = [];
                    $.each(bloques, function(index, bloque) {
                        templateData.push({
                            dia_semana_id: bloque.dia_semana_id,
                            start_time: bloque.start_time,
                            end_time: bloque.end_time,
                            slot_duration: bloque.slot_duration
                        });
                    });

                    VAApi.saveSchedule(professionalId, templateData, null)
                        .done(function(response) {
                            if (response.success) {
                                $scheduleFeedback.text('Plantilla importada correctamente').css('color', 'green').show();
                                // Actualizar datos y vista
                                fetchAndUpdateScheduleData().done(function() {
                                    updateDaysStatus();
                                });
                            } else {
                                $scheduleFeedback.text(response.data).css('color', 'red').show();
                            }
                        });
                    hideModals();
                }
            } else {
                // UI clásica: comportamiento original
                if (confirm('¿Estás seguro de que quieres reemplazar tu horario actual con esta plantilla? Esto eliminará tus entradas actuales.')) {
                    $scheduleEntries.empty();
                    $.each(bloques, function(index, bloque) {
                        addScheduleBlock(bloque.dia_semana_id, bloque.start_time, bloque.end_time, bloque.slot_duration);
                    });
                    $noScheduleMessage.hide();
                    hideModals();
                }
            }
        }
    }

    function handleListingChange() {
        professionalId = $(this).val();
        $professionalScheduleForm.data('professional-id', professionalId);
        console.log("Listado seleccionado cambiado a ID:", professionalId);
        fetchAndRenderSchedule();
    }

    function addScheduleBlock(dayId = '', startTime = '09:00', endTime = '17:00', slotDuration = '30') {
        console.log('addScheduleBlock - dayId:', dayId); // Añadido para depuración
        if ($noScheduleMessage.is(':visible')) {
            $noScheduleMessage.hide();
        }

        var useNewUI = $professionalScheduleForm.hasClass('va-schedule-new-ui');
        var showDaySelector = true;
        var preselectedDay = dayId;

        // En nueva UI, si estamos en vista de slots, no mostrar selector de día
        if (useNewUI && currentView === 'slots' && selectedDayId) {
            showDaySelector = false;
            preselectedDay = selectedDayId;
        }

        var blockHtml = '';

        if (showDaySelector) {
            var daysOfWeek = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };
            var options = '<option value="">Selecciona un día</option>';
            $.each(daysOfWeek, function(id, name) {
                options += '<option value="' + id + '">' + name + '</option>';
            });
            blockHtml = '<div class="va-schedule-block">' +
                '<label>Día:</label>' +
                '<select name="dia_semana_id[]" class="va-day-of-week" required>' + options + '</select>';
        } else {
            blockHtml = '<div class="va-schedule-block">' +
                '<input type="hidden" name="dia_semana_id[]" value="' + preselectedDay + '">';
        }

        blockHtml += '<label>Inicio:</label>' +
            '<input type="time" name="start_time[]" class="va-start-time" value="' + startTime + '" required>' +
            '<label>Fin:</label>' +
            '<input type="time" name="end_time[]" class="va-end-time" value="' + endTime + '" required>' +
            '<label>Duración del slot (min):</label>' +
            '<input type="number" name="slot_duration[]" class="va-slot-duration" value="' + slotDuration + '" min="15" required>' +
            '<button type="button" class="va-remove-schedule-block">Eliminar</button>' +
            '</div>';

        var $newBlock = $(blockHtml);

        if (showDaySelector) {
            $newBlock.find('.va-day-of-week').val(preselectedDay);
        }

        $scheduleEntries.append($newBlock);
    }

    function renderScheduleForm(scheduleData) {
        $scheduleEntries.empty();
        if (scheduleData && scheduleData.length > 0) {
            $noScheduleMessage.hide();
            $.each(scheduleData, function(index, entry) {
                // *** CORRECCIÓN CLAVE ***
                addScheduleBlock(entry.dia_semana_id, entry.start_time, entry.end_time, entry.slot_duration);
            });
        } else {
            $noScheduleMessage.show();
        }
    }

    // ===== NUEVAS FUNCIONES PARA LA NUEVA UI =====

    function handleBackNavigation(e) {
        if (currentView === 'slots') {
            // Si estamos en vista de franjas, prevenir comportamiento normal y volver a días
            e.preventDefault();
            navigateToDaysView();
            return false;
        }
        // Si estamos en vista de días, permitir comportamiento normal (volver al dashboard)
        // El enlace tiene href="?page=veterinalia-dashboard" que llevará al dashboard principal
    }

    function handleAddButtonClick() {
        if (currentView === 'days') {
            // En vista de días: importar plantilla para todos los días
            showActionSheetModal();
        } else if (currentView === 'slots') {
            // En vista de franjas: añadir franja al día seleccionado
            addScheduleBlock(selectedDayId);
        }
    }

    function handleDaySelection() {
        var $dayItem = $(this);
        selectedDayId = $dayItem.data('day-id');
        selectedDayName = $dayItem.data('day-name');

        // Filtrar horarios del día seleccionado
        var daySchedule = filterScheduleByDay(selectedDayId);

        // Navegar a vista de franjas
        navigateToSlotsView(selectedDayName, daySchedule);
    }

    function navigateToDaysView() {
        currentView = 'days';
        selectedDayId = null;
        selectedDayName = null;

        // Actualizar UI
        $moduleTitle.text('Horarios del Profesional');
        // Restaurar el texto original del botón de volver
        $backBtn.find('span').text('Volver');
        // Restaurar el título del botón para mejor UX
        $backBtn.attr('title', 'Volver al dashboard principal');
        $daysView.show();
        $slotsView.hide();

        // Limpiar formulario de franjas
        $scheduleEntries.empty();
        $noScheduleMessage.hide();

        // Actualizar estado de días
        updateDaysStatus();
    }

    function navigateToSlotsView(dayName, daySchedule) {
        currentView = 'slots';

        // Actualizar UI
        $moduleTitle.text('Franjas de ' + dayName);
        // Cambiar el texto del botón de volver para indicar que vuelve a días
        $backBtn.find('span').text('Volver a Días');
        // Cambiar el título del botón para mejor UX
        $backBtn.attr('title', 'Volver a la vista de días');
        $daysView.hide();
        $slotsView.show();

        // Cargar horarios del día
        loadDaySchedule(daySchedule);
    }

    function filterScheduleByDay(dayId) {
        if (!existingScheduleData || !Array.isArray(existingScheduleData)) {
            return [];
        }

        return existingScheduleData.filter(function(schedule) {
            return parseInt(schedule.dia_semana_id) === parseInt(dayId);
        });
    }

    function loadDaySchedule(daySchedule) {
        $scheduleEntries.empty();

        if (daySchedule && daySchedule.length > 0) {
            $noScheduleMessage.hide();
            $.each(daySchedule, function(index, entry) {
                addScheduleBlock(entry.dia_semana_id, entry.start_time, entry.end_time, entry.slot_duration);
            });
        } else {
            $noScheduleMessage.show();
        }
    }

    function updateDaysStatus() {
        $('.va-day-item').each(function() {
            var $dayItem = $(this);
            var dayId = $dayItem.data('day-id');
            var daySchedule = filterScheduleByDay(dayId);

            var $status = $dayItem.find('.va-day-status');
            var $preview = $dayItem.find('.va-day-slots-preview');

            if (daySchedule.length > 0) {
                $status.text(daySchedule.length + ' franja' + (daySchedule.length > 1 ? 's' : ''));

                // Mostrar preview de franjas
                var previewText = daySchedule.map(function(slot) {
                    return slot.start_time + ' - ' + slot.end_time;
                }).join(', ');

                $preview.text(previewText).show();
                $dayItem.addClass('has-schedule');
            } else {
                $status.text('Sin configurar');
                $preview.empty().hide();
                $dayItem.removeClass('has-schedule');
            }
        });
    }

    // ===== FUNCIONES EXISTENTES =====

    function loadInitialSchedule() {
        try {
            existingScheduleData = $professionalScheduleForm.data('existing-schedule');
            if (typeof existingScheduleData === 'string') {
                existingScheduleData = JSON.parse(existingScheduleData);
            }

            var useNewUI = $professionalScheduleForm.hasClass('va-schedule-new-ui');

            if (useNewUI) {
                // Para nueva UI: actualizar estado de días
                updateDaysStatus();
            } else {
                // Para UI clásica: comportamiento original
                if (existingScheduleData && existingScheduleData.length > 0) {
                    renderScheduleForm(existingScheduleData);
                } else {
                    $noScheduleMessage.show();
                }
            }
        } catch (e) {
            console.error("Error parsing existing schedule data:", e);
            $noScheduleMessage.show();
        }
    }

    function handleRemoveBlock() {
        $(this).closest('.va-schedule-block').remove();
        if ($scheduleEntries.children('.va-schedule-block').length === 0) {
            $noScheduleMessage.show();
        }
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        var scheduleData = [];
        var isValidForm = true;
        $scheduleFeedback.empty().hide();

        var useNewUI = $professionalScheduleForm.hasClass('va-schedule-new-ui');

        // Lógica diferente según la vista actual (igual que performAutoSave)
        if (useNewUI && currentView === 'slots' && selectedDayId) {
            // Estamos en vista de slots: combinar franjas del día actual con franjas de otros días
            var currentDaySlots = [];
            var otherDaysSlots = [];

            // Recopilar franjas del día actual (visibles en el DOM)
            $('.va-schedule-block').each(function() {
                var $block = $(this);
                var day = selectedDayId; // Siempre usar el día seleccionado en vista slots
                var start = $block.find('.va-start-time').val();
                var end = $block.find('.va-end-time').val();
                var duration = $block.find('.va-slot-duration').val();

                if (!start || !end || !duration || start >= end) {
                    isValidForm = false;
                    return false;
                }

                currentDaySlots.push({
                    dia_semana_id: day,
                    start_time: start,
                    end_time: end,
                    slot_duration: duration
                });
            });

            // Si los datos del día actual son válidos, combinar con franjas de otros días
            if (isValidForm && existingScheduleData) {
                // Filtrar franjas de otros días (excluyendo el día actual)
                otherDaysSlots = existingScheduleData.filter(function(slot) {
                    return parseInt(slot.dia_semana_id) !== parseInt(selectedDayId);
                });

                // Combinar: otros días + día actual actualizado
                scheduleData = otherDaysSlots.concat(currentDaySlots);
            } else {
                scheduleData = currentDaySlots;
            }
        } else {
            // Vista de días o UI clásica: recopilar todos los bloques visibles
            $('.va-schedule-block').each(function() {
                var $block = $(this);
                var day = $block.find('.va-day-of-week').val();
                var start = $block.find('.va-start-time').val();
                var end = $block.find('.va-end-time').val();
                var duration = $block.find('.va-slot-duration').val();

                if (!day || !start || !end || !duration || start >= end) {
                    isValidForm = false;
                    return false;
                }

                scheduleData.push({
                    dia_semana_id: day,
                    start_time: start,
                    end_time: end,
                    slot_duration: duration
                });
            });
        }

        if (!isValidForm) {
            $scheduleFeedback.text('Por favor, corrige los errores en los bloques de horario.').css('color', 'red').show();
            return;
        }

        VAApi.saveSchedule(professionalId, scheduleData, $('#va-save-schedule-btn'))
            .done(function(response) {
                if (response.success) {
                    // Manejar el nuevo formato de respuesta
                    var message = typeof response.data === 'object' && response.data.message ? response.data.message : response.data;
                    var scheduleData = typeof response.data === 'object' && response.data.data ? response.data.data : null;
                    
                    $scheduleFeedback.text(message).css('color', 'green').show();

                    // Actualizar datos locales con la respuesta del servidor
                    if (scheduleData && Array.isArray(scheduleData)) {
                        existingScheduleData = scheduleData;
                    }

                    if (useNewUI) {
                        // Si ya tenemos los datos actualizados, navegar directamente; si no, hacer fetch
                        if (scheduleData && Array.isArray(scheduleData)) {
                            navigateToDaysView();
                        } else {
                            fetchAndUpdateScheduleData().done(() => navigateToDaysView());
                        }
                    } else {
                        // Comportamiento original
                        fetchAndRenderSchedule();
                    }

                    // Mostrar indicador de guardado exitoso (solo si no es autoguardado)
                    if (!isAutoSaving) {
                        showAutoSaveIndicator('saved');
                    }
                } else {
                    $scheduleFeedback.text(response.data).css('color', 'red').show();
                    if (!isAutoSaving) {
                        showAutoSaveIndicator('error', response.data);
                    }
                }
            });
    }

    function fetchAndUpdateScheduleData() {
        var deferred = $.Deferred();

        VAApi.getSchedule(professionalId)
            .done(function(response) {
                if (response.success) {
                    existingScheduleData = response.data;
                    deferred.resolve();
                } else {
                    $scheduleFeedback.text("Error al cargar el horario.").css('color', 'red').show();
                    deferred.reject();
                }
            })
            .fail(function() {
                $scheduleFeedback.text("Error al cargar el horario.").css('color', 'red').show();
                deferred.reject();
            });

        return deferred.promise();
    }

    // ===== FUNCIONES DE AUTOGUARDADO =====

    function setupAutoSave() {
        // Escuchar cambios en inputs de tiempo y duración
        $(document).on('input change', '.va-schedule-block input[type="time"], .va-schedule-block input[type="number"]', handleInputChange);

        // Escuchar cambios en selectores de día (solo en vista de slots)
        $(document).on('change', '.va-schedule-block select', handleInputChange);
    }

    function handleInputChange() {
        if (isAutoSaving) return; // Evitar múltiples autoguardados simultáneos

        // Limpiar timeout anterior
        clearTimeout(autoSaveTimeout);

        // Mostrar indicador de autoguardado pendiente
        showAutoSaveIndicator('pending');

        // Programar autoguardado con delay
        autoSaveTimeout = setTimeout(function() {
            performAutoSave();
        }, autoSaveDelay);
    }

    function performAutoSave() {
        if (isAutoSaving) return;

        var scheduleData = [];
        var isValidForm = true;

        // Lógica diferente según la vista actual
        if ($professionalScheduleForm.hasClass('va-schedule-new-ui') && currentView === 'slots' && selectedDayId) {
            // Estamos en vista de slots: combinar franjas del día actual con franjas de otros días
            var currentDaySlots = [];
            var otherDaysSlots = [];

            // Recopilar franjas del día actual (visibles en el DOM)
            $('.va-schedule-block').each(function() {
                var $block = $(this);
                var day = selectedDayId; // Siempre usar el día seleccionado en vista slots
                var start = $block.find('.va-start-time').val();
                var end = $block.find('.va-end-time').val();
                var duration = $block.find('.va-slot-duration').val();

                if (!start || !end || !duration || start >= end) {
                    isValidForm = false;
                    return false;
                }

                currentDaySlots.push({
                    dia_semana_id: day,
                    start_time: start,
                    end_time: end,
                    slot_duration: duration
                });
            });

            // Si los datos del día actual son válidos, combinar con franjas de otros días
            if (isValidForm && existingScheduleData) {
                // Filtrar franjas de otros días (excluyendo el día actual)
                otherDaysSlots = existingScheduleData.filter(function(slot) {
                    return parseInt(slot.dia_semana_id) !== parseInt(selectedDayId);
                });

                // Combinar: otros días + día actual actualizado
                scheduleData = otherDaysSlots.concat(currentDaySlots);
            } else {
                scheduleData = currentDaySlots;
            }
        } else {
            // Vista de días o UI clásica: recopilar todos los bloques visibles
            $('.va-schedule-block').each(function() {
                var $block = $(this);
                var day = $block.find('.va-day-of-week').val() || $block.find('input[name*="dia_semana_id"]').val();
                var start = $block.find('.va-start-time').val();
                var end = $block.find('.va-end-time').val();
                var duration = $block.find('.va-slot-duration').val();

                if (!day || !start || !end || !duration || start >= end) {
                    isValidForm = false;
                    return false;
                }

                scheduleData.push({
                    dia_semana_id: day,
                    start_time: start,
                    end_time: end,
                    slot_duration: duration
                });
            });
        }

        if (!isValidForm || scheduleData.length === 0) {
            showAutoSaveIndicator('error', 'Datos inválidos');
            return;
        }

        isAutoSaving = true;
        showAutoSaveIndicator('saving');

        // Realizar el guardado automático
        VAApi.saveSchedule(professionalId, scheduleData, null)
            .done(function(response) {
                if (response.success) {
                    showAutoSaveIndicator('saved');

                    // Actualizar datos locales con la respuesta del servidor
                    var scheduleData = typeof response.data === 'object' && response.data.data ? response.data.data : response.data;
                    if (scheduleData && Array.isArray(scheduleData)) {
                        existingScheduleData = scheduleData;
                    }

                    // Actualizar la vista de días si estamos en nueva UI
                    if ($professionalScheduleForm.hasClass('va-schedule-new-ui')) {
                        updateDaysStatus();
                    }
                } else {
                    showAutoSaveIndicator('error', response.data);
                }
            })
            .fail(function() {
                showAutoSaveIndicator('error', 'Error de conexión');
            })
            .always(function() {
                isAutoSaving = false;
            });
    }

    function showAutoSaveIndicator(status, message) {
        // Remover indicadores anteriores
        $('.va-autosave-indicator').remove();

        var indicatorHtml = '';
        var indicatorClass = 'va-autosave-indicator';

        switch (status) {
            case 'pending':
                indicatorHtml = '<div class="' + indicatorClass + ' pending"><span>Guardando...</span></div>';
                break;
            case 'saving':
                indicatorHtml = '<div class="' + indicatorClass + ' saving"><span>Guardando...</span></div>';
                break;
            case 'saved':
                indicatorHtml = '<div class="' + indicatorClass + ' saved"><span>✓ Guardado</span></div>';
                // Auto-ocultar después de 2 segundos
                setTimeout(function() {
                    $('.va-autosave-indicator.saved').fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
                break;
            case 'error':
                indicatorHtml = '<div class="' + indicatorClass + ' error"><span>✗ ' + (message || 'Error al guardar') + '</span></div>';
                // Auto-ocultar después de 4 segundos para errores
                setTimeout(function() {
                    $('.va-autosave-indicator.error').fadeOut(function() {
                        $(this).remove();
                    });
                }, 4000);
                break;
        }

        if (indicatorHtml) {
            // Agregar indicador al header del módulo
            $('.module-header').append(indicatorHtml);
        }
    }

    function fetchAndRenderSchedule() {
        VAApi.getSchedule(professionalId)
            .done(function(response) {
                if (response.success) {
                    renderScheduleForm(response.data);
                } else {
                    $scheduleFeedback.text("Error al cargar el horario.").css('color', 'red').show();
                }
            });
    }

    return {
        init: init
    };

})(jQuery);