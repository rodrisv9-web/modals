<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Módulo "schedule": renderiza el formulario de horarios del profesional
 * usando el `employee_id` recibido vía REST.
 */

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : 0;
$professional_id = $employee_id;

$manager      = Veterinalia_Appointment_Manager::get_instance();
$db_handler   = Veterinalia_Appointment_Database::get_instance();
$existing_schedule = $manager->get_professional_schedule( $employee_id );

// Listados del usuario para selector (cuando hay múltiples)
$user_listings = [];
if ( get_current_user_id() ) {
    $listing_ids = $db_handler->get_listings_by_author_id( get_current_user_id() );
    $user_listings = array_values( array_filter( array_map( static function ( $listing_id ) {
        $post = get_post( $listing_id );
        if ( ! $post ) {
            return null;
        }

        return [
            'id'    => intval( $post->ID ),
            'title' => $post->post_title,
        ];
    }, $listing_ids ) ) );
}

// Cabecera común del módulo con botón de retorno
?>
<div class="appointments-module-container">
    <div class="module-header">
        <a href="?page=veterinalia-dashboard" class="back-to-prof-main" id="va-schedule-back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg>
            <span>Volver</span>
        </a>
        <h2 class="dashboard-section-title" id="va-schedule-title">Configurar Horario</h2>
        <button class="add-new-item-btn" id="va-schedule-add-btn" title="Añadir nuevo horario">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
        </button>
    </div>
    <!-- Action Sheet Modal -->
    <div id="schedule-action-sheet-modal" class="modal-overlay action-sheet">
        <div class="modal-content">
            <div class="action-sheet-header">
                <h3>¿Qué quieres hacer?</h3>
            </div>
            <div class="action-sheet-options">
                <button class="action-option" id="add-manual-schedule-btn">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Añadir Horario Manualmente</span>
                        <span class="action-subtitle">Configurar horarios personalizados</span>
                    </div>
                </button>
                <button class="action-option" id="import-template-schedule-btn">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Importar desde Plantilla</span>
                        <span class="action-subtitle">Usar horarios predefinidos</span>
                    </div>
                </button>
            </div>
            <button class="action-sheet-cancel" id="cancel-schedule-action-btn">Cancelar</button>
        </div>
    </div>

    <!-- Template Import Modal (empty container) -->
    <div id="schedule-template-import-modal-container" class="modal-overlay full-screen">
        <div class="modal-content">
            <!-- Content will be rendered dynamically by JavaScript -->
        </div>
    </div>
</div>
<?php

// Reutilizar la plantilla existente del formulario de horarios
include VA_PLUGIN_DIR . '/templates/professional-schedule.php';


