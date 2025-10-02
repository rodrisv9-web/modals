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

    <div id="appointment-modal" class="modal-overlay hidden agenda-modal">
        <div class="modal-content modal-small appointment-modal">
            <div class="modal-handle" aria-hidden="true"></div>
            <header class="modal-header appointment-modal__header">
                <div class="appointment-modal__title-group">
                    <h3 id="modal-title" class="modal-title">Detalles de la Cita</h3>
                    <div id="appointment-status-pill" class="status-pill" hidden>
                        <span class="status-pill-indicator" aria-hidden="true"></span>
                        <span id="appointment-status-label">Estado</span>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" aria-label="Cerrar modal">
                    <i class="fas fa-times"></i>
                </button>
            </header>
            <div class="modal-body appointment-modal__body">
                <div id="modal-details" class="appointment-details-grid"></div>
            </div>
            <footer class="modal-footer appointment-modal__footer">
                <div id="status-buttons-container" class="status-actions"></div>
            </footer>
        </div>
    </div>

    <div id="agenda-booking-wizard-modal" class="modal-overlay hidden agenda-modal">
        <div class="modal-content modal-large agenda-wizard">
            <div class="modal-handle" aria-hidden="true"></div>
            <header class="modal-header wizard-header">
                <div class="wizard-header__text">
                    <h3 id="wizard-title" class="modal-title">Agendar Nueva Cita</h3>
                    <p id="wizard-subtitle" class="modal-subtitle">Sigue los pasos para crear una cita en minutos.</p>
                </div>
                <button id="wizard-close-btn" class="modal-close-btn" aria-label="Cerrar modal">
                    <i class="fas fa-times"></i>
                </button>
            </header>

            <div id="wizard-body" class="modal-body wizard-body">
                <nav class="progress-bar" aria-label="Progreso del asistente">
                    <div class="progress-step active" data-progress-step="1">
                        <div class="progress-step-number">1</div>
                        <span class="progress-step-label">Cliente</span>
                    </div>
                    <div class="progress-bar-divider" aria-hidden="true"></div>
                    <div class="progress-step" data-progress-step="2">
                        <div class="progress-step-number">2</div>
                        <span class="progress-step-label">Mascota</span>
                    </div>
                    <div class="progress-bar-divider" aria-hidden="true"></div>
                    <div class="progress-step" data-progress-step="3">
                        <div class="progress-step-number">3</div>
                        <span class="progress-step-label">Servicio y horario</span>
                    </div>
                </nav>

                <div class="wizard-steps">
                    <section class="wizard-step wizard-step-content active" data-step="1" aria-labelledby="wizard-step-1-title">
                        <div class="wizard-step-header">
                            <h4 id="wizard-step-1-title" class="wizard-step-title">Identificar al cliente</h4>
                            <p class="wizard-step-subtitle">Busca un cliente existente o regístralo en el momento.</p>
                        </div>
                        <div class="wizard-step-body">
                            <div class="form-group">
                                <label for="wizard-client-search" class="form-label">Buscar cliente por nombre o email</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                    <input type="text" id="wizard-client-search" class="form-input" placeholder="Comienza a escribir para buscar...">
                                </div>
                            </div>
                            <div id="wizard-search-results" class="search-results-card">
                                <p class="search-placeholder">Introduce al menos 3 caracteres para buscar.</p>
                            </div>
                        </div>
                        <div class="wizard-step-footer">
                            <button id="wizard-new-client-btn" class="btn-secondary">
                                <i class="fas fa-user-plus"></i> Registrar cliente nuevo
                            </button>
                        </div>
                    </section>

                    <section class="wizard-step wizard-step-content" data-step="1.5" aria-labelledby="wizard-step-1-5-title">
                        <div class="wizard-step-header">
                            <h4 id="wizard-step-1-5-title" class="wizard-step-title">Registrar cliente nuevo</h4>
                            <p class="wizard-step-subtitle">Completa la información básica para crear el perfil.</p>
                        </div>
                        <form id="wizard-client-form-inline" class="wizard-form">
                            <div class="form-group">
                                <label for="wizard-client-name-inline" class="form-label">Nombre del cliente *</label>
                                <input type="text" id="wizard-client-name-inline" name="client-name" class="form-input" required placeholder="Ej: Ana García Martínez">
                            </div>
                            <div class="form-group">
                                <label for="wizard-client-email-inline" class="form-label">Correo electrónico</label>
                                <input type="email" id="wizard-client-email-inline" name="client-email" class="form-input" placeholder="ana.garcia@email.com">
                                <small class="form-help">Se enviará una invitación automática si se proporciona email.</small>
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
                                <small class="form-help">Selecciona la lada y escribe el número local.</small>
                            </div>
                            <div class="wizard-step-footer">
                                <button type="button" id="wizard-back-to-search-inline" class="btn-light">Volver a búsqueda</button>
                                <button type="submit" class="btn-primary" id="wizard-create-client-inline">Crear cliente</button>
                            </div>
                        </form>
                    </section>

                    <section class="wizard-step wizard-step-content" data-step="2" aria-labelledby="wizard-step-2-title">
                        <div class="wizard-step-header">
                            <h4 id="wizard-step-2-title" class="wizard-step-title">Seleccionar mascota</h4>
                            <p class="wizard-step-subtitle">Elige la mascota asociada a la cita o regístrala rápidamente.</p>
                        </div>
                        <div class="wizard-step-body">
                            <div class="info-banner" id="wizard-selected-client-banner">
                                Cliente seleccionado: <span id="wizard-selected-client-name"></span>
                            </div>
                            <div id="wizard-pet-selection" class="pet-selection-list"></div>
                        </div>
                        <div class="wizard-step-footer">
                            <button id="wizard-back-to-search-btn" class="btn-light">Volver a búsqueda</button>
                            <button id="wizard-new-pet-btn" class="btn-secondary">
                                <i class="fas fa-plus"></i> Registrar mascota nueva
                            </button>
                        </div>
                    </section>

                    <section class="wizard-step wizard-step-content" data-step="2.5" aria-labelledby="wizard-step-2-5-title">
                        <div class="wizard-step-header">
                            <h4 id="wizard-step-2-5-title" class="wizard-step-title">Registrar mascota nueva</h4>
                            <p class="wizard-step-subtitle">Añade los datos básicos para crear la ficha de la mascota.</p>
                        </div>
                        <form id="wizard-pet-form-inline" class="wizard-form">
                            <p class="wizard-form-intro">Cliente: <strong id="wizard-selected-client-name-pet"></strong></p>
                            <div class="form-group">
                                <label for="wizard-pet-name-inline" class="form-label">Nombre de la mascota *</label>
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
                                <label for="wizard-pet-share-code-inline" class="form-label">Share code</label>
                                <div class="input-group">
                                    <input type="text" id="wizard-pet-share-code-inline" name="pet-share-code" class="form-input" readonly placeholder="Se genera automáticamente">
                                    <button type="button" class="btn-light" id="wizard-regenerate-code-inline" aria-label="Generar nuevo share code">🔄</button>
                                </div>
                                <small class="form-help">Este código se enviará al cliente para vincular la mascota.</small>
                            </div>
                            <div class="wizard-step-footer">
                                <button type="button" id="wizard-back-to-pets-inline" class="btn-light">Volver a mascotas</button>
                                <button type="submit" class="btn-primary" id="wizard-create-pet-inline">Crear mascota</button>
                            </div>
                        </form>
                    </section>

                    <section class="wizard-step wizard-step-content" data-step="3" aria-labelledby="wizard-step-3-title">
                        <div class="wizard-step-header">
                            <h4 id="wizard-step-3-title" class="wizard-step-title">Servicio y horario</h4>
                            <p id="wizard-selected-pet-name" class="wizard-step-subtitle"></p>
                        </div>
                        <div class="wizard-step-body">
                            <div class="wizard-grid">
                                <div class="form-group">
                                    <label class="form-label" for="wiz-category">Categoría</label>
                                    <select id="wiz-category" class="form-input">
                                        <option value="">Selecciona una categoría</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz-service">Servicio</label>
                                    <select id="wiz-service" class="form-input" disabled>
                                        <option value="">Selecciona una categoría primero</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="wiz-date">Fecha</label>
                                    <input type="date" id="wiz-date" class="form-input">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="wiz-slots">Horarios disponibles</label>
                                <div id="wiz-slots-container" class="slot-picker">
                                    <div id="wiz-slots-wrapper" class="slot-list">
                                        <div id="wiz-slots" class="slots-message">Selecciona un servicio para ver los horarios disponibles.</div>
                                    </div>
                                    <div id="wiz-slots-pagination" class="slot-pagination" aria-hidden="true"></div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step-footer">
                            <button id="wizard-back-to-pets-btn" class="btn-light">Volver a mascotas</button>
                            <button id="wizard-confirm-appointment-btn" class="btn-primary" disabled>Confirmar cita</button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
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