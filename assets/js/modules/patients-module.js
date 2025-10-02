/**
 * MÓDULO "MIS PACIENTES" - CRM VETERINARIO v2.1 (CORREGIDO)
 * Sistema de gestión de pacientes con navegación móvil mejorada
 * Gestión multi-profesional de expedientes médicos
 * 
 * CORRECCIÓN v2.1: Solucionado problema de navegación del botón "Volver" en interfaz móvil
 */
   
// ====================================================================
// ESTADO GLOBAL DEL MÓDULO
// ====================================================================

let activeClientId = null;         // Cliente actualmente seleccionado
let db = { clients: [], pets: [], appointments: [] }; // Base de datos filtrada del profesional
let globalDB = { clients: [], pets: [], appointments: [] }; // Base de datos completa del sistema
let professionalId = 0;           // ID del profesional actual
let searchFilter = '';            // Filtro de búsqueda activo
let isMobileInterface = false;    // Flag para detectar si estamos en interfaz móvil

// Flags para controlar enlace de listeners globales y re-bind tras reinyección
let documentListenersBound = false;
let documentClickHandler = null;
let documentKeydownHandler = null;
// Habilitar/deshabilitar logs de depuración
const DEBUG = true;
function dlog(...args) {
    if (DEBUG) console.log('[PatientsModule]', ...args);
}

// Estado global compartido entre re-evaluaciones del script
if (!window.PatientsModuleGlobal) {
    window.PatientsModuleGlobal = {
        listenersBound: false,
        moduleInitialized: false,
        initializing: false
    };
} else {
    // Ensure new flag exists if script was previously loaded
    if (typeof window.PatientsModuleGlobal.initializing === 'undefined') {
        window.PatientsModuleGlobal.initializing = false;
    }
}

/**
 * Reemplaza un elemento por su clon para limpiar event listeners previos
 * y devuelve la nueva referencia.
 */
function replaceElementWithCloneById(id) {
    const el = document.getElementById(id);
    if (!el || !el.parentNode) return el;
    const clone = el.cloneNode(true);
    el.parentNode.replaceChild(clone, el);
    return clone;
}

// ====================================================================
// CONFIGURACIÓN DE API
// ====================================================================

let apiConfig = {
    baseUrl: '',
    nonce: ''
};

/**
 * Inicializa la configuración de la API
 */
function initializeDatabase() {
    // Obtener configuración de la API desde el script de datos iniciales
    const initialDataScript = document.getElementById('patients-initial-data');
    if (initialDataScript) {
        try {
            const initialData = JSON.parse(initialDataScript.textContent);
            professionalId = initialData.professional_id;
            
            // Configurar URL base y nonce
            if (window.VA_REST && window.VA_REST.api_url) {
                apiConfig.baseUrl = window.VA_REST.api_url;
                apiConfig.nonce = window.VA_REST.api_nonce;
            } else {
                // Fallback para configuración manual
                apiConfig.baseUrl = '/wp-json/vetapp/v1/';
                apiConfig.nonce = initialData.nonce || '';
            }
            
            console.log('🏥 API configurada:', {
                baseUrl: apiConfig.baseUrl,
                nonce: apiConfig.nonce ? 'Presente' : 'Falta',
                professionalId: professionalId
            });
            
        } catch (e) {
            console.error('Error parsing initial data:', e);
            return;
        }
    } else {
        console.error('No se encontró el script de datos iniciales');
        return;
    }

    // Cargar datos iniciales desde la API
    loadClientsFromAPI();
}

/**
 * Realiza una petición a la API REST
 * @param {string} endpoint - Endpoint de la API
 * @param {object} options - Opciones de la petición
 * @returns {Promise} Respuesta de la API
 */
async function apiRequest(endpoint, options = {}) {
    const url = apiConfig.baseUrl + endpoint;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include'
    };

    // Solo añadir nonce si existe
    if (apiConfig.nonce) {
        defaultOptions.headers['X-WP-Nonce'] = apiConfig.nonce;
    }

    const requestOptions = { ...defaultOptions, ...options };
    
    try {
        console.log(`🌐 API Request: ${requestOptions.method || 'GET'} ${url}`);
        console.log('🔧 Request options:', requestOptions);
        
        const response = await fetch(url, requestOptions);
        
        console.log(`📡 Response status: ${response.status} ${response.statusText}`);
        
        if (!response.ok) {
            let errorData;
            try {
                errorData = await response.json();
                console.error('❌ Error response data:', errorData);
            } catch (e) {
                errorData = { message: `HTTP ${response.status}: ${response.statusText}` };
            }
            
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }
        
        const data = await response.json();
        console.log(`✅ API Response:`, data);
        return data;
        
    } catch (error) {
        window.PatientsModuleGlobal.initializing = false;
        console.error(`❌ API Error:`, error);
        console.error('❌ Full error object:', {
            message: error.message,
            stack: error.stack,
            url: url,
            options: requestOptions
        });
        throw error;
    }
}

/**
 * Carga los clientes desde la API
 */
async function loadClientsFromAPI() {
    try {
        showLoadingState();
        
        if (!professionalId) {
            throw new Error('Professional ID no configurado');
        }
        
        console.log(`🔍 Cargando clientes para professional_id: ${professionalId}`);
        
        const response = await apiRequest(`patients/clients?professional_id=${professionalId}`);
        
        if (response && response.success) {
            const clients = response.data || [];
            
            console.log(`📊 Datos recibidos: ${clients.length} clientes`);
            
            if (clients.length === 0) {
                // No hay clientes, mostrar estado vacío
                showEmptyState();
                return;
            }
            
            // Transformar datos de la API al formato esperado por el frontend
            db.clients = clients.map(client => ({
                id: parseInt(client.client_id, 10),
                name: client.name,
                email: client.email,
                phone: client.phone,
                address: client.address,
                notes: client.notes,
                dateCreated: client.date_created,
                pets: (client.pets || []).map(pet => ({
                    id: parseInt(pet.pet_id, 10),
                    clientId: parseInt(pet.client_id, 10),
                    name: pet.name,
                    species: pet.species,
                    breed: pet.breed,
                    birthDate: pet.birth_date,
                    gender: pet.gender,
                    weight: pet.weight,
                    microchipNumber: pet.microchip_number,
                    shareCode: pet.share_code,
                    notes: pet.notes,
                    dateCreated: pet.date_created
                }))
            }));
            
            // Flatten pets for easier access
            db.pets = [];
            db.clients.forEach(client => {
                if (client.pets) {
                    db.pets.push(...client.pets);
                }
            });
            
            console.log(`✅ Datos procesados: ${db.clients.length} clientes, ${db.pets.length} mascotas`);
            
            renderClientsList();
            hideLoadingState();
            
        } else {
            throw new Error('Respuesta de API inválida: ' + JSON.stringify(response));
        }
        
    } catch (error) {
        console.error('❌ Error loading clients:', error);
        showNotification('Error al cargar los datos de pacientes: ' + error.message, 'error');
        
        showErrorState(error.message);
    }
}

/**
 * Muestra estado vacío cuando no hay clientes
 */
function showEmptyState() {
    const container = document.getElementById('clients-list');
    if (!container) {
        window.PatientsModuleGlobal.initializing = false;
        dlog('showEmptyState: clients-list container NOT found');
        return;
    }
    container.innerHTML = `
        <div class="loading-placeholder">
            <div style="text-align: center; padding: 2rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin-bottom: 1rem; opacity: 0.5;">
                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 style="margin: 0 0 0.5rem 0; color: #6B7280;">No tienes pacientes aún</h3>
                <p style="margin: 0 0 1rem 0; color: #9CA3AF; font-size: 0.875rem;">Comienza añadiendo tu primer cliente</p>
                <button onclick="showNewClientModal()" style="padding: 0.5rem 1rem; background: #3182CE; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ➕ Añadir Primer Cliente
                </button>
            </div>
        </div>
    `;
}

/**
 * Muestra estado de error
 */
function showErrorState(errorMessage) {
    const container = document.getElementById('clients-list');
    if (!container) {
        dlog('showErrorState: clients-list container NOT found, message=', errorMessage);
        return;
    }
    container.innerHTML = `
        <div class="loading-placeholder">
            <div style="text-align: center; padding: 2rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin-bottom: 1rem; color: #EF4444;">
                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3 style="margin: 0 0 0.5rem 0; color: #EF4444;">Error al cargar pacientes</h3>
                <p style="margin: 0 0 1rem 0; color: #6B7280; font-size: 0.875rem;">${errorMessage}</p>
                <button onclick="loadClientsFromAPI()" style="margin-right: 0.5rem; padding: 0.5rem 1rem; background: #3182CE; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    🔄 Reintentar
                </button>
                <button onclick="showNewClientModal()" style="padding: 0.5rem 1rem; background: #10B981; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ➕ Añadir Cliente
                </button>
            </div>
        </div>
    `;
}

/**
 * Muestra el estado de carga
 */
function showLoadingState() {
    const container = document.getElementById('clients-list');
    if (!container) {
        dlog('showLoadingState: clients-list container NOT found');
        return;
    }
    container.innerHTML = `
        <div class="loading-placeholder">
            <div class="loader"></div>
            <p>Cargando pacientes...</p>
        </div>
    `;
}

/**
 * Oculta el estado de carga
 */
function hideLoadingState() {
    // El estado se oculta cuando se renderiza la lista real
}

// ====================================================================
// UTILIDADES
// ====================================================================

/**
 * Genera un código único de compartir para una mascota
 * @param {string} name - Nombre de la mascota
 * @returns {string} Código único en formato MASCOTA-XXXX
 */
function generateShareCode(name = 'MASCOTA') {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 4; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return `${name.substring(0, 6).toUpperCase()}-${code}`;
}

/**
 * Genera las iniciales de un nombre para el avatar
 * @param {string} name - Nombre completo
 * @returns {string} Iniciales (máximo 2 caracteres)
 */
function getInitials(name) {
    if (!name) return '??';
    const names = name.trim().split(' ');
    if (names.length === 1) return names[0].substring(0, 2).toUpperCase();
    return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
}

/**
 * Obtiene el icono y clase CSS para el avatar de una mascota
 * @param {string} species - Especie de la mascota
 * @returns {object} {icon, className}
 */
function getPetAvatarInfo(species) {
    const speciesMap = {
        dog: { icon: '🐕', className: 'dog' },
        cat: { icon: '🐱', className: 'cat' },
        bird: { icon: '🐦', className: 'other' },
        rabbit: { icon: '🐰', className: 'other' },
        hamster: { icon: '🐹', className: 'other' }
    };
    return speciesMap[species] || { icon: '🐾', className: 'other' };
}

/**
 * Formatea una fecha en formato legible
 * @param {string} dateString - Fecha en formato ISO
 * @returns {string} Fecha formateada
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const day = date.getDate();
    const month = date.toLocaleString('es', { month: 'short' }).toUpperCase();
    const year = date.getFullYear();
    return `${day} ${month}, ${year}`;
}

/**
 * Copia texto al portapapeles
 * @param {string} text - Texto a copiar
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Código copiado al portapapeles', 'success');
    } catch (err) {
        console.error('Error al copiar:', err);
        showNotification('Error al copiar el código', 'error');
    }
}

/**
 * Muestra una notificación temporal
 * @param {string} message - Mensaje a mostrar
 * @param {string} type - Tipo de notificación (success, error, info)
 */
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3182CE'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Animar entrada
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Remover después de 3 segundos
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// ====================================================================
// INTERFAZ MÓVIL - MÁQUINA DE ESTADOS
// ====================================================================

/**
 * Clase para gestionar la interfaz móvil con navegación por pasos
 */
class MobileUIManager {
    constructor() {
        this.state = {
            currentView: 'client',           // 'client', 'pet', 'history'
            selectedClientId: null,          // ID del cliente seleccionado
            selectedPetId: null,             // ID de la mascota seleccionada
            history: []                      // Historial de navegación para el botón "Volver"
        };
    }

    /**
     * Navega a una vista específica
     * @param {string} view - Vista destino ('client', 'pet', 'history')
     * @param {object} data - Datos adicionales para la vista
     */
    navigateTo(view, data = {}) {
        dlog(`MobileUIManager: navigating to ${view}`, data);

        // Guardar vista actual en el historial
        if (this.state.currentView !== view) {
            this.state.history.push({
                view: this.state.currentView,
                clientId: this.state.selectedClientId,
                petId: this.state.selectedPetId
            });
        }

        // Actualizar estado
        const previousView = this.state.currentView;
        this.state.currentView = view;
        
        if (data.clientId) this.state.selectedClientId = data.clientId;
        if (data.petId) this.state.selectedPetId = data.petId;

        // Realizar transición
        this.performViewTransition(previousView, view);
        
        // Actualizar header
        this.updateHeader();
        
        // Cargar contenido de la vista
        this.loadViewContent(view, data);
    }

    /**
     * Vuelve a la vista anterior
     */
    navigateBack() {
        dlog('MobileUIManager: navigateBack called');
        
        if (this.state.history.length === 0) {
            // No hay historial, volver al dashboard principal
            if (window.returnToDashboard) {
                window.returnToDashboard();
            }
            return;
        }

        // Obtener vista anterior del historial
        const previousState = this.state.history.pop();
        const currentView = this.state.currentView;

        // Restaurar estado anterior
        this.state.currentView = previousState.view;
        this.state.selectedClientId = previousState.clientId;
        this.state.selectedPetId = previousState.petId;

        // Realizar transición inversa
        this.performViewTransition(currentView, previousState.view, true);
        
        // Actualizar header
        this.updateHeader();
        
        // Cargar contenido de la vista anterior
        this.loadViewContent(previousState.view, {
            clientId: previousState.clientId,
            petId: previousState.petId
        });
    }

    /**
     * Realiza la transición animada entre vistas
     * @param {string} fromView - Vista origen
     * @param {string} toView - Vista destino
     * @param {boolean} isBack - Si es una navegación hacia atrás
     */
    performViewTransition(fromView, toView, isBack = false) {
        const fromElement = document.getElementById(`mobile-${fromView}-view`);
        const toElement = document.getElementById(`mobile-${toView}-view`);

        if (!fromElement || !toElement) {
            dlog('MobileUIManager: transition elements not found', fromView, toView);
            return;
        }

        // Configurar posiciones iniciales
        if (isBack) {
            // Navegación hacia atrás: la vista destino entra desde la izquierda
            toElement.style.transform = 'translateX(-100%)';
            toElement.classList.add('is-active');
        } else {
            // Navegación hacia adelante: la vista destino entra desde la derecha
            toElement.style.transform = 'translateX(100%)';
            toElement.classList.add('is-active');
        }

        // Animar transición
        requestAnimationFrame(() => {
            // Vista destino se mueve al centro
            toElement.style.transform = 'translateX(0)';
            
            // Vista origen se mueve hacia el lado opuesto
            if (isBack) {
                fromElement.style.transform = 'translateX(100%)';
            } else {
                fromElement.style.transform = 'translateX(-100%)';
            }
        });

        // Limpiar después de la transición
        setTimeout(() => {
            fromElement.classList.remove('is-active');
            fromElement.style.transform = 'translateX(100%)'; // Reset position
        }, 300);
    }

    /**
     * Actualiza el header según la vista actual
     */
    updateHeader() {
        const titleElement = document.getElementById('patients-title');
        const backBtnTextElement = document.getElementById('patients-back-btn-text');
        const addActionsBtnElement = document.getElementById('add-actions-btn');
        const searchBarElement = document.getElementById('search-bar-container');

        if (!titleElement || !backBtnTextElement) return;

        switch (this.state.currentView) {
            case 'client':
                titleElement.textContent = 'Mis Pacientes';
                backBtnTextElement.textContent = 'Dashboard';
                if (addActionsBtnElement) addActionsBtnElement.style.display = 'block';
                if (searchBarElement) searchBarElement.style.display = 'none';
                break;

            case 'pet':
                const client = db.clients.find(c => c.id === this.state.selectedClientId);
                titleElement.textContent = client ? client.name : 'Cliente';
                backBtnTextElement.textContent = 'Pacientes';
                if (addActionsBtnElement) addActionsBtnElement.style.display = 'none';
                if (searchBarElement) searchBarElement.style.display = 'none';
                break;

            case 'history':
                const pet = db.pets.find(p => p.id === this.state.selectedPetId);
                titleElement.textContent = pet ? `Historial de ${pet.name}` : 'Historial';
                backBtnTextElement.textContent = 'Mascotas';
                if (addActionsBtnElement) addActionsBtnElement.style.display = 'none';
                if (searchBarElement) searchBarElement.style.display = 'none';
                break;
        }
    }

    /**
     * Carga el contenido de una vista específica
     * @param {string} view - Vista a cargar
     * @param {object} data - Datos para la vista
     */
    async loadViewContent(view, data = {}) {
        switch (view) {
            case 'client':
                this.renderMobileClientList();
                break;

            case 'pet':
                if (data.clientId) {
                    this.renderMobilePetList(data.clientId);
                }
                break;

            case 'history':
                if (data.petId) {
                    await this.renderMobilePetHistory(data.petId);
                }
                break;
        }
    }

    /**
     * Renderiza la lista de clientes para móvil
     */
    renderMobileClientList() {
        const container = document.getElementById('mobile-client-list');
        if (!container) return;

        const filteredClients = filterClients(searchFilter);
        
        if (filteredClients.length === 0) {
            container.innerHTML = `
                <div class="loading-placeholder">
                    <p>No se encontraron clientes</p>
                </div>
            `;
            return;
        }

        const clientsHTML = filteredClients.map(client => {
            const clientPets = db.pets.filter(p => p.clientId === client.id);
            const petCount = clientPets.length;
            
            return `
                <div class="mobile-client-result" onclick="mobileUIManager.selectClient(${client.id})">
                    <div class="client-avatar">${getInitials(client.name)}</div>
                    <div class="client-info">
                        <h4>${client.name}</h4>
                        <div class="client-pets-count">${petCount} mascota${petCount !== 1 ? 's' : ''}</div>
                    </div>
                    <svg class="chevron-right" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                    </svg>
                </div>
            `;
        }).join('');

        container.innerHTML = clientsHTML;
    }

    /**
     * Selecciona un cliente y navega a la vista de mascotas
     * @param {number} clientId - ID del cliente
     */
    selectClient(clientId) {
        const client = db.clients.find(c => c.id === clientId);
        if (!client) return;

        dlog(`MobileUIManager: client selected`, clientId, client.name);
        this.navigateTo('pet', { clientId });
    }

    /**
     * Renderiza la lista de mascotas de un cliente para móvil
     * @param {number} clientId - ID del cliente
     */
    renderMobilePetList(clientId) {
        const client = db.clients.find(c => c.id === clientId);
        if (!client) return;

        // Renderizar header del cliente
        const clientHeaderContainer = document.getElementById('mobile-client-header');
        if (clientHeaderContainer) {
            clientHeaderContainer.innerHTML = `
                <div class="client-title-section">
                    <div class="client-large-avatar">${getInitials(client.name)}</div>
                    <div class="client-title-info">
                        <h2>${client.name}</h2>
                        <div class="client-contact-info">
                            ${client.email ? `📧 ${client.email}` : ''}
                            ${client.email && client.phone ? ' • ' : ''}
                            ${client.phone ? `📞 ${client.phone}` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // Renderizar lista de mascotas
        const petListContainer = document.getElementById('mobile-pet-list');
        if (!petListContainer) return;

        const clientPets = db.pets.filter(p => p.clientId === clientId);
        
        if (clientPets.length === 0) {
            petListContainer.innerHTML = `
                <div class="loading-placeholder">
                    <p>Este cliente no tiene mascotas registradas</p>
                    <button onclick="addPetToClient(${clientId})" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #3182CE; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        ➕ Añadir Primera Mascota
                    </button>
                </div>
            `;
            return;
        }

        const petsHTML = clientPets.map(pet => {
            const avatarInfo = getPetAvatarInfo(pet.species);
            
            return `
                <div class="mobile-pet-card" onclick="mobileUIManager.selectPet(${pet.id})">
                    <div class="pet-header">
                        <div class="pet-info-section">
                            <div class="pet-avatar ${avatarInfo.className}">${avatarInfo.icon}</div>
                            <div class="pet-info">
                                <h4>${pet.name}</h4>
                                <div class="pet-species">${pet.breed ? `${pet.species} • ${pet.breed}` : pet.species}</div>
                            </div>
                        </div>
                        <svg class="chevron-right" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </div>
                    <div class="share-code">
                        <span>${pet.shareCode}</span>
                        <button class="share-code-copy" onclick="event.stopPropagation(); copyToClipboard('${pet.shareCode}')" title="Copiar código">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        petListContainer.innerHTML = petsHTML;
    }

    /**
     * Selecciona una mascota y navega a la vista de historial
     * @param {number} petId - ID de la mascota
     */
    selectPet(petId) {
        const pet = db.pets.find(p => p.id === petId);
        if (!pet) return;

        dlog(`MobileUIManager: pet selected`, petId, pet.name);
        this.navigateTo('history', { petId });
    }

    /**
     * Renderiza el historial de una mascota para móvil
     * @param {number} petId - ID de la mascota
     */
    async renderMobilePetHistory(petId) {
        const pet = db.pets.find(p => p.id === petId);
        if (!pet) return;

        // Renderizar header de la mascota
        const petHeaderContainer = document.getElementById('mobile-pet-header');
        if (petHeaderContainer) {
            const avatarInfo = getPetAvatarInfo(pet.species);
            petHeaderContainer.innerHTML = `
                <div class="pet-title-section">
                    <div class="pet-large-avatar ${avatarInfo.className}">${avatarInfo.icon}</div>
                    <div class="pet-title-info">
                        <h2>${pet.name}</h2>
                        <div class="pet-details">
                            ${pet.breed ? `${pet.species} • ${pet.breed}` : pet.species}
                            ${pet.shareCode ? ` • Código: ${pet.shareCode}` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // Renderizar historial
        const historyContainer = document.getElementById('mobile-history-content');
        if (!historyContainer) return;

        // Mostrar loading
        historyContainer.innerHTML = `
            <div class="loading-placeholder">
                <div class="loader"></div>
                <p>Cargando historial...</p>
            </div>
        `;

        try {
            const response = await apiRequest(`patients/pets/${petId}/logs`);
            
            if (response.success) {
                const logs = response.data;
                
                if (logs.length === 0) {
                    historyContainer.innerHTML = `
                        <div class="loading-placeholder">
                            <h4>No hay entradas</h4>
                            <p>${pet.name} aún no tiene historial médico registrado.</p>
                        </div>
                    `;
                    return;
                }
                
                historyContainer.innerHTML = renderFullPetHistory(logs);
            } else {
                throw new Error(response.message || 'Respuesta de API inválida');
            }
        } catch (error) {
            console.error('Error loading mobile pet history:', error);
            historyContainer.innerHTML = `
                <div class="loading-placeholder">
                    <h4>Error al cargar el historial</h4>
                    <p>${error.message}</p>
                    <button onclick="mobileUIManager.renderMobilePetHistory(${petId})" style="margin-top: 1rem; padding: 0.5rem 1rem; background: #3182CE; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        🔄 Reintentar
                    </button>
                </div>
            `;
        }
    }
}

// Instancia global del gestor de UI móvil
let mobileUIManager = null;

// ====================================================================
// CORRECCIÓN DE NAVEGACIÓN MÓVIL
// ====================================================================

/**
 * Corrige el problema de navegación hacia atrás en la interfaz móvil.
 * Este manejador se ejecuta en la fase de captura, ANTES que el controlador global.
 */
function setupBackButtonFix() {
    const moduleContainer = document.getElementById('patients-module-container');
    if (!moduleContainer) {
        dlog('setupBackButtonFix: patients-module-container not found');
        return;
    }

    moduleContainer.addEventListener('click', (e) => {
        // Solo nos interesa el botón de volver
        const backButton = e.target.closest('.back-to-prof-main');
        if (!backButton) return;

        // Si estamos en la interfaz móvil y no estamos en la primera pantalla...
        if (isMobileInterface && mobileUIManager && mobileUIManager.state.currentView !== 'client') {
            dlog('setupBackButtonFix: Interceptando clic en botón "Volver" móvil');
            
            // 1. Prevenimos el comportamiento por defecto del enlace (si lo tuviera)
            e.preventDefault();
            
            // 2. ¡La parte más importante! Detenemos la propagación del evento inmediatamente.
            // Esto evita que el manejador global en dashboard-controller.js se ejecute.
            e.stopImmediatePropagation();
            
            // 3. Dejamos que nuestro gestor móvil maneje la navegación hacia atrás.
            mobileUIManager.navigateBack();
        }
        // Si no se cumplen las condiciones, no hacemos nada y dejamos que el controlador global actúe.
    }, true); // El 'true' al final es crucial, activa la FASE DE CAPTURA.

    dlog('setupBackButtonFix: Parche de navegación para el botón "Volver" móvil aplicado.');
}

// ====================================================================
// GESTIÓN DE ESTADO Y RENDERIZADO
// ====================================================================

/**
 * Filtra los clientes según el término de búsqueda
 * @param {string} filter - Término de búsqueda
 * @returns {Array} Lista de clientes filtrados
 */
function filterClients(filter) {
    if (!filter) return db.clients;
    
    const searchTerm = filter.toLowerCase();
    return db.clients.filter(client => {
        const clientPets = db.pets.filter(p => p.clientId === client.id);
        return client.name.toLowerCase().includes(searchTerm) ||
               client.email.toLowerCase().includes(searchTerm) ||
               clientPets.some(p => p.name.toLowerCase().includes(searchTerm));
    });
}

/**
 * Renderiza la lista de clientes en el sidebar (escritorio) o móvil
 */
function renderClientsList() {
    // Si estamos en móvil, usar el gestor móvil
    if (isMobileInterface && mobileUIManager) {
        mobileUIManager.renderMobileClientList();
        return;
    }
    
    // Renderizado para escritorio
    const container = document.getElementById('clients-list');
    if (!container) {
        dlog('renderClientsList: clients-list container NOT found');
        return;
    }
    const filteredClients = filterClients(searchFilter);
    
    if (filteredClients.length === 0) {
        container.innerHTML = `
            <div class="loading-placeholder">
                <p>No se encontraron clientes</p>
            </div>
        `;
        return;
    }

    const clientsHTML = filteredClients.map(client => {
        const clientPets = db.pets.filter(p => p.clientId === client.id);
        const petCount = clientPets.length;
        const isActive = activeClientId === client.id;
        
        return `
            <div class="client-item ${isActive ? 'active' : ''}" data-client-id="${client.id}" onclick="selectClient(${client.id})">
                <div class="client-avatar">${getInitials(client.name)}</div>
                <div class="client-info">
                    <h4>${client.name}</h4>
                    <div class="client-pets-count">${petCount} mascota${petCount !== 1 ? 's' : ''}</div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = clientsHTML;
    
    console.log(`📋 Lista de clientes renderizada: ${filteredClients.length} clientes`);
}

/**
 * Renderiza los detalles del cliente seleccionado
 * @param {number} clientId - ID del cliente
 */
function renderClientDetails(clientId) {
    const client = db.clients.find(c => c.id === clientId);
    if (!client) return;

    const clientPets = db.pets.filter(p => p.clientId === clientId);
    const container = document.getElementById('client-details');
    if (!container) {
        dlog('renderClientDetails: client-details container NOT found for clientId=', clientId);
        return;
    }
    
    // Header del cliente
    const headerHTML = `
        <div class="client-header">
            <div class="client-title-section">
                <div class="client-large-avatar">${getInitials(client.name)}</div>
                <div class="client-title-info">
                    <h2>${client.name}</h2>
                    <div class="client-contact-info">
                        ${client.email ? `📧 ${client.email}` : ''}
                        ${client.email && client.phone ? ' • ' : ''}
                        ${client.phone ? `📞 ${client.phone}` : ''}
                    </div>
                </div>
            </div>
            <div class="client-actions">
                <button class="btn-icon" onclick="editClient(${client.id})" title="Editar cliente">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </button>
            </div>
        </div>
    `;

    // Sección de mascotas
    const petsHTML = `
        <div class="pets-section">
            <div class="section-header">
                <h3 class="section-title">Mascotas (${clientPets.length})</h3>
                <button class="btn-add-pet" onclick="addPetToClient(${clientId})">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    Añadir otra mascota
                </button>
            </div>
            <div class="pets-grid">
                ${clientPets.map(pet => renderPetCard(pet)).join('')}
            </div>
        </div>
    `;

    container.innerHTML = headerHTML + petsHTML;
    container.style.display = 'block';
    
    // Ocultar estado vacío
    document.getElementById('empty-state').style.display = 'none';
}

/**
 * Renderiza una tarjeta de mascota
 * @param {object} pet - Objeto de mascota
 * @returns {string} HTML de la tarjeta
 */
function renderPetCard(pet) {
    const avatarInfo = getPetAvatarInfo(pet.species);
    
    return `
        <div class="pet-card">
            <div class="pet-header">
                <div class="pet-avatar ${avatarInfo.className}">${avatarInfo.icon}</div>
                <div class="pet-info">
                    <h4>${pet.name}</h4>
                    <div class="pet-species">${pet.breed ? `${pet.species} • ${pet.breed}` : pet.species}</div>
                </div>
            </div>
            
            <div class="pet-actions">
                <button class="btn-pet-action" onclick="editPet(${pet.id})">✏️ Editar</button>
                <button class="btn-pet-action" onclick="viewPetHistory(${pet.id})">📋 Historial</button>
            </div>
            
            <div class="share-code">
                <span>${pet.shareCode}</span>
                <button class="share-code-copy" onclick="copyToClipboard('${pet.shareCode}')" title="Copiar código">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                </button>
            </div>
            
            <div class="timeline-section">
                <h5 style="margin: 1rem 0 0.5rem 0; font-size: 0.875rem; color: var(--color-gray-600);">Historial Médico</h5>
                <p style="font-size: 0.75rem; color: var(--color-gray-500); margin: 0;">Haz clic en "📋 Historial" para ver el historial completo</p>
            </div>
        </div>
    `;
}

/**
 * Renderiza el timeline médico de una mascota
 * @param {Array} appointments - Lista de citas
 * @returns {string} HTML del timeline
 */
function renderPetTimeline(appointments) {
    return `
        <ul class="timeline">
            ${appointments.map(appointment => `
                <li class="timeline-item">
                    <div class="timeline-dot"></div>
                    <p class="timeline-date">${formatDate(appointment.date)}</p>
                    <div class="timeline-content">
                        <span><strong>${appointment.description}</strong> - ${getStatusText(appointment.status)}</span>
                        ${appointment.status === 'ready_for_pickup' ? `
                            <div class="timeline-action">
                                <button class="btn-timeline-action" onclick="verifyPickup(${appointment.id})">
                                    Verificar Recogida
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </li>
            `).join('')}
        </ul>
    `;
}

/**
 * Obtiene el texto de estado de una cita
 * @param {string} status - Estado de la cita
 * @returns {string} Texto legible del estado
 */
function getStatusText(status) {
    const statusMap = {
        completed: 'Completada',
        ready_for_pickup: 'Lista para recoger',
        scheduled: 'Programada',
        cancelled: 'Cancelada'
    };
    return statusMap[status] || status;
}

// ====================================================================
// GESTIÓN DE EVENTOS
// ====================================================================

/**
 * Configura todos los event listeners del módulo
 */
function setupEventListeners() {
    dlog('setupEventListeners called, local bound =', documentListenersBound, 'global bound =', window.PatientsModuleGlobal.listenersBound);
    // Navegación principal y delegación global (usa un único handler para evitar listeners duplicados)
    if (!window.PatientsModuleGlobal.listenersBound) {
        documentClickHandler = function(e) {
            dlog('documentClickHandler invoked - target:', e.target && (e.target.id || e.target.className || e.target.tagName));

            // Volver al dashboard principal o navegación móvil
            if (e.target.closest && e.target.closest('.back-to-prof-main')) {
                e.preventDefault();
                dlog('back-to-prof-main clicked');
                
                // Si estamos en interfaz móvil, usar el gestor móvil
                if (isMobileInterface && mobileUIManager) {
                    mobileUIManager.navigateBack();
                } else {
                    // Escritorio: volver al dashboard principal
                    if (window.returnToDashboard) {
                        window.returnToDashboard();
                    }
                }
                return;
            }

            // Selección de cliente (delegación global evita problemas al reemplazar el contenedor)
            const clientItem = e.target.closest && e.target.closest('.client-item');
            if (clientItem) {
                const clientId = parseInt(clientItem.dataset.clientId);
                dlog('client-item clicked, id=', clientId);
                selectClient(clientId);
                return;
            }

            // Cerrar modales al hacer clic en el overlay (delegado)
            if (e.target.classList && e.target.classList.contains('modal-overlay')) {
                dlog('overlay clicked, hiding modal', e.target.id);
                hideModal(e.target.id);
                return;
            }
        };

        document.addEventListener('click', documentClickHandler);

        // ESC para cerrar modales
        documentKeydownHandler = function(e) {
            if (e.key === 'Escape') {
                dlog('Escape pressed');
                const visibleModal = document.querySelector('.modal-overlay.visible');
                if (visibleModal) {
                    hideModal(visibleModal.id);
                }
            }
        };
        document.addEventListener('keydown', documentKeydownHandler);

        // Cerrar con botones de cierre (delegación no es posible para NodeList, así que enlazamos ahora)
        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                dlog('modal-close-btn clicked', modal && modal.id);
                if (modal) hideModal(modal.id);
            });
        });

        // Marcar como enlazado tanto en scope local como global para sobrevivir a recargas de script
        documentListenersBound = true;
        window.PatientsModuleGlobal.listenersBound = true;
    } else {
        dlog('setupEventListeners: listeners already bound, skipping re-bind');
    }

    // Toggle de búsqueda
    // Reemplazar elementos clave para limpiar handlers anteriores si el módulo fue reinyectado
    const searchToggle = replaceElementWithCloneById('search-toggle-btn');
    const searchContainer = document.getElementById('search-bar-container');
    const searchInput = replaceElementWithCloneById('patients-search');
    const searchClose = replaceElementWithCloneById('search-close-btn');

    if (searchToggle) {
        searchToggle.addEventListener('click', function() {
            searchContainer.classList.add('visible');
            searchInput.focus();
        });
    }

    if (searchClose) {
        searchClose.addEventListener('click', function() {
            searchContainer.classList.remove('visible');
            searchInput.value = '';
            searchFilter = '';
            renderClientsList();
        });
    }

    // Búsqueda en tiempo real
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            searchFilter = e.target.value;
            renderClientsList();
        });
    }

    // Búsqueda móvil en tiempo real
    const mobileSearchInput = document.getElementById('mobile-clients-search');
    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function(e) {
            searchFilter = e.target.value;
            if (isMobileInterface && mobileUIManager) {
                mobileUIManager.renderMobileClientList();
            }
        });
    }

    // Action sheet
    const addActionsBtn = replaceElementWithCloneById('add-actions-btn');
    const actionSheetModal = document.getElementById('action-sheet-modal');
    const actionSheetCancel = replaceElementWithCloneById('action-sheet-cancel');

    if (addActionsBtn) {
        addActionsBtn.addEventListener('click', function() {
            showModal('action-sheet-modal');
        });
    }

    if (actionSheetCancel) {
        actionSheetCancel.addEventListener('click', function() {
            hideModal('action-sheet-modal');
        });
    }

    // Opciones del action sheet
    const addClientOption = replaceElementWithCloneById('add-client-option');
    const importPatientOption = replaceElementWithCloneById('import-patient-option');

    if (addClientOption) {
        addClientOption.addEventListener('click', function() {
            hideModal('action-sheet-modal');
            showNewClientModal();
        });
    }

    if (importPatientOption) {
        importPatientOption.addEventListener('click', function() {
            hideModal('action-sheet-modal');
            showImportModal();
        });
    }

    // Nota: la selección de clientes se maneja por delegación global en el handler de click

    // Cerrar modales con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const visibleModal = document.querySelector('.modal-overlay.visible');
            if (visibleModal) {
                hideModal(visibleModal.id);
            }
        }
    });

    // Cerrar modales al hacer clic en el overlay
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            hideModal(e.target.id);
        }
    });

    // Cerrar modales con botones de cierre
    document.querySelectorAll('.modal-close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) hideModal(modal.id);
        });
    });

    setupFormListeners();
}

/**
 * Configura los event listeners para formularios
 */
function setupFormListeners() {
    // Formulario de cliente
    const clientForm = document.getElementById('client-form');
    clientForm?.addEventListener('submit', handleClientFormSubmit);

    document.getElementById('client-form-cancel')?.addEventListener('click', function() {
        hideModal('client-modal');
    });

    // Formulario de mascota
    const petForm = document.getElementById('pet-form');
    petForm?.addEventListener('submit', handlePetFormSubmit);

    document.getElementById('pet-form-cancel')?.addEventListener('click', function() {
        hideModal('pet-modal');
    });

    // Auto-generar código al cambiar nombre de mascota
    document.getElementById('pet-name')?.addEventListener('input', function(e) {
        const shareCodeInput = document.getElementById('pet-share-code');
        if (shareCodeInput && !shareCodeInput.dataset.manual) {
            shareCodeInput.value = generateShareCode(e.target.value);
        }
    });

    // Botón copiar código de mascota
    document.getElementById('share-code-copy-btn')?.addEventListener('click', function() {
        const shareCode = document.getElementById('pet-share-code').value;
        copyToClipboard(shareCode);
    });

    // Formulario de importación
    const importForm = document.getElementById('import-form');
    importForm?.addEventListener('submit', handleImportFormSubmit);

    document.getElementById('import-form-cancel')?.addEventListener('click', function() {
        hideModal('import-modal');
    });

    // Formulario de verificación de recogida
    const pickupForm = document.getElementById('pickup-form');
    pickupForm?.addEventListener('submit', handlePickupFormSubmit);

    document.getElementById('pickup-form-cancel')?.addEventListener('click', function() {
        hideModal('pickup-modal');
    });
}

// ====================================================================
// GESTIÓN DE MODALES
// ====================================================================

/**
 * Muestra un modal
 * @param {string} modalId - ID del modal a mostrar
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Oculta un modal
 * @param {string} modalId - ID del modal a ocultar
 */
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('visible');
        document.body.style.overflow = '';
        
        // Limpiar formularios
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
}

// ====================================================================
// OPERACIONES CRUD
// ====================================================================

/**
 * Selecciona un cliente y muestra sus detalles
 * @param {number} clientId - ID del cliente
 */
function selectClient(clientId) {
    console.log(`👤 Cliente seleccionado: ID ${clientId}`);
    
    const client = db.clients.find(c => c.id === clientId);
    if (!client) {
        console.error(`❌ Cliente no encontrado: ID ${clientId}`);
        return;
    }
    
    activeClientId = clientId;
    renderClientsList(); // Re-renderizar para mostrar cliente activo
    renderClientDetails(clientId); // Mostrar detalles en panel derecho
    
    console.log(`✅ Cliente seleccionado: ${client.name}`);
}

/**
 * Muestra el modal para crear un nuevo cliente
 */
function showNewClientModal() {
    document.getElementById('client-modal-title').textContent = 'Nuevo Cliente';
    document.getElementById('client-form-submit').textContent = 'Guardar Cliente';
    showModal('client-modal');
}

/**
 * Edita un cliente existente
 * @param {number} clientId - ID del cliente a editar
 */
function editClient(clientId) {
    const client = db.clients.find(c => c.id === clientId);
    if (!client) return;

    document.getElementById('client-modal-title').textContent = 'Editar Cliente';
    document.getElementById('client-form-submit').textContent = 'Actualizar Cliente';
    
    // Pre-poblar formulario
    document.getElementById('client-name').value = client.name;
    document.getElementById('client-email').value = client.email || '';
    document.getElementById('client-phone').value = client.phone || '';
    
    // Agregar data attribute para identificar edición
    document.getElementById('client-form').dataset.editingId = clientId;
    
    showModal('client-modal');
}

/**
 * Maneja el envío del formulario de cliente
 * @param {Event} e - Evento de envío
 */
async function handleClientFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const clientData = {
        name: formData.get('client-name') || document.getElementById('client-name').value,
        email: formData.get('client-email') || document.getElementById('client-email').value,
        phone: formData.get('client-phone') || document.getElementById('client-phone').value,
        professional_id: professionalId
    };

    const editingId = e.target.dataset.editingId;
    
    try {
        if (editingId) {
            // Actualizar cliente existente
            await apiRequest(`patients/clients/${editingId}`, {
                method: 'PUT',
                body: JSON.stringify(clientData)
            });
            
            showNotification('Cliente actualizado exitosamente', 'success');
            delete e.target.dataset.editingId;
        } else {
            // Crear nuevo cliente
            const response = await apiRequest('patients/clients', {
                method: 'POST',
                body: JSON.stringify(clientData)
            });
            
            showNotification('Cliente creado exitosamente', 'success');
        }
        
        // Recargar datos desde la API
        await loadClientsFromAPI();
        
        hideModal('client-modal');
        
    } catch (error) {
        console.error('Error saving client:', error);
        showNotification('Error al guardar el cliente: ' + error.message, 'error');
    }
}

/**
 * Añade una nueva mascota a un cliente
 * @param {number} clientId - ID del cliente
 */
function addPetToClient(clientId) {
    document.getElementById('pet-modal-title').textContent = 'Nueva Mascota';
    document.getElementById('pet-form-submit').textContent = 'Guardar Mascota';
    document.getElementById('pet-form').dataset.clientId = clientId;
    
    // Generar código inicial
    document.getElementById('pet-share-code').value = generateShareCode();
    delete document.getElementById('pet-share-code').dataset.manual;
    
    showModal('pet-modal');
}

/**
 * Edita una mascota existente
 * @param {number} petId - ID de la mascota a editar
 */
function editPet(petId) {
    const pet = db.pets.find(p => p.id === petId);
    if (!pet) return;

    document.getElementById('pet-modal-title').textContent = 'Editar Mascota';
    document.getElementById('pet-form-submit').textContent = 'Actualizar Mascota';
    
    // Pre-poblar formulario
    document.getElementById('pet-name').value = pet.name;
    document.getElementById('pet-species').value = pet.species;
    document.getElementById('pet-breed').value = pet.breed || '';
    document.getElementById('pet-share-code').value = pet.shareCode;
    document.getElementById('pet-share-code').dataset.manual = 'true';
    
    document.getElementById('pet-form').dataset.editingId = petId;
    
    showModal('pet-modal');
}

/**
 * Maneja el envío del formulario de mascota
 * @param {Event} e - Evento de envío
 */
async function handlePetFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const petData = {
        name: formData.get('pet-name') || document.getElementById('pet-name').value,
        species: formData.get('pet-species') || document.getElementById('pet-species').value,
        breed: formData.get('pet-breed') || document.getElementById('pet-breed').value,
        share_code: document.getElementById('pet-share-code').value,
        professional_id: professionalId
    };

    const editingId = e.target.dataset.editingId;
    const clientId = e.target.dataset.clientId;
    
    try {
        if (editingId) {
            // Actualizar mascota existente
            await apiRequest(`patients/pets/${editingId}`, {
                method: 'PUT',
                body: JSON.stringify(petData)
            });
            
            showNotification('Mascota actualizada exitosamente', 'success');
            delete e.target.dataset.editingId;
        } else {
            // Crear nueva mascota
            petData.client_id = parseInt(clientId);
            
            await apiRequest('patients/pets', {
                method: 'POST',
                body: JSON.stringify(petData)
            });
            
            showNotification('Mascota creada exitosamente', 'success');
        }
        
        // Recargar datos desde la API
        await loadClientsFromAPI();
        
        // Re-seleccionar el cliente activo si existe
        if (activeClientId) {
            selectClient(activeClientId);
        }
        
        delete e.target.dataset.clientId;
        hideModal('pet-modal');
        
    } catch (error) {
        console.error('Error saving pet:', error);
        showNotification('Error al guardar la mascota: ' + error.message, 'error');
    }
}

/**
 * Muestra el modal de importación
 */
function showImportModal() {
    showModal('import-modal');
    document.getElementById('import-code').focus();
}

/**
 * Maneja el envío del formulario de importación
 * @param {Event} e - Evento de envío
 */
async function handleImportFormSubmit(e) {
    e.preventDefault();
    
    const code = document.getElementById('import-code').value.toUpperCase().trim();
    
    if (!code) {
        showNotification('Por favor introduce un código válido', 'error');
        return;
    }
    
    try {
        const response = await apiRequest('patients/import', {
            method: 'POST',
            body: JSON.stringify({
                share_code: code,
                professional_id: professionalId
            })
        });
        
        if (response.success) {
            showNotification(`Paciente ${response.data.pet.name} importado exitosamente`, 'success');
            
            // Recargar datos desde la API
            await loadClientsFromAPI();
            
            // Seleccionar el cliente importado
            if (response.data.client) {
                selectClient(parseInt(response.data.client.client_id, 10));
            }
            
            hideModal('import-modal');
        }
        
    } catch (error) {
        console.error('Error importing patient:', error);
        
        // Manejar diferentes tipos de errores
        let errorMessage = 'Error al importar paciente';
        if (error.message.includes('not found')) {
            errorMessage = 'Código no válido o no encontrado';
        } else if (error.message.includes('already_imported')) {
            errorMessage = 'Este paciente ya está en tu lista';
        } else if (error.message.includes('access_denied')) {
            errorMessage = 'No tienes permisos para importar este paciente';
        } else {
            errorMessage = 'Error al importar paciente: ' + error.message;
        }
        
        showNotification(errorMessage, 'error');
    }
}

/**
 * Muestra el historial completo de una mascota
 * @param {number} petId - ID de la mascota
 */
async function viewPetHistory(petId) {
    const pet = db.pets.find(p => p.id === petId);
    if (!pet) return;

    // Si estamos en interfaz móvil, usar la navegación móvil
    if (isMobileInterface && mobileUIManager) {
        mobileUIManager.navigateTo('history', { petId });
        return;
    }

    // Escritorio: mostrar el modal
    document.getElementById('pet-history-modal-title').textContent = `Historial Médico de ${pet.name}`;
    const modalBody = document.getElementById('pet-history-modal-body');
    modalBody.innerHTML = `<div class="loading-placeholder"><div class="loader"></div><p>Cargando historial...</p></div>`;
    showModal('pet-history-modal');
    
    try {
        // La API ya devuelve los logs completos con meta y productos
        const response = await apiRequest(`patients/pets/${petId}/logs`);
        
        if (response.success) {
            const logs = response.data;
            
            if (logs.length === 0) {
                modalBody.innerHTML = `
                    <div class="empty-state-compact">
                        <h4>No hay entradas</h4>
                        <p>${pet.name} aún no tiene historial médico registrado.</p>
                    </div>`;
                return;
            }
            
            // Usar la nueva función para renderizar el historial detallado
            modalBody.innerHTML = renderFullPetHistory(logs);
            
        } else {
            throw new Error(response.message || 'Respuesta de API inválida');
        }
        
    } catch (error) {
        console.error('Error loading pet history:', error);
        modalBody.innerHTML = `<div class="error-state-compact"><h4>Error al cargar el historial</h4><p>${error.message}</p></div>`;
    }
}

/**
 * Renderiza el HTML completo para una lista de entradas del historial.
 * @param {Array} logs - La lista de entradas del historial desde la API.
 * @returns {string} El HTML del timeline.
 */
function renderFullPetHistory(logs) {
    const historyHtml = logs.map(log => {
        // Renderizar los metadatos del formulario personalizado
        const metaHtml = log.meta && Object.keys(log.meta).length > 0
            ? Object.entries(log.meta).map(([key, value]) => {
                if (!value) return ''; // No mostrar campos vacíos
                const cleanKey = key.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
                return `<div class="log-detail-item"><strong>${cleanKey}:</strong> <span>${value}</span></div>`;
            }).join('')
            : '';

        // Renderizar los productos utilizados
        const productsHtml = log.products && log.products.length > 0
            ? '<div class="log-detail-section-title">Productos Utilizados</div>' + log.products.map(p => `
                <div class="log-product-item">
                    <strong>${p.product_name}</strong>
                    <small>
                        ${p.lot_number ? `Lote: ${p.lot_number}` : ''}
                        ${p.expiration_date ? ` | Cad: ${formatDate(p.expiration_date)}` : ''}
                    </small>
                </div>
            `).join('')
            : '';

        return `
            <li class="timeline-entry">
                <div class="timeline-entry-header">
                    <span class="timeline-entry-title">${log.title}</span>
                    <span class="timeline-entry-date">${formatDate(log.entry_date)}</span>
                </div>
                <div class="timeline-entry-meta">
                    Por: <strong>${log.professional_name || 'Profesional no especificado'}</strong>
                </div>
                <div class="timeline-entry-body">
                    ${metaHtml ? `<div class="log-details-grid">${metaHtml}</div>` : ''}
                    ${productsHtml ? `<div class="log-products-section">${productsHtml}</div>` : ''}
                </div>
            </li>
        `;
    }).join('');

    return `<ul class="pet-history-timeline">${historyHtml}</ul>`;
}

/**
 * Inicia el proceso de verificación de recogida
 * @param {number} appointmentId - ID de la cita
 */
function verifyPickup(appointmentId) {
    document.getElementById('pickup-form').dataset.appointmentId = appointmentId;
    showModal('pickup-modal');
    document.getElementById('pickup-code').focus();
}

/**
 * Maneja el envío del formulario de verificación de recogida
 * @param {Event} e - Evento de envío
 */
function handlePickupFormSubmit(e) {
    e.preventDefault();
    
    const appointmentId = parseInt(e.target.dataset.appointmentId);
    const enteredCode = document.getElementById('pickup-code').value.toUpperCase().trim();
    
    const appointment = db.appointments.find(a => a.id === appointmentId);
    if (!appointment) return;
    
    if (enteredCode === appointment.pickupCode) {
        // Actualizar estado de la cita
        appointment.status = 'completed';
        delete appointment.pickupCode;
        
        showNotification('Recogida verificada exitosamente', 'success');
        
        // Re-renderizar detalles del cliente si está activo
        if (activeClientId) {
            renderClientDetails(activeClientId);
        }
    } else {
        showNotification('Código de recogida incorrecto', 'error');
        return;
    }
    
    delete e.target.dataset.appointmentId;
    hideModal('pickup-modal');
}

// ====================================================================
// INICIALIZACIÓN
// ====================================================================

/**
 * Renderiza el estado inicial del módulo
 */
function renderInitialState() {
    dlog('renderInitialState: rendering clients list and setting visibility');
    renderClientsList();
    
    // Mostrar estado vacío por defecto
    const emptyStateEl = document.getElementById('empty-state');
    const clientDetailsEl = document.getElementById('client-details');
    if (emptyStateEl) emptyStateEl.style.display = 'flex'; else dlog('renderInitialState: empty-state not found');
    if (clientDetailsEl) clientDetailsEl.style.display = 'none'; else dlog('renderInitialState: client-details not found');
}

/**
 * Detecta si estamos en una pantalla móvil
 * @returns {boolean} true si es móvil, false si es escritorio
 */
function detectMobileInterface() {
    return window.innerWidth <= 768;
}

/**
 * Inicialización automática cuando el DOM está listo
 */
function initializePatientsModule() {
    if (window.PatientsModuleGlobal.initializing) {
        dlog('initializePatientsModule: initialization already in progress, skipping');
        return false;
    }
    if (window.PatientsModuleGlobal.moduleInitialized) {
        dlog('initializePatientsModule: already initialized, skipping');
        return true;
    }
    // Will set initializing flag after verifying container exists
    const container = document.getElementById('patients-module-container');
    if (!container) {
        console.log('🏥 Contenedor del módulo de pacientes no encontrado, esperando...');
        return false;
    }
    
    console.log('🏥 Inicializando módulo Mis Pacientes...');
    dlog('initializePatientsModule: container found', container);
    window.PatientsModuleGlobal.initializing = true;
    
    try {
        // Detectar interfaz móvil
        isMobileInterface = detectMobileInterface();
        dlog('initializePatientsModule: mobile interface detected:', isMobileInterface);
        
        // Inicializar gestor de UI móvil si es necesario
        if (isMobileInterface) {
            mobileUIManager = new MobileUIManager();
            dlog('initializePatientsModule: mobile UI manager created');
        }
        
        // Inicialización completa
        initializeDatabase();
        dlog('initializePatientsModule: database init complete');
        setupEventListeners();
        dlog('initializePatientsModule: event listeners setup complete');
        setupBackButtonFix();
        dlog('initializePatientsModule: back button fix applied');
        renderInitialState();
        dlog('initializePatientsModule: initial state rendered');
        
        // Marcar el módulo como inicializado para evitar re-renderizaciones innecesarias
        window.PatientsModuleGlobal.moduleInitialized = true;
        window.PatientsModuleGlobal.initializing = false;
        
        console.log('✅ Módulo Mis Pacientes inicializado correctamente');
        return true;
    } catch (error) {
        console.error('❌ Error inicializando módulo de pacientes:', error);
        window.PatientsModuleGlobal.initializing = false;
        return false;
    }
}

// Intentar inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializePatientsModule();
});

// Nota: removido observer de detección AJAX para simplificar la orquestación.
// El dashboard-controller.js debe llamar explícitamente a `window.initVeterinaliaPatientsModule`.
// Conservamos `DOMContentLoaded` y `removalObserver` para compatibilidad y reset de flags.

// Observador para detectar cuando el contenedor es removido y reinyectado
const removalObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
            // Si alguno de los removedNodes contiene el container, resetear flags
            mutation.removedNodes.forEach(function(node) {
                if (node.nodeType === 1 && (node.id === 'patients-module-container' || node.querySelector && node.querySelector('#patients-module-container'))) {
                    dlog('removalObserver: patients-module-container removed, resetting moduleInitialized');
                    window.PatientsModuleGlobal.moduleInitialized = false;
                    window.PatientsModuleGlobal.initializing = false;
                    // No desconectamos listeners globales, solo permitimos re-inicializar el módulo
                }
            });
        }
    });
});
removalObserver.observe(document.body, { childList: true, subtree: true });

// Punto de entrada explícito para inicialización desde el orquestador (dashboard-controller)
window.initVeterinaliaPatientsModule = function(profId, opts = {}) {
    try {
        if (profId) {
            professionalId = parseInt(profId, 10);
        }
        if (opts && typeof opts === 'object') {
            if (opts.api_url) apiConfig.baseUrl = opts.api_url;
            if (opts.api_nonce) apiConfig.nonce = opts.api_nonce;
        }

        // Permitir re-inicialización explícita
        window.PatientsModuleGlobal.moduleInitialized = false;
        window.PatientsModuleGlobal.initializing = false;

        // Si el contenedor ya está presente, inicializar ahora; si no, el observer hará el resto
        initializePatientsModule();
    } catch (e) {
        console.error('Error en initVeterinaliaPatientsModule:', e);
    }
};

// Exportar funciones para uso global
window.PatientsModule = {
    selectClient,
    editClient,
    addPetToClient,
    editPet,
    viewPetHistory,
    verifyPickup,
    copyToClipboard,
    showNotification,
    mobileUIManager: () => mobileUIManager
};

// Exportar funciones necesarias directamente al window para onclick
window.selectClient = selectClient;
window.editClient = editClient;
window.addPetToClient = addPetToClient;
window.editPet = editPet;
window.viewPetHistory = viewPetHistory;
window.verifyPickup = verifyPickup;
window.copyToClipboard = copyToClipboard;
window.showNewClientModal = showNewClientModal;
window.showImportModal = showImportModal;
window.showModal = showModal;
window.hideModal = hideModal;

// Exportar gestor móvil para uso global
window.mobileUIManager = mobileUIManager;
