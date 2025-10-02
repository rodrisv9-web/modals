<?php
/**
 * Módulo "Agenda Interactiva" v1.0 - Integrado nativamente al plugin
 * Reemplaza el módulo appointments-module.php con funcionalidad avanzada
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : (isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0);
$manager = Veterinalia_Appointment_Manager::get_instance();
$db_handler = Veterinalia_Appointment_Database::get_instance();

// Verificar que las clases existan
if (!$manager || !$db_handler) {
    echo '<div class="error-message">Error: No se pudieron inicializar las clases del plugin</div>';
    return;
}

// Obtener datos reales del profesional usando métodos nativos
try {
    $appointments = $manager->get_professional_appointments($employee_id);
    if ($appointments === false) {
        $appointments = [];
    }
} catch (Exception $e) {
    error_log('Error al obtener citas: ' . $e->getMessage());
    $appointments = [];
}

try {
    $services = $db_handler->get_services_by_professional($employee_id);
    if ($services === false) {
        $services = [];
    }
} catch (Exception $e) {
    error_log('Error al obtener servicios: ' . $e->getMessage());
    $services = [];
}

// Preparar datos para JavaScript
$appointments_data = [];
if (!empty($appointments)) {
    foreach ($appointments as $app) {
        $timestamp = strtotime($app->appointment_start);
        
        // Verificar que los campos existan antes de usarlos
        $appointments_data[] = [
            'id' => $app->id ?? 0,
            'date' => wp_date('Y-m-d', $timestamp),
            'start' => wp_date('H:i', $timestamp),
            'end' => wp_date('H:i', strtotime($app->appointment_end)), // Usar wp_date aquí también
            'service' => $app->service_name ?? 'Servicio no especificado',
            'service_id' => isset($app->service_id) ? intval($app->service_id) : null,
            'entry_type_id' => isset($app->entry_type_id) ? intval($app->entry_type_id) : null,
            'client_id' => $app->client_id, // Añadido client_id
            'client' => $app->client_name_actual ?? $app->client_name ?? 'Cliente no especificado', // Usar nuevo campo
            'pet_id' => $app->pet_id, // Añadido pet_id
            'pet' => $app->pet_name_actual ?? $app->pet_name ?? 'Mascota no especificada', // Usar nuevo campo
            'status' => $app->status,
            'phone' => $app->client_phone,
            'email' => $app->client_email,
            'description' => $app->notes
        ];
    }
}

$services_data = [];
if (!empty($services)) {
    foreach ($services as $service) {
        $services_data[] = [
            'id' => $service->service_id,
            'name' => $service->name,
            'duration' => $service->duration ?? 60,
            'price' => $service->price ?? 0,
            'entry_type_id' => isset($service->entry_type_id) ? intval($service->entry_type_id) : 0
        ];
    }
}
?>

<div class="agenda-module-container" id="agenda-module" data-professional-id="<?php echo esc_attr($employee_id); ?>">
    
    <div class="module-header">
        <!-- ESTRUCTURA DESKTOP (>480px) -->
        <div class="header-left-section">
            <a href="#" class="back-to-prof-main">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                </svg>
                <span>Volver</span>
            </a>
        </div>
        
        <div class="header-center-content">
            <div class="date-navigation">
                </div>
            <div class="view-switcher">
                <button id="view-switcher-btn" class="view-switcher-btn">
                    <span>Agenda</span><i class="fas fa-chevron-down"></i>
                </button>
                <div id="view-switcher-menu" class="view-switcher-menu hidden">
                    <a href="#" data-view="agenda" class="active">Agenda</a>
                    <a href="#" data-view="day" class="">Día</a>
                    <a href="#" data-view="week" class="">Semana</a>
                </div>
            </div>
        </div>
        
        <button class="add-new-item-btn" id="add-appointment-btn" title="Añadir cita">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path>
            </svg>
        </button>

        <!-- ESTRUCTURA MÓVIL (<=480px) - Solo visible en móviles -->
        <!-- FILA 1: [Volver] [Cambio de Vista] [+] -->
        <div class="mobile-top-controls">
            <div class="header-left-section">
                <a href="#" class="back-to-prof-main">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                    </svg>
                    <span>Volver</span>
                </a>
            </div>
            
            <div class="mobile-view-switcher">
                <div class="view-switcher">
                    <button id="mobile-view-switcher-btn" class="view-switcher-btn">
                        <span>Agenda</span><i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="mobile-view-switcher-menu" class="view-switcher-menu hidden">
                        <a href="#" data-view="agenda" class="active">Agenda</a>
                        <a href="#" data-view="day" class="">Día</a>
                        <a href="#" data-view="week" class="">Semana</a>
                    </div>
                </div>
            </div>
            
            <button class="add-new-item-btn" id="mobile-add-appointment-btn" title="Añadir cita">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path>
                </svg>
            </button>
        </div>

        <!-- FILA 2: date-navigation -->
        <div class="mobile-date-navigation">
            <div class="date-navigation" id="mobile-date-navigation">
                </div>
        </div>
    </div>

    <div class="agenda-body" id="agenda-body-container">
        <div class="loading-state">
            <div class="loader"></div>
            <p>Cargando agenda...</p>
        </div>
    </div>

    <div id="appointment-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <button type="button" class="modal-close-x" aria-label="Cerrar modal">&times;</button>
            <h3 id="modal-title" class="modal-title">Detalles de la Cita</h3>
            <div id="modal-details" class="modal-details"></div>
            
            <!-- SECCIÓN PARA CAMBIAR ESTADO -->
            <div class="modal-section">
                <p class="modal-section-title">Cambiar estado de la cita:</p>
                <div id="status-buttons-container" class="status-buttons-grid">
                    <!-- Los botones se generan dinámicamente aquí -->
                </div>
            </div>

            
        </div>
    </div>

    <!-- <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 1.1 --> -->
    <div id="agenda-booking-wizard-modal" class="modal-overlay hidden">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="wizard-title" class="modal-title">Agendar Nueva Cita</h3>
                <button id="wizard-close-btn" class="modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="wizard-body" class="modal-body">

                <div class="wizard-step active" data-step="1">
                    <h4 class="wizard-step-title">Paso 1: Identificar al Cliente</h4>
                    <div class="form-group">
                        <label for="wizard-client-search" class="form-label">Buscar cliente por nombre o email</label>
                        <input type="text" id="wizard-client-search" class="form-input" placeholder="Comienza a escribir para buscar...">
                    </div>
                    <div id="wizard-search-results" class="search-results-container">
                        <p class="text-center text-gray-500">Introduce al menos 3 caracteres para buscar.</p>
                    </div>
                    <div class="form-actions" style="justify-content: center; margin-top: 1rem;">
                        <button id="wizard-new-client-btn" class="btn-secondary">
                            <i class="fas fa-user-plus"></i> Registrar Cliente Nuevo
                        </button>
                    </div>
                </div>

                <div class="wizard-step" data-step="1.5" style="display: none;">
                    <h4 class="wizard-step-title">Paso 1.5: Registrar Cliente Nuevo</h4>
                    <form id="wizard-client-form-inline">
                        <div class="form-group">
                            <label for="wizard-client-name-inline" class="form-label">Nombre del Cliente *</label>
                            <input type="text" id="wizard-client-name-inline" name="client-name" class="form-input" required placeholder="Ej: Ana García Martínez">
                        </div>
                        <div class="form-group">
                            <label for="wizard-client-email-inline" class="form-label">Correo Electrónico</label>
                            <input type="email" id="wizard-client-email-inline" name="client-email" class="form-input" placeholder="ana.garcia@email.com">
                            <small class="form-help">Se enviará una invitación automática si se proporciona email</small>
                        </div>
                        <div class="form-group">
                            <label for="wizard-client-phone-inline" class="form-label">Teléfono</label>
                            <div class="phone-input-group">
                                <select id="wizard-client-phone-code-inline" name="client-phone-code" class="form-input phone-code-select">
                                    <option value="+52">🇲🇽 +52 MX</option>
                                    <option value="+1">🇺🇸 +1 US</option>
                                    <option value="+1">🇨🇦 +1 CA</option>
                                    <option value="+34">🇪🇸 +34 ES</option>
                                    <option value="+44">🇬🇧 +44 UK</option>
                                    <option value="+33">🇫🇷 +33 FR</option>
                                    <option value="+49">🇩🇪 +49 DE</option>
                                    <option value="+39">🇮🇹 +39 IT</option>
                                    <option value="+7">🇷🇺 +7 RU</option>
                                    <option value="+81">🇯🇵 +81 JP</option>
                                    <option value="+86">🇨🇳 +86 CN</option>
                                    <option value="+55">🇧🇷 +55 BR</option>
                                    <option value="+54">🇦🇷 +54 AR</option>
                                    <option value="+57">🇨🇴 +57 CO</option>
                                    <option value="+56">🇨🇱 +56 CL</option>
                                    <option value="+58">🇻🇪 +58 VE</option>
                                    <option value="+503">🇸🇻 +503 SV</option>
                                    <option value="+505">🇳🇮 +505 NI</option>
                                    <option value="+506">🇨🇷 +506 CR</option>
                                    <option value="+507">🇵🇦 +507 PA</option>
                                    <option value="+502">🇬🇹 +502 GT</option>
                                    <option value="+504">🇭🇳 +504 HN</option>
                                </select>
                                <input type="tel" id="wizard-client-phone-inline" name="client-phone" class="form-input phone-number-input" placeholder="Número local (ej: 555 123 4567)">
                            </div>
                            <small class="form-help">Selecciona la lada y escribe el número local</small>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="wizard-back-to-search-inline" class="btn-secondary">Volver a Búsqueda</button>
                            <button type="submit" class="btn-primary" id="wizard-create-client-inline">Crear Cliente</button>
                        </div>
                    </form>
                </div>

                <div class="wizard-step" data-step="2" style="display: none;">
                    <h4 class="wizard-step-title">Paso 2: Seleccionar Mascota</h4>
                    <p>Cliente: <strong id="wizard-selected-client-name"></strong></p>
                    <div id="wizard-pet-selection" class="pet-selection-container">
                        </div>
                    <div class="form-actions" style="margin-top: 1rem;">
                        <button id="wizard-back-to-search-btn" class="btn-secondary">Volver a la Búsqueda</button>
                        <button id="wizard-new-pet-btn" class="btn-primary">
                            <i class="fas fa-plus"></i> Registrar Mascota Nueva
                        </button>
                    </div>
                </div>

                <div class="wizard-step" data-step="2.5" style="display: none;">
                    <h4 class="wizard-step-title">Paso 2.5: Registrar Mascota Nueva</h4>
                    <p>Cliente: <strong id="wizard-selected-client-name-pet"></strong></p>
                    <form id="wizard-pet-form-inline">
                        <div class="form-group">
                            <label for="wizard-pet-name-inline" class="form-label">Nombre de la Mascota *</label>
                            <input type="text" id="wizard-pet-name-inline" name="pet-name" class="form-input" required placeholder="Ej: Luna">
                        </div>
                        <div class="form-group">
                            <label for="wizard-pet-species-inline" class="form-label">Especie *</label>
                            <select id="wizard-pet-species-inline" name="pet-species" class="form-input" required>
                                <option value="">Selecciona una especie</option>
                                <option value="dog">Perro</option>
                                <option value="cat">Gato</option>
                                <option value="bird">Ave</option>
                                <option value="rabbit">Conejo</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="wizard-pet-breed-inline" class="form-label">Raza</label>
                            <input type="text" id="wizard-pet-breed-inline" name="pet-breed" class="form-input" placeholder="Ej: Labrador, Siamés, etc.">
                        </div>
                        <div class="form-group">
                            <label for="wizard-pet-gender-inline" class="form-label">Género</label>
                            <select id="wizard-pet-gender-inline" name="pet-gender" class="form-input">
                                <option value="unknown">No especificar</option>
                                <option value="male">Macho</option>
                                <option value="female">Hembra</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="wizard-pet-share-code-inline" class="form-label">Share Code</label>
                            <div class="input-group">
                                <input type="text" id="wizard-pet-share-code-inline" name="pet-share-code" class="form-input" readonly placeholder="Se genera automáticamente">
                                <button type="button" class="btn-secondary" id="wizard-regenerate-code-inline">🔄</button>
                            </div>
                            <small class="form-help">Este código se enviará al cliente por email para vincular su mascota</small>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="wizard-back-to-pets-inline" class="btn-secondary">Volver a Mascotas</button>
                            <button type="submit" class="btn-primary" id="wizard-create-pet-inline">Crear Mascota</button>
                        </div>
                    </form>
                </div>

                <div class="wizard-step" data-step="3" style="display: none;">
                    <h4 class="wizard-step-title">Paso 3: Seleccionar Servicio y Horario</h4>
                    <p>Agendando para: <strong id="wizard-selected-pet-name"></strong></p>
                    
                                                    <div id="wizard-scheduling-interface" class="service-selection-layout">
                                    <div class="category-tabs">
                                    </div>
                                    <div class="service-list-content">
                                    </div>
                                    <div class="time-selection-content" style="display: none;">
                                        <div class="va-calendar-wrapper">
                                            <div id="va-calendar-header"></div>
                                            <div id="va-calendar-grid"></div>
                                        </div>
                                        <div id="va-slots-container" class="time-slots-wrapper">
                                            <p class="initial-message">Selecciona una fecha para ver los horarios.</p>
                                        </div>
                                    </div>
                                </div>

                    <div class="form-actions" style="margin-top: 1rem;">
                        <button id="wizard-back-to-pets-btn" class="btn-secondary">Volver a Mascotas</button>
                        <button id="wizard-confirm-appointment-btn" class="btn-primary" disabled>Confirmar Cita</button>
                    </div>
                </div>

            </div>
        </div>
        <script>
            // Mensaje de depuración para verificar la carga del nuevo HTML
            console.log("✅ Chocovainilla: Nuevo HTML del Wizard de Agendamiento cargado en el DOM.");
        </script>
    </div>
    <!-- <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 1.1 --> -->

    <!-- El modal de la bitácora ha sido eliminado. La funcionalidad ahora está integrada en #appointment-modal -->
</div>

<script type="application/json" id="agenda-initial-data">
{
    "professional_id": <?php echo intval($employee_id); ?>,
    "appointments": <?php echo json_encode($appointments_data); ?>,
    "services": <?php echo json_encode($services_data); ?>,
    "nonce": "<?php echo wp_create_nonce('va_agenda_nonce'); ?>",
    "ajax_url": "<?php echo admin_url('admin-ajax.php'); ?>"
}
</script>

<?php
// ¡IMPORTANTE! Se eliminan las llamadas a wp_enqueue_style y wp_enqueue_script de aquí.
// Estos archivos ya se cargan correctamente desde la clase Veterinalia_Appointment_Manager
// cuando se renderiza el shortcode principal del dashboard.
?>