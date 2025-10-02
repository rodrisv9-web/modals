// assets/js/modules/professional-services.js (V6.0 - Mobile UI con Header Unificado)

/**
 * ===================================================================
 * === GESTOR DE LA INTERFAZ MÓVIL (HEADER UNIFICADO)             ===
 * ===================================================================
 */
const MobileUI = {
    state: { currentView: 'categories', currentCategoryId: null, currentCategoryName: '', professionalId: null, },
    init: function() { const container = document.getElementById('services-module-v2'); if (!container) return; this.state.professionalId = container.dataset.professionalId; this.cacheDOMElements(); this.bindEvents(); console.log('Módulo de Servicios v6.0: UI Móvil Inicializada.'); },
    
    cacheDOMElements: function() {
        this.dom = {
            container: document.getElementById('services-module-v2'),
            // Elementos del Header Principal
            mainHeader: document.querySelector('.services-module-container > .module-header'),
            headerTitle: document.querySelector('.services-module-container > .module-header .dashboard-section-title'),
            backButton: document.querySelector('.services-module-container > .module-header .back-to-prof-main'),
            addButton: document.querySelector('.services-module-container > .module-header .add-new-item-btn'),
            // Vistas Móviles
            categoryView: document.getElementById('category-view-container'),
            serviceView: document.getElementById('service-view-container'),
            categoryList: document.querySelector('#category-view-container .mobile-list-container'),
            serviceList: document.getElementById('services-list-target'),
        };
    },

    bindEvents: function() {
        this.dom.container.addEventListener('click', (e) => {
            const target = e.target;
            // Manejo de botones del header
            if (target.closest('.back-to-prof-main') && this.state.currentView === 'categories') { return; } // La acción por defecto ya funciona
            if (target.closest('.back-to-prof-main') && this.state.currentView === 'services') { e.preventDefault(); this.navigateToCategoriesView(); return; }
            if (target.closest('.add-new-item-btn')) { e.preventDefault(); this.handleAddButtonClick(); return; }
            
            // Lógica existente
            const actionsBtn = target.closest('.item-actions-btn');
            const actionsMenu = target.closest('.item-actions-menu');
            if (!actionsBtn && !actionsMenu) { this.closeItemActionsMenu(); }
            if (target.closest('#action-create-category') || target.closest('#action-import-template') || target.closest('#action-cancel-add') || target.closest('.mobile-modal-backdrop')) { this.handleAddActions(e); return; }
            if (actionsBtn) { e.preventDefault(); e.stopPropagation(); this.showItemActionsMenu(actionsBtn); return; }
            if (actionsMenu) { this.handleActionsMenuClick(e); return; }
            if (target.closest('.category-item')) { this.handleNavigation(e); return; }
        });
    },
    
    handleAddButtonClick: function() {
        if (this.state.currentView === 'categories') {
            this.showAddActionsMenu();
        } else {
            this.renderServiceModal();
        }
    },

    navigateToServicesView: function(categoryId, categoryName) {
        this.state.currentView = 'services';
        this.state.currentCategoryId = categoryId;
        this.state.currentCategoryName = categoryName;

        // Actualizar Header
        this.dom.headerTitle.textContent = categoryName;
        this.dom.backButton.querySelector('span').textContent = 'Categorías';

        // Cambiar Vistas
        this.dom.categoryView.classList.remove('is-active');
        this.dom.categoryView.classList.add('is-exiting');
        this.dom.serviceView.classList.remove('is-exiting');
        this.dom.serviceView.classList.add('is-active');
        
        this.loadServicesForCategory(categoryId);
    },

    navigateToCategoriesView: function() {
        this.state.currentView = 'categories';
        this.state.currentCategoryId = null;
        this.state.currentCategoryName = '';
        
        // Restaurar Header
        this.dom.headerTitle.textContent = 'Administrar Servicios';
        this.dom.backButton.querySelector('span').textContent = 'Volver';

        // Cambiar Vistas
        this.dom.serviceView.classList.remove('is-active');
        this.dom.categoryView.classList.remove('is-exiting');
        this.dom.categoryView.classList.add('is-active');
        
        this.closeAddActionsMenu();
    },

    // --- Resto de funciones de MobileUI sin cambios ---
    handleNavigation: function(e) { const categoryItem = e.target.closest('.category-item'); if (categoryItem) { this.navigateToServicesView(categoryItem.dataset.categoryId, categoryItem.dataset.categoryName); } },
    handleAddActions: function(e) { if (e.target.closest('#action-create-category')) { this.closeAddActionsMenu(); setTimeout(() => this.renderCategoryModal(), 350); } else if (e.target.closest('#action-import-template')) { this.closeAddActionsMenu(); setTimeout(() => this.renderTemplateImportModal(), 350); } else if (e.target.closest('#action-cancel-add') || e.target.closest('.mobile-modal-backdrop')) { e.preventDefault(); this.closeAddActionsMenu(); } },
    handleActionsMenuClick: function(e) { const item = e.target.closest('.mobile-list-item'); if (!item) return; if (e.target.closest('.edit-action')) { e.preventDefault(); item.classList.contains('category-item') ? this.renderCategoryModal(item.dataset.categoryId, item.querySelector('.item-name').textContent) : this.renderServiceModal(item.dataset.serviceId, { name: item.querySelector('.item-name').textContent, duration: item.dataset.duration, price: item.dataset.price }); this.closeItemActionsMenu(); } if (e.target.closest('.delete-action')) { e.preventDefault(); if (confirm('¿Estás seguro de que quieres eliminar esto?')) { item.classList.contains('category-item') ? this.deleteCategory(item.dataset.categoryId) : this.deleteService(item.dataset.serviceId); } this.closeItemActionsMenu(); } },
    showAddActionsMenu: function() { 
        const container = document.getElementById('mobile-add-actions-container'); 
        if (container) { 
            container.classList.add('visible'); 
        } 
    },
    closeAddActionsMenu: function() { 
        const container = document.getElementById('mobile-add-actions-container'); 
        if (container && container.classList.contains('visible')) { 
            container.classList.add('is-closing');
            setTimeout(() => { 
                container.classList.remove('visible', 'is-closing'); 
            }, 300); 
        } 
    },
    renderTemplateImportModal: async function() {
        this.closeModal();
        const modalHtml = `
            <div class="modal-overlay visible">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Importar desde Plantilla</h3>
                        <button class="modal-close-btn" id="modal-cancel-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <form>
                        <div id="mobile-templates-list" style="max-height: 40vh; overflow-y: auto; margin-bottom: 1rem;">
                            <p class="loading-message">Cargando plantillas...</p>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" id="modal-cancel-btn-2">Cancelar</button>
                            <button type="button" class="btn-primary" id="modal-import-btn" disabled>Importar</button>
                        </div>
                    </form>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        document.getElementById('modal-cancel-btn').onclick = () => this.closeModal();
        document.getElementById('modal-cancel-btn-2').onclick = () => this.closeModal();
        document.querySelector('.modal-overlay').onclick = (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        };
        
        const response = await VAApi.getServiceTemplates();
        const listContainer = document.getElementById('mobile-templates-list');
        
        if (response.success && response.data.length > 0) {
            const templatesHtml = response.data.map(template => `
                <label class="template-list-item">
                    <input type="radio" name="template_selection" class="form-checkbox" value="${template.id}">
                    <div class="template-info">
                        <span>${template.name}</span>
                        <p>${template.description || ''}</p>
                    </div>
                </label>
            `).join('');
            listContainer.innerHTML = templatesHtml;

            listContainer.addEventListener('change', () => { document.getElementById('modal-import-btn').disabled = false; });

            document.getElementById('modal-import-btn').onclick = async () => {
                const selectedId = listContainer.querySelector('input[name="template_selection"]:checked')?.value;
                if (!selectedId) return;
                
                const importResponse = await VAApi.importTemplate(this.state.professionalId, selectedId);
                if (importResponse.success) {
                    this.closeModal();
                    document.querySelector('.quick-action-card[data-module="services"]').click();
                    alert('Plantilla importada con éxito.');
                } else {
                    alert('Error al importar: ' + (importResponse.data.message || 'Error desconocido.'));
                }
            };
        } else {
            listContainer.innerHTML = '<p class="empty-list-message">No hay plantillas disponibles.</p>';
        }
    },
    renderCategoryModal: function(id = null, name = '') {
        this.closeModal(); 
        const isEditing = id !== null; 
        const title = isEditing ? 'Editar Categoría' : 'Añadir Nueva Categoría'; 
        const buttonText = isEditing ? 'Guardar Cambios' : 'Añadir Categoría'; 
        const modalHtml = `
            <div class="modal-overlay visible">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close-btn" id="modal-cancel-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                    <form>
                        <div class="form-group">
                            <label for="modal-category-name" class="form-label">Nombre de la categoría</label>
                            <input type="text" id="modal-category-name" class="form-input" placeholder="Ej: Consulta General" value="${name}">
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" id="modal-cancel-btn-2">Cancelar</button>
                            <button type="button" class="btn-primary" id="modal-save-category-btn" data-id="${id || ''}">${buttonText}</button>
                        </div>
                    </form>
                </div>
            </div>`; 
        document.body.insertAdjacentHTML('beforeend', modalHtml); 
        document.getElementById('modal-save-category-btn').onclick = () => this.saveCategory(isEditing); 
        document.getElementById('modal-cancel-btn').onclick = () => this.closeModal(); 
        document.getElementById('modal-cancel-btn-2').onclick = () => this.closeModal();
        document.querySelector('.modal-overlay').onclick = (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        };
    },
    renderServiceModal: async function(id = null, data = {}) {
        this.closeModal(); 
        const isEditing = id !== null; 
        const title = isEditing ? 'Editar Servicio' : 'Añadir Nuevo Servicio'; 
        const buttonText = isEditing ? 'Guardar Cambios' : 'Añadir Servicio';

        // Obtener tipos de entrada
        let entryTypesOptions = '<option value="">Cargando...</option>';
        try {
            const response = await VAApi.getEntryTypes();
            if (response.success && response.data.length > 0) {
                entryTypesOptions = response.data.map(et => 
                    `<option value="${et.entry_type_id}">${et.name}</option>`
                ).join('');
            } else {
                entryTypesOptions = '<option value="">No hay tipos disponibles</option>';
            }
        } catch (error) {
            entryTypesOptions = '<option value="">Error al cargar</option>';
        }

        const modalHtml = `
            <div class="modal-overlay visible">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="modal-close-btn" id="modal-cancel-btn"><i class="fas fa-times"></i></button>
                    </div>
                    <form>
                        <div class="form-group">
                            <label for="modal-service-name" class="form-label">Nombre del servicio</label>
                            <input type="text" id="modal-service-name" class="form-input" placeholder="Ej: Consulta Veterinaria" value="${data.name || ''}">
                        </div>
                        <div class="form-group">
                            <label for="modal-entry-type" class="form-label">Tipo de Entrada (para historial)*</label>
                            <select id="modal-entry-type" class="form-input">${entryTypesOptions}</select>
                        </div>
                        <div class="form-grid cols-2">
                            <div class="form-group">
                                <label for="modal-service-duration" class="form-label">Duración (min)</label>
                                <input type="number" id="modal-service-duration" class="form-input" placeholder="30" value="${data.duration || '30'}">
                            </div>
                            <div class="form-group">
                                <label for="modal-service-price" class="form-label">Precio ($)</label>
                                <input type="number" id="modal-service-price" class="form-input" placeholder="0.00" step="0.01" value="${data.price || '0.00'}">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" id="modal-cancel-btn-2">Cancelar</button>
                            <button type="button" class="btn-primary" id="modal-save-service-btn" data-id="${id || ''}">${buttonText}</button>
                        </div>
                    </form>
                </div>
            </div>`; 
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Si estamos editando, seleccionamos el entry_type guardado
        if (isEditing && data.entry_type_id) {
            document.getElementById('modal-entry-type').value = data.entry_type_id;
        }

        document.getElementById('modal-save-service-btn').onclick = () => this.saveService(isEditing); 
        document.getElementById('modal-cancel-btn').onclick = () => this.closeModal(); 
        document.getElementById('modal-cancel-btn-2').onclick = () => this.closeModal();
    },
    closeModal: function() {
        // Seleccionamos los modales con las nuevas clases unificadas
        const modalOverlay = document.querySelector('.modal-overlay.visible');

        if (modalOverlay) {
            modalOverlay.classList.add('is-closing');
            setTimeout(() => {
                modalOverlay.remove();
            }, 300);
        }
    },
    saveCategory: async function(isEditing) {
        const id = document.getElementById('modal-save-category-btn').dataset.id; 
        const name = document.getElementById('modal-category-name').value.trim(); 
        if (!name) { 
            alert('El nombre es obligatorio.'); 
            return; 
        } 
        const response = isEditing ? await VAApi.editCategory(id, name) : await VAApi.saveCategory(this.state.professionalId, name); 
        if (response.success) { 
            this.closeModal(); 
            if (isEditing) { 
                const categoryItem = this.dom.categoryList.querySelector(`.category-item[data-category-id="${response.data.category.id}"]`); 
                if (categoryItem) { 
                    categoryItem.querySelector('.item-name').textContent = response.data.category.name; 
                    categoryItem.dataset.categoryName = response.data.category.name; 
                    categoryItem.classList.add('is-updated'); 
                    setTimeout(() => categoryItem.classList.remove('is-updated'), 800); 
                } 
            } else { 
                this.dom.categoryList.querySelector('.empty-list-message')?.remove(); 
                const newCategoryHtml = `<div class="mobile-list-item category-item is-new" data-category-id="${response.data.category.id}" data-category-name="${response.data.category.name}"><span class="item-name">${response.data.category.name}</span><button class="item-actions-btn" aria-label="Acciones para ${response.data.category.name}"><i class="fas fa-ellipsis-v"></i></button></div>`; 
                this.dom.categoryList.insertAdjacentHTML('beforeend', newCategoryHtml); 
            } 
            this.closeAddActionsMenu();  // Asegura que el menú esté cerrado después de guardar
        } else { 
            alert('Error: ' + (response.data.message || 'No se pudo guardar la categoría.')); 
        }
    },
    deleteCategory: async function(categoryId) { const response = await VAApi.deleteCategory(this.state.professionalId, categoryId); if(response.success) { document.querySelector(`.category-item[data-category-id="${categoryId}"]`)?.remove(); } else { alert('Error: ' + (response.data.message || 'No se pudo eliminar la categoría.')); } },
    loadServicesForCategory: async function(categoryId) {
        this.dom.serviceList.innerHTML = `<p class="loading-message">Cargando servicios...</p>`;
        const response = await VAApi.getServicesForCategory(categoryId);
        if (response.success && response.data) {
            if (response.data.length > 0) {
                const servicesHtml = response.data.map(service => `<div class="mobile-list-item service-item" data-service-id="${service.service_id}" data-duration="${service.duration}" data-price="${service.price}"><div class="item-details"><span class="item-name">${service.name}</span><span class="item-subtext">${service.duration} min - $${parseFloat(service.price).toFixed(2)}</span></div><button class="item-actions-btn" aria-label="Acciones para ${service.name}"><i class="fas fa-ellipsis-v"></i></button></div>`).join('');
                this.dom.serviceList.innerHTML = servicesHtml;
            } else {
                this.dom.serviceList.innerHTML = `<p class="empty-list-message">No hay servicios en esta categoría. Toca '+' para añadir el primero.</p>`;
            }
        } else {
            this.dom.serviceList.innerHTML = `<p class="empty-list-message">Error al cargar los servicios.</p>`;
        }
    },
    saveService: async function(isEditing) { 
        const id = document.getElementById('modal-save-service-btn').dataset.id;
        const serviceData = { 
            name: document.getElementById('modal-service-name').value.trim(), 
            duration: document.getElementById('modal-service-duration').value, 
            price: document.getElementById('modal-service-price').value,
            entry_type_id: document.getElementById('modal-entry-type').value // <-- NUEVO
        }; 

        if (!serviceData.name || !serviceData.duration || !serviceData.price) { 
            alert('Nombre, duración y precio son obligatorios.'); 
            return; 
        } 
        
        if (!serviceData.entry_type_id) { // <-- NUEVA VALIDACIÓN
            alert('Por favor, selecciona un Tipo de Entrada para el servicio.');
            return;
        }

        const response = isEditing ? 
            await VAApi.editService(id, serviceData) : 
            await VAApi.saveService({ 
                professional_id: this.state.professionalId, 
                category_id: this.state.currentCategoryId, 
                service_name: serviceData.name, 
                service_duration: serviceData.duration, 
                service_price: serviceData.price,
                entry_type_id: serviceData.entry_type_id // <-- NUEVO
            }); 
            
        if (response.success) { 
            this.closeModal(); 
            this.loadServicesForCategory(this.state.currentCategoryId); 
        } else { 
            alert('Error: ' + (response.data.message || 'No se pudo guardar el servicio.')); 
        } 
    },
    deleteService: async function(serviceId) { const response = await VAApi.deleteService(this.state.professionalId, serviceId); if (response.success) { document.querySelector(`.service-item[data-service-id="${serviceId}"]`)?.remove(); } else { alert('Error: ' + (response.data.message || 'No se pudo eliminar el servicio.')); } },
    showItemActionsMenu: function(button) { this.closeItemActionsMenu(); const item = button.closest('.mobile-list-item'); const menuHtml = `<div class="item-actions-menu"><button class="edit-action"><i class="fas fa-pencil-alt"></i> Editar</button><button class="delete-action"><i class="fas fa-trash-alt"></i> Eliminar</button></div>`; item.style.position = 'relative'; item.insertAdjacentHTML('beforeend', menuHtml); setTimeout(() => { item.querySelector('.item-actions-menu').style.display = 'block'; }, 0); },
    closeItemActionsMenu: function() { document.querySelector('.item-actions-menu')?.remove(); }
};


/**
 * ===================================================================
 * === GESTOR DE LA INTERFAZ DE ESCRITORIO (SIN CAMBIOS)          ===
 * ===================================================================
 */
const DesktopUI = {
    // ... (Toda la lógica de DesktopUI se mantiene exactamente igual que en la versión anterior) ...
    state: { professionalId: null, currentCategoryId: null, },
    init: function() { const container = document.getElementById('services-module-v2'); if (!container) return; this.state.professionalId = container.dataset.professionalId; this.cacheDOMElements(); this.bindEvents(); this.selectFirstCategory(); console.log('Módulo de Servicios v6.0: UI de Escritorio Inicializada.'); },
    cacheDOMElements: function() { this.dom = { container: document.getElementById('services-module-v2'), categorySidebar: document.getElementById('desktop-category-nav'), servicesContentArea: document.getElementById('services-content-area'), addActionsButton: document.getElementById('show-add-actions-modal-btn'), }; },
    bindEvents: function() { this.dom.categorySidebar.addEventListener('click', (e) => { const categoryTab = e.target.closest('.category-tab'); if (!categoryTab) return; if (e.target.closest('.edit-category-btn')) { e.preventDefault(); const categoryName = categoryTab.querySelector('span').textContent; this.renderCategoryModal(categoryTab.dataset.categoryId, categoryName); } else if (e.target.closest('.delete-category-btn')) { e.preventDefault(); this.deleteCategory(categoryTab.dataset.categoryId); } else { this.handleCategoryClick(categoryTab); } }); this.dom.addActionsButton.addEventListener('click', (e) => { e.preventDefault(); this.renderCategoryModal(); }); this.dom.servicesContentArea.addEventListener('click', (e) => { if (e.target.closest('.add-service-btn')) { e.preventDefault(); this.renderServiceModal(); } const serviceCard = e.target.closest('.service-card'); if (!serviceCard) return; if (e.target.closest('.edit-service-btn')) { e.preventDefault(); const serviceId = serviceCard.dataset.serviceId; const data = { name: serviceCard.querySelector('h4').textContent, description: serviceCard.querySelector('p').textContent, duration: serviceCard.querySelector('.duration').textContent.match(/\d+/)[0], price: serviceCard.querySelector('.price').textContent.replace('$', '') }; this.renderServiceModal(serviceId, data); } else if (e.target.closest('.delete-service-btn')) { e.preventDefault(); this.deleteService(serviceCard.dataset.serviceId); } }); },
    selectFirstCategory: function() { const firstCategory = this.dom.categorySidebar.querySelector('.category-tab'); if (firstCategory) { firstCategory.click(); } else { this.dom.servicesContentArea.innerHTML = `<div class="empty-state"><i class="fas fa-folder-plus"></i><h3>Bienvenido</h3><p>Crea tu primera categoría usando el botón (+) en la esquina superior derecha.</p></div>`; } },
    handleCategoryClick: function(categoryTab) { const categoryId = categoryTab.dataset.categoryId; if (this.state.currentCategoryId === categoryId) return; this.dom.categorySidebar.querySelectorAll('.category-tab').forEach(tab => tab.classList.remove('active')); categoryTab.classList.add('active'); this.state.currentCategoryId = categoryId; this.loadServicesForCategory(categoryId); },
    loadServicesForCategory: async function(categoryId) { this.dom.servicesContentArea.innerHTML = `<div class="empty-state"><p>Cargando servicios...</p></div>`; try { const response = await VAApi.getServicesForCategory(categoryId); if (response.success && response.data) { this.renderServiceCards(response.data); } else { throw new Error('No se pudieron cargar los servicios.'); } } catch (error) { this.dom.servicesContentArea.innerHTML = `<div class="empty-state"><p>Error al cargar servicios.</p></div>`; } },
    renderServiceCards: function(services) { const categoryName = this.dom.categorySidebar.querySelector('.category-tab.active span')?.textContent || 'Servicios'; let headerHtml = `<div class="services-header"><h3>${categoryName}</h3><button class="btn btn-primary add-service-btn"><i class="fas fa-plus"></i> Añadir Servicio</button></div>`; let servicesHtml; if (services.length > 0) { servicesHtml = services.map(service => `<div class="service-card" data-service-id="${service.service_id}"><div class="service-card-body"><h4>${service.name}</h4><p>${service.description || 'Sin descripción.'}</p></div><div class="service-card-footer"><span class="duration"><i class="far fa-clock"></i> ${service.duration} min</span><span class="price">$${parseFloat(service.price).toFixed(2)}</span></div><div class="service-card-actions"><button class="action-btn edit-service-btn" title="Editar"><i class="fas fa-pencil-alt"></i></button><button class="action-btn delete-service-btn delete" title="Eliminar"><i class="fas fa-trash-alt"></i></button></div></div>`).join(''); } else { servicesHtml = `<div class="empty-state"><i class="fas fa-box-open"></i><h3>No hay servicios aquí</h3><p>Añade tu primer servicio a esta categoría usando el botón de arriba.</p></div>`; } this.dom.servicesContentArea.innerHTML = headerHtml + `<div class="services-grid">${servicesHtml}</div>`; },
    renderModal: function(config) { this.closeModal(); const modalHtml = `<div id="desktop-modal-container"><div class="modal-backdrop"></div><div class="modal-container slide-in-up"><div class="modal-content"><div class="modal-header"><h3>${config.title}</h3><button class="close-btn">&times;</button></div><div class="modal-body">${config.bodyHtml}</div><div class="modal-footer"><button class="btn btn-secondary" id="modal-cancel-btn">Cancelar</button><button class="btn btn-primary" id="modal-save-btn" data-id="${config.id || ''}">${config.buttonText}</button></div></div></div></div>`; document.body.insertAdjacentHTML('beforeend', modalHtml); const modalContainer = document.getElementById('desktop-modal-container'); modalContainer.querySelector('.close-btn').onclick = () => this.closeModal(); modalContainer.querySelector('#modal-cancel-btn').onclick = () => this.closeModal(); modalContainer.querySelector('.modal-backdrop').onclick = () => this.closeModal(); modalContainer.querySelector('#modal-save-btn').onclick = config.onSave; },
    closeModal: function() { document.getElementById('desktop-modal-container')?.remove(); },
    renderCategoryModal: function(id = null, name = '') { const isEditing = id !== null; this.renderModal({ title: isEditing ? 'Editar Categoría' : 'Añadir Nueva Categoría', buttonText: isEditing ? 'Guardar Cambios' : 'Añadir Categoría', id: id, bodyHtml: `<div class="form-group"><label for="modal-category-name" class="form-label">Nombre de la categoría</label><input type="text" id="modal-category-name" class="form-input" value="${name}" required></div>`, onSave: () => this.saveCategory(isEditing) }); },
    renderServiceModal: async function(id = null, data = {}) {
        if (!this.state.currentCategoryId && id === null) {
            alert("Por favor, selecciona una categoría primero.");
            return;
        }
        const isEditing = id !== null;
    
        // Obtener tipos de entrada
        let entryTypesOptions = '<option value="">Cargando...</option>';
        try {
            const response = await VAApi.getEntryTypes();
            if (response.success && response.data.length > 0) {
                entryTypesOptions = response.data.map(et => 
                    `<option value="${et.entry_type_id}">${et.name}</option>`
                ).join('');
            } else {
                entryTypesOptions = '<option value="">No hay tipos disponibles</option>';
            }
        } catch (error) {
            entryTypesOptions = '<option value="">Error al cargar</option>';
        }
    
        this.renderModal({
            title: isEditing ? 'Editar Servicio' : 'Añadir Nuevo Servicio',
            buttonText: isEditing ? 'Guardar Cambios' : 'Añadir Servicio',
            id: id,
            bodyHtml: `
                <div class="form-group">
                    <label for="modal-service-name" class="form-label">Nombre del servicio</label>
                    <input type="text" id="modal-service-name" class="form-input" value="${data.name || ''}" required>
                </div>
                <div class="form-group">
                    <label for="modal-entry-type" class="form-label">Tipo de Entrada (para historial)*</label>
                    <select id="modal-entry-type" class="form-input">${entryTypesOptions}</select>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal-service-duration" class="form-label">Duración (min)</label>
                        <input type="number" id="modal-service-duration" class="form-input" value="${data.duration || '30'}" min="5" required>
                    </div>
                    <div class="form-group">
                        <label for="modal-service-price" class="form-label">Precio ($)</label>
                        <input type="number" id="modal-service-price" class="form-input" value="${data.price || '0.00'}" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="modal-service-description" class="form-label">Descripción (opcional)</label>
                    <textarea id="modal-service-description" class="form-input" rows="3">${data.description || ''}</textarea>
                </div>`,
            onSave: () => this.saveService(isEditing)
        });
    
        // Si estamos editando, seleccionamos el entry_type guardado
        if (isEditing && data.entry_type_id) {
            document.getElementById('modal-entry-type').value = data.entry_type_id;
        }
    },
    saveCategory: async function(isEditing) { const id = document.getElementById('modal-save-btn').dataset.id; const name = document.getElementById('modal-category-name').value.trim(); if (!name) { alert('El nombre es obligatorio.'); return; } const response = isEditing ? await VAApi.editCategory(id, name) : await VAApi.saveCategory(this.state.professionalId, name); if (response.success) { this.closeModal(); document.querySelector('.quick-action-card[data-module="services"]').click(); } else { alert('Error: ' + (response.data.message || 'No se pudo guardar la categoría.')); } },
    deleteCategory: async function(categoryId) { if (confirm('¿Estás seguro de que quieres eliminar esta categoría? Todos sus servicios también serán eliminados.')) { const response = await VAApi.deleteCategory(this.state.professionalId, categoryId); if (response.success) { document.querySelector('.quick-action-card[data-module="services"]').click(); } else { alert('Error: ' + (response.data.message || 'No se pudo eliminar la categoría.')); } } },
    saveService: async function(isEditing) {
        const id = document.getElementById('modal-save-btn').dataset.id;
        const serviceData = {
            name: document.getElementById('modal-service-name').value.trim(),
            duration: document.getElementById('modal-service-duration').value,
            price: document.getElementById('modal-service-price').value,
            description: document.getElementById('modal-service-description').value,
            entry_type_id: document.getElementById('modal-entry-type').value // <-- NUEVO
        };
    
        if (!serviceData.name || !serviceData.duration || !serviceData.price) {
            alert('Nombre, duración y precio son obligatorios.');
            return;
        }
    
        if (!serviceData.entry_type_id) { // <-- NUEVA VALIDACIÓN
            alert('Por favor, selecciona un Tipo de Entrada para el servicio.');
            return;
        }
    
        const response = isEditing ?
            await VAApi.editService(id, serviceData) :
            await VAApi.saveService({
                professional_id: this.state.professionalId,
                category_id: this.state.currentCategoryId,
                service_name: serviceData.name,
                service_duration: serviceData.duration,
                service_price: serviceData.price,
                entry_type_id: serviceData.entry_type_id // <-- NUEVO
            });
    
        if (response.success) {
            this.closeModal();
            this.loadServicesForCategory(this.state.currentCategoryId);
        } else {
            alert('Error: ' + (response.data.message || 'No se pudo guardar el servicio.'));
        }
    },
    deleteService: async function(serviceId) { if (confirm('¿Estás seguro de que quieres eliminar este servicio?')) { const response = await VAApi.deleteService(this.state.professionalId, serviceId); if (response.success) { this.loadServicesForCategory(this.state.currentCategoryId); } else { alert('Error: ' + (response.data.message || 'No se pudo eliminar el servicio.')); } } }
};


/**
 * ===================================================================
 * === INICIALIZADOR PRINCIPAL DEL MÓDULO                         ===
 * ===================================================================
 */
(function($) {
    window.VA_Professional_Services = {
        init: function() {
            if (window.innerWidth < 768) {
                MobileUI.init();
            } else {
                DesktopUI.init();
            }
        }
    };
})(jQuery);

/**
 * Patch: Control local del botón "Volver" con pasos
 * - Si está en sub-paso (vista 'services'), vuelve al paso anterior dentro del módulo
 * - Si está en el primer paso (vista 'categories'), vuelve al dashboard general
 * - Intercepta en fase de captura para evitar los listeners globales del dashboard
 */
(function() {
    try {
        if (typeof MobileUI === 'undefined' || !MobileUI || typeof MobileUI.bindEvents !== 'function') return;
        const originalBindEvents = MobileUI.bindEvents.bind(MobileUI);
        MobileUI.bindEvents = function() {
            originalBindEvents();
            try {
                if (!this.dom || !this.dom.container) return;
                this.dom.container.addEventListener('click', (e) => {
                    const target = e.target;
                    const back = target && target.closest && target.closest('.back-to-prof-main');
                    if (!back) return;

                    // Control condicional de navegación
                    e.preventDefault();
                    if (this.state && this.state.currentView === 'services') {
                        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
                        this.navigateToCategoriesView();
                    } else if (this.state && this.state.currentView === 'categories') {
                        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
                        if (typeof window.returnToDashboard === 'function') window.returnToDashboard();
                    }
                }, true); // fase de captura
            } catch (_) {}
        };
    } catch (_) {}
})();
