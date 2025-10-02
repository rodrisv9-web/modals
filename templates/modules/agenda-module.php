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
        
        <div class="button-group" style="margin-left: auto;">
            <button data-modal-id="view-appointment-modal" class="btn btn-primary"><i class="fa-solid fa-eye"></i> Ver Detalles de Cita</button>
            <button data-modal-id="booking-wizard-modal" class="btn btn-secondary"><i class="fa-solid fa-magic-wand-sparkles"></i> Crear Cita (Wizard)</button>
            <button data-modal-id="logbook-modal" class="btn btn-primary" style="background-color: #3B82F6;"><i class="fa-solid fa-book-medical"></i> Registrar Bitácora</button>
        </div>

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

    <!-- MODAL 1: Ver Detalles de Cita -->
    <div id="view-appointment-modal" class="modal-overlay">
        <div class="modal-content modal-small">
            <div class="modal-handle"></div>
            <header class="modal-header">
                <div>
                    <h3 class="modal-title">Detalles de la Cita</h3>
                    <div style="display: inline-flex; margin-top: 0.5rem; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; background-color: var(--green-100); color: var(--green-800); font-size: 0.875rem; font-weight: 500; border-radius: 99px;"><i class="fa-solid fa-check-circle"></i> Confirmada</div>
                </div>
                <button class="modal-close-btn" aria-label="Cerrar modal"><i class="fa-solid fa-times"></i></button>
            </header>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="info-card">
                    <p class="info-card-header">CLIENTE Y MASCOTA</p>
                    <div class="info-card-body">
                        <div class="info-card-row"><i class="fa-solid fa-user info-card-icon"></i><span class="info-card-data strong">Carlos López</span></div>
                        <div class="info-card-row"><i class="fa-solid fa-paw info-card-icon"></i><span class="info-card-data">Rocky (Pastor Alemán)</span></div>
                    </div>
                </div>
                <div class="info-card">
                    <p class="info-card-header">FECHA Y SERVICIO</p>
                    <div class="info-card-body">
                        <div class="info-card-row"><i class="fa-solid fa-scissors info-card-icon"></i><span class="info-card-data strong">Corte de Pelo y Baño</span></div>
                        <div class="info-card-row"><i class="fa-solid fa-calendar-day info-card-icon"></i><span class="info-card-data">Jueves, 2 de Octubre, 2025</span></div>
                        <div class="info-card-row"><i class="fa-solid fa-clock info-card-icon"></i><span class="info-card-data">12:00 PM - 1:00 PM</span></div>
                    </div>
                </div>
            </div>
            <footer class="modal-footer"><button class="btn btn-light">Cancelar Cita</button><button class="btn btn-primary">Completar y Registrar</button></footer>
        </div>
    </div>

    <!-- MODAL 2: Asistente para Agendar -->
    <div id="booking-wizard-modal" class="modal-overlay">
        <div class="modal-content modal-large">
            <div class="modal-handle"></div>
            <header class="modal-header"><h3 class="modal-title">Agendar Nueva Cita</h3><button class="modal-close-btn" aria-label="Cerrar modal"><i class="fa-solid fa-times"></i></button></header>
            <div class="modal-body">
                <div class="progress-bar">
                    <div class="progress-step active" data-step="1"><div class="progress-step-number">1</div><span class="progress-step-label">Cliente</span></div>
                    <div style="flex-grow: 1; border-top: 2px solid var(--gray-200); margin: 0 1rem;"></div>
                    <div class="progress-step" data-step="2"><div class="progress-step-number">2</div><span class="progress-step-label">Mascota</span></div>
                    <div style="flex-grow: 1; border-top: 2px solid var(--gray-200); margin: 0 1rem;"></div>
                    <div class="progress-step" data-step="3"><div class="progress-step-number">3</div><span class="progress-step-label">Servicio y Hora</span></div>
                    <div style="flex-grow: 1; border-top: 2px solid var(--gray-200); margin: 0 1rem;"></div>
                    <div class="progress-step" data-step="4"><div class="progress-step-number">4</div><span class="progress-step-label">Confirmar</span></div>
                </div>

                <div class="wizard-step-content active" data-step="1">
                    <div style="text-align: center; margin-bottom: 1.5rem;"><h4 style="font-size: 1.125rem; font-weight: 700; color: #1F2937;">Identificar al Cliente</h4><p style="font-size: 0.875rem; color: #6B7280;">Busca un cliente existente o registra uno nuevo.</p></div>
                    <div class="form-group"><div class="input-with-icon"><i class="fa-solid fa-search"></i><input id="client-search-input" type="text" class="form-input" placeholder="Buscar por nombre, email..."></div></div>
                    <div id="client-search-results" style="border: 1px solid var(--gray-200); border-radius: 0.5rem; background-color: #F9FAFB; padding: 1rem; margin-top: 1rem; color: #6B7280; font-size: 0.875rem;"><p style="text-align: center;">Introduce al menos 3 caracteres para buscar.</p></div>
                </div>

                <div class="wizard-step-content" data-step="2">
                     <div style="text-align: center; margin-bottom: 1.5rem;"><h4 style="font-size: 1.125rem; font-weight: 700; color: #1F2937;">Seleccionar Mascota</h4><p style="font-size: 0.875rem; color: #6B7280;">Elige la mascota para la cita o añade una nueva.</p></div>
                     <div id="wizard-client-info" style="background-color: var(--purple-100); color: var(--purple-800); padding: 0.75rem; border-radius: 0.5rem; text-align: center; font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem;"></div>
                     <div id="wizard-pet-list" style="display: flex; flex-direction: column; gap: 0.75rem;"></div>
                </div>

                <div class="wizard-step-content" data-step="2.5">
                    <div style="text-align: center; margin-bottom: 1.5rem;"><h4 style="font-size: 1.125rem; font-weight: 700; color: #1F2937;">Registrar Nueva Mascota</h4><p id="wizard-new-pet-subtitle" style="font-size: 0.875rem; color: #6B7280;"></p></div>
                    <form id="wizard-new-pet-form" style="display: flex; flex-direction: column; gap: 1rem;">
                        <div class="form-group"><label for="new-pet-name" class="form-label">Nombre de la Mascota</label><input type="text" id="new-pet-name" class="form-input" required></div>
                        <div class="grid grid-cols-2-md">
                            <div class="form-group"><label for="new-pet-species" class="form-label">Especie</label><select id="new-pet-species" class="form-select"><option>Perro</option><option>Gato</option><option>Otro</option></select></div>
                            <div class="form-group"><label for="new-pet-breed" class="form-label">Raza (Opcional)</label><input type="text" id="new-pet-breed" class="form-input"></div>
                        </div>
                         <div class="form-actions" style="margin-top: 1rem; padding-top: 0; border: none; justify-content: flex-end;"><button type="submit" class="btn btn-primary">Guardar Mascota</button></div>
                    </form>
                </div>

                <div class="wizard-step-content" data-step="3">
                    <div style="text-align: center; margin-bottom: 1.5rem;"><h4 style="font-size: 1.125rem; font-weight: 700; color: #1F2937;">Servicio y Horario</h4><p id="wizard-service-subtitle" style="font-size: 0.875rem; color: #6B7280;"></p></div>
                     <div class="grid grid-cols-3-md">
                        <div class="form-group"><label class="form-label" for="wiz-category">Categoría</label><select id="wiz-category" class="form-select"></select></div>
                        <div class="form-group"><label class="form-label" for="wiz-service">Servicio</label><select id="wiz-service" class="form-select" disabled><option>Elige una categoría primero</option></select></div>
                        <div class="form-group"><label class="form-label" for="wiz-date">Fecha</label><input type="date" id="wiz-date" class="form-input" value="2025-10-02"></div>
                     </div>
                     <div class="form-group">
                        <label class="form-label">Horarios Disponibles</label>
                        <div id="wiz-slots-container">
                            <div id="wiz-slots-wrapper">
                                <div id="wiz-slots" style="color: #9CA3AF; padding: 0.5rem; border: 1px solid #E5E7EB; border-radius: 0.5rem; text-align: center;">Elige un servicio</div>
                            </div>
                            <div id="wiz-slots-pagination"></div>
                        </div>
                     </div>
                </div>

                <div class="wizard-step-content" data-step="4">
                    <div style="text-align: center; margin-bottom: 1.5rem;"><h4 style="font-size: 1.125rem; font-weight: 700; color: #1F2937;">Confirmar Cita</h4><p style="font-size: 0.875rem; color: #6B7280;">Revisa los detalles antes de confirmar.</p></div>
                    <div id="wizard-summary" style="display: flex; flex-direction: column; gap: 1rem;"></div>
                </div>
            </div>
            <footer class="modal-footer">
                <button class="btn btn-light" data-wizard-action="back" style="display: none;">Anterior</button>
                <button class="btn btn-primary" data-wizard-action="next" disabled>Siguiente</button>
                <button class="btn btn-secondary" data-wizard-action="confirm" style="display: none;">Confirmar Cita</button>
            </footer>
        </div>
    </div>

    <!-- MODAL 3: Registrar en Bitácora -->
    <div id="logbook-modal" class="modal-overlay">
        <div class="modal-content modal-large">
            <div class="modal-handle"></div>
            <header class="modal-header"><h3 class="modal-title">Registrar en Bitácora</h3><button class="modal-close-btn" aria-label="Cerrar modal"><i class="fa-solid fa-times"></i></button></header>
            <div class="modal-body">
                <div class="info-card" style="margin-bottom: 1.5rem;"><p class="info-card-header">REGISTRANDO PARA LA CITA</p><div class="info-card-body"><div class="info-card-row"><i class="fa-solid fa-paw info-card-icon"></i><span class="info-card-data strong">Rocky (Corte de Pelo y Baño)</span></div></div></div>
                <form style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group"><label class="form-label" for="log-title">Título / Motivo de la Visita</label><input type="text" id="log-title" class="form-input" value="Corte de Pelo y Baño" required></div>
                    <div class="form-group"><label class="form-label" for="log-vaccine">Vacuna Aplicada (Opcional)</label><select id="log-vaccine" class="form-select"><option value="">Ninguna</option><option value="rabia">Vacuna Antirrábica</option><option value="polivalente">Vacuna Polivalente</option></select></div>
                    <div class="form-group"><label class="form-label" for="log-notes">Observaciones y Notas</label><textarea id="log-notes" class="form-textarea" placeholder="Añade cualquier observación relevante..."></textarea></div>
                </form>
            </div>
            <footer class="modal-footer"><button class="btn btn-light">Completar sin Registrar</button><button class="btn btn-primary">Guardar y Completar Cita</button></footer>
        </div>
    </div>
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