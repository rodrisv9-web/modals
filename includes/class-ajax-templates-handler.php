<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Maneja acciones AJAX para plantillas y agenda.
 */
class Veterinalia_Appointment_AJAX_Templates_Handler {
    private static $instance = null;

    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'wp_ajax_va_save_template', [ $this, 'handle_save_template' ] );
        add_action( 'wp_ajax_va_get_template_details', [ $this, 'handle_get_template_details' ] );
        add_action( 'wp_ajax_va_get_service_templates', [ $this, 'handle_get_service_templates' ] );
        add_action( 'wp_ajax_va_import_template', [ $this, 'handle_import_template' ] );
        add_action( 'wp_ajax_va_add_category_to_template', [ $this, 'handle_add_category_to_template' ] );
        add_action( 'wp_ajax_va_add_service_to_template', [ $this, 'handle_add_service_to_template' ] );
        add_action( 'wp_ajax_va_get_schedule_templates', [ $this, 'handle_get_schedule_templates' ] );
        add_action( 'wp_ajax_get_available_slots', [ $this, 'handle_get_available_slots' ] );
        add_action( 'wp_ajax_nopriv_get_available_slots', [ $this, 'handle_get_available_slots' ] );
        add_action( 'wp_ajax_create_appointment', [ $this, 'handle_client_booking' ] );
        add_action( 'wp_ajax_nopriv_create_appointment', [ $this, 'handle_client_booking' ] );
        add_action( 'wp_ajax_va_get_agenda_data', [ $this, 'handle_get_agenda_data' ] );
        add_action( 'wp_ajax_va_add_appointment', [ $this, 'handle_add_appointment' ] );
        add_action( 'wp_ajax_va_change_appointment_status', [ $this, 'handle_change_appointment_status' ] );
        add_action( 'wp_ajax_va_get_agenda_availability', [ $this, 'handle_get_agenda_availability' ] );
    }

    /**
     * Manejador AJAX para guardar una plantilla de servicios (Proyecto Quiz).
     * CORRECCIÓN DEFINITIVA: Utiliza check_ajax_referer con una acción de nonce específica y dedicada.
     */
    // =======================================================
    // === MANEJADORES PARA PLANTILLAS DE SERVICIOS (ADMIN)
    // =======================================================

    public function handle_save_template() {
        // <<-- CAMBIO CLAVE -->>
        check_ajax_referer( 'va_admin_ajax_nonce', '_wpnonce' );

        // A partir de aquí, la seguridad del nonce ya está garantizada.
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'No tienes permisos para realizar esta acción.'], 403);
            return;
        }

        $template_data = [
            'template_id'       => isset($_POST['template_id']) ? intval($_POST['template_id']) : 0,
            'template_name'     => isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '',
            'professional_type' => isset($_POST['professional_type']) ? sanitize_text_field($_POST['professional_type']) : '',
            'description'       => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
            'is_active'         => isset($_POST['is_active']) ? intval($_POST['is_active']) : 0,
        ];

        if ( empty($template_data['template_name']) || empty($template_data['professional_type']) ) {
            wp_send_json_error(['message' => 'El nombre de la plantilla y el tipo de profesional son obligatorios.'], 400);
            return;
        }

        $db_handler = Veterinalia_Templates_Database::get_instance();
        $template_id = $db_handler->save_template($template_data);

        if ( $template_id ) {
            wp_send_json_success(['template_id' => $template_id]);
        } else {
            wp_send_json_error(['message' => 'No se pudo guardar la plantilla en la base de datos.']);
        }
    }

    /**
     * Manejador AJAX para obtener los detalles de una plantilla y renderizar su HTML para edición.
     */
    public function handle_get_template_details() {
        // <<-- CAMBIO CLAVE -->>
        // Verificamos contra la nueva acción de nonce y esperamos el campo '_wpnonce'.
        if ( ! check_ajax_referer('va_admin_ajax_nonce', '_wpnonce', false) || ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
            return;
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        if ( $template_id <= 0 ) {
            wp_send_json_error(['message' => 'ID de plantilla no válido.'], 400);
            return;
        }

        $db_handler = Veterinalia_Templates_Database::get_instance();
        $template = $db_handler->get_full_template_details( $template_id );

        if ( ! $template ) {
            wp_send_json_error(['message' => 'Plantilla no encontrada.'], 404);
            return;
        }

        // Usamos output buffering para capturar el HTML de la plantilla de edición
        ob_start();
        include_once VA_PLUGIN_DIR . '/templates/admin-template-editor.php';
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Manejador AJAX para obtener las plantillas de servicios activas (Proyecto Quiz).
     */
    public function handle_get_service_templates() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) || ! is_user_logged_in() ) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
            return;
        }

        $db_handler = Veterinalia_Templates_Database::get_instance();
        $all_templates = $db_handler->get_templates();

        // Filtrar solo las plantillas activas y preparar para la respuesta JSON
        $active_templates = [];
        foreach ( $all_templates as $template ) {
            if ( $template->is_active ) {
                $active_templates[] = [
                    'id' => $template->template_id,
                    'name' => $template->template_name,
                    'type' => $template->professional_type,
                    'description' => $template->description,
                ];
            }
        }

        wp_send_json_success($active_templates);
    }

    /**
     * Manejador AJAX para importar una plantilla completa a un listado profesional (Proyecto Quiz).
     */
    public function handle_import_template() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad.'], 403);
            return;
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $current_user_id = get_current_user_id();

        // Log inicial para depuración
        error_log('VA Import Template - Request received: template_id=' . $template_id . ', professional_id=' . $professional_id . ', user_id=' . $current_user_id);

        if ( $template_id <= 0 || $professional_id <= 0 ) {
            error_log('VA Import Template - Invalid IDs');
            wp_send_json_error(['message' => 'IDs de plantilla o profesional no válidos.'], 400);
            return;
        }

        // Seguridad: Verificar que el usuario actual es el dueño del listado de destino.
        $post_author_id = get_post_field( 'post_author', $professional_id );
        if ( $post_author_id != $current_user_id ) {
            error_log('VA Import Template - Permission denied: post_author=' . $post_author_id . ', current_user=' . $current_user_id);
            wp_send_json_error(['message' => 'No tienes permiso para importar datos a este listado.'], 403);
            return;
        }

        try {
            // Usar la clase de Templates para leer la plantilla y la clase de Appointment DB para insertar
            $templates_db = Veterinalia_Templates_Database::get_instance();
            $appointment_db = Veterinalia_Appointment_Database::get_instance();
            $template = $templates_db->get_full_template_details( $template_id );

            if ( ! $template || empty($template->categories) ) {
                error_log('VA Import Template - Template not found or empty for id: ' . $template_id);
                wp_send_json_error(['message' => 'La plantilla seleccionada no se encontró o está vacía.'], 404);
                return;
            }

            // --- Proceso de Importación ---
            foreach ( $template->categories as $category_template ) {
                // 1. Crear la categoría para el profesional
                $new_category_data = [
                    'professional_id' => $professional_id,
                    'name'            => $category_template->category_name,
                ];
                $new_category_id = $appointment_db->save_category($new_category_data);

                if ( ! $new_category_id ) {
                    error_log('VA Import Template - Failed to create category: ' . $category_template->category_name);
                    // Continuar con la siguiente categoría en lugar de abortar todo el proceso
                    continue;
                }

                if ( ! empty( $category_template->services ) ) {
                    // 2. Si la categoría se creó, importar sus servicios
                    foreach ( $category_template->services as $service_template ) {
                        $new_service_data = [
                            'professional_id'  => $professional_id,
                            'category_id'      => $new_category_id,
                            'name'             => $service_template->service_name,
                            'description'      => $service_template->description,
                            'price'            => $service_template->suggested_price,
                            'duration'         => $service_template->suggested_duration,
                        ];
                        $saved = $appointment_db->save_service($new_service_data);
                        if ( ! $saved ) {
                            error_log('VA Import Template - Failed to save service: ' . $service_template->service_name . ' in category id ' . $new_category_id);
                        }
                    }
                }
            }

            // --- Devolver el HTML actualizado ---
            $final_categories = $appointment_db->get_categories_by_professional( $professional_id );
            foreach ( $final_categories as $category ) {
                $category->services = $appointment_db->get_services_by_category( $category->category_id );
            }

            ob_start();
            if ( empty($final_categories) ) {
                echo '<p class="va-no-categories-message">¡Importación completada! Añade o modifica tus nuevos servicios.</p>';
            } else {
                foreach ($final_categories as $category) {
                    // Pasamos la variable $professional_id que el template necesita
                    include VA_PLUGIN_DIR . '/templates/category-item.php';
                }
            }
            $html = ob_get_clean();

            wp_send_json_success([
                'message' => 'Plantilla importada con éxito.',
                'html'    => $html
            ]);

        } catch (Exception $e) {
            error_log('VA Import Template - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor durante la importación. Revisa los logs para más detalles.']);
        }
    }

    /**
     * Manejador AJAX para añadir una categoría a una plantilla de servicio.
     */
    public function handle_add_category_to_template() {
        // <<-- CAMBIO CLAVE -->>
        check_ajax_referer( 'va_admin_ajax_nonce', '_wpnonce' );

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'No tienes permisos para esta acción.'], 403);
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';

        if ( empty($template_id) || empty($category_name) ) {
            wp_send_json_error(['message' => 'Faltan datos para añadir la categoría.'], 400);
        }

        $db_handler = Veterinalia_Templates_Database::get_instance();
        $new_category_id = $db_handler->add_category_to_template($template_id, $category_name);

        if ( $new_category_id ) {
            // --- INICIO DE CAMBIOS ---
            // 1. Obtener el objeto completo de la nueva categoría
            $category = $db_handler->get_template_category_by_id($new_category_id);
            $category->services = []; // Inicializar como vacío

            // 2. Renderizar el HTML usando la nueva plantilla reutilizable
            ob_start();
            include VA_PLUGIN_DIR . '/templates/admin-template-category-item.php';
            $html = ob_get_clean();

            // 3. Enviar el HTML en la respuesta
            wp_send_json_success([
                'message' => 'Categoría añadida con éxito.',
                'html'    => $html
            ]);
            // --- FIN DE CAMBIOS ---
        } else {
            wp_send_json_error(['message' => 'No se pudo guardar la categoría. Es posible que ya exista.']);
        }
    }

    /**
     * Manejador AJAX para añadir un servicio a una categoría de plantilla.
     */
    public function handle_add_service_to_template() {
        check_ajax_referer( 'va_admin_ajax_nonce', '_wpnonce' );

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        $service_data = [
            'template_category_id' => isset($_POST['template_category_id']) ? intval($_POST['template_category_id']) : 0,
            'service_name'         => isset($_POST['service_name']) ? sanitize_text_field($_POST['service_name']) : '',
            'suggested_duration'   => isset($_POST['suggested_duration']) ? intval($_POST['suggested_duration']) : 30,
            'suggested_price'      => isset($_POST['suggested_price']) ? floatval($_POST['suggested_price']) : 0.00,
        ];

        if ( empty($service_data['template_category_id']) || empty($service_data['service_name']) ) {
            wp_send_json_error(['message' => 'Faltan datos para añadir el servicio.'], 400);
        }

        $db_handler = Veterinalia_Templates_Database::get_instance();
        $new_service_id = $db_handler->add_service_to_template_category($service_data);

        if ( $new_service_id ) {
            wp_send_json_success([
                'message' => 'Servicio añadido con éxito.',
                'service' => $service_data // Devolvemos los datos para mostrarlos en el frontend
            ]);
        } else {
            wp_send_json_error(['message' => 'No se pudo guardar el servicio.']);
        }
    }

    /**
     * Manejador AJAX para obtener las plantillas de horarios.
     */
    // =======================================================
    // === MANEJADORES PARA PLANTILLAS DE HORARIOS
    // =======================================================

    public function handle_get_schedule_templates() {
        check_ajax_referer('va_appointment_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Acceso denegado.'], 403);
        }

        try {
            $db_handler = Veterinalia_Templates_Database::get_instance();
            $templates = $db_handler->get_active_schedule_templates();
            $response_data = [];

            foreach ($templates as $template) {
                $response_data[] = [
                    'id'          => $template->plantilla_id,
                    'nombre'      => $template->nombre_plantilla,
                    'descripcion' => $template->descripcion,
                    'bloques'     => $db_handler->get_schedule_template_details($template->plantilla_id)
                ];
            }
            wp_send_json_success($response_data);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error al obtener las plantillas.']);
        }
    }

    /**
     * Manejador AJAX para obtener los slots de tiempo disponibles para un servicio y fecha específicos.
     */
    public function handle_get_available_slots() {
        check_ajax_referer('va_appointment_nonce', 'nonce');

        error_log('[Veterinalia AJAX] handle_get_available_slots llamado con POST: ' . json_encode($_POST));

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        error_log('[Veterinalia AJAX] Parámetros procesados - professional_id: ' . $professional_id . ', service_id: ' . $service_id . ', date: ' . $date);

        if (empty($professional_id) || empty($service_id) || empty($date)) {
            error_log('[Veterinalia AJAX] ERROR: Faltan parámetros para obtener los horarios');
            wp_send_json_error(['message' => 'Faltan parámetros para obtener los horarios.']);
            return;
        }

        $db_handler = Veterinalia_Appointment_Database::get_instance();
        $service = $db_handler->get_service_by_id($service_id);

        if (!$service) {
            error_log('[Veterinalia AJAX] ERROR: Servicio no encontrado - ID: ' . $service_id);
            wp_send_json_error(['message' => 'El servicio seleccionado no es válido.']);
            return;
        }

        error_log('[Veterinalia AJAX] Servicio encontrado: ' . $service->name . ' (duración: ' . $service->duration . ' min)');

        $manager = Veterinalia_Appointment_Manager::get_instance();
        $available_slots = $manager->get_available_slots_for_date($professional_id, $date, $service->duration);

        error_log('[Veterinalia AJAX] Slots disponibles encontrados: ' . json_encode($available_slots));
        wp_send_json_success($available_slots);
    }

    /**
     * Manejador AJAX para el nuevo wizard de reserva del cliente.
     * Recibe los datos del formulario por pasos y crea la cita.
     */
    // =======================================================
    // === MANEJADORES PARA RESERVAS (BOOKING)
    // =======================================================

    public function handle_client_booking() {
        check_ajax_referer('va_appointment_nonce', 'nonce');

        error_log('[Veterinalia AJAX] handle_client_booking llamado con POST: ' . json_encode($_POST));

        // Recolectar y sanitizar todos los datos enviados por el wizard
        $booking_data = [
            'professional_id' => isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0,
            'service_id'      => isset($_POST['service_id']) ? intval($_POST['service_id']) : 0,
            'appointment_start' => isset($_POST['date']) && isset($_POST['time']) ? sanitize_text_field($_POST['date'] . ' ' . $_POST['time']) : '',
            'client_name'     => isset($_POST['client_name']) ? sanitize_text_field($_POST['client_name']) : '',
            'pet_name'        => isset($_POST['pet_name']) ? sanitize_text_field($_POST['pet_name']) : '',
            'pet_species'     => isset($_POST['pet_species']) ? sanitize_text_field($_POST['pet_species']) : '',
            'pet_breed'       => isset($_POST['pet_breed']) ? sanitize_text_field($_POST['pet_breed']) : '',
            'pet_gender'      => isset($_POST['pet_gender']) ? sanitize_text_field($_POST['pet_gender']) : 'unknown',
            'pet_id'          => isset($_POST['pet_id']) ? intval($_POST['pet_id']) : null, // Añadido pet_id
            'client_email'    => isset($_POST['client_email']) ? sanitize_email($_POST['client_email']) : '',
            'client_phone'    => isset($_POST['client_phone']) ? sanitize_text_field($_POST['client_phone']) : '',
            'notes'           => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
        ];

        error_log('[Veterinalia AJAX] Datos procesados: ' . json_encode($booking_data));

        // Validación de datos críticos
        if (empty($booking_data['professional_id']) || empty($booking_data['service_id']) || empty($booking_data['appointment_start']) || empty($booking_data['client_name']) || empty($booking_data['pet_name']) || empty($booking_data['pet_species']) || !is_email($booking_data['client_email'])) {
            wp_send_json_error(['message' => 'Por favor, completa todos los campos requeridos.']);
            return;
        }

        // Usar el Appointment Manager para procesar la reserva
        $manager = Veterinalia_Appointment_Manager::get_instance();
        $result = $manager->book_appointment($booking_data);

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }

    // =======================================================
    // === MANEJADORES PARA EL MÓDULO DE AGENDA V2
    // =======================================================

    /**
     * Manejador AJAX para obtener datos de la agenda.
     * Devuelve citas y servicios del profesional.
     * ¡NUEVA FUNCIÓN!
     */
    public function handle_get_agenda_data() {
        check_ajax_referer('va_agenda_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Acceso denegado.'], 403);
            return;
        }

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        
        if (empty($professional_id)) {
            wp_send_json_error(['message' => 'ID de profesional no válido.']);
            return;
        }

        try {
            $manager = Veterinalia_Appointment_Manager::get_instance();
            $db_handler = Veterinalia_Appointment_Database::get_instance();

            // Obtener citas
            $appointments = $manager->get_professional_appointments($professional_id);
            $appointments_data = [];
            if (!empty($appointments)) {
                foreach ($appointments as $app) {
                    $timestamp = strtotime($app->appointment_start);
                    
                    // Verificar que los campos existan antes de usarlos
                    $appointments_data[] = [
                        'id' => $app->id ?? 0,
                        'date' => wp_date('Y-m-d', $timestamp),
                        'start' => wp_date('H:i', $timestamp),
                        'end' => wp_date('H:i', strtotime($app->appointment_end)),
                        'service' => $app->service_name ?? 'Servicio no especificado',
                        'client_id' => $app->client_id ?? null, // Añadido client_id
                        'client' => $app->client_name_actual ?? $app->client_name ?? 'Cliente no especificado',
                        'pet_id' => $app->pet_id ?? null, // Añadido pet_id
                        'pet' => $app->pet_name_actual ?? $app->pet_name ?? 'Mascota no especificada',
                        'status' => $app->status ?? 'pending',
                        'phone' => $app->client_phone ?? '',
                        'email' => $app->client_email ?? '',
                        'description' => $app->notes ?? ''
                    ];
                }
            }

            // LOG TEMPORAL: Volcar appointments_data para debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VA DEBUG] appointments_data for professional ' . $professional_id . ': ' . print_r($appointments_data, true));
            }

            // Obtener servicios
            $services = $db_handler->get_services_by_professional($professional_id);
            $services_data = [];
            if (!empty($services)) {
                foreach ($services as $service) {
                    $services_data[] = [
                        'id' => $service->service_id,
                        'name' => $service->name,
                        'duration' => $service->duration ?? 60,
                        'price' => $service->price ?? 0,
                        'entry_type_id' => $service->entry_type_id ?? null
                    ];
                }
            }

            // LOG TEMPORAL: Volcar services_data para debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[VA DEBUG] services_data for professional ' . $professional_id . ': ' . print_r($services_data, true));
            }

            wp_send_json_success([
                'appointments' => $appointments_data,
                'services' => $services_data
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error al obtener datos de la agenda: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para añadir una nueva cita desde la agenda.
     */
    public function handle_add_appointment() {
        check_ajax_referer('va_agenda_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Acceso denegado.'], 403);
        }

        // Recolectar y sanitizar datos
        $appointment_data = [
            'professional_id' => isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0,
            'service_id' => isset($_POST['service_id']) ? intval($_POST['service_id']) : 0,
            'client_id' => isset($_POST['client_id']) ? intval($_POST['client_id']) : null, // Añadido client_id
            'client_name' => isset($_POST['client_name']) ? sanitize_text_field($_POST['client_name']) : '',
            'pet_name' => isset($_POST['pet_name']) ? sanitize_text_field($_POST['pet_name']) : '',
            'pet_id' => isset($_POST['pet_id']) ? intval($_POST['pet_id']) : null, // Añadido pet_id
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'date' => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '',
            'start_time' => isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '',
            'end_time' => isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '',
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''
        ];

        // Validar campos requeridos
        $required_fields = ['professional_id', 'service_id', 'client_id', 'client_name', 'pet_name', 'pet_id', 'date', 'start_time']; // Añadido client_id y pet_id
        foreach ($required_fields as $field) {
            if (empty($appointment_data[$field])) {
                wp_send_json_error(['message' => "Campo requerido faltante: {$field}"]);
                return;
            }
        }

        // Si no se especifica hora de fin, calcular basado en duración del servicio
        if (empty($appointment_data['end_time'])) {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $service = $db_handler->get_service_by_id($appointment_data['service_id']);
            
            if ($service && !empty($service->duration)) {
                $start_time = new DateTime($appointment_data['start_time']);
                $start_time->add(new DateInterval('PT' . $service->duration . 'M'));
                $appointment_data['end_time'] = $start_time->format('H:i');
            } else {
                // Por defecto, 1 hora
                $start_time = new DateTime($appointment_data['start_time']);
                $start_time->add(new DateInterval('PT60M'));
                $appointment_data['end_time'] = $start_time->format('H:i');
            }
        }

        try {
            $manager = Veterinalia_Appointment_Manager::get_instance();
            $result = $manager->create_appointment_from_agenda($appointment_data);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error al crear la cita: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para cambiar el estado de una cita.
     */
    public function handle_change_appointment_status() {
        check_ajax_referer('va_agenda_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Acceso denegado.'], 403);
        }

        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if (empty($appointment_id) || empty($new_status)) {
            wp_send_json_error(['message' => 'ID de cita y nuevo estado son requeridos.']);
            return;
        }

        // Validar estado válido
        $valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Estado de cita no válido.']);
            return;
        }

        try {
            $manager = Veterinalia_Appointment_Manager::get_instance();
            $result = $manager->update_appointment_status($appointment_id, $new_status);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Estado de cita actualizado correctamente.',
                    'appointment_id' => $appointment_id,
                    'new_status' => $new_status
                ]);
            } else {
                wp_send_json_error(['message' => 'No se pudo actualizar el estado de la cita.']);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error al actualizar estado: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para obtener disponibilidad de un profesional para un rango de fechas.
     */
    public function handle_get_agenda_availability() {
        check_ajax_referer('va_agenda_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Acceso denegado.'], 403);
        }

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        if (empty($professional_id) || empty($date_from) || empty($date_to)) {
            wp_send_json_error(['message' => 'Todos los parámetros son requeridos.']);
            return;
        }

        try {
            $manager = Veterinalia_Appointment_Manager::get_instance();
            $availability = $manager->get_professional_availability_for_range($professional_id, $date_from, $date_to);

            wp_send_json_success($availability);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error al obtener disponibilidad: ' . $e->getMessage()]);
        }
    }
}
