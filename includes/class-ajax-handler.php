<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar todas las peticiones AJAX de Veterinalia Appointment.
 */
require_once __DIR__ . "/class-ajax-services-handler.php";
require_once __DIR__ . "/class-ajax-templates-handler.php";
class Veterinalia_Appointment_AJAX_Handler {

    private static $instance = null;

    /**
     * Obtiene la única instancia de la clase.
     * @return Veterinalia_Appointment_AJAX_Handler
     */
    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor.
    }

    /**
     * Inicializa los hooks de AJAX.
     */
    public function init(): void {
        // Aquí moveremos todos los add_action para wp_ajax_*
        // Hooks para AJAX (para usuarios logueados y no logueados)
        add_action( 'wp_ajax_va_save_professional_schedule', [ $this, 'handle_save_professional_schedule' ] );
        add_action( 'wp_ajax_nopriv_va_save_professional_schedule', [ $this, 'handle_save_professional_schedule' ] );

        add_action( 'wp_ajax_va_get_professional_availability', [ $this, 'handle_get_professional_availability' ] );
        add_action( 'wp_ajax_nopriv_va_get_professional_availability', [ $this, 'handle_get_professional_availability' ] );

        add_action( 'wp_ajax_va_book_appointment', [ $this, 'handle_book_appointment' ] );
        add_action( 'wp_ajax_nopriv_va_book_appointment', [ $this, 'handle_book_appointment' ] );

        add_action( 'wp_ajax_va_update_appointment_status', [ $this, 'handle_update_appointment_status' ] ); 
        add_action( 'wp_ajax_nopriv_va_update_appointment_status', [ $this, 'handle_update_appointment_status' ] ); 

        add_action( 'wp_ajax_va_get_professional_schedule', [ $this, 'handle_get_professional_schedule' ] ); // Nuevo hook AJAX
        add_action( 'wp_ajax_nopriv_va_get_professional_schedule', [ $this, 'handle_get_professional_schedule' ] ); // Asegurar que funcione si es un perfil público
        add_action( 'wp_ajax_va_get_professional_appointments', [ $this, 'handle_get_professional_appointments' ] ); // Nuevo hook AJAX para citas
        add_action( 'wp_ajax_nopriv_va_get_professional_appointments', [ $this, 'handle_get_professional_appointments' ] ); // Asegurar que funcione si es un perfil público
        add_action( 'wp_ajax_va_get_availability_for_range', [ $this, 'handle_get_availability_for_range' ] ); // Nuevo hook AJAX para obtener disponibilidad por rango
        add_action( 'wp_ajax_nopriv_va_get_availability_for_range', [ $this, 'handle_get_availability_for_range' ] ); // Asegurar que funcione si es un perfil público
        Veterinalia_Appointment_AJAX_Services_Handler::get_instance()->init();
        Veterinalia_Appointment_AJAX_Templates_Handler::get_instance()->init();
    }

    /**
     * Obtiene el manejador especializado de categorías y servicios.
     *
     * @return Veterinalia_Appointment_AJAX_Services_Handler
     */
    private function get_services_handler(): Veterinalia_Appointment_AJAX_Services_Handler {
        return Veterinalia_Appointment_AJAX_Services_Handler::get_instance();
    }

    /**
     * Obtiene el manejador especializado de plantillas y agenda.
     *
     * @return Veterinalia_Appointment_AJAX_Templates_Handler
     */
    private function get_templates_handler(): Veterinalia_Appointment_AJAX_Templates_Handler {
        return Veterinalia_Appointment_AJAX_Templates_Handler::get_instance();
    }

    // Aquí moveremos todas las funciones handle_*

    // =======================================================
    // === MANEJADORES PARA HORARIOS (SCHEDULE)
    // =======================================================

    /**
     * Manejador AJAX para guardar el horario del profesional.
     */
    public function handle_save_professional_schedule() {
        check_ajax_referer( 'va_appointment_nonce', 'nonce' );

        $professional_id = isset( $_POST['professional_id'] ) ? intval( $_POST['professional_id'] ) : 0;
        $schedule_data   = isset( $_POST['schedule_data'] ) ? json_decode( wp_unslash( $_POST['schedule_data'] ), true ) : [];

        if ( empty( $professional_id ) || empty( $schedule_data ) ) {
            wp_send_json_error( 'Datos de solicitud incompletos.' );
        }

        // Verificar si el usuario actual tiene permisos para editar este listado
        if ( ! current_user_can( 'edit_post', $professional_id ) ) {
            wp_send_json_error( 'No tienes permiso para editar este profesional.' );
        }

        // Guardar el horario usando el manager
        $manager = Veterinalia_Appointment_Manager::get_instance();
        if ( $manager->save_professional_schedule( $professional_id, $schedule_data ) ) {
            // Obtener el horario actualizado para evitar una segunda petición
            $updated_schedule = $manager->get_professional_schedule( $professional_id );
            wp_send_json_success( array( 
                'message' => 'Horario guardado correctamente.',
                'data' => $updated_schedule 
            ) );
        } else {
            wp_send_json_error( 'Error al guardar el horario.' );
        }
    }

    /**
     * Manejador AJAX para obtener la disponibilidad del profesional.
     */
    public function handle_get_professional_availability() {
        check_ajax_referer( 'va_appointment_nonce', 'nonce' ); // Verificar el nonce de seguridad

        $professional_id = isset( $_POST['professional_id'] ) ? intval( $_POST['professional_id'] ) : 0;
        $selected_date   = isset( $_POST['selected_date'] ) ? sanitize_text_field( $_POST['selected_date'] ) : '';

        if ( empty( $professional_id ) || empty( $selected_date ) ) {
            wp_send_json_error( 'Datos de solicitud incompletos.' );
        }

        // Obtener los slots disponibles usando el manager
        $manager = Veterinalia_Appointment_Manager::get_instance();
        $available_slots = $manager->get_available_slots_for_date( $professional_id, $selected_date );

        wp_send_json_success( $available_slots );
    }

    /**
     * Manejador AJAX para obtener la disponibilidad para un rango de fechas.
     * CORREGIDO para pasar una duración por defecto y ser compatible con la nueva lógica.
     */
    // =======================================================
    // === MANEJADORES PARA DISPONIBILIDAD (AVAILABILITY)
    // =======================================================

    public function handle_get_availability_for_range() {
        check_ajax_referer('va_appointment_nonce', 'nonce');

        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        $professional_id = intval($_POST['professional_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Se usa 30 minutos como valor por defecto temporal.
        $service_duration = isset($_POST['duration']) ? intval($_POST['duration']) : 30;

        if (strtotime($start_date) > strtotime($end_date)) {
            wp_send_json_error('Rango de fechas inválido');
        }

        $manager = Veterinalia_Appointment_Manager::get_instance();
        $availability_data = $manager->get_available_slots_for_range($professional_id, $start_date, $end_date, $service_duration);

        wp_send_json_success($availability_data);
    }

/**
 * Manejador AJAX para reservar una cita.
 * CORREGIDO para validar 'appointment_start' en lugar de los campos separados.
 */
    // =======================================================
    // === MANEJADORES PARA RESERVAS (BOOKING)
    // =======================================================

public function handle_book_appointment() {
    check_ajax_referer( 'va_appointment_nonce', 'nonce' );

    $booking_data_json = isset( $_POST['booking_data'] ) ? wp_unslash( $_POST['booking_data'] ) : '';
    $booking_data      = json_decode( $booking_data_json, true );

    if ( ! $booking_data || ! is_array( $booking_data ) ) {
         wp_send_json_error( [ 'message' => 'Datos de reserva inválidos.' ] );
    }

    // <<-- INICIO DE LA CORRECCIÓN -->>

    // Obtenemos los campos correctos que envía el JavaScript.
    $professional_id   = isset( $booking_data['professional_id'] ) ? intval( $booking_data['professional_id'] ) : 0;
    $service_id        = isset( $booking_data['service_id'] ) ? intval( $booking_data['service_id'] ) : 0;
    $appointment_start = isset( $booking_data['appointment_start'] ) ? sanitize_text_field( $booking_data['appointment_start'] ) : '';
    $client_name       = isset( $booking_data['client_name'] ) ? sanitize_text_field( $booking_data['client_name'] ) : '';
    $client_email      = isset( $booking_data['client_email'] ) ? sanitize_email( $booking_data['client_email'] ) : '';
    
    // Validamos usando los nombres de campo correctos ('appointment_start' y 'service_id').
    if ( empty( $professional_id ) || empty($service_id) || empty( $appointment_start ) || empty( $client_name ) || ! is_email( $client_email ) ) {
        // Este es el mensaje de error que estabas viendo. Ahora se activará correctamente.
        wp_send_json_error( [ 'message' => 'Por favor, completa todos los campos requeridos.' ] );
    }
    
    // <<-- FIN DE LA CORRECCIÓN -->>
    
    // El resto de la función llama al Manager, que ya está preparado para recibir estos datos.
    $manager = Veterinalia_Appointment_Manager::get_instance();
    $result = $manager->book_appointment( $booking_data );

    if ( $result['success'] ) {
        wp_send_json_success( $result['data'] );
    } else {
        wp_send_json_error( $result['data'] );
    }
}

    /**
     * Manejador AJAX para actualizar el estado de una cita.
     */
    // =======================================================
    // === MANEJADORES PARA GESTIÓN DE CITAS
    // =======================================================

    public function handle_update_appointment_status() {
        check_ajax_referer( 'va_appointment_nonce', 'nonce' );

        $appointment_id = isset( $_POST['appointment_id'] ) ? intval( $_POST['appointment_id'] ) : 0;
        $new_status     = isset( $_POST['new_status'] ) ? sanitize_text_field( $_POST['new_status'] ) : '';
        $professional_id = get_current_user_id(); // El ID del profesional logueado

        if ( empty( $appointment_id ) || empty( $new_status ) || empty( $professional_id ) ) {
            wp_send_json_error( 'Datos de solicitud incompletos para actualizar el estado de la cita.' );
        }

        // Validar que el nuevo estado sea uno permitido
        $allowed_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if ( ! in_array( $new_status, $allowed_statuses ) ) {
            wp_send_json_error( 'Estado de cita no permitido.' );
        }

        // Opcional: Verificar que el profesional que intenta actualizar la cita es el dueño del listado.
        // Esto requeriría una consulta a la base de datos para obtener el professional_id de la cita.
        // Por simplicidad, por ahora nos basamos en que el usuario logueado tenga permiso para editar posts.
        if ( ! current_user_can( 'edit_posts' ) ) {
             wp_send_json_error( 'No tienes permiso para realizar esta acción.' );
        }

        $manager = Veterinalia_Appointment_Manager::get_instance();
        // Pasamos 0 como professional_id adicional si no lo estamos verificando en update_appointment_status del manager
        if ( $manager->update_appointment_status( $appointment_id, $new_status, $professional_id ) ) {
            wp_send_json_success( 'Estado de cita actualizado con éxito.' );
        } else {
            wp_send_json_error( 'Error al actualizar el estado de la cita.' );
        }
    }

    /**
     * Manejador AJAX para obtener el horario de disponibilidad de un profesional.
     * Utilizado para refrescar el formulario de horario del profesional después de guardar.
     */
    public function handle_get_professional_schedule() {
        check_ajax_referer( 'va_appointment_nonce', 'nonce' );

        $professional_id = isset( $_POST['professional_id'] ) ? intval( $_POST['professional_id'] ) : 0;

        if ( empty( $professional_id ) ) {
            wp_send_json_error( 'ID de profesional no proporcionado.' );
        }

        // Opcional: Verificar si el usuario actual tiene permisos para ver este horario
        // si la página no es pública. Por ahora, asumimos que es para el profesional logueado.
        if ( ! current_user_can( 'edit_post', $professional_id ) && $professional_id !== get_current_user_id() ) {
            wp_send_json_error( 'No tienes permiso para ver este horario.' );
        }

        $manager = Veterinalia_Appointment_Manager::get_instance();
        $schedule = $manager->get_professional_schedule( $professional_id );

        wp_send_json_success( $schedule );
    }

/**
     * Renderiza el HTML de la tabla de citas para un profesional.
     * CORREGIDO para usar la nueva estructura de la base de datos.
     * * @param array $appointments Array de objetos de citas
     * @return string HTML de la tabla de citas
     */
    public function render_appointments_table_html( $appointments ) {
        if ( empty( $appointments ) ) {
            return '<p>No tienes citas agendadas aún para este listado.</p>';
        }

        ob_start();
        ?>
        <table class="va-appointments-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Email Cliente</th>
                    <th>Estado</th>
                    <th>Notas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $appointments as $appointment ) : ?>
                    <?php 
                        // Extraemos la fecha y la hora del nuevo campo appointment_start
                        $appointment_timestamp = strtotime($appointment->appointment_start);
                        $appointment_date_display = date_i18n( VA_Config::get( 'date_format' ), $appointment_timestamp );
                        $appointment_time_display = date_i18n( VA_Config::get( 'time_format' ), $appointment_timestamp );
                    ?>
                    <tr data-appointment-id="<?php echo esc_attr( $appointment->id ); ?>">
                        <td><?php echo esc_html( $appointment_date_display ); ?></td>
                        <td><?php echo esc_html( $appointment_time_display ); ?></td>
                        <td><?php echo esc_html( $appointment->client_name_actual ?? $appointment->client_name ); ?></td>
                        <td><?php echo esc_html( $appointment->client_email_actual ?? $appointment->client_email ); ?></td>
                        <td class="va-appointment-status-<?php echo esc_attr( $appointment->status ); ?>">
                            <?php echo esc_html( ucfirst( $appointment->status ) ); ?>
                        </td>
                        <td><?php echo esc_html( $appointment->notes ); ?></td>
                        <td>
                            <?php if ( $appointment->status === 'pending' ) : ?>
                                <button class="va-confirm-appointment-btn" data-status="confirmed">Confirmar</button>
                                <button class="va-cancel-appointment-btn" data-status="cancelled">Cancelar</button>
                            <?php elseif ( $appointment->status === 'confirmed' ) : ?>
                                <button class="va-complete-appointment-btn" data-status="completed">Completar</button>
                                <button class="va-cancel-appointment-btn" data-status="cancelled">Cancelar</button>
                            <?php else : ?>
                                <span class="va-status-display"><?php echo esc_html( ucfirst( $appointment->status ) ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Manejador AJAX para obtener las citas de un profesional (listado) específico.
     * Utilizado para cargar citas dinámicamente al cambiar el listado en el dashboard.
     */
    public function handle_get_professional_appointments() {
        check_ajax_referer( 'va_appointment_nonce', 'nonce' );

        $professional_id = isset( $_POST['professional_id'] ) ? intval( $_POST['professional_id'] ) : 0;

        if ( empty( $professional_id ) ) {
            wp_send_json_error( 'ID de listado de profesional no proporcionado.' );
        }

        // Opcional: Verificar que el usuario actual es el propietario de este listado
        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            wp_send_json_error( 'Debes iniciar sesión para ver las citas.' );
        }
        $post_author_id = get_post_field( 'post_author', $professional_id );
        if ( $post_author_id != $current_user_id ) {
            wp_send_json_error( 'No tienes permiso para ver las citas de este listado.' );
        }

        $manager = Veterinalia_Appointment_Manager::get_instance();
        $appointments = $manager->get_professional_appointments( $professional_id );

        // Usar el método centralizado para renderizar el HTML de las citas
        $html = $this->render_appointments_table_html( $appointments );
        wp_send_json_success( $html );
    }

    // =======================================================
    // === MÉTODOS DELEGADOS PARA COMPATIBILIDAD ===
    // =======================================================

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_save_category().
     */
    public function handle_save_category() {
        return $this->get_services_handler()->handle_save_category();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_save_service().
     */
    public function handle_save_service() {
        return $this->get_services_handler()->handle_save_service();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_inherit_categories_services().
     */
    public function handle_inherit_categories_services() {
        return $this->get_services_handler()->handle_inherit_categories_services();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_get_listing_categories_services().
     */
    public function handle_get_listing_categories_services() {
        return $this->get_services_handler()->handle_get_listing_categories_services();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_edit_category().
     */
    public function handle_edit_category() {
        return $this->get_services_handler()->handle_edit_category();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_delete_category().
     */
    public function handle_delete_category() {
        return $this->get_services_handler()->handle_delete_category();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_edit_service().
     */
    public function handle_edit_service() {
        return $this->get_services_handler()->handle_edit_service();
    }

    /**
     * Delegado al manejador de servicios para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Services_Handler::handle_delete_service().
     */
    public function handle_delete_service() {
        return $this->get_services_handler()->handle_delete_service();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_save_template().
     */
    public function handle_save_template() {
        return $this->get_templates_handler()->handle_save_template();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_template_details().
     */
    public function handle_get_template_details() {
        return $this->get_templates_handler()->handle_get_template_details();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_service_templates().
     */
    public function handle_get_service_templates() {
        return $this->get_templates_handler()->handle_get_service_templates();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_import_template().
     */
    public function handle_import_template() {
        return $this->get_templates_handler()->handle_import_template();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_add_category_to_template().
     */
    public function handle_add_category_to_template() {
        return $this->get_templates_handler()->handle_add_category_to_template();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_add_service_to_template().
     */
    public function handle_add_service_to_template() {
        return $this->get_templates_handler()->handle_add_service_to_template();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_schedule_templates().
     */
    public function handle_get_schedule_templates() {
        return $this->get_templates_handler()->handle_get_schedule_templates();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_available_slots().
     */
    public function handle_get_available_slots() {
        return $this->get_templates_handler()->handle_get_available_slots();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_client_booking().
     */
    public function handle_client_booking() {
        return $this->get_templates_handler()->handle_client_booking();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_agenda_data().
     */
    public function handle_get_agenda_data() {
        return $this->get_templates_handler()->handle_get_agenda_data();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_add_appointment().
     */
    public function handle_add_appointment() {
        return $this->get_templates_handler()->handle_add_appointment();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_change_appointment_status().
     */
    public function handle_change_appointment_status() {
        return $this->get_templates_handler()->handle_change_appointment_status();
    }

    /**
     * Delegado al manejador de plantillas para mantener compatibilidad.
     *
     * @deprecated 1.0.7 Usa Veterinalia_Appointment_AJAX_Templates_Handler::handle_get_agenda_availability().
     */
    public function handle_get_agenda_availability() {
        return $this->get_templates_handler()->handle_get_agenda_availability();
    }
}
