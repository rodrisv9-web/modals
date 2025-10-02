<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

// Cargar las clases de los managers
require_once VA_PLUGIN_DIR . '/includes/managers/class-va-assets-manager.php';
require_once VA_PLUGIN_DIR . '/includes/managers/class-va-schedule-manager.php';
require_once VA_PLUGIN_DIR . '/includes/managers/class-va-booking-manager.php';
require_once VA_PLUGIN_DIR . '/includes/managers/class-va-api-manager.php';
require_once VA_PLUGIN_DIR . '/includes/managers/class-va-agenda-manager.php';

/**
 * Clase principal para la gestiÃƒÂ³n de citas de Veterinalia Appointment.
 * ActÃƒÂºa como coordinador principal delegando responsabilidades a managers especializados.
 */
class Veterinalia_Appointment_Manager {

    private static $instance = null;
    private $db_handler; // Para acceder a la base de datos
    private $mailer; // Para enviar correos electrÃƒÂ³nicos
    private $crm_service;
    
    // Managers especializados
    private $assets_manager;
    private $schedule_manager;
    private $booking_manager;
    private $api_manager;
    private $agenda_manager;


    /**
     * Obtiene la ÃƒÂºnica instancia de la clase.
     *
     * @return Veterinalia_Appointment_Manager
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Inicializar servicios bÃƒÂ¡sicos
        $this->db_handler   = Veterinalia_Appointment_Database::get_instance();
        $this->crm_service  = VA_CRM_Service::get_instance();
        $this->mailer       = new Veterinalia_Appointment_Mailer();
        
        // Inicializar managers especializados
        $this->init_managers();
        
        // Registrar hooks principales
        add_action( 'plugins_loaded', [ $this, 'init_shortcodes' ] );
        add_action( 'plugins_loaded', [ $this, 'init_admin_settings' ] );
    }
    
    /**
     * Inicializa los managers especializados
     */
    private function init_managers() {
        $this->assets_manager = VA_Assets_Manager::get_instance();
        $this->schedule_manager = VA_Schedule_Manager::get_instance();
        $this->booking_manager = VA_Booking_Manager::get_instance();
        $this->api_manager = VA_API_Manager::get_instance();
        $this->agenda_manager = VA_Agenda_Manager::get_instance();
    }

    /**
     * Obtiene el servicio CRM
     * @return VA_CRM_Service
     */
    private function crm(): VA_CRM_Service {
        if ( ! $this->crm_service instanceof VA_CRM_Service ) {
            $this->crm_service = VA_CRM_Service::get_instance();
        }
        return $this->crm_service;
    }

    /**
     * Inicializa el manager.
     */
    public function init() {
        // Ejecutar migraciÃƒÂ³n de base de datos
        $this->schedule_manager->run_database_migration();
    }

    /**
     * Inicializa la clase de Shortcodes.
     */
    public function init_shortcodes() {
        // Asegurarse de que la clase de shortcodes estÃƒÂ© cargada
        if ( class_exists( 'Veterinalia_Appointment_Shortcodes' ) ) {
            Veterinalia_Appointment_Shortcodes::get_instance()->init();
        }
    }

    /**
     * Inicializa la clase de Ajustes de AdministraciÃƒÂ³n.
     */
    public function init_admin_settings() {
        if ( class_exists( 'Veterinalia_Appointment_Admin_Settings' ) ) {
            Veterinalia_Appointment_Admin_Settings::get_instance()->init();
        }
    }

    /**
     * Encola los scripts y estilos necesarios para el plugin.
     * DEPRECADO: Este método se mantiene por compatibilidad.
     * Los assets ahora son manejados por VA_Assets_Manager.
     */
    public function enqueue_assets() {
        // Delegado a VA_Assets_Manager
        // Este método se mantiene vacío por compatibilidad

        return;
    }

    /**
     * Encola los scripts y estilos necesarios para el área de administración.
     * DEPRECADO: Este método se mantiene por compatibilidad.
     * Los assets ahora son manejados por VA_Assets_Manager.
     */
    public function enqueue_admin_assets( $hook ) {
        // Delegado a VA_Assets_Manager
        return;
    }

    /**
     * Guarda el horario de disponibilidad para un profesional.
     *
     * @param int   $professional_id ID del profesional.
     * @param array $schedule_data   Array de datos del horario: [ 'day_of_week', 'start_time', 'end_time', 'slot_duration' ]
     * @return bool True si se guarda correctamente, false en caso contrario.
     */
    public function save_professional_schedule( $professional_id, $schedule_data ) {
        // Delegar a VA_Schedule_Manager
        return $this->schedule_manager->save_professional_schedule( $professional_id, $schedule_data );
    }

    /**
     * Obtiene el horario de disponibilidad para un profesional.
     *
     * @param int $professional_id ID del profesional.
     * @return array Array de objetos de los horarios, o un array vacío si no se encuentran.
     */
    public function get_professional_schedule( $professional_id ) {
        // Delegar a VA_Schedule_Manager
        return $this->schedule_manager->get_professional_schedule( $professional_id );
    }

    /**
     * Calcula los slots de tiempo disponibles usando la lógica delegada.
     *
     * @param int    $professional_id   ID del profesional.
     * @param string $selected_date     Fecha seleccionada en formato YYYY-MM-DD.
     * @param int    $service_duration  Duración del servicio en minutos.
     * @return array Lista de slots de tiempo disponibles en formato 'H:i'.
     */
    public function get_available_slots_for_date( $professional_id, $selected_date, $service_duration ) {
        // Delegar a VA_Schedule_Manager
        return $this->schedule_manager->get_available_slots_for_date( $professional_id, $selected_date, $service_duration );
    }

    /**
     * Calcula los slots de tiempo disponibles para un rango de fechas.
     *
     * @param int    $professional_id  ID del profesional.
     * @param string $date_start        Fecha de inicio en formato YYYY-MM-DD.
     * @param string $date_end          Fecha de fin en formato YYYY-MM-DD.
     * @param int    $service_duration  Duración del servicio en minutos.
     * @return array Disponibilidad agrupada por fecha.
     */
    public function get_available_slots_for_range( $professional_id, $date_start, $date_end, $service_duration ) {
        return $this->schedule_manager->get_available_slots_for_range( $professional_id, $date_start, $date_end, $service_duration );
    }

    /**
     * Procesa la reserva de una cita usando la nueva estructura de la base de datos.
     *
     * @param array $booking_data Datos de la reserva.
     * @return array Resultado de la operación.
     */
    public function book_appointment( $booking_data ) {
        // Delegar a VA_Booking_Manager
        return $this->booking_manager->book_appointment( $booking_data );
    }

    /**
     * Obtiene todas las citas para un profesional específico.
     *
     * @param int   $professional_id ID del profesional.
     * @param array $args            Argumentos de la consulta (ej. 'status', 'orderby', 'order').
     * @return array Array de objetos de las citas.
     */
    public function get_professional_appointments( $professional_id, $args = [] ) {
        // Delegar a VA_Booking_Manager
        return $this->booking_manager->get_professional_appointments( $professional_id, $args );
    }

    /**
     * Actualiza el estado de una cita.
     *
     * @param int    $appointment_id  ID de la cita a actualizar.
     * @param string $new_status      El nuevo estado (e.g., 'confirmed', 'cancelled', 'completed').
     * @param int    $professional_id Opcional. ID del profesional para verificar la propiedad de la cita.
     * @return bool True si la actualización fue exitosa, false en caso contrario.
     */
    public function update_appointment_status( $appointment_id, $new_status, $professional_id = 0 ) {
        // Delegar a VA_Booking_Manager
        return $this->booking_manager->update_appointment_status( $appointment_id, $new_status, $professional_id );
    }

    /**
     * Registra las rutas de la API REST para el dashboard del profesional.
     */
    public function register_api_routes() {
        // Delegar a VA_API_Manager
        return $this->api_manager->register_api_routes();
    }

    /**
     * Función de callback para manejar las peticiones a la API del dashboard.
     * Obtiene el contenido HTML de un módulo específico.
     *
     * @param WP_REST_Request $request La petición de la API.
     * @return WP_REST_Response La respuesta de la API.
     */
    public function get_dashboard_module_content( $request ) {
        // Delegar a VA_API_Manager
        return $this->api_manager->get_dashboard_module_content( $request );
    }

    /**
     * Callback de la API para actualizar el estado de una cita.
     *
     * @param WP_REST_Request $request La petición de la API.
     * @return WP_REST_Response|WP_Error La respuesta de la API.
     */
    public function update_appointment_status_api( $request ) {
        // Delegar a VA_API_Manager
        return $this->api_manager->update_appointment_status_api( $request );
    }

    /**
     * Ejecuta la migración de la base de datos para la estructura de horarios.
     * Se ejecuta una sola vez.
     */
    public function run_database_migration() {
        // Delegar a VA_Schedule_Manager
        return $this->schedule_manager->run_database_migration();
    }

    /**
     * Callback de la API para obtener los servicios de una categoría.
     *
     * @param WP_REST_Request $request La petición de la API.
     * @return WP_REST_Response|WP_Error La respuesta de la API.
     */
    public function get_services_for_category_api( $request ) {
        // Delegar a VA_API_Manager
        return $this->api_manager->get_services_for_category_api( $request );
    }

    /**
     * Obtiene la disponibilidad de un profesional para un rango de fechas.
     *
     * @param int    $professional_id ID del profesional.
     * @param string $date_from       Fecha de inicio (Y-m-d).
     * @param string $date_to         Fecha de fin (Y-m-d).
     * @return array Array con disponibilidad por día.
     */
    public function get_professional_availability_for_range( $professional_id, $date_from, $date_to ) {
        // Delegar a VA_Schedule_Manager
        return $this->schedule_manager->get_professional_availability_for_range( $professional_id, $date_from, $date_to );
    }

    /**
     * Crea una nueva cita desde el módulo de agenda.
     *
     * @param array $appointment_data Datos de la cita.
     * @return array Resultado de la operación.
     */
    public function create_appointment_from_agenda( $appointment_data ) {
        // Delegar a VA_Agenda_Manager
        return $this->agenda_manager->create_appointment_from_agenda( $appointment_data );
    }

    /**
     * Orquesta el proceso de completar una cita y registrar su bitácora.
     *
     * @param array $log_data Datos del formulario de la bitácora.
     * @return array Resultado de la operación.
     */
    public function complete_appointment_with_log( $log_data ) {
        // Delegar a VA_Agenda_Manager
        return $this->agenda_manager->complete_appointment_with_log( $log_data );
    }

    /**
     * Envía los emails correspondientes después de una reserva exitosa.
     * DEPRECADO: Este método se mantiene por compatibilidad.
     * Los emails ahora son manejados por VA_Booking_Manager.
     */
    private function send_booking_emails($appointment_id, $is_new_client, $client_id, $pet_id, $booking_data) {
        // Método deprecado - mantenido por compatibilidad
        return;
    }

    /**
     * Obtiene el client_id del usuario actual o lo crea automáticamente si no existe
     * @return int El client_id o 0 si no se pudo resolver
     */
    public function get_or_create_client_id_for_current_user() {
        $client_id_val = 0;

        if ( ! is_user_logged_in() ) {
            return $client_id_val;
        }

        $user_id = get_current_user_id();
        $crm = $this->crm();
        $client_repository = $crm->clients();
        $client_obj = $client_repository->get_client_by_user_id( $user_id );

        if ( ! empty( $client_obj ) && ! empty( $client_obj->client_id ) ) {
            return intval( $client_obj->client_id );
        }

        $user_type = get_user_meta( $user_id, '_user_type', true );
        if ( $user_type === 'author' ) {
            error_log('[Appointment Manager] Usuario ' . $user_id . ' tiene _user_type=author. NO se crea registro de cliente.');
            return 0;
        } elseif ( $user_type !== 'general' ) {
            error_log('[Appointment Manager] Usuario ' . $user_id . ' tiene _user_type=' . $user_type . ' (no es general). NO se crea registro de cliente.');
            return 0;
        }

        error_log('[Appointment Manager] Usuario ' . $user_id . ' tiene _user_type=general y no tiene registro. Creando automáticamente...');

        $client_obj = $crm->ensure_client_for_user( $user_id );

        if ( ! empty( $client_obj ) && ! empty( $client_obj->client_id ) ) {
            $client_id_val = intval( $client_obj->client_id );
            error_log('[Appointment Manager] Cliente creado automáticamente con ID: ' . $client_id_val . ' para user_id: ' . $user_id);
        } else {
            error_log('[Appointment Manager] Error al crear cliente automático para user_id: ' . $user_id);
        }

        return $client_id_val;
    }
}
    


// Inicializar la clase
Veterinalia_Appointment_Manager::get_instance()->init();
