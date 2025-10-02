// assets/js/modules/client-booking-wizard.js (V4 - FINAL)

(function($) {
    const BookingWizard = {
        state: {
            currentStep: 1,
            totalSteps: 4,
            professionalId: null,
            selectedService: { id: null, name: null, duration: null },
            selectedPet: { id: null, name: null, species: null, breed: null },
            selectedDate: null,
            selectedSlot: null,
            currentCalendarDate: new Date(),
            clientData: { name: null, petName: null, petSpecies: null, petBreed: null, petGender: null, email: null, phone: null, notes: null }
        },

        init: function() {
            this.cacheDOMElements();
            if (!this.dom.wizardContainer.length) return;
            this.state.professionalId = this.dom.wizardContainer.data('professional-id');
            this.bindEvents();
            this.updateView();
            console.log('Booking Wizard V4 (Final) Initialized.');
        },

        cacheDOMElements: function() {
            this.dom = {
                wizardContainer: $('.booking-wizard'),
                steps: $('.step'),
                stepContents: $('.booking-step'),
                nextBtn: $('#va-next-step-btn'),
                prevBtn: $('#va-prev-step-btn'),
                confirmBtn: $('#va-confirm-booking-btn'),
                categoryTabs: $('.category-tab'),
                serviceLists: $('.service-list'),
                serviceSelectBtns: $('.va-select-service-btn'),
                calendarHeader: $('#va-calendar-header'),
                calendarGrid: $('#va-calendar-grid'),
                slotsContainer: $('#va-slots-container'),
                clientForm: $('#va-client-details-form'),
                bookingSummary: $('#va-booking-summary'),
            };
        },

        bindEvents: function() {
            this.dom.nextBtn.on('click', () => this.goToNextStep());
            this.dom.prevBtn.on('click', () => this.goToPrevStep());
            this.dom.confirmBtn.on('click', () => this.createAppointment());

            this.dom.categoryTabs.on('click', (e) => {
                const tab = $(e.currentTarget);
                this.dom.categoryTabs.removeClass('active');
                tab.addClass('active');
                this.dom.serviceLists.removeClass('active');
                $('#services-' + tab.data('category')).addClass('active');
            });

            // --- CORRECCIÓN CLAVE ---
            this.dom.serviceSelectBtns.on('click', (e) => {
                const serviceItem = $(e.currentTarget).closest('.service-item');
                this.state.selectedService = { id: serviceItem.data('service-id'), name: serviceItem.data('name'), duration: serviceItem.data('duration') };
                $('#va-selected-service-name-step2').text(this.state.selectedService.name);
                this.goToNextStep(); // <-- ESTA LÍNEA ES LA SOLUCIÓN
            });

            this.dom.slotsContainer.on('click', '.time-slot', (e) => {
                const slot = $(e.currentTarget);
                this.state.selectedSlot = slot.data('time');
                this.dom.slotsContainer.find('.time-slot').removeClass('selected');
                slot.addClass('selected');
                this.dom.nextBtn.removeClass('hidden').prop('disabled', false);
            });

            this.dom.calendarHeader.on('click', '#prev-month-btn', () => this.changeMonth(-1));
            this.dom.calendarHeader.on('click', '#next-month-btn', () => this.changeMonth(1));
            
            this.dom.calendarGrid.on('click', '.calendar-day:not(.disabled, .not-in-month)', (e) => {
                 const dayEl = $(e.currentTarget);
                 this.dom.calendarGrid.find('.calendar-day').removeClass('selected');
                 dayEl.addClass('selected');
                 this.state.selectedDate = dayEl.data('date');
                 this.fetchAndRenderSlots(this.state.selectedDate);
            });

            // Evento para selección de mascota (para clientes con mascotas vinculadas)
            $('.pet-selector-item').on('click', (e) => {
                const petItem = $(e.currentTarget);
                $('.pet-selector-item').removeClass('selected');
                petItem.addClass('selected');

                // Guardar datos de la mascota seleccionada
                this.state.selectedPet = {
                    id: petItem.data('pet-id'),
                    name: petItem.data('pet-name'),
                    species: petItem.data('pet-species'),
                    breed: petItem.data('pet-breed')
                };

                // Llenar automáticamente los campos del formulario con los datos de la mascota
                $('#va-pet-name').val(this.state.selectedPet.name || '');
                $('#va-pet-species').val(this.state.selectedPet.species ?
                    this.state.selectedPet.species.charAt(0).toUpperCase() + this.state.selectedPet.species.slice(1) : '');
                $('#va-pet-breed').val(this.state.selectedPet.breed || '');
                // Asegurar que el género de la mascota también se propaga
                if ( typeof this.state.selectedPet.gender !== 'undefined' ) {
                    $('#va-pet-gender').val(this.state.selectedPet.gender);
                } else if ( typeof this.state.selectedPet.species !== 'undefined' ) {
                    // Si no existe gender, dejar 'unknown' por defecto
                    $('#va-pet-gender').val('unknown');
                }
            });
        },

        goToNextStep: function() {
            if (this.state.currentStep < this.state.totalSteps) {
                if (this.state.currentStep === 3) {
                    if (!this.validateClientForm()) {
                        alert('Por favor, completa todos los campos obligatorios.');
                        return;
                    }
                    this.collectClientData();
                }
                this.state.currentStep++;
                this.updateView();
            }
        },

        validateClientForm: function() {
            const form = $('#va-client-details-form');
            const isClientWithPets = form.hasClass('client-has-pets');

            // Campos siempre requeridos
            const clientName = $('#va-client-name').val().trim();
            const clientEmail = $('#va-client-email').val().trim();

            if (!clientName) {
                alert('Por favor, ingresa tu nombre.');
                return false;
            }

            if (!clientEmail) {
                alert('Por favor, ingresa tu correo electrónico.');
                return false;
            }

            // Si el cliente tiene mascotas vinculadas, validar que haya seleccionado una
            if (isClientWithPets) {
                if (!this.state.selectedPet.id) {
                    alert('Por favor, selecciona una mascota.');
                    return false;
                }
            } else {
                // Si no tiene mascotas vinculadas, validar campos manuales de mascota
                const petName = $('#va-pet-name').val().trim();
                const petSpecies = $('#va-pet-species').val();

                if (!petName) {
                    alert('Por favor, ingresa el nombre de tu mascota.');
                    return false;
                }

                if (!petSpecies) {
                    alert('Por favor, selecciona el tipo de animal.');
                    return false;
                }
            }

            return true;
        },

        goToPrevStep: function() {
            if (this.state.currentStep > 1) {
                this.state.currentStep--;
                this.updateView();
            }
        },

        updateView: function() {
            const currentStep = this.state.currentStep;
            this.dom.steps.each((index, el) => {
                const stepEl = $(el);
                const stepNum = stepEl.data('step');
                stepEl.removeClass('active completed');
                if (stepNum < currentStep) stepEl.addClass('completed');
                else if (stepNum === currentStep) stepEl.addClass('active');
            });
            this.dom.stepContents.removeClass('active');
            $('#step-' + currentStep).addClass('active');

            // Lógica de visibilidad de botones corregida
            this.dom.prevBtn.toggleClass('hidden', currentStep === 1);
            this.dom.nextBtn.toggleClass('hidden', currentStep !== 3); // Solo visible en el paso 3
            this.dom.confirmBtn.toggleClass('hidden', currentStep !== this.state.totalSteps);

            if (currentStep === 2) {
                this.renderCalendar();
            } else if (currentStep === 4) {
                this.renderSummary();
            }
        },
        
        changeMonth: function(direction) {
            this.state.currentCalendarDate.setMonth(this.state.currentCalendarDate.getMonth() + direction);
            this.renderCalendar();
        },

        renderCalendar: function() {
            this.dom.slotsContainer.html('<p class="initial-message">Selecciona una fecha para ver los horarios.</p>');
            const date = this.state.currentCalendarDate;
            const year = date.getFullYear();
            const month = date.getMonth();
            const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
            this.dom.calendarHeader.html(`<button id="prev-month-btn" aria-label="Mes anterior"><i class="fas fa-chevron-left"></i></button><span id="va-calendar-month-year">${monthNames[month]} ${year}</span><button id="next-month-btn" aria-label="Mes siguiente"><i class="fas fa-chevron-right"></i></button>`);
            let gridHtml = '<div class="calendar-day-name">D</div><div class="calendar-day-name">L</div><div class="calendar-day-name">M</div><div class="calendar-day-name">M</div><div class="calendar-day-name">J</div><div class="calendar-day-name">V</div><div class="calendar-day-name">S</div>';
            const firstDayIndex = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            for (let i = 0; i < firstDayIndex; i++) gridHtml += '<div class="calendar-day not-in-month"></div>';
            for (let i = 1; i <= daysInMonth; i++) {
                const dayDate = new Date(year, month, i);
                let classes = 'calendar-day';
                if (dayDate < today) classes += ' disabled';
                const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                gridHtml += `<div class="${classes}" data-date="${dateString}">${i}</div>`;
            }
            this.dom.calendarGrid.html(gridHtml);
        },

        fetchAndRenderSlots: async function(date) {
            this.dom.slotsContainer.html('<p>Cargando horarios...</p>');
            this.dom.nextBtn.addClass('hidden').prop('disabled', true);
            try {
                const response = await VAApi.getAvailableSlots(this.state.professionalId, this.state.selectedService.id, date);
                if (response.success && response.data.length > 0) {
                    const slotsHtml = response.data.map(slot => `<button class="time-slot" data-time="${slot}">${slot}</button>`).join('');
                    this.dom.slotsContainer.html(`<div class="time-slots">${slotsHtml}</div>`);
                } else {
                    this.dom.slotsContainer.html('<p>No hay horarios disponibles para este día.</p>');
                }
            } catch (error) {
                this.dom.slotsContainer.html('<p>Ocurrió un error al cargar los horarios.</p>');
            }
        },

        validateClientForm: function() {
            const form = $('#va-client-details-form');
            const isClientWithPets = form.hasClass('client-has-pets');

            // Campos siempre requeridos
            const clientName = $('#va-client-name').val();
            const clientEmail = $('#va-client-email').val();

            if (!clientName || clientName.trim() === '') {
                alert('Por favor, ingresa tu nombre.');
                return false;
            }

            if (!clientEmail || clientEmail.trim() === '') {
                alert('Por favor, ingresa tu correo electrónico.');
                return false;
            }

            // Si el cliente tiene mascotas vinculadas, validar que haya seleccionado una
            if (isClientWithPets) {
                if (!this.state.selectedPet.id) {
                    alert('Por favor, selecciona una mascota.');
                    return false;
                }
            } else {
                // Si no tiene mascotas vinculadas, validar campos manuales de mascota
                const petName = $('#va-pet-name').val();
                const petSpecies = $('#va-pet-species').val();

                if (!petName || petName.trim() === '') {
                    alert('Por favor, ingresa el nombre de tu mascota.');
                    return false;
                }

                if (!petSpecies || petSpecies === '') {
                    alert('Por favor, selecciona el tipo de animal.');
                    return false;
                }
            }

            return true;
        },

        collectClientData: function() {
            const form = $('#va-client-details-form');
            const isClientWithPets = form.hasClass('client-has-pets');

            // Función auxiliar para obtener valores seguros
            const getSafeValue = (selector) => {
                const val = $(selector).val();
                return val ? val.trim() : '';
            };

            if (isClientWithPets && this.state.selectedPet.id) {
                // Usar datos de la mascota seleccionada
                this.state.clientData = {
                    name: getSafeValue('#va-client-name'),
                    petName: this.state.selectedPet.name,
                    petSpecies: this.state.selectedPet.species,
                    petBreed: this.state.selectedPet.breed || '',
                    petGender: getSafeValue('#va-pet-gender'),
                    email: getSafeValue('#va-client-email'),
                    phone: getSafeValue('#va-client-phone'),
                    notes: getSafeValue('#va-notes'),
                    petId: this.state.selectedPet.id // ID de la mascota del CRM
                };
            } else {
                // Usar datos del formulario manual
                this.state.clientData = {
                    name: getSafeValue('#va-client-name'),
                    petName: getSafeValue('#va-pet-name'),
                    petSpecies: getSafeValue('#va-pet-species'),
                    petBreed: getSafeValue('#va-pet-breed'),
                    petGender: getSafeValue('#va-pet-gender'),
                    email: getSafeValue('#va-client-email'),
                    phone: getSafeValue('#va-client-phone'),
                    notes: getSafeValue('#va-notes')
                };
            }
        },

        renderSummary: function() {
            const genderText = this.state.clientData.petGender === 'male' ? 'Macho' :
                              this.state.clientData.petGender === 'female' ? 'Hembra' :
                              this.state.clientData.petGender === 'unknown' ? 'Género no especificado' : '';

            const petInfo = `${this.state.clientData.petName} (${this.state.clientData.petSpecies}${this.state.clientData.petBreed ? ', ' + this.state.clientData.petBreed : ''}${genderText ? ', ' + genderText : ''})`;
            const summaryHtml = `
                <div class="summary-item"><span class="summary-label">Servicio:</span><span class="summary-value">${this.state.selectedService.name}</span></div>
                <div class="summary-item"><span class="summary-label">Fecha y Hora:</span><span class="summary-value">${this.state.selectedDate} - ${this.state.selectedSlot}</span></div>
                <div class="summary-item"><span class="summary-label">Cliente:</span><span class="summary-value">${this.state.clientData.name}</span></div>
                <div class="summary-item"><span class="summary-label">Mascota:</span><span class="summary-value">${petInfo}</span></div>
                <div class="summary-item"><span class="summary-label">Contacto:</span><span class="summary-value">${this.state.clientData.email}</span></div>
            `;
            this.dom.bookingSummary.html(summaryHtml);
        },

        createAppointment: async function() {
            this.dom.confirmBtn.prop('disabled', true).text('Confirmando...');
            try {
                const appointmentData = {
                    professional_id: this.state.professionalId,
                    service_id: this.state.selectedService.id,
                    date: this.state.selectedDate,
                    time: this.state.selectedSlot,
                    client_name: this.state.clientData.name,
                    pet_name: this.state.clientData.petName,
                    pet_species: this.state.clientData.petSpecies,
                    pet_breed: this.state.clientData.petBreed,
                    pet_gender: this.state.clientData.petGender,
                    client_email: this.state.clientData.email,
                    client_phone: this.state.clientData.phone,
                    notes: this.state.clientData.notes,
                };

                console.log('[Booking Wizard] Enviando datos de cita:', appointmentData);
                console.log('[Booking Wizard] va_ajax_object disponible:', typeof va_ajax_object !== 'undefined' ? va_ajax_object : 'NO DISPONIBLE');

                const response = await VAApi.createAppointment(appointmentData);
                if (response.success) {
                    this.dom.wizardContainer.html('<div class="booking-content" style="text-align: center;"><i class="fas fa-check-circle" style="font-size: 4rem; color: var(--color-green);"></i><h3>¡Cita Confirmada!</h3><p>Hemos enviado los detalles de tu cita a tu correo electrónico.</p></div>');
                } else {
                    throw new Error(response.data.message || 'No se pudo crear la cita.');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.dom.confirmBtn.prop('disabled', false).text('Confirmar Cita');
            }
        }
    };

    $(document).ready(() => BookingWizard.init());
})(jQuery);
