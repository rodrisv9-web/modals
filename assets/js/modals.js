document.addEventListener('DOMContentLoaded', () => {
    // This script will be adapted to work with the WordPress environment.
    // The core logic for modal interaction is preserved.

    // Mock data is removed, will be fetched from the API.

    // Function to show a modal
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.classList.add('modal-open');
        }
    }

    // Function to hide a modal
    function hideModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        // Use a timeout to allow the fade-out animation to complete
        setTimeout(() => {
            if (!document.querySelector('.modal-overlay.active')) {
                document.body.classList.remove('modal-open');
            }
        }, 300); // Should match --modal-transition-speed
    }

    // Event listeners for trigger buttons (will be adapted in the plugin)
    document.querySelectorAll('button[data-modal-id]').forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.dataset.modalId;
            showModal(modalId);
        });
    });

    // Event listeners for closing modals
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        // Close by clicking on the overlay background
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                hideModal(overlay);
            }
        });
        // Close by clicking the close button
        const closeBtn = overlay.querySelector('.modal-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => hideModal(overlay));
        }
    });

    // Close with the Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const visibleModal = document.querySelector('.modal-overlay.active');
            if (visibleModal) {
                hideModal(visibleModal);
            }
        }
    });

    // --- Booking Wizard Logic ---
    const wizardModal = document.getElementById('booking-wizard-modal');
    if(wizardModal) {
        let clientSearchResults = []; // To store results from the API temporarily
        let categoriesAndServices = []; // To store service categories from the API
        const wizardState = { currentStep: 1, totalSteps: 4, selectedClient: null, selectedPet: null, selectedService: null, selectedDate: new Date().toISOString().split('T')[0], selectedSlot: null };

        const backBtn = wizardModal.querySelector('[data-wizard-action="back"]');
        const nextBtn = wizardModal.querySelector('[data-wizard-action="next"]');
        const confirmBtn = wizardModal.querySelector('[data-wizard-action="confirm"]');

        function updateWizardView() {
            const currentStepFloat = parseFloat(wizardState.currentStep);
            const mainStep = Math.floor(currentStepFloat);

            wizardModal.querySelectorAll('.wizard-step-content').forEach(s => s.classList.remove('active'));
            const activeStepContent = wizardModal.querySelector(`.wizard-step-content[data-step="${wizardState.currentStep}"]`);
            if (activeStepContent) {
                activeStepContent.classList.add('active');
            }

            wizardModal.querySelectorAll('.progress-step').forEach(s => {
                s.classList.remove('active');
                if (parseInt(s.dataset.step) <= mainStep) {
                    s.classList.add('active');
                }
            });

            backBtn.style.display = currentStepFloat > 1 ? 'inline-flex' : 'none';
            nextBtn.style.display = currentStepFloat < wizardState.totalSteps && currentStepFloat !== 2.5 ? 'inline-flex' : 'none';
            confirmBtn.style.display = currentStepFloat === wizardState.totalSteps ? 'inline-flex' : 'none';

            nextBtn.disabled = true;

            const modalTitle = wizardModal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Agendar Cita - Paso ${mainStep}/${wizardState.totalSteps}`;
            }

            if(currentStepFloat === 1) nextBtn.disabled = !wizardState.selectedClient;
            if(currentStepFloat === 2) {
                document.getElementById('wizard-client-info').textContent = `Cliente: ${wizardState.selectedClient.name}`;
                const petList = document.getElementById('wizard-pet-list');
                petList.innerHTML = wizardState.selectedClient.pets.map(p => `<div class="pet-selector" data-pet-id="${p.id}"><div style="display: flex; align-items: center; gap: 1rem;"><div style="font-size: 1.5rem;">🐾</div><div><div style="font-weight: 700;">${p.name}</div><div style="font-size: 0.875rem; color: #6B7280;">${p.species}</div></div></div><i class="fa-solid fa-chevron-right" style="color: #9CA3AF;"></i></div>`).join('') + `<div id="add-new-pet-btn"><i class="fa-solid fa-plus" style="margin-right: 0.5rem;"></i>Añadir Nueva Mascota</div>`;
                nextBtn.disabled = !wizardState.selectedPet;
            }
            if(currentStepFloat === 2.5) {
                document.getElementById('wizard-new-pet-subtitle').textContent = `Para el cliente: ${wizardState.selectedClient.name}`;
                document.getElementById('wizard-new-pet-form').reset();
            }
            if(currentStepFloat === 3) {
                document.getElementById('wizard-service-subtitle').textContent = `Elige el servicio y la fecha para la cita de ${wizardState.selectedPet.name}.`;
                nextBtn.disabled = !(wizardState.selectedService && wizardState.selectedDate && wizardState.selectedSlot);

                // Fetch services and categories if not already loaded
                if (categoriesAndServices.length === 0) {
                    const professionalId = document.getElementById('agenda-module').dataset.professionalId;
                    const servicesApiUrl = (window.VA_REST?.api_url || '/wp-json/vetapp/v1/') + `professionals/${professionalId}/services-and-categories`;

                    fetch(servicesApiUrl, { headers: { 'X-WP-Nonce': window.VA_REST?.api_nonce }})
                        .then(response => response.json())
                        .then(response => {
                            if (response.success && response.data) {
                                categoriesAndServices = response.data;
                                const categorySelect = document.getElementById('wiz-category');
                                categorySelect.innerHTML = `<option value="">Elige una categoría</option>` + categoriesAndServices.map(c => `<option value="${c.category_id}">${c.name}</option>`).join('');
                            }
                        })
                        .catch(error => console.error('Error fetching services:', error));
                }
            }
            if(currentStepFloat === 4) {
                document.getElementById('wizard-summary').innerHTML = `<div class="info-card"><p class="info-card-header">CLIENTE Y MASCOTA</p><div class="info-card-body"><div class="info-card-row"><i class="fa-solid fa-user info-card-icon"></i><span class="info-card-data strong">${wizardState.selectedClient.name}</span></div><div class="info-card-row"><i class="fa-solid fa-paw info-card-icon"></i><span class="info-card-data">${wizardState.selectedPet.name} (${wizardState.selectedPet.species})</span></div></div></div><div class="info-card"><p class="info-card-header">FECHA Y SERVICIO</p><div class="info-card-body"><div class="info-card-row"><i class="fa-solid fa-tag info-card-icon"></i><span class="info-card-data strong">${wizardState.selectedService.name}</span></div><div class="info-card-row"><i class="fa-solid fa-calendar-day info-card-icon"></i><span class="info-card-data">${new Date(wizardState.selectedDate).toLocaleDateString('es-ES', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</span></div><div class="info-card-row"><i class="fa-solid fa-clock info-card-icon"></i><span class="info-card-data">${wizardState.selectedSlot}</span></div></div></div>`;
            }
        }

        wizardModal.addEventListener('click', e => {
            const petDiv = e.target.closest('.pet-selector');
            const addPetBtn = e.target.closest('#add-new-pet-btn');
            if (petDiv) {
                wizardState.selectedPet = wizardState.selectedClient.pets.find(p => p.id == petDiv.dataset.petId);
                nextBtn.disabled = false;
                wizardModal.querySelectorAll('.pet-selector').forEach(el => el.classList.remove('selected'));
                petDiv.classList.add('selected');
            }
            if (addPetBtn) {
                wizardState.currentStep = 2.5;
                updateWizardView();
            }
        });

        document.getElementById('wizard-new-pet-form').addEventListener('submit', (ev) => {
            ev.preventDefault();
            const newPetNameInput = document.getElementById('new-pet-name');
            if (!newPetNameInput.value.trim()) { return; }
            const newPet = { id: Date.now(), name: newPetNameInput.value, species: document.getElementById('new-pet-species').value };
            wizardState.selectedClient.pets.push(newPet);
            wizardState.selectedPet = newPet;
            wizardState.currentStep = 3;
            updateWizardView();
        });

        document.getElementById('client-search-input').addEventListener('input', e => {
            const term = e.target.value.toLowerCase();
            const resultsContainer = document.getElementById('client-search-results');

            if (term.length < 3) {
                resultsContainer.innerHTML = '<p style="text-align: center;">Introduce al menos 3 caracteres para buscar.</p>';
                clientSearchResults = [];
                return;
            }

            const professionalId = document.getElementById('agenda-module').dataset.professionalId;
            // Assumes VA_REST is localized with api_url and api_nonce
            const apiUrl = (window.VA_REST?.api_url || '/wp-json/vetapp/v1/') + `clients/search?term=${encodeURIComponent(term)}&professional_id=${professionalId}`;

            resultsContainer.innerHTML = '<p style="text-align: center;">Buscando...</p>';

            fetch(apiUrl, {
                headers: { 'X-WP-Nonce': window.VA_REST?.api_nonce }
            })
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data) {
                    clientSearchResults = response.data; // Store full client objects
                    if (response.data.length) {
                        resultsContainer.innerHTML = response.data.map(c =>
                            `<div class="client-selector" data-client-id="${c.client_id}"><div><div style="font-weight: 700;">${c.name}</div><div style="font-size: 0.875rem; color: #6B7280;">${c.email || ''}</div></div><i class="fa-solid fa-chevron-right" style="color: #9CA3AF;"></i></div>`
                        ).join('');
                    } else {
                        resultsContainer.innerHTML = '<p style="text-align: center;">No se encontraron clientes.</p>';
                    }
                } else {
                    resultsContainer.innerHTML = `<p style="text-align: center; color: var(--modal-danger-color);">Error: ${response.message || 'No se pudo buscar clientes.'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching clients:', error);
                resultsContainer.innerHTML = '<p style="text-align: center; color: var(--modal-danger-color);">Error de red al buscar clientes.</p>';
            });
        });

        document.getElementById('client-search-results').addEventListener('click', e => {
            const clientDiv = e.target.closest('.client-selector');
            if (clientDiv) {
                wizardState.selectedClient = clientSearchResults.find(c => c.client_id == clientDiv.dataset.clientId);

                if (wizardState.selectedClient) {
                    // Also fetch pets for the client
                    const professionalId = document.getElementById('agenda-module').dataset.professionalId;
                    const petsApiUrl = (window.VA_REST?.api_url || '/wp-json/vetapp/v1/') + `patients/clients/${wizardState.selectedClient.client_id}/pets?professional_id=${professionalId}`;

                    fetch(petsApiUrl, { headers: { 'X-WP-Nonce': window.VA_REST?.api_nonce }})
                        .then(response => response.json())
                        .then(petResponse => {
                            if (petResponse.success) {
                                wizardState.selectedClient.pets = petResponse.data;
                            } else {
                                wizardState.selectedClient.pets = [];
                            }
                            wizardState.currentStep = 2;
                            updateWizardView();
                        })
                        .catch(err => {
                            console.error("Error fetching pets", err);
                            wizardState.selectedClient.pets = [];
                            wizardState.currentStep = 2;
                            updateWizardView();
                        });
                }
            }
        });

        const categorySelect = document.getElementById('wiz-category');
        const serviceSelect = document.getElementById('wiz-service');
        const dateInput = document.getElementById('wiz-date');

        function fetchAvailableSlots() {
            if (!wizardState.selectedService || !wizardState.selectedDate) {
                renderSlotsCarousel([]);
                const slotsContainer = document.getElementById('wiz-slots');
                if (slotsContainer) slotsContainer.innerHTML = '<p style="text-align: center;">Elige un servicio y fecha.</p>';
                return;
            }

            const professionalId = document.getElementById('agenda-module').dataset.professionalId;
            const initialData = JSON.parse(document.getElementById('agenda-initial-data').textContent);

            const formData = new URLSearchParams();
            formData.append('action', 'va_get_availability_for_range');
            formData.append('nonce', initialData.nonce);
            formData.append('professional_id', professionalId);
            formData.append('start_date', wizardState.selectedDate);
            formData.append('end_date', wizardState.selectedDate);
            // Use the service duration from the service object, or a default
            formData.append('duration', wizardState.selectedService.duration || 30);

            const slotsContainer = document.getElementById('wiz-slots-container');
            if (slotsContainer) {
                 slotsContainer.innerHTML = '<p style="text-align: center;">Buscando horarios...</p>';
            }

            fetch(initialData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data && response.data[wizardState.selectedDate] && response.data[wizardState.selectedDate].slots) {
                    // The endpoint returns slot objects, we just need the time string
                    const slots = response.data[wizardState.selectedDate].slots.map(slot => slot.time);
                    renderSlotsCarousel(slots);
                } else {
                    renderSlotsCarousel([]);
                    const container = document.getElementById('wiz-slots');
                    if(container) container.innerHTML = '<p style="text-align: center;">No hay horarios disponibles para este día.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching slots:', error);
                const container = document.getElementById('wiz-slots-container');
                if(container) container.innerHTML = '<p style="text-align: center; color: var(--modal-danger-color);">Error de red al cargar horarios.</p>';
            });
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', e => {
                const catId = e.target.value;
                const category = categoriesAndServices.find(c => c.category_id == catId);

                serviceSelect.innerHTML = `<option value="">Elige un servicio</option>` + (category && category.services ? category.services.map(s => `<option value="${s.service_id}">${s.name}</option>`).join('') : '');
                serviceSelect.disabled = !category;

                wizardState.selectedService = null;
                wizardState.selectedSlot = null;
                renderSlotsCarousel([]);
                nextBtn.disabled = true;
            });
        }

        if (serviceSelect) {
            serviceSelect.addEventListener('change', e => {
                const serviceId = e.target.value;
                if(serviceId) {
                   const category = categoriesAndServices.find(c => c.services.some(s => s.service_id == serviceId));
                   if (category) {
                       // Find the correct service object and store it
                       wizardState.selectedService = category.services.find(s => s.service_id == serviceId);
                   }
                   wizardState.selectedSlot = null;
                   fetchAvailableSlots();
                   nextBtn.disabled = true;
                } else {
                    wizardState.selectedService = null;
                    wizardState.selectedSlot = null;
                    renderSlotsCarousel([]);
                    nextBtn.disabled = true;
                }
            });
        }

        if(dateInput) {
            dateInput.addEventListener('change', e => {
                wizardState.selectedDate = e.target.value;
                wizardState.selectedSlot = null;
                fetchAvailableSlots();
                nextBtn.disabled = true;
            });
        }

        function renderSlotsCarousel(slots) {
            const slotsPerPage = window.innerWidth < 768 ? 6 : 8;
            const totalPages = Math.ceil(slots.length / slotsPerPage);
            const slotsContainer = document.getElementById('wiz-slots-container');

            if (!slots.length) {
                slotsContainer.innerHTML = `<div id="wiz-slots-wrapper"><div id="wiz-slots" style="color: #9CA3AF; padding: 0.5rem; border: 1px solid #E5E7EB; border-radius: 0.5rem; text-align: center;">Elige un servicio</div></div><div id="wiz-slots-pagination"></div>`;
                return;
            }

            let carouselHTML = `<div id="wiz-slots-wrapper"><div id="wiz-slots">`;
            for(let i = 0; i < totalPages; i++) {
                carouselHTML += `<div class="slot-page">`;
                const pageSlots = slots.slice(i * slotsPerPage, (i + 1) * slotsPerPage);
                carouselHTML += pageSlots.map(slot => `<div class="time-slot">${slot}</div>`).join('');
                carouselHTML += `</div>`;
            }
            carouselHTML += `</div></div><div id="wiz-slots-pagination"></div>`;
            slotsContainer.innerHTML = carouselHTML;

            const paginationContainer = document.getElementById('wiz-slots-pagination');
            if (totalPages > 1) {
               paginationContainer.innerHTML = Array.from({length: totalPages}, (_, i) => `<div class="pagination-dot ${i === 0 ? 'active' : ''}" data-page="${i}"></div>`).join('');
            }

            const wrapper = document.getElementById('wiz-slots-wrapper');
            paginationContainer.addEventListener('click', e => {
                const dot = e.target.closest('.pagination-dot');
                if (dot) {
                    const pageIndex = parseInt(dot.dataset.page);
                    wrapper.scrollLeft = pageIndex * wrapper.offsetWidth;
                    paginationContainer.querySelectorAll('.pagination-dot').forEach(d => d.classList.remove('active'));
                    dot.classList.add('active');
                }
            });

            document.getElementById('wiz-slots').addEventListener('click', e => {
                const slotDiv = e.target.closest('.time-slot');
                if(slotDiv) {
                    wizardState.selectedSlot = slotDiv.textContent;
                     document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
                     slotDiv.classList.add('selected');
                     nextBtn.disabled = !(wizardState.selectedService && wizardState.selectedDate && wizardState.selectedSlot);
                }
            });
        }

        nextBtn.addEventListener('click', () => {
            if (parseFloat(wizardState.currentStep) < wizardState.totalSteps) {
                wizardState.currentStep = Math.floor(wizardState.currentStep) + 1;
                updateWizardView();
            }
        });

        backBtn.addEventListener('click', () => {
            if (wizardState.currentStep === 2.5) {
                wizardState.currentStep = 2;
            } else if (wizardState.currentStep > 1) {
                wizardState.currentStep = Math.ceil(wizardState.currentStep) - 1;
            }
            updateWizardView();
        });

        confirmBtn.addEventListener('click', () => {
            const professionalId = document.getElementById('agenda-module').dataset.professionalId;
            const initialData = JSON.parse(document.getElementById('agenda-initial-data').textContent);

            const timeParts = wizardState.selectedSlot.match(/(\d+):(\d+)\s*(AM|PM)/i);
            let hours = parseInt(timeParts[1], 10);
            const minutes = parseInt(timeParts[2], 10);
            if (timeParts[3].toUpperCase() === 'PM' && hours < 12) hours += 12;
            if (timeParts[3].toUpperCase() === 'AM' && hours === 12) hours = 0;

            const appointmentDateTime = new Date(`${wizardState.selectedDate}T00:00:00`);
            appointmentDateTime.setHours(hours, minutes);

            const appointmentStartString = appointmentDateTime.getFullYear() + '-' +
                ('0' + (appointmentDateTime.getMonth() + 1)).slice(-2) + '-' +
                ('0' + appointmentDateTime.getDate()).slice(-2) + ' ' +
                ('0' + appointmentDateTime.getHours()).slice(-2) + ':' +
                ('0' + appointmentDateTime.getMinutes()).slice(-2) + ':00';

            const bookingData = {
                professional_id: professionalId,
                client_id: wizardState.selectedClient.client_id,
                pet_id: wizardState.selectedPet.pet_id,
                service_id: wizardState.selectedService.service_id,
                appointment_start: appointmentStartString,
                notes: '', // Placeholder for future implementation
            };

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Confirmando...';

            const formData = new URLSearchParams();
            formData.append('action', 'va_book_appointment');
            formData.append('nonce', initialData.nonce);
            formData.append('booking_data', JSON.stringify(bookingData));

            fetch(initialData.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    alert('Cita confirmada con éxito.'); // Replace with a better notification
                    hideModal(wizardModal);
                    // Here you would typically trigger a refresh of the agenda view
                } else {
                    alert(`Error: ${response.data.message || 'No se pudo agendar la cita.'}`);
                }
            })
            .catch(error => {
                console.error('Booking error:', error);
                alert('Error de red al agendar la cita.');
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Confirmar Cita';
            });
        });

        const closeBtn = wizardModal.querySelector('.modal-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                setTimeout(() => {
                    wizardState.currentStep = 1;
                    wizardState.selectedClient = null;
                    wizardState.selectedPet = null;
                    wizardState.selectedService = null;
                    wizardState.selectedSlot = null;
                    categoriesAndServices = []; // Reset categories
                    clientSearchResults = [];
                    updateWizardView();
                    document.getElementById('client-search-input').value = '';
                    document.getElementById('client-search-results').innerHTML = '<p style="text-align: center;">Introduce al menos 3 caracteres para buscar.</p>';
                }, 300);
            });
        }
    }
});