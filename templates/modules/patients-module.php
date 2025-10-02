<?php
/**
 * Módulo "Mis Pacientes" - CRM Veterinario v2.0
 * Sistema de gestión de pacientes con códigos de compartir únicos
 */
if (!defined('ABSPATH')) { exit; }

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : (isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0);
$manager = Veterinalia_Appointment_Manager::get_instance();
$db_handler = Veterinalia_Appointment_Database::get_instance();

// Verificar que las clases existan
if (!$manager || !$db_handler) {
    echo '<div class="error-message">Error: No se pudieron inicializar las clases del plugin</div>';
    return;
}

// Asegurar que las tablas estén creadas
$db_handler->create_tables();

// Log para debugging
error_log('[Patients Module] Módulo cargado para employee_id: ' . $employee_id);
?>

<div class="patients-module-container" id="patients-module-container" data-professional-id="<?php echo esc_attr($employee_id); ?>">
    
    <!-- Header Unificado -->
    <div class="module-header">
        <!-- Desktop Layout -->
        <div class="header-left-section">
            <a href="#" class="back-to-prof-main">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                </svg>
                <span id="patients-back-btn-text">Volver</span>
            </a>
        </div>
        
        <div class="header-center-content">
            <h1 class="dashboard-section-title" id="patients-title">Mis Pacientes</h1>
            <div class="search-bar-container" id="search-bar-container">
                <div class="search-input-wrapper">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    <input type="text" id="patients-search" placeholder="Buscar clientes o mascotas..." />
                    <button class="search-close-btn" id="search-close-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <button class="add-new-item-btn" id="search-toggle-btn" title="Buscar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
        </button>
        <button class="add-new-item-btn" id="add-actions-btn" title="Añadir">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
        </button>
    </div>

    <!-- Vista de Escritorio -->
    <div class="desktop-only-view">
        <!-- Layout Master-Detail Responsivo -->
        <div class="patients-layout">
            <!-- Sidebar (Master) -->
            <div class="patients-list-sidebar">
                <div class="clients-list" id="clients-list">
                    <!-- Lista de clientes se carga dinámicamente -->
                    <div class="loading-placeholder">
                        <div class="loader"></div>
                        <p>Cargando pacientes...</p>
                    </div>
                </div>
            </div>

            <!-- Content Area (Detail) -->
            <div class="patients-content-area" id="patients-content-area">
                <!-- Estado vacío inicial -->
                <div class="empty-state" id="empty-state">
                    <div class="empty-state-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor" style="color: #CBD5E0;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <h3>Selecciona un cliente</h3>
                    <p>Elige un cliente de la lista para ver sus mascotas e historial médico</p>
                </div>
                
                <!-- Contenido del cliente seleccionado -->
                <div class="client-details" id="client-details" style="display: none;">
                    <!-- El contenido se carga dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Vista Móvil -->
    <div class="mobile-only-view">
        <div class="mobile-views-wrapper">
            <!-- Vista 1: Búsqueda y Lista de Clientes -->
            <div id="mobile-client-view" class="mobile-view is-active">
                <div class="mobile-search-section">
                    <div class="mobile-search-input-wrapper">
                        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        <input type="text" id="mobile-clients-search" placeholder="Buscar clientes..." />
                    </div>
                </div>
                <div class="mobile-content-area">
                    <div id="mobile-client-list" class="mobile-client-list">
                        <!-- Lista de clientes se carga dinámicamente -->
                        <div class="loading-placeholder">
                            <div class="loader"></div>
                            <p>Cargando pacientes...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vista 2: Lista de Mascotas del Cliente -->
            <div id="mobile-pet-view" class="mobile-view">
                <div class="mobile-content-area">
                    <div class="mobile-client-header" id="mobile-client-header">
                        <!-- Header del cliente seleccionado -->
                    </div>
                    <div id="mobile-pet-list" class="mobile-pet-list">
                        <!-- Lista de mascotas se carga dinámicamente -->
                    </div>
                </div>
            </div>

            <!-- Vista 3: Historial Clínico de la Mascota -->
            <div id="mobile-history-view" class="mobile-view">
                <div class="mobile-content-area">
                    <div class="mobile-pet-header" id="mobile-pet-header">
                        <!-- Header de la mascota seleccionada -->
                    </div>
                    <div id="mobile-history-content" class="mobile-history-content">
                        <!-- Historial clínico se carga dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Sheet Modal -->
    <div class="modal-overlay action-sheet" id="action-sheet-modal">
        <div class="modal-content">
            <div class="action-sheet-header">
                <h3>¿Qué quieres hacer?</h3>
            </div>
            <div class="action-sheet-options">
                <button class="action-option" id="add-client-option">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Añadir Nuevo Cliente</span>
                        <span class="action-subtitle">Crear un cliente con sus mascotas</span>
                    </div>
                </button>
                <button class="action-option featured" id="import-patient-option">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Importar Paciente por Código</span>
                        <span class="action-subtitle">⭐ Usar código único de mascota</span>
                    </div>
                </button>
            </div>
            <button class="action-sheet-cancel" id="action-sheet-cancel">Cancelar</button>
        </div>
    </div>

    <!-- Client Modal (Crear/Editar Cliente) -->
    <div class="modal-overlay" id="client-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="client-modal-title">Nuevo Cliente</h3>
                <button class="modal-close-btn" id="client-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="client-form">
                <div class="form-group">
                    <label for="client-name" class="form-label">Nombre del Cliente *</label>
                    <input type="text" id="client-name" class="form-input" required placeholder="Ej: Ana García Martínez">
                </div>
                <div class="form-group">
                    <label for="client-email" class="form-label">Correo Electrónico</label>
                    <input type="email" id="client-email" class="form-input" placeholder="ana.garcia@email.com">
                </div>
                <div class="form-group">
                    <label for="client-phone" class="form-label">Teléfono</label>
                    <input type="tel" id="client-phone" class="form-input" placeholder="+1 234 567 8900">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="client-form-cancel">Cancelar</button>
                    <button type="submit" class="btn-primary" id="client-form-submit">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pet Modal (Crear/Editar Mascota) -->
    <div class="modal-overlay" id="pet-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="pet-modal-title">Nueva Mascota</h3>
                <button class="modal-close-btn" id="pet-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="pet-form">
                <div class="form-group">
                    <label for="pet-name" class="form-label">Nombre de la Mascota *</label>
                    <input type="text" id="pet-name" class="form-input" required placeholder="Ej: Luna">
                </div>
                <div class="form-group">
                    <label for="pet-species" class="form-label">Especie *</label>
                    <select id="pet-species" class="form-input" required>
                        <option value="">Selecciona una especie</option>
                        <option value="dog">Perro</option>
                        <option value="cat">Gato</option>
                        <option value="bird">Ave</option>
                        <option value="rabbit">Conejo</option>
                        <option value="hamster">Hámster</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pet-breed" class="form-label">Raza</label>
                    <input type="text" id="pet-breed" class="form-input" placeholder="Ej: Golden Retriever">
                </div>
                <div class="form-group">
                    <label for="pet-share-code" class="form-label">Código de Compartir</label>
                    <div class="share-code-container">
                        <input type="text" id="pet-share-code" class="form-input" readonly placeholder="Se genera automáticamente">
                        <button type="button" class="share-code-copy-btn" id="share-code-copy-btn" title="Copiar código">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="pet-form-cancel">Cancelar</button>
                    <button type="submit" class="btn-primary" id="pet-form-submit">Guardar Mascota</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal-overlay" id="import-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Importar Paciente por Código</h3>
                <button class="modal-close-btn" id="import-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="import-form">
                <div class="import-explanation">
                    <p>Introduce el código único de la mascota para importar su expediente completo:</p>
                </div>
                <div class="form-group">
                    <label for="import-code" class="form-label">Código de Paciente *</label>
                    <input type="text" id="import-code" class="form-input" required placeholder="Ej: LUNA-G7K4" style="text-transform: uppercase;">
                    <div class="form-hint">Formato: MASCOTA-XXXX</div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="import-form-cancel">Cancelar</button>
                    <button type="submit" class="btn-primary" id="import-form-submit">Importar Paciente</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pickup Verification Modal -->
    <div class="modal-overlay" id="pickup-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Verificar Recogida</h3>
                <button class="modal-close-btn" id="pickup-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <form id="pickup-form">
                <div class="pickup-explanation">
                    <p>Introduce el código de recogida proporcionado al cliente:</p>
                </div>
                <div class="form-group">
                    <label for="pickup-code" class="form-label">Código de Recogida *</label>
                    <input type="text" id="pickup-code" class="form-input" required placeholder="Ej: REC123" style="text-transform: uppercase;">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" id="pickup-form-cancel">Cancelar</button>
                    <button type="submit" class="btn-primary" id="pickup-form-submit">Verificar Recogida</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pet History Modal -->
    <div class="modal-overlay" id="pet-history-modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="pet-history-modal-title">Historial Médico de [Mascota]</h3>
                <button class="modal-close-btn" id="pet-history-modal-close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="pet-history-modal-body">
                <!-- El historial se cargará aquí dinámicamente -->
                <div class="loading-placeholder">
                    <div class="loader"></div>
                    <p>Cargando historial...</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script type="application/json" id="patients-initial-data">
{
    "professional_id": <?php echo intval($employee_id); ?>,
    "nonce": "<?php echo wp_create_nonce('va_patients_nonce'); ?>",
    "ajax_url": "<?php echo admin_url('admin-ajax.php'); ?>"
}
</script>

