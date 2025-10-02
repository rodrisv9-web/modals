/**
 * Módulo de Agenda Interactiva - Veterinalia Appointment Plugin
 * Versión: 2.0 - Proyecto Chocovainilla Implementado
 */
(function($) {
    'use strict';

    class VeterinaliaAgendaModule {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            if (!this.container) return;
            
            this.state = {
                currentView: 'agenda',
                currentDate: new Date(),
                appointments: [],
                services: [],
                professionalId: null,
                nonce: null,
                ajax_url: null,
                currentAppointmentForLog: null, // Guardará la cita que estamos completando
                visiblePastDays: 1, // Solo ayer inicialmente
                visibleFutureDays: 1, // Solo mañana inicialmente
                isLoadingPast: false, // Estado de carga hacia el pasado
                isLoadingFuture: false, // Estado de carga hacia el futuro
                loadIncrement: 2 // Días a cargar en cada scroll
            };

            this.timeIndicatorInterval = null;
            this.navigationHandler = null;
            this.mobileNavigationHandler = null;
            this.init();
        }

        async init() {
            this.loadInitialDataFromJSON();
            this.setupEventListeners();
            this.updateViewSwitcherActiveState(); // Inicializar estado activo
            this.render(); // Renderizado inicial
        }

        loadInitialDataFromJSON() {
            const dataScript = document.getElementById('agenda-initial-data');
            if (!dataScript) throw new Error("Faltan los datos iniciales para la agenda.");
            
            try {
                const data = JSON.parse(dataScript.textContent);
                this.state.professionalId = data.professional_id;
                this.state.appointments = data.appointments || [];
                this.state.services = data.services || [];
                this.state.nonce = data.nonce;
                this.state.ajax_url = data.ajax_url;

                console.log("✅ Datos iniciales cargados:", this.state.appointments.length + " citas");
            } catch (e) {
                console.error("Error al parsear los datos JSON iniciales:", e);
                throw new Error("Los datos iniciales de la agenda son inválidos.");
            }
        }
        
        setupEventListeners() {
            this.setupHeaderEventListeners();
            this.setupModalEventListeners();
            this.setupFormEventListeners();
            // Ya no es necesario, los listeners de la bitácora se añaden dinámicamente
            // this.setupLogbookEventListeners();
            this.setupNavigationEventListeners();
        }

        // Esta función ya no es necesaria, los listeners se añaden en transitionToLogbookView
        // setupLogbookEventListeners() { ... }

        setupHeaderEventListeners() {
            // Event listeners para view switcher desktop
            const viewSwitcherBtn = this.container.querySelector('#view-switcher-btn');
            const viewSwitcherMenu = this.container.querySelector('#view-switcher-menu');
            if (viewSwitcherBtn && viewSwitcherMenu) {
                viewSwitcherBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    viewSwitcherMenu.classList.toggle('hidden');
                });
                viewSwitcherMenu.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target.dataset.view) {
                        this.state.currentView = e.target.dataset.view;
                        this.updateViewSwitcherActiveState();
                        viewSwitcherMenu.classList.add('hidden');
                        this.render();
                    }
                });
            }
            
            // Event listeners para view switcher móvil
            const mobileViewSwitcherBtn = this.container.querySelector('#mobile-view-switcher-btn');
            const mobileViewSwitcherMenu = this.container.querySelector('#mobile-view-switcher-menu');
            if (mobileViewSwitcherBtn && mobileViewSwitcherMenu) {
                mobileViewSwitcherBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mobileViewSwitcherMenu.classList.toggle('hidden');
                });
                mobileViewSwitcherMenu.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.target.dataset.view) {
                        this.state.currentView = e.target.dataset.view;
                        this.updateViewSwitcherActiveState();
                        mobileViewSwitcherMenu.classList.add('hidden');
                        this.render();
                    }
                });
            }
            
            // Event listener para botón add desktop
            const addBtn = this.container.querySelector('#add-appointment-btn');
            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    console.log("▶️ Chocovainilla: Botón '+' presionado. Lanzando el wizard...");
                    AgendaWizard.open(this.state.professionalId);
                });
            }
            
            // Event listener para botón add móvil
            const mobileAddBtn = this.container.querySelector('#mobile-add-appointment-btn');
            if (mobileAddBtn) {
                mobileAddBtn.addEventListener('click', () => {
                    console.log("▶️ Chocovainilla: Botón '+' (móvil) presionado. Lanzando el wizard...");
                    AgendaWizard.open(this.state.professionalId);
                });
            }
            
            // Cerrar menús del view switcher cuando se hace clic fuera
            document.addEventListener('click', (e) => {
                const viewSwitcherMenus = [
                    this.container.querySelector('#view-switcher-menu'),
                    this.container.querySelector('#mobile-view-switcher-menu')
                ];
                
                viewSwitcherMenus.forEach(menu => {
                    if (menu && !menu.contains(e.target) && !e.target.closest('.view-switcher-btn')) {
                        menu.classList.add('hidden');
                    }
                });
            });
            
            // Navegación con teclado
            document.addEventListener('keydown', (e) => {
                if (this.state.currentView === 'day') {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.state.currentDate.setDate(this.state.currentDate.getDate() - 1);
                        this.render();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.state.currentDate.setDate(this.state.currentDate.getDate() + 1);
                        this.render();
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        this.state.currentDate = new Date();
                        this.render();
                    }
                } else if (this.state.currentView === 'week') {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.state.currentDate.setDate(this.state.currentDate.getDate() - 7);
                        this.render();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.state.currentDate.setDate(this.state.currentDate.getDate() + 7);
                        this.render();
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        this.state.currentDate = new Date();
                        this.render();
                    }
                }
            });
            
            // Gestos táctiles para vistas de día y semana
            this.setupHorizontalSwipes();
        }

        updateViewSwitcherActiveState() {
            // Actualizar estado activo en ambos view switchers (desktop y móvil)
            const viewSwitchers = [
                this.container.querySelector('#view-switcher-menu'),
                this.container.querySelector('#mobile-view-switcher-menu')
            ];
            
            viewSwitchers.forEach(menu => {
                if (menu) {
                    // Remover clase active de todos los enlaces
                    menu.querySelectorAll('a').forEach(link => {
                        link.classList.remove('active');
                    });
                    
                    // Agregar clase active al enlace correspondiente
                    const activeLink = menu.querySelector(`a[data-view="${this.state.currentView}"]`);
                    if (activeLink) {
                        activeLink.classList.add('active');
                    }
                }
            });
            
            // Actualizar texto del botón
            const viewSwitcherBtns = [
                this.container.querySelector('#view-switcher-btn span'),
                this.container.querySelector('#mobile-view-switcher-btn span')
            ];
            
            const viewLabels = {
                'agenda': 'Agenda',
                'day': 'Día',
                'week': 'Semana'
            };
            
            viewSwitcherBtns.forEach(btn => {
                if (btn) {
                    btn.textContent = viewLabels[this.state.currentView] || 'Agenda';
                }
            });
            
            // Resetear fecha actual si se cambia a vista de agenda
            if (this.state.currentView === 'agenda') {
                this.state.currentDate = new Date();
            }
        }

        setupModalEventListeners() {
            const modal = this.container.querySelector('#appointment-modal');
            if (modal) {
                const closeBtn = modal.querySelector('.modal-close-btn');
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => this.hideModal(modal));
                }
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) this.hideModal(modal);
                });
            }
        }

        setupFormEventListeners() {
            // Event listeners para citas en todas las vistas
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (agendaBody) {
                agendaBody.addEventListener('click', (e) => {
                    // Buscar tarjetas de citas en todas las vistas
                    const appointmentCard = e.target.closest('.appointment-card-vision, .day-appointment-card, .week-appointment-card');
                    if (appointmentCard && appointmentCard.dataset.id) {
                        const appointment = this.state.appointments.find(app => app.id == appointmentCard.dataset.id);
                        if (appointment) this.showAppointmentModal(appointment);
                    }
                });
            }
        }

        // --- INICIO DE NUEVAS FUNCIONES PARA LA BITÁCORA ---

        /**
     * Construye el HTML del formulario dinámicamente.
     * @param {Array} fields - Array de objetos de campo.
     * @param {Array} products - Array de productos del catálogo del profesional.
     * @returns {string} - El HTML del formulario.
     */
    buildFormFromSchema(fields, products = []) {
        if (!fields || fields.length === 0) {
            return '<p>No hay campos personalizados para este tipo de entrada.</p>';
        }

        let formHtml = '';
        fields.forEach(field => {
            const isRequired = field.is_required ? 'required' : '';
            const fieldId = `custom-field-${field.field_key}`;
            let fieldInput = '';

            switch (field.field_type) {
                case 'textarea':
                    fieldInput = `<textarea id="${fieldId}" name="${field.field_key}" class="form-input form-textarea custom-field" rows="3" ${isRequired}></textarea>`;
                    break;
                case 'number':
                    fieldInput = `<input type="number" id="${fieldId}" name="${field.field_key}" class="form-input custom-field" ${isRequired}>`;
                    break;
                case 'date':
                    fieldInput = `<input type="date" id="${fieldId}" name="${field.field_key}" class="form-input custom-field" ${isRequired}>`;
                    break;
                case 'product_selector':
                    // Si no hay productos en el catálogo, mostrar un mensaje.
                    if (!products || products.length === 0) {
                        fieldInput = `
                            <div class="product-selector-empty-state">
                                <p class="empty-state-message">No hay productos en tu catálogo.</p>
                                <a href="/wp-admin/admin.php?page=veterinalia-appointment-catalog" target="_blank" class="button-secondary">Añadir Productos</a>
                            </div>
                        `;
                        break;
                    }

                    // ¡Aquí está el puente! Filtramos los productos.
                    const filteredProducts = field.product_filter_type
                        ? products.filter(p => p.product_type === field.product_filter_type)
                        : products;

                    if (filteredProducts.length === 0) {
                        fieldInput = `
                            <div class="product-selector-empty-state">
                                <p class="empty-state-message">No se encontraron productos de tipo "${field.product_filter_type}".</p>
                                <a href="/wp-admin/admin.php?page=veterinalia-appointment-catalog" target="_blank" class="button-secondary">Revisar Catálogo</a>
                            </div>
                        `;
                        break;
                    }

                    const productOptions = filteredProducts
                        .map(p => `<option value="${p.product_id}">${p.product_name}</option>`)
                        .join('');

                    fieldInput = `
                        <select id="${fieldId}" name="${field.field_key}" class="form-input custom-field product-selector" ${isRequired}>
                            <option value="">Seleccionar ${field.product_filter_type || 'producto'}...</option>
                            ${productOptions}
                        </select>
                        <div class="product-context-fields" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9;">
                            <div class="form-grid cols-2">
                                <div class="form-group">
                                    <label for="${fieldId}_lot" class="form-label" style="font-size: 0.75rem;">Número de Lote</label>
                                    <input type="text" id="${fieldId}_lot" name="${field.field_key}_lot" class="form-input" placeholder="Lote">
                                </div>
                                <div class="form-group">
                                    <label for="${fieldId}_exp" class="form-label" style="font-size: 0.75rem;">Caducidad</label>
                                    <input type="date" id="${fieldId}_exp" name="${field.field_key}_exp" class="form-input">
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'next_appointment':
                    // Componente especial para la próxima cita
                    const serviceOptions = this.state.services
                        .map(s => `<option value="${s.id}">${s.name}</option>`)
                        .join('');
                    fieldInput = `
                        <div class="next-appointment-fields form-grid cols-2">
                            <input type="date" name="${field.field_key}_date" class="form-input">
                            <select name="${field.field_key}_service" class="form-input service-selector">
                                <option value="">Seleccionar servicio para próxima cita...</option>
                                ${serviceOptions}
                            </select>
                        </div>`;
                    break;
                case 'text':
                default:
                    fieldInput = `<input type="text" id="${fieldId}" name="${field.field_key}" class="form-input custom-field" ${isRequired}>`;
                    break;
            }

            formHtml += `
                <div class="form-group">
                    <label for="${fieldId}" class="form-label">${field.field_label}${field.is_required ? '*' : ''}</label>
                    ${fieldInput}
                </div>`;
        });
        return formHtml;
    }

    async transitionToLogbookView(appointment) {
        this.state.currentAppointmentForLog = appointment;
        const modal = this.container.querySelector('#appointment-modal');
        if (!modal) return;

        console.log("🔄 Transición a la vista de bitácora para la cita ID:", appointment.id);

        const modalTitle = modal.querySelector('#modal-title');
        const modalBody = modal.querySelector('#modal-details');
        const modalFooter = modal.querySelector('#status-buttons-container');
        const statusPill = modal.querySelector('#appointment-status-pill');

        if (modalTitle) modalTitle.textContent = 'Registrar en Bitácora';
        if (modalBody) modalBody.innerHTML = '<div class="loading-state"><div class="loader"></div><p>Cargando formulario...</p></div>';
        if (modalFooter) modalFooter.innerHTML = '';
        if (statusPill) statusPill.hidden = true;

        try {
            let service = null;
            if (appointment.service_id) {
                service = this.state.services.find(s => parseInt(s.id) === parseInt(appointment.service_id));
            }
            if (!service) {
                service = this.state.services.find(s => s.name === appointment.service);
            }

            let formHtml;
            if (!service || !service.entry_type_id) {
                formHtml = `
                    <div class="form-group">
                        <label for="logbook-title" class="form-label">Título / Motivo *</label>
                        <input type="text" id="logbook-title" name="title" class="form-input" value="${appointment.service}" required>
                    </div>
                    <div class="form-group">
                        <label for="logbook-description" class="form-label">Observaciones</label>
                        <textarea id="logbook-description" name="description" rows="3" class="form-input form-textarea"></textarea>
                    </div>`;
            } else {
                const [formResponse, productsResponse] = await Promise.all([
                    VAApi.getFormFields(service.entry_type_id),
                    VAApi.getProductsByProfessional(this.state.professionalId)
                ]);

                if (formResponse.success && productsResponse.success) {
                    formHtml = this.buildFormFromSchema(formResponse.data, productsResponse.data);
                } else {
                    throw new Error('No se pudo cargar la información del formulario o los productos.');
                }
            }

            if (modalBody) {
                modalBody.innerHTML = `
                    <form id="logbook-form-dynamic" class="logbook-form">
                        <p class="logbook-intro">Registrando visita para: <strong>${appointment.pet} (${appointment.client})</strong></p>
                        ${formHtml}
                        <input type="hidden" id="logbook-appointment-id" value="${appointment.id}">
                        <input type="hidden" id="logbook-pet-id" value="${appointment.pet_id || 0}">
                    </form>
                `;
            }

            if (modalFooter) {
                modalFooter.innerHTML = `
                    <div class="form-actions">
                        <button type="button" id="logbook-skip-btn-dynamic" class="btn-secondary">Completar sin Registrar</button>
                        <button type="submit" form="logbook-form-dynamic" id="logbook-save-btn-dynamic" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar y Completar
                        </button>
                    </div>
                `;
            }

            const form = modal.querySelector('#logbook-form-dynamic');
            const skipBtn = modal.querySelector('#logbook-skip-btn-dynamic');

            if (form) {
                form.addEventListener('submit', this.handleLogbookSubmit.bind(this));
            }
            if (skipBtn) {
                skipBtn.addEventListener('click', this.handleLogbookSkip.bind(this));
            }

        } catch (error) {
            if (modalBody) modalBody.innerHTML = `<p class="error-message">Error al cargar el formulario: ${error.message}</p>`;
        }
    }

    async handleLogbookSubmit(e) {
        e.preventDefault();
        e.stopPropagation(); // Evita que el evento se propague al contenedor padre.

        const form = e.target;
        const modal = form.closest('.modal-overlay') || this.container.querySelector('#appointment-modal');
        
        if (!modal) {
            console.error('❌ Modal no encontrado para el formulario de bitácora');
            return;
        }
        
        const saveBtn = modal.querySelector('#logbook-save-btn-dynamic');
        if (!saveBtn) {
            console.error('❌ Botón de guardar no encontrado en el modal');
            return;
        }
        
        const originalBtnText = saveBtn.innerHTML;
        saveBtn.innerHTML = 'Guardando...';
        saveBtn.disabled = true;

        const appointment = this.state.currentAppointmentForLog;
        const service = this.state.services.find(s => s.name === appointment.service);

        const metaData = {};
        const productsData = [];
        const nextAppointmentData = {};

        const customFields = form.querySelectorAll('.custom-field');
        customFields.forEach(field => {
            const key = field.name;
            if (!key) return;

            if (field.classList.contains('product-selector') && field.value) {
                const productId = field.value;
                const lotField = form.querySelector(`[name="${key}_lot"]`);
                const expField = form.querySelector(`[name="${key}_exp"]`);
                const lot = lotField ? lotField.value : '';
                const exp = expField ? expField.value : '';
                productsData.push({ product_id: productId, lot_number: lot, expiration_date: exp });
            } else {
                const value = field.type === 'checkbox' ? field.checked : field.value;
                metaData[key] = value;
            }
        });
        
        const nextAppDateField = form.querySelector('[name$="_date"]');
        const nextAppServiceField = form.querySelector('[name$="_service"]');
        if (nextAppDateField && nextAppDateField.value && nextAppServiceField && nextAppServiceField.value) {
            nextAppointmentData.date = nextAppDateField.value;
            nextAppointmentData.service_id = nextAppServiceField.value;
        }

        const appointmentIdField = form.querySelector('#logbook-appointment-id');
        const petIdField = form.querySelector('#logbook-pet-id');
        
        if (!appointmentIdField) {
            console.error('❌ Campo appointment_id no encontrado en el formulario');
            saveBtn.innerHTML = originalBtnText;
            saveBtn.disabled = false;
            return;
        }

        const logData = {
            appointment_id: appointmentIdField.value,
            pet_id: petIdField ? petIdField.value : 0,
            entry_type_id: service ? service.entry_type_id : null,
            title: appointment.service,
            meta: metaData,
            products: productsData,
            next_appointment: nextAppointmentData
        };

        try {
            const response = await VAApi.createPetLog(logData);
            if (response && response.success) {
                this.showSuccess('Bitácora guardada y cita completada.');
                this.hideModal(this.container.querySelector('#appointment-modal'));
                await this.reloadDataFromAJAX();
            } else {
                const msg = (response?.data?.message) || (response?.message) || 'No se pudo guardar la entrada.';
                throw new Error(msg);
            }
        } catch (error) {
            console.error("Error al guardar la bitácora:", error);
            this.showError(error?.message || 'Error inesperado');
        } finally {
            saveBtn.innerHTML = originalBtnText;
            saveBtn.disabled = false;
        }
    }

    async handleLogbookSkip(e) {
        e.stopPropagation(); // Evita que el evento se propague al contenedor padre.
        console.log("⏩ Omitiendo registro en bitácora. Solo completando cita.");
        const appointment = this.state.currentAppointmentForLog;
        if (!appointment) return;

        this.hideModal(this.container.querySelector('#appointment-modal'));
        await this.changeAppointmentStatus(appointment.id, 'completed');
    }

        // --- FIN DE NUEVAS FUNCIONES PARA LA BITÁCORA ---

    showAppointmentModal(appointment) {
        const modal = this.container.querySelector('#appointment-modal');
        if (!modal) return;

        // Limpiar listeners anteriores para evitar duplicados
        const oldStatusContainer = modal.querySelector('#status-buttons-container');
        if (oldStatusContainer) {
            const newStatusContainer = oldStatusContainer.cloneNode(false); // Clonar sin hijos para eliminar listeners
            oldStatusContainer.parentNode.replaceChild(newStatusContainer, oldStatusContainer);
        }

        const modalTitle = modal.querySelector('#modal-title');
        const modalDetails = modal.querySelector('#modal-details');
        const statusPill = modal.querySelector('#appointment-status-pill');
        const statusLabel = modal.querySelector('#appointment-status-label');

        if (modalTitle) {
            modalTitle.textContent = appointment.service;
        }

        const statusMap = {
            confirmed: { label: 'Confirmada', className: 'status-pill--confirmed' },
            completed: { label: 'Completada', className: 'status-pill--completed' },
            cancelled: { label: 'Cancelada', className: 'status-pill--cancelled' },
            pending: { label: 'Pendiente', className: 'status-pill--pending' }
        };

        if (statusPill) {
            const pillClasses = ['status-pill--confirmed', 'status-pill--completed', 'status-pill--cancelled', 'status-pill--pending'];
            statusPill.classList.remove(...pillClasses);
            const statusInfo = statusMap[appointment.status] || { label: 'Sin estado', className: '' };
            if (statusInfo.className) {
                statusPill.classList.add(statusInfo.className);
            }
            statusPill.hidden = false;
            statusPill.setAttribute('data-status', appointment.status || 'unknown');
            if (statusLabel) {
                statusLabel.textContent = statusInfo.label;
            }
        }

        const appointmentDate = new Date(appointment.date + 'T12:00:00');
        const formattedDate = appointmentDate.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        if (modalDetails) {
            const buildInfoRows = (rows = []) => rows.map(row => `
                <div class="info-card-row">
                    <span class="info-card-icon"><i class="fas ${row.icon}" aria-hidden="true"></i></span>
                    <span class="info-card-data${row.strong ? ' strong' : ''}">${row.label}</span>
                </div>
            `).join('');

            const clientRows = [
                { icon: 'fa-user', label: appointment.client || 'Cliente sin nombre', strong: true },
                { icon: 'fa-paw', label: appointment.pet || 'Mascota no especificada' }
            ];

            if (appointment.phone) {
                clientRows.push({ icon: 'fa-phone', label: appointment.phone });
            }
            if (appointment.email) {
                clientRows.push({ icon: 'fa-envelope', label: appointment.email });
            }

            const serviceRows = [
                { icon: 'fa-scissors', label: appointment.service || 'Servicio no especificado', strong: true },
                { icon: 'fa-calendar-day', label: formattedDate },
                { icon: 'fa-clock', label: `${appointment.start} - ${appointment.end}` }
            ];

            const notesContent = appointment.description
                ? `<p class="info-card-note">${appointment.description}</p>`
                : '<p class="info-card-note muted">Sin notas registradas para esta cita.</p>';

            modalDetails.innerHTML = `
                <article class="info-card">
                    <p class="info-card-header">CLIENTE Y MASCOTA</p>
                    <div class="info-card-body">
                        ${buildInfoRows(clientRows)}
                    </div>
                </article>
                <article class="info-card">
                    <p class="info-card-header">SERVICIO Y HORARIO</p>
                    <div class="info-card-body">
                        ${buildInfoRows(serviceRows)}
                    </div>
                </article>
                <article class="info-card">
                    <p class="info-card-header">OBSERVACIONES</p>
                    <div class="info-card-body">
                        ${notesContent}
                    </div>
                </article>
            `;
        }

        const statusContainer = modal.querySelector('#status-buttons-container');
        if (statusContainer) {
            const statuses = [
                { key: 'confirmed', label: 'Confirmar', class: 'confirmed', icon: 'fa-circle-check' },
                { key: 'completed', label: 'Completar', class: 'completed', icon: 'fa-flag-checkered' },
                { key: 'cancelled', label: 'Cancelar', class: 'cancelled', icon: 'fa-circle-xmark' }
            ];

            statusContainer.innerHTML = statuses.map(statusInfo => {
                const isCurrentStatus = appointment.status === statusInfo.key;
                return `
                    <button
                        type="button"
                        class="status-btn ${isCurrentStatus ? 'disabled' : statusInfo.class}"
                        data-status="${statusInfo.key}"
                        data-appointment-id="${appointment.id}"
                        ${isCurrentStatus ? 'disabled' : ''}
                    >
                        <i class="fas ${statusInfo.icon}" aria-hidden="true"></i>
                        <span>${statusInfo.label}</span>
                    </button>
                `;
            }).join('');

            // --- NUEVO MANEJO DE EVENTOS ---
            statusContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('status-btn') && !e.target.disabled) {
                    e.preventDefault();
                    const newStatus = e.target.dataset.status;
                    const appointmentId = e.target.dataset.appointmentId;

                    if (newStatus === 'completed') {
                        console.log("▶️ Botón 'Completar' presionado. Iniciando transición a bitácora.");
                        const appointmentToLog = this.state.appointments.find(app => app.id == appointmentId);
                        this.transitionToLogbookView(appointmentToLog); // <-- ¡LA MAGIA OCURRE AQUÍ!
                    } else {
                        // Para otros estados, el comportamiento es el mismo
                        this.changeAppointmentStatus(appointmentId, newStatus);
                    }
                }
            });
        }

        this.showModal(modal);
    }

        // Función de cambio de estado (ahora usada por "skip" y otros estados)
        async changeAppointmentStatus(appointmentId, newStatus) {
            try {
                const response = await fetch(this.state.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'va_change_appointment_status',
                        appointment_id: appointmentId,
                        new_status: newStatus,
                        nonce: this.state.nonce
                    })
                });

                if (!response.ok) throw new Error('Error en la respuesta del servidor');
                
                const data = await response.json();
                if (data.success) {
                    const appointment = this.state.appointments.find(app => app.id == appointmentId);
                    if (appointment) {
                        appointment.status = newStatus;
                    }
                    
                    this.showSuccess('Estado actualizado correctamente');
                    const modal = this.container.querySelector('#appointment-modal');
                    this.hideModal(modal);
                    await this.reloadDataFromAJAX();

                } else {
                    throw new Error(data.data.message || 'Error al cambiar el estado');
                }
            } catch (error) {
                console.error('Error cambiando estado:', error);
                this.showError('No se pudo cambiar el estado: ' + error.message);
            }
        }

        async reloadDataFromAJAX(skipRender = false) {
            try {
                console.log('🔄 Recargando datos desde AJAX...');
                const response = await fetch(this.state.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'va_get_agenda_data',
                        professional_id: this.state.professionalId,
                        nonce: this.state.nonce
                    })
                });
                if (!response.ok) throw new Error('Error en la respuesta del servidor');
                const data = await response.json();
                console.log('📊 Datos recibidos:', data);
                if (data.success) {
                    this.state.appointments = data.data.appointments || [];
                    this.state.services = data.data.services || [];
                    console.log(`✅ Datos actualizados: ${this.state.appointments.length} citas, ${this.state.services.length} servicios`);
                    console.log(`📅 Aplicando rango: ${this.state.visiblePastDays} días pasados, ${this.state.visibleFutureDays} días futuros`);
                    
                    // Solo renderizar si no se especifica skipRender
                    if (!skipRender) {
                        this.render();
                    }
                } else {
                    throw new Error(data.data.message || 'Error al recargar datos');
                }
            } catch (error) {
                console.error('Error cargando datos desde AJAX:', error);
                this.showError('No se pudo actualizar la agenda.');
            }
        }

        render() {
            this.renderHeader();
            
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;
            
            agendaBody.innerHTML = `<div class="loading-state"><div class="loader"></div><p>Renderizando vista...</p></div>`;
            
            setTimeout(() => {
                switch (this.state.currentView) {
                    case 'day':
                        this.renderDayView();
                        break;
                    case 'week':
                        this.renderWeekView();
                        break;
                    case 'agenda':
                    default:
                        this.renderAgendaView();
                        break;
                }
            }, 50);
        }

        renderHeader() {
            const dateNavigation = this.container.querySelector('.date-navigation');
            const mobileDateNavigation = this.container.querySelector('#mobile-date-navigation .date-navigation');
            
            if (dateNavigation) {
                // Renderizar navegación según la vista actual
                switch (this.state.currentView) {
                    case 'day':
                        this.renderDayNavigation(dateNavigation);
                        break;
                    case 'week':
                        this.renderWeekNavigation(dateNavigation);
                        break;
                    default:
                        dateNavigation.innerHTML = `
                            <div class="agenda-header-simple">
                                <h2 class="dashboard-section-title">Agenda de Citas</h2>
                                <p class="agenda-subtitle">Desliza para ver más días</p>
                            </div>
                        `;
                        break;
                }
            }
            
            // Renderizar navegación móvil también
            if (mobileDateNavigation) {
                switch (this.state.currentView) {
                    case 'day':
                        this.renderDayNavigation(mobileDateNavigation);
                        break;
                    case 'week':
                        this.renderWeekNavigation(mobileDateNavigation);
                        break;
                    default:
                        mobileDateNavigation.innerHTML = `
                            <div class="agenda-header-simple mobile">
                                <h2 class="dashboard-section-title">Agenda de Citas</h2>
                                <p class="agenda-subtitle">Desliza para ver más días</p>
                            </div>
                        `;
                        break;
                }
            }
        }

        renderDayNavigation(container) {
            const currentDate = this.state.currentDate;
            const dayOfWeek = currentDate.toLocaleDateString('es-ES', { weekday: 'long' });
            const dayDate = currentDate.toLocaleDateString('es-ES', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });

            container.innerHTML = `
                <button class="nav-btn prev-day-btn" title="Día anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="date-info">
                    <h2 class="dashboard-section-title">${dayOfWeek}</h2>
                    <p class="date-subtitle">${dayDate}</p>
                </div>
                <button class="nav-btn next-day-btn" title="Día siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button class="today-btn today-day-btn">Hoy</button>
            `;

            // Configurar event listeners para navegación
            this.setupDayNavigationEvents();
        }

        renderWeekNavigation(container) {
            const currentDate = this.state.currentDate;
            const weekStart = new Date(currentDate);
            weekStart.setDate(currentDate.getDate() - currentDate.getDay() + 1); // Lunes
            
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6); // Domingo

            const weekStartStr = weekStart.toLocaleDateString('es-ES', { 
                day: 'numeric', 
                month: 'short' 
            });
            const weekEndStr = weekEnd.toLocaleDateString('es-ES', { 
                day: 'numeric', 
                month: 'short',
                year: 'numeric'
            });

            container.innerHTML = `
                <button class="nav-btn prev-week-btn" title="Semana anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="date-info">
                    <h2 class="dashboard-section-title">Semana del ${weekStartStr} - ${weekEndStr}</h2>
                </div>
                <button class="nav-btn next-week-btn" title="Semana siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <button class="today-btn today-week-btn">Hoy</button>
            `;

            // Configurar event listeners para navegación
            this.setupWeekNavigationEvents();
        }

        setupNavigationEventListeners() {
            // Event delegation para todos los botones de navegación
            this.container.addEventListener('click', (e) => {
                // Navegación de día
                if (e.target.closest('.prev-day-btn')) {
                    e.preventDefault();
                    this.state.currentDate.setDate(this.state.currentDate.getDate() - 1);
                    this.render();
                } else if (e.target.closest('.next-day-btn')) {
                    e.preventDefault();
                    this.state.currentDate.setDate(this.state.currentDate.getDate() + 1);
                    this.render();
                } else if (e.target.closest('.today-day-btn')) {
                    e.preventDefault();
                    this.state.currentDate = new Date();
                    this.render();
                }
                // Navegación de semana
                else if (e.target.closest('.prev-week-btn')) {
                    e.preventDefault();
                    this.state.currentDate.setDate(this.state.currentDate.getDate() - 7);
                    this.render();
                } else if (e.target.closest('.next-week-btn')) {
                    e.preventDefault();
                    this.state.currentDate.setDate(this.state.currentDate.getDate() + 7);
                    this.render();
                } else if (e.target.closest('.today-week-btn')) {
                    e.preventDefault();
                    this.state.currentDate = new Date();
                    this.render();
                }
                // Enlaces "Mostrar más" para cargar días adicionales
                else if (e.target.closest('.load-more-past')) {
                    e.preventDefault();
                    this.loadMorePastDays();
                } else if (e.target.closest('.load-more-future')) {
                    e.preventDefault();
                    this.loadMoreFutureDays();
                }
            });
        }

        setupDayNavigationEvents() {
            // Esta función ya no es necesaria, pero la mantenemos por compatibilidad
        }

        setupWeekNavigationEvents() {
            // Esta función ya no es necesaria, pero la mantenemos por compatibilidad
        }

        /**
         * Carga más días hacia el pasado con animación incremental
         */
        async loadMorePastDays() {
            if (this.state.visiblePastDays >= 30) return; // Límite máximo
            
            const previousDays = this.state.visiblePastDays;
            this.state.visiblePastDays += this.state.loadIncrement;
            
            console.log(`🔄 Agregando ${this.state.loadIncrement} días más hacia el pasado. Total: ${this.state.visiblePastDays}`);
            
            // Mostrar indicador de carga
            this.showLoadingIndicator('past');
            
            try {
                // Recargar datos del servidor primero (sin renderizar)
                await this.reloadDataFromAJAX(true);
                
                // Luego agregar solo los nuevos días al inicio
                this.addNewPastDays(previousDays);
            } finally {
                // Ocultar indicador de carga
                this.hideLoadingIndicator('past');
            }
        }

        /**
         * Carga más días hacia el futuro con animación incremental
         */
        async loadMoreFutureDays() {
            if (this.state.visibleFutureDays >= 60) return; // Límite máximo
            
            const previousDays = this.state.visibleFutureDays;
            this.state.visibleFutureDays += this.state.loadIncrement;
            
            console.log(`🔄 Agregando ${this.state.loadIncrement} días más hacia el futuro. Total: ${this.state.visibleFutureDays}`);
            
            // Mostrar indicador de carga
            this.showLoadingIndicator('future');
            
            try {
                // Recargar datos del servidor primero (sin renderizar)
                await this.reloadDataFromAJAX(true);
                
                // Luego agregar solo los nuevos días al final
                this.addNewFutureDays(previousDays);
            } finally {
                // Ocultar indicador de carga
                this.hideLoadingIndicator('future');
            }
        }

        /**
         * Agrega nuevos días hacia el pasado con animación
         */
        addNewPastDays(previousDays) {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const groupedAppointments = this.groupAppointmentsByDate();
            
            // Calcular los nuevos días a agregar
            const startDay = this.state.visiblePastDays;
            const endDay = previousDays;
            
            const newDaysHTML = [];
            
            for (let i = startDay; i > endDay; i--) {
                const date = new Date(today);
                date.setDate(today.getDate() - i);
                const dateKey = this.formatDate(date);
                const dayAppointments = groupedAppointments[dateKey] || [];
                const isToday = this.isToday(date);
                
                if (dayAppointments.length > 0 || this.isWithinRange(date, -this.state.visiblePastDays, this.state.visibleFutureDays)) {
                    newDaysHTML.push(this.renderDayGroup(date, dayAppointments, isToday));
                }
            }
            
            // Insertar los nuevos días al inicio con animación
            this.insertDaysWithAnimation(newDaysHTML, 'past');
            
            // Actualizar botón "mostrar más"
            this.updateLoadMoreButtons();
        }

        /**
         * Agrega nuevos días hacia el futuro con animación
         */
        addNewFutureDays(previousDays) {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const groupedAppointments = this.groupAppointmentsByDate();
            
            // Calcular los nuevos días a agregar
            const startDay = previousDays + 1;
            const endDay = this.state.visibleFutureDays;
            
            const newDaysHTML = [];
            
            for (let i = startDay; i <= endDay; i++) {
                const date = new Date(today);
                date.setDate(today.getDate() + i);
                const dateKey = this.formatDate(date);
                const dayAppointments = groupedAppointments[dateKey] || [];
                const isToday = this.isToday(date);
                
                if (dayAppointments.length > 0 || this.isWithinRange(date, -this.state.visiblePastDays, this.state.visibleFutureDays)) {
                    newDaysHTML.push(this.renderDayGroup(date, dayAppointments, isToday));
                }
            }
            
            // Insertar los nuevos días al final con animación
            this.insertDaysWithAnimation(newDaysHTML, 'future');
            
            // Actualizar botón "mostrar más"
            this.updateLoadMoreButtons();
        }

        /**
         * Inserta nuevos días con animación de aterrizaje
         */
        insertDaysWithAnimation(daysHTML, direction) {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody || daysHTML.length === 0) return;
            
            daysHTML.forEach((dayHTML, index) => {
                // Crear elemento temporal
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = dayHTML;
                const dayElement = tempDiv.firstElementChild;
                
                // Configurar estado inicial de animación
                dayElement.style.opacity = '0';
                dayElement.style.transform = direction === 'past' ? 'translateY(-30px) scale(0.95)' : 'translateY(30px) scale(0.95)';
                dayElement.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                
                // Insertar en la posición correcta
                if (direction === 'past') {
                    // Insertar después del botón "mostrar más" pasado
                    const firstLoadMore = agendaBody.querySelector('.load-more-container');
                    if (firstLoadMore && firstLoadMore.nextSibling) {
                        agendaBody.insertBefore(dayElement, firstLoadMore.nextSibling);
                    } else {
                        agendaBody.appendChild(dayElement);
                    }
                } else {
                    // Insertar antes del botón "mostrar más" futuro
                    const lastLoadMore = agendaBody.querySelector('.load-more-container:last-child');
                    if (lastLoadMore) {
                        agendaBody.insertBefore(dayElement, lastLoadMore);
                    } else {
                        agendaBody.appendChild(dayElement);
                    }
                }
                
                // Animar entrada con delay escalonado
                setTimeout(() => {
                    dayElement.style.opacity = '1';
                    dayElement.style.transform = 'translateY(0) scale(1)';
                }, index * 100 + 100); // Delay escalonado para efecto cascada
            });
        }

        /**
         * Actualiza la visibilidad de los botones "mostrar más"
         */
        updateLoadMoreButtons() {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;
            
            // Actualizar botón de días pasados
            const pastButton = agendaBody.querySelector('.load-more-past');
            if (pastButton) {
                if (this.state.visiblePastDays >= 30) {
                    pastButton.parentElement.style.display = 'none';
                } else {
                    pastButton.innerHTML = `
                        <i class="fas fa-chevron-up"></i>
                        Mostrar ${this.state.loadIncrement} días anteriores
                    `;
                }
            }
            
            // Actualizar botón de días futuros
            const futureButton = agendaBody.querySelector('.load-more-future');
            if (futureButton) {
                if (this.state.visibleFutureDays >= 60) {
                    futureButton.parentElement.style.display = 'none';
                } else {
                    futureButton.innerHTML = `
                        Mostrar ${this.state.loadIncrement} días siguientes
                        <i class="fas fa-chevron-down"></i>
                    `;
                }
            }
        }

        /**
         * Muestra indicador de carga temporal
         */
        showLoadingIndicator(direction) {
            const button = direction === 'past' 
                ? document.querySelector('.load-more-past')
                : document.querySelector('.load-more-future');
                
            if (button) {
                const originalText = button.innerHTML;
                button.dataset.originalText = originalText;
                button.innerHTML = `
                    <div class="loading-spinner"></div>
                    Cargando...
                `;
                button.disabled = true;
            }
        }

        /**
         * Oculta indicador de carga
         */
        hideLoadingIndicator(direction) {
            const button = direction === 'past' 
                ? document.querySelector('.load-more-past')
                : document.querySelector('.load-more-future');
                
            if (button && button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                button.disabled = false;
                delete button.dataset.originalText;
            }
        }

        setupHorizontalSwipes() {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;
            
            let startX = 0;
            let startY = 0;
            
            agendaBody.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }, { passive: true });
            
            agendaBody.addEventListener('touchend', (e) => {
                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                const diffX = startX - endX;
                const diffY = startY - endY;
                const minSwipeDistance = 50;
                
                // Solo para swipes horizontales en vistas de día/semana
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > minSwipeDistance) {
                    if (this.state.currentView === 'day') {
                        if (diffX > 0) {
                            this.state.currentDate.setDate(this.state.currentDate.getDate() + 1);
                            this.render();
                        } else {
                            this.state.currentDate.setDate(this.state.currentDate.getDate() - 1);
                            this.render();
                        }
                    } else if (this.state.currentView === 'week') {
                        if (diffX > 0) {
                            this.state.currentDate.setDate(this.state.currentDate.getDate() + 7);
                            this.render();
                        } else {
                            this.state.currentDate.setDate(this.state.currentDate.getDate() - 7);
                            this.render();
                        }
                    }
                }
            }, { passive: true });
        }

        renderAgendaView() {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;

            if (this.state.appointments.length === 0) {
                agendaBody.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No hay citas programadas</h3>
                        <p>Añade tu primera cita usando el botón '+' de arriba.</p>
                    </div>
                `;
                return;
            }

            const groupedAppointments = this.groupAppointmentsByDate();
            
            // Normalizar la fecha a medianoche para evitar desfases por zona horaria
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const displayStartDate = new Date(today);
            displayStartDate.setDate(today.getDate() - this.state.visiblePastDays);

            const displayEndDate = new Date(today);
            displayEndDate.setDate(today.getDate() + this.state.visibleFutureDays);

            let agendaHTML = '';
            const currentDate = new Date(displayStartDate);

            while (currentDate <= displayEndDate) {
                const dateKey = this.formatDate(currentDate);
                const dayAppointments = groupedAppointments[dateKey] || [];
                const isToday = this.isToday(currentDate);
                
                if (dayAppointments.length > 0 || this.isWithinRange(currentDate, -this.state.visiblePastDays, this.state.visibleFutureDays)) {
                    agendaHTML += this.renderDayGroup(currentDate, dayAppointments, isToday);
                }
                
                currentDate.setDate(currentDate.getDate() + 1);
            }

            // Agregar enlace "Mostrar más" para días pasados (si no hemos llegado al límite)
            let finalHTML = '';
            if (this.state.visiblePastDays < 30) {
                finalHTML += `
                    <div class="load-more-container">
                        <button class="load-more-link load-more-past">
                            <i class="fas fa-chevron-up"></i>
                            Mostrar ${this.state.loadIncrement} días anteriores
                        </button>
                    </div>
                `;
            }
            
            finalHTML += agendaHTML;
            
            // Agregar enlace "Mostrar más" para días futuros (si no hemos llegado al límite)
            if (this.state.visibleFutureDays < 60) {
                finalHTML += `
                    <div class="load-more-container">
                        <button class="load-more-link load-more-future">
                            Mostrar ${this.state.loadIncrement} días siguientes
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                `;
            }
            
            // Aplicar animación suave al cambiar contenido
            agendaBody.style.opacity = '0';
            agendaBody.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                agendaBody.innerHTML = finalHTML;
                
                // Animar entrada suave
                agendaBody.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                agendaBody.style.opacity = '1';
                agendaBody.style.transform = 'translateY(0)';
                
                // Animar cada día individualmente
                const dayGroups = agendaBody.querySelectorAll('.day-group');
                dayGroups.forEach((group, index) => {
                    group.style.opacity = '0';
                    group.style.transform = 'translateY(20px)';
                    
                    setTimeout(() => {
                        group.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                        group.style.opacity = '1';
                        group.style.transform = 'translateY(0)';
                    }, index * 50); // Delay escalonado para efecto cascada
                });
            }, 50);
        }

        renderDayGroup(date, appointments, isToday) {
            const dayOfWeek = date.toLocaleDateString('es-ES', { weekday: 'short' });
            const dayNumber = date.getDate();
            
            return `
                <div class="day-group ${isToday ? 'today-group' : ''}" data-date="${this.formatDate(date)}">
                    <div class="date-column ${isToday ? 'date-today' : ''}">
                        <div class="date-day-of-week">${dayOfWeek}</div>
                        <div class="date-day-number">${dayNumber}</div>
                    </div>
                    <div class="appointments-column ${isToday ? 'today-appointments-column' : ''}">
                        ${appointments.length > 0 ? 
                            appointments.map(app => this.renderAppointmentCard(app)).join('') : 
                            '<p class="no-appointments">No hay citas programadas.</p>'
                        }
                    </div>
                </div>
            `;
        }

        renderAppointmentCard(appointment) {
            return `
                <div class="appointment-card-vision" data-id="${appointment.id}">
                    <div class="appointment-card__time-block">${appointment.start}</div>
                    <div class="appointment-card__info-block">
                        <div>
                            <p class="appointment-time-mobile">${appointment.start} - ${appointment.end}</p>
                            <p class="main-content__service-name">${appointment.service}</p>
                            <p class="main-content__client-wrapper">${appointment.client} (${appointment.pet})</p>
                        </div>
                        <span class="status-icon status-${appointment.status}" title="${this.getStatusLabel(appointment.status)}">${this.getStatusIcon(appointment.status)}</span>
                    </div>
                </div>
            `;
        }

        showModal(modal) {
            const modalContent = modal.querySelector('.modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                if (modalContent) {
                    modalContent.classList.add('show');
                }
            }, 10);
        }

        hideModal(modal) {
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) modalContent.classList.remove('show');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        showError(message) {
            this.showNotification(message, 'error');
        }

        showNotification(message, type = 'info') {
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed; top: 20px; right: 20px; z-index: 9999;
                padding: 12px 20px; border-radius: 6px; color: white;
                background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 4000);
        }

        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        isToday(date) {
            const today = new Date();
            return date.toDateString() === today.toDateString();
        }

        groupAppointmentsByDate() {
            return this.state.appointments.reduce((acc, app) => {
                (acc[app.date] = acc[app.date] || []).push(app);
                return acc;
            }, {});
        }

        isWithinRange(date, startOffset, endOffset) {
            const today = new Date();
            const start = new Date(today);
            start.setDate(today.getDate() + startOffset);
            const end = new Date(today);
            end.setDate(today.getDate() + endOffset);
            
            return date >= start && date <= end;
        }

        getStatusLabel(status) {
            const statusLabels = {
                'pending': 'Pendiente',
                'confirmed': 'Confirmada',
                'completed': 'Completada',
                'cancelled': 'Cancelada'
            };
            return statusLabels[status] || status;
        }

        getStatusIcon(status) {
            const statusIcons = {
                'pending': '<i class="fas fa-clock"></i>',
                'confirmed': '<i class="fas fa-check"></i>',
                'completed': '<i class="fas fa-check-double"></i>',
                'cancelled': '<i class="fas fa-times"></i>'
            };
            return statusIcons[status] || '<i class="fas fa-question"></i>';
        }

        renderDayView() {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;

            const currentDate = this.state.currentDate;
            const dayOfWeek = currentDate.toLocaleDateString('es-ES', { weekday: 'long' });
            const dayDate = currentDate.toLocaleDateString('es-ES', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });

            // Obtener citas del día seleccionado
            const dateKey = this.formatDate(currentDate);
            const dayAppointments = this.state.appointments.filter(app => app.date === dateKey);
            
            // Ordenar citas por hora de inicio
            dayAppointments.sort((a, b) => a.start.localeCompare(b.start));

            let dayHTML = `
                <div class="day-view-container">
                    <div class="day-view-header">
                        <div class="day-info ${this.isToday(currentDate) ? 'today' : ''}">
                            <h2 class="day-title">${dayOfWeek}</h2>
                            <p class="day-date">${dayDate}</p>
                        </div>
                        <div class="day-stats">
                            <span class="appointments-count">${dayAppointments.length} citas</span>
                        </div>
                    </div>
                    <div class="day-schedule">
                        <div class="time-column">
                            ${this.generateTimeSlots()}
                        </div>
                        <div class="appointments-column">
                            <div class="appointments-grid">
                                ${this.renderDayAppointments(dayAppointments)}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            agendaBody.innerHTML = dayHTML;
        }

        renderWeekView() {
            const agendaBody = this.container.querySelector('#agenda-body-container');
            if (!agendaBody) return;

            const currentDate = this.state.currentDate;
            const weekStart = new Date(currentDate);
            weekStart.setDate(currentDate.getDate() - currentDate.getDay() + 1); // Lunes

            let weekHTML = `
                <div class="week-view-container">
                    <div class="week-grid">
            `;

            // Generar columnas para cada día de la semana
            for (let i = 0; i < 7; i++) {
                const dayDate = new Date(weekStart);
                dayDate.setDate(weekStart.getDate() + i);
                
                const dayKey = this.formatDate(dayDate);
                const dayAppointments = this.state.appointments.filter(app => app.date === dayKey);
                
                const dayName = dayDate.toLocaleDateString('es-ES', { weekday: 'short' });
                const dayNumber = dayDate.getDate();
                const isToday = this.isToday(dayDate);

                weekHTML += `
                    <div class="week-day-column ${isToday ? 'today' : ''}">
                        <div class="week-day-header">
                            <div class="week-day-name">${dayName}</div>
                            <div class="week-day-number ${isToday ? 'today' : ''}">${dayNumber}</div>
                        </div>
                        <div class="week-day-appointments">
                            ${dayAppointments.length > 0 ? 
                                dayAppointments.map(app => this.renderWeekAppointmentCard(app)).join('') : 
                                '<p class="no-appointments-week">Sin citas</p>'
                            }
                        </div>
                    </div>
                `;
            }

            weekHTML += `
                    </div>
                </div>
            `;

            agendaBody.innerHTML = weekHTML;
        }

        generateTimeSlots() {
            let timeSlotsHTML = '';
            for (let hour = 8; hour <= 20; hour++) {
                const time = `${hour.toString().padStart(2, '0')}:00`;
                timeSlotsHTML += `
                    <div class="day-time-slot">
                        <div class="time-label">${time}</div>
                    </div>
                `;
            }
            return timeSlotsHTML;
        }

        renderDayAppointments(appointments) {
            if (appointments.length === 0) {
                return '<p class="no-appointments-week">No hay citas programadas para hoy</p>';
            }

            return appointments.map(app => {
                const startHour = parseInt(app.start.split(':')[0]);
                const startMinute = parseInt(app.start.split(':')[1]);
                const top = (startHour - 8) * 80 + (startMinute / 60) * 80;
                
                return `
                    <div class="day-appointment-card status-${app.status}" 
                         style="top: ${top}px; height: 60px;" 
                         data-id="${app.id}">
                        <div class="appointment-time">${app.start} - ${app.end}</div>
                        <div class="appointment-service">${app.service}</div>
                        <div class="appointment-client">${app.client} (${app.pet})</div>
                        <span class="status-icon status-${app.status}" title="${this.getStatusLabel(app.status)}">
                            ${this.getStatusIcon(app.status)}
                        </span>
                    </div>
                `;
            }).join('');
        }

        renderWeekAppointmentCard(appointment) {
            return `
                <div class="week-appointment-card status-${appointment.status}" data-id="${appointment.id}">
                    <div class="appointment-time">${appointment.start} - ${appointment.end}</div>
                    <div class="appointment-service">${appointment.service}</div>
                    <div class="appointment-client">${appointment.client} (${appointment.pet})</div>
                    <span class="status-icon status-${appointment.status}" title="${this.getStatusLabel(appointment.status)}">
                        ${this.getStatusIcon(appointment.status)}
                    </span>
                </div>
            `;
        }
    }

    // Inicialización global
    window.initVeterinaliaAgendaModule = function() {
        // Exponer la instancia globalmente para que otros módulos (wizard) puedan refrescar datos sin recargar la página
        window.VA_AgendaModule = new VeterinaliaAgendaModule('agenda-module');
    };

})(jQuery);
