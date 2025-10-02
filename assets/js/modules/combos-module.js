/**
 * Módulo de Combos de Servicios - Veterinalia Appointment Plugin
 * Versión: 1.0.0 - Gestión de paquetes de servicios
 */
(function($) {
    'use strict';

    class VeterinaliaCombosModule {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            
            if (!this.container) {
                return;
            }
            
            this.state = {
                combos: [],
                services: [],
                selectedServices: [],
                editingCombo: null,
                professionalId: null,
                nonce: null,
                ajax_url: null,
                // Estados para navegación móvil
                currentView: 'list', // 'list' | 'details'
                currentCombo: null
            };

            this.mobileUI = new MobileUI(this);
            
            this.init();
        }

        async init() {
            try {
                this.loadInitialDataFromJSON();
                this.setupEventListeners();
                this.updateStats();
            } catch (error) {
                this.showError('Error al cargar el módulo de combos: ' + error.message);
            }
        }
        
        loadInitialDataFromJSON() {
            const dataScript = document.getElementById('combos-initial-data');
            if (!dataScript) throw new Error("Faltan los datos iniciales para el módulo de combos.");
            
            try {
                const data = JSON.parse(dataScript.textContent);
                this.state.professionalId = data.professional_id;
                this.state.combos = data.combos || [];
                
                // Validar y normalizar servicios
                this.state.services = (data.services || []).map(service => ({
                    id: service.id || 0,
                    name: service.name || 'Servicio sin nombre',
                    price: parseFloat(service.price) || 0,
                    duration: parseInt(service.duration) || 60
                }));
                
                this.state.nonce = data.nonce;
                this.state.ajax_url = data.ajax_url;

            } catch (e) {
                throw new Error("Los datos iniciales del módulo de combos son inválidos.");
            }
        }

        setupEventListeners() {
            this.setupHeaderEventListeners();
            this.setupCardEventListeners();
            this.setupModalEventListeners();
            this.setupFormEventListeners();
        }
        
        setupHeaderEventListeners() {
            // Botones para crear combo (desktop y móvil)
            const addBtns = [
                this.container.querySelector('#add-combo-btn'),
                this.container.querySelector('#mobile-add-combo-btn')
            ];
            
            addBtns.forEach((btn, index) => {
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        this.showComboModal();
                    });
                }
            });
        }

        setupCardEventListeners() {
            const combosGrid = this.container.querySelector('.combos-grid');
            if (!combosGrid) return;

            combosGrid.addEventListener('click', (e) => {
                const comboCard = e.target.closest('.combo-card');
                if (!comboCard) return;

                const comboId = parseInt(comboCard.dataset.comboId);
                const combo = this.state.combos.find(c => c.id === comboId);
                if (!combo) return;

                // Acciones específicas
                if (e.target.closest('.edit-combo-btn')) {
                    e.preventDefault();
                    this.editCombo(combo);
                } else if (e.target.closest('.duplicate-combo-btn')) {
                    e.preventDefault();
                    this.duplicateCombo(combo);
                } else if (e.target.closest('.delete-combo-btn')) {
                    e.preventDefault();
                    this.showDeleteModal(combo);
                } else if (e.target.closest('.use-combo-btn')) {
                    e.preventDefault();
                    this.useCombo(combo);
                }
            });
        }

        setupModalEventListeners() {
            // Modal principal de combo
            const comboModal = this.container.querySelector('#combo-modal');
            if (comboModal) {
                const closeBtn = comboModal.querySelector('#close-combo-modal');
                const cancelBtn = comboModal.querySelector('#cancel-combo-btn');
                
                if (closeBtn) {
                    closeBtn.addEventListener('click', () => this.hideModal(comboModal));
                }
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', () => this.hideModal(comboModal));
                }
                
                comboModal.addEventListener('click', (e) => {
                    if (e.target === comboModal) this.hideModal(comboModal);
                });
            }

            // Modal de confirmación de eliminación
            const deleteModal = this.container.querySelector('#delete-combo-modal');
            if (deleteModal) {
                const cancelBtn = deleteModal.querySelector('#cancel-delete-btn');
                const confirmBtn = deleteModal.querySelector('#confirm-delete-btn');
                
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', () => this.hideModal(deleteModal));
                }
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', () => this.confirmDelete());
                }
                
                deleteModal.addEventListener('click', (e) => {
                    if (e.target === deleteModal) this.hideModal(deleteModal);
                });
            }
        }

        setupFormEventListeners() {
            const form = this.container.querySelector('#combo-form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleComboSubmit(form);
                });
            }

            // Selector de servicios
            this.setupServicesSelector();
            
            // Campo de precio con cálculo automático de descuento
            const priceInput = this.container.querySelector('#combo-price');
            if (priceInput) {
                priceInput.addEventListener('input', () => {
                    this.updateSavingsDisplay();
                });
            }
        }

        setupServicesSelector() {
            const searchInput = this.container.querySelector('#services-search');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.filterAvailableServices(e.target.value);
                });
            }

            // Event delegation para botones de servicios
            const availableList = this.container.querySelector('#available-services-list');
            const selectedList = this.container.querySelector('#selected-services-list');
            
            if (availableList) {
                availableList.addEventListener('click', (e) => {
                    if (e.target.closest('.add-service-btn')) {
                        const serviceOption = e.target.closest('.service-option');
                        this.addServiceToCombo(serviceOption);
                    }
                });
            }

            if (selectedList) {
                selectedList.addEventListener('click', (e) => {
                    if (e.target.closest('.remove-service-btn')) {
                        const serviceItem = e.target.closest('.selected-service-item');
                        this.removeServiceFromCombo(serviceItem);
                    }
                });
            }

            // Inicializar lista de servicios disponibles
            this.renderAvailableServices();
        }

        showComboModal(combo = null) {
            const modal = this.container.querySelector('#combo-modal');
            const title = modal?.querySelector('#combo-modal-title');
            const form = modal?.querySelector('#combo-form');
            
            if (!modal || !title || !form) {
                return;
            }

            // Resetear estado
            this.state.editingCombo = combo;
            this.state.selectedServices = [];
            
            if (combo) {
                // Modo edición
                title.textContent = 'Editar Combo';
                this.loadComboDataToForm(combo);
            } else {
                // Modo creación
                title.textContent = 'Crear Nuevo Combo';
                form.reset();
                this.clearSelectedServices();
            }

            this.renderAvailableServices();
            
            this.showModal(modal);
            
            // Forzar visibilidad después de un breve delay
            setTimeout(() => {
                this.forceModalVisibility(modal);
            }, 100);
        }

        loadComboDataToForm(combo) {
            const form = this.container.querySelector('#combo-form');
            if (!form) return;

            // Campos básicos
            form.querySelector('#combo-name').value = combo.name || '';
            form.querySelector('#combo-description').value = combo.description || '';
            form.querySelector('#combo-duration').value = combo.duration || '';
            form.querySelector('#combo-price').value = parseFloat(combo.combo_price) || '';

            // Servicios seleccionados (simulados para el ejemplo)
            this.state.selectedServices = (combo.services || []).map((serviceName, index) => ({
                id: index + 1,
                name: serviceName || 'Servicio sin nombre',
                price: parseFloat(20 + (index * 10)) || 0, // Precios simulados
                duration: parseInt(30) || 30
            }));
            
            this.renderSelectedServices();
            this.updateCombototals();
            this.updateSavingsDisplay();
        }

        addServiceToCombo(serviceOption) {
            const serviceId = parseInt(serviceOption.dataset.serviceId) || 0;
            const serviceName = serviceOption.dataset.serviceName || 'Servicio sin nombre';
            const servicePrice = parseFloat(serviceOption.dataset.servicePrice) || 0;
            const serviceDuration = parseInt(serviceOption.dataset.serviceDuration) || 60;

            // Verificar si ya está seleccionado
            if (this.state.selectedServices.find(s => s.id === serviceId)) {
                this.showError('Este servicio ya está incluido en el combo');
                return;
            }

            // Agregar servicio
            this.state.selectedServices.push({
                id: serviceId,
                name: serviceName,
                price: servicePrice,
                duration: serviceDuration
            });

            this.renderSelectedServices();
            this.updateCombototals();
            this.updateSavingsDisplay();
            
            // Feedback visual
            serviceOption.style.opacity = '0.5';
            setTimeout(() => {
                if (serviceOption.style) serviceOption.style.opacity = '1';
            }, 500);
        }

        removeServiceFromCombo(serviceItem) {
            const serviceId = parseInt(serviceItem.dataset.serviceId);
            this.state.selectedServices = this.state.selectedServices.filter(s => s.id !== serviceId);
            
            this.renderSelectedServices();
            this.updateCombototals();
            this.updateSavingsDisplay();
        }

        renderAvailableServices() {
            const container = this.container.querySelector('#available-services-list');
            if (!container) return;

            if (this.state.services.length === 0) {
                container.innerHTML = '<div class="empty-selection"><p>No hay servicios disponibles</p></div>';
                return;
            }

            container.innerHTML = this.state.services.map(service => {
                // Convertir price a número y validar
                const price = parseFloat(service.price) || 0;
                const duration = parseInt(service.duration) || 60;
                
                return `
                    <div class="service-option" 
                         data-service-id="${service.id}" 
                         data-service-name="${service.name || 'Servicio sin nombre'}"
                         data-service-price="${price}"
                         data-service-duration="${duration}">
                        <span class="service-name">${service.name || 'Servicio sin nombre'}</span>
                        <span class="service-price">$${price.toFixed(2)}</span>
                        <button type="button" class="btn-small add-service-btn">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                `;
            }).join('');
        }

        renderSelectedServices() {
            const container = this.container.querySelector('#selected-services-list');
            if (!container) return;

            if (this.state.selectedServices.length === 0) {
                container.innerHTML = `
                    <div class="empty-selection">
                        <i class="fas fa-arrow-left"></i>
                        <p>Selecciona servicios de la izquierda</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = this.state.selectedServices.map(service => {
                const price = parseFloat(service.price) || 0;
                return `
                    <div class="selected-service-item" data-service-id="${service.id}">
                        <span class="service-name">${service.name || 'Servicio sin nombre'}</span>
                        <span class="service-price">$${price.toFixed(2)}</span>
                        <button type="button" class="btn-small remove-btn remove-service-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            }).join('');
        }

        updateCombototals() {
            const totalPrice = this.state.selectedServices.reduce((sum, service) => {
                const price = parseFloat(service.price) || 0;
                return sum + price;
            }, 0);
            
            const totalDuration = this.state.selectedServices.reduce((sum, service) => {
                const duration = parseInt(service.duration) || 0;
                return sum + duration;
            }, 0);
            
            const priceEl = this.container.querySelector('#total-individual-price');
            const durationEl = this.container.querySelector('#total-duration');
            const totalsEl = this.container.querySelector('#combo-totals');
            
            if (priceEl) priceEl.textContent = `$${totalPrice.toFixed(2)}`;
            if (durationEl) durationEl.textContent = `${totalDuration} min`;
            
            if (totalsEl) {
                totalsEl.style.display = this.state.selectedServices.length > 0 ? 'block' : 'none';
            }

            // Actualizar duración sugerida en el formulario
            const durationInput = this.container.querySelector('#combo-duration');
            if (durationInput && !durationInput.value) {
                durationInput.value = totalDuration;
            }
        }

        updateSavingsDisplay() {
            const totalIndividualPrice = this.state.selectedServices.reduce((sum, service) => {
                const price = parseFloat(service.price) || 0;
                return sum + price;
            }, 0);
            
            const comboPriceInput = this.container.querySelector('#combo-price');
            const savingsDisplay = this.container.querySelector('#savings-display');
            const savingsAmount = this.container.querySelector('#savings-amount');
            const discountPercentage = this.container.querySelector('#discount-percentage');
            
            if (!comboPriceInput || !savingsDisplay) return;
            
            const comboPrice = parseFloat(comboPriceInput.value) || 0;
            
            if (comboPrice > 0 && totalIndividualPrice > 0 && comboPrice < totalIndividualPrice) {
                const savings = totalIndividualPrice - comboPrice;
                const discount = (savings / totalIndividualPrice) * 100;
                
                if (savingsAmount) savingsAmount.textContent = `$${savings.toFixed(2)}`;
                if (discountPercentage) discountPercentage.textContent = `${discount.toFixed(0)}%`;
                
                savingsDisplay.style.display = 'block';
            } else {
                savingsDisplay.style.display = 'none';
            }
        }

        filterAvailableServices(searchTerm) {
            const serviceOptions = this.container.querySelectorAll('.service-option');
            
            serviceOptions.forEach(option => {
                const serviceName = option.dataset.serviceName.toLowerCase();
                const matches = serviceName.includes(searchTerm.toLowerCase());
                option.style.display = matches ? 'flex' : 'none';
            });
        }

        clearSelectedServices() {
            this.state.selectedServices = [];
            this.renderSelectedServices();
            this.updateCombototals();
            this.updateSavingsDisplay();
        }

        async handleComboSubmit(form) {
            const formData = new FormData(form);
            
            // Validaciones
            if (this.state.selectedServices.length === 0) {
                this.showError('Debes seleccionar al menos un servicio para el combo');
                return;
            }

            const comboData = {
                name: formData.get('combo-name') || this.container.querySelector('#combo-name').value,
                description: formData.get('combo-description') || this.container.querySelector('#combo-description').value,
                duration: parseInt(this.container.querySelector('#combo-duration').value) || 0,
                combo_price: parseFloat(this.container.querySelector('#combo-price').value) || 0,
                services: this.state.selectedServices.map(s => s.id),
                professional_id: this.state.professionalId,
                nonce: this.state.nonce
            };

            // Validaciones adicionales
            if (!comboData.name.trim()) {
                this.showError('El nombre del combo es obligatorio');
                return;
            }

            if (comboData.combo_price <= 0) {
                this.showError('El precio del combo debe ser mayor a 0');
                return;
            }

            try {
                const action = this.state.editingCombo ? 'va_update_combo' : 'va_create_combo';
                if (this.state.editingCombo) {
                    comboData.combo_id = this.state.editingCombo.id;
                }

                // Simulación de guardado exitoso (aquí iría la llamada AJAX real)
                await this.simulateSaveCombo(comboData);
                
                this.showSuccess(this.state.editingCombo ? 'Combo actualizado correctamente' : 'Combo creado correctamente');
                const modal = this.container.querySelector('#combo-modal');
                this.hideModal(modal);
                
                // Actualizar la vista (aquí se recargarian los datos reales)
                this.updateComboInState(comboData);
                this.updateStats();
                
            } catch (error) {
                this.showError('No se pudo guardar el combo: ' + error.message);
            }
        }

        async simulateSaveCombo(comboData) {
            // Simulación de delay de red
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve({ success: true });
                }, 500);
            });
        }

        updateComboInState(comboData) {
            if (this.state.editingCombo) {
                // Actualizar combo existente
                const index = this.state.combos.findIndex(c => c.id === this.state.editingCombo.id);
                if (index !== -1) {
                    this.state.combos[index] = { ...this.state.editingCombo, ...comboData };
                }
            } else {
                // Agregar nuevo combo
                const newCombo = {
                    id: Date.now(), // ID temporal
                    ...comboData,
                    services: this.state.selectedServices.map(s => s.name),
                    original_price: this.state.selectedServices.reduce((sum, s) => sum + s.price, 0),
                    savings: this.state.selectedServices.reduce((sum, s) => sum + s.price, 0) - comboData.combo_price,
                    status: 'active'
                };
                this.state.combos.push(newCombo);
            }
        }

        editCombo(combo) {
            this.showComboModal(combo);
        }

        duplicateCombo(combo) {
            const duplicatedCombo = {
                ...combo,
                name: combo.name + ' (Copia)',
                id: null // Será un nuevo combo
            };
            this.showComboModal(duplicatedCombo);
        }

        showDeleteModal(combo) {
            this.state.comboToDelete = combo;
            const modal = this.container.querySelector('#delete-combo-modal');
            this.showModal(modal);
        }

        async confirmDelete() {
            if (!this.state.comboToDelete) return;

            try {
                // Aquí iría la llamada AJAX real para eliminar
                await this.simulateDeleteCombo(this.state.comboToDelete.id);
                
                // Eliminar del estado local
                this.state.combos = this.state.combos.filter(c => c.id !== this.state.comboToDelete.id);
                
                this.showSuccess('Combo eliminado correctamente');
                this.updateStats();
                
                const modal = this.container.querySelector('#delete-combo-modal');
                this.hideModal(modal);
                
            } catch (error) {
                this.showError('No se pudo eliminar el combo');
            }
        }

        async simulateDeleteCombo(comboId) {
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve({ success: true });
                }, 300);
            });
        }

        useCombo(combo) {
            // Aquí se implementaría la lógica para usar el combo en una cita
            this.showSuccess(`Combo "${combo.name}" seleccionado para nueva cita`);
            
            // Ejemplo: navegar al módulo de agenda con el combo preseleccionado
            // window.dashboardController?.showModule('agenda', { preselectedCombo: combo });
        }

        updateStats() {
            const activeCount = this.state.combos.filter(c => c.status === 'active').length;
            
            const desktopCounter = this.container.querySelector('#active-combos-count');
            const mobileCounter = this.container.querySelector('#mobile-active-combos-count');
            
            if (desktopCounter) desktopCounter.textContent = activeCount;
            if (mobileCounter) mobileCounter.textContent = activeCount;
        }

        showModal(modal) {
            if (!modal) {
                return;
            }
            
            const modalContent = modal.querySelector('.modal-content');
            
            // Remover clase hidden
            modal.classList.remove('hidden');
            
            // Asegurar visibilidad inmediata
            if (modalContent) {
                modalContent.style.opacity = '1';
                modalContent.style.transform = 'scale(1)';
                modalContent.style.visibility = 'visible';
            }
            
            // Agregar clase show con delay para la animación
            setTimeout(() => {
                if (modalContent) {
                    modalContent.classList.add('show');
                }
            }, 50);
        }

        forceModalVisibility(modal) {
            if (!modal) return;
            
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                // Forzar estilos inline para asegurar visibilidad
                modalContent.style.setProperty('opacity', '1', 'important');
                modalContent.style.setProperty('transform', 'scale(1)', 'important');
                modalContent.style.setProperty('visibility', 'visible', 'important');
                modalContent.style.setProperty('display', 'block', 'important');
            }
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
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed; top: 20px; right: 20px; z-index: 9999;
                padding: 12px 20px; border-radius: 6px; color: white;
                background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease;
            `;
            
            // Añadir animación CSS
            if (!document.querySelector('#notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 4000);
        }
    }

    // ===================================================================
    // === GESTOR DE LA INTERFAZ MÓVIL (HEADER DINÁMICO)              ===
    // ===================================================================
    class MobileUI {
        constructor(parent) {
            this.parent = parent;
            this.state = {
                currentView: 'list'
            };
            this.init();
        }

        init() {
            this.cacheDOMElements();
            this.bindEvents();
        }

        cacheDOMElements() {
            this.dom = {
                container: this.parent.container,
                // Elementos del Header Principal
                headerTitle: this.parent.container.querySelector('.module-header .dashboard-section-title'),
                backButton: this.parent.container.querySelector('.module-header .back-to-prof-main'),
                addButton: this.parent.container.querySelector('.module-header .add-new-item-btn'),
                // Vistas Móviles
                listView: this.parent.container.querySelector('#combos-list-view-container'),
                detailsView: this.parent.container.querySelector('#combo-details-view-container'),
                listContainer: this.parent.container.querySelector('.mobile-list-container'),
                detailsContainer: this.parent.container.querySelector('#combo-details-target')
            };
        }

        bindEvents() {
            // Navegación en lista de combos
            if (this.dom.listContainer) {
                this.dom.listContainer.addEventListener('click', (e) => {
                    this.handleListNavigation(e);
                });
            }

            // Botón de volver dinámico
            if (this.dom.backButton) {
                this.dom.backButton.addEventListener('click', (e) => {
                    this.handleBackNavigation(e);
                });
            }
        }

        handleListNavigation(e) {
            const comboItem = e.target.closest('.combo-item');
            if (comboItem) {
                e.stopPropagation();
                const comboId = parseInt(comboItem.dataset.comboId);
                const combo = this.parent.state.combos.find(c => c.id === comboId);
                if (combo) {
                    this.navigateToDetailsView(combo);
                }
                return;
            }

            // Botón de acciones
            const actionsBtn = e.target.closest('.item-actions-btn');
            if (actionsBtn) {
                e.stopPropagation();
                const comboItem = actionsBtn.closest('.combo-item');
                const comboId = parseInt(comboItem.dataset.comboId);
                const combo = this.parent.state.combos.find(c => c.id === comboId);
                if (combo) {
                    this.showMobileActionsMenu(combo);
                }
            }
        }

        handleBackNavigation(e) {
            if (this.state.currentView === 'details') {
                e.preventDefault();
                e.stopPropagation();
                this.navigateToListView();
            }
            // Si está en 'list', deja que el evento normal de volver funcione
        }

        navigateToDetailsView(combo) {
            this.state.currentView = 'details';
            this.parent.state.currentCombo = combo;

            // Actualizar Header
            this.dom.headerTitle.textContent = combo.name;
            this.dom.backButton.querySelector('span').textContent = 'Combos';

            // Cambiar Vistas
            this.dom.listView.classList.remove('is-active');
            this.dom.listView.classList.add('is-exiting');
            this.dom.detailsView.classList.remove('is-exiting');
            this.dom.detailsView.classList.add('is-active');

            // Cargar detalles del combo
            this.loadComboDetails(combo);
        }

        navigateToListView() {
            this.state.currentView = 'list';
            this.parent.state.currentCombo = null;

            // Restaurar Header
            this.dom.headerTitle.textContent = 'Combos de Servicios';
            this.dom.backButton.querySelector('span').textContent = 'Volver';

            // Cambiar Vistas
            this.dom.detailsView.classList.remove('is-active');
            this.dom.listView.classList.remove('is-exiting');
            this.dom.listView.classList.add('is-active');
        }

        loadComboDetails(combo) {
            this.dom.detailsContainer.innerHTML = `
                <div class="combo-detail-card">
                    <div class="combo-detail-header">
                        <h2>${combo.name}</h2>
                    </div>
                    
                    <div class="combo-detail-description">
                        <p>${combo.description}</p>
                    </div>
                    
                    <div class="combo-detail-services">
                        <h3>Servicios incluidos</h3>
                        <ul class="services-included">
                            ${combo.services.map(service => `
                                <li class="service-included-item">
                                    <i class="fas fa-check"></i>
                                    ${service}
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                    
                    <div class="combo-detail-footer">
                        <div class="combo-detail-meta">
                            <div class="duration-info">
                                <i class="fas fa-clock"></i>
                                ${combo.duration} min
                            </div>
                            <div class="savings-info">
                                <i class="fas fa-tag"></i>
                                Ahorras $${combo.savings.toFixed(2)}
                            </div>
                            <div class="combo-price-info">
                                <span class="original-price">$${combo.original_price.toFixed(2)}</span>
                                <span class="current-price">$${combo.combo_price.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="combo-detail-actions">
                            <button class="btn-primary use-combo-btn" data-combo-id="${combo.id}">
                                <i class="fas fa-calendar-plus"></i>
                                Agendar
                            </button>
                            <button class="btn-secondary edit-combo-btn" data-combo-id="${combo.id}">
                                <i class="fas fa-edit"></i>
                                Editar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Agregar event listeners a los botones
            this.dom.detailsContainer.addEventListener('click', (e) => {
                if (e.target.closest('.use-combo-btn')) {
                    this.parent.useCombo(combo);
                } else if (e.target.closest('.edit-combo-btn')) {
                    this.parent.editCombo(combo);
                }
            });
        }

        showMobileActionsMenu(combo) {
            // Crear menú contextual móvil
            const actions = [
                { icon: 'fas fa-calendar-plus', label: 'Agendar', action: () => this.parent.useCombo(combo) },
                { icon: 'fas fa-edit', label: 'Editar', action: () => this.parent.editCombo(combo) },
                { icon: 'fas fa-copy', label: 'Duplicar', action: () => this.parent.duplicateCombo(combo) },
                { icon: 'fas fa-trash', label: 'Eliminar', action: () => this.parent.showDeleteModal(combo) }
            ];

            // Implementar un simple action sheet
            const actionSheet = document.createElement('div');
            actionSheet.className = 'mobile-action-sheet-overlay';
            actionSheet.innerHTML = `
                <div class="mobile-action-sheet">
                    <div class="action-sheet-header">
                        <h3>${combo.name}</h3>
                    </div>
                    <div class="action-sheet-buttons">
                        ${actions.map(action => `
                            <button class="action-sheet-btn" data-action="${action.label.toLowerCase()}">
                                <i class="${action.icon}"></i>
                                ${action.label}
                            </button>
                        `).join('')}
                        <button class="action-sheet-btn cancel">
                            Cancelar
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(actionSheet);

            // Event listeners
            actionSheet.addEventListener('click', (e) => {
                if (e.target.classList.contains('cancel') || e.target.classList.contains('mobile-action-sheet-overlay')) {
                    actionSheet.remove();
                } else {
                    const btn = e.target.closest('.action-sheet-btn');
                    if (btn && btn.dataset.action) {
                        const action = actions.find(a => a.label.toLowerCase() === btn.dataset.action);
                        if (action) action.action();
                        actionSheet.remove();
                    }
                }
            });
        }
    }

    // Función global para inicialización desde el dashboard controller
    window.initVeterinaliaCombosModule = function() {
        const combosElement = document.getElementById('combos-module');
        
        if (combosElement) {
            try {
                const moduleInstance = new VeterinaliaCombosModule('combos-module');
            } catch (error) {
                // Error silencioso para evitar problemas en producción
            }
        }
    };

})(jQuery);
