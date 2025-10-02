<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar los shortcodes de Veterinalia Appointment.
 * Registra los shortcodes y sus funciones de callback para el frontend.
 */
class Veterinalia_Appointment_Shortcodes {

    private static $instance = null;
    private $crm_service;

    /**
     * Obtiene la Ãºnica instancia de la clase.
     *
     * @return Veterinalia_Appointment_Shortcodes
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor.
    }

    private function crm(): VA_CRM_Service {
        if ( ! $this->crm_service instanceof VA_CRM_Service ) {
            $this->crm_service = VA_CRM_Service::get_instance();
        }

        return $this->crm_service;
    }

    /**
     * Inicializa los shortcodes y los manejadores AJAX.
     */
    public function init() {
        add_shortcode( 'directorist_professional_schedule_form', [ $this, 'render_professional_schedule_form' ] );
        add_shortcode( 'directorist_client_booking_form', [ $this, 'render_client_booking_form' ] );
        add_shortcode( 'directorist_professional_appointments', [ $this, 'render_professional_appointments_dashboard' ] );
        add_shortcode( 'vetapp_professional_services', [ $this, 'render_professional_services_dashboard' ] );
        add_shortcode( 'vetapp_professional_dashboard', [ $this, 'render_professional_dashboard' ] ); // <-- AÃ‘ADIDO
        // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 2.3 (Shortcode) -->
        add_shortcode( 'vetapp_client_dashboard', [ $this, 'render_client_dashboard' ] );
        // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 2.3 (Shortcode) -->
    }

    /**
     * Callback para el shortcode [directorist_professional_schedule_form].
     */
    public function render_professional_schedule_form( $atts ) {
        // Wrapper al nuevo dashboard maestro
        return do_shortcode('[vetapp_professional_dashboard]');
    }

    /**
     * Renderiza el nuevo formulario de reserva del cliente (wizard por pasos).
     */
    public function render_client_booking_form($atts) {
        // --- Encolar assets directamente aquÃ­ para garantizar la carga ---
        wp_enqueue_style('va-dashboard-base-styles', VA_PLUGIN_URL . 'assets/css/dashboard-base.css', [], VA_PLUGIN_VERSION);
        wp_enqueue_style('va-dashboard-components-styles', VA_PLUGIN_URL . 'assets/css/dashboard-components.css', ['va-dashboard-base-styles'], VA_PLUGIN_VERSION);
        wp_enqueue_style('va-client-booking-styles', VA_PLUGIN_URL . 'assets/css/client-booking.css', ['va-dashboard-components-styles'], VA_PLUGIN_VERSION);

        // Asegurar que va-api-client estÃ© cargado
        wp_enqueue_script('va-api-client', VA_PLUGIN_URL . 'assets/js/api-client.js', ['jquery'], VA_PLUGIN_VERSION, true);

        // Inicializar el objeto AJAX para el client booking wizard
        wp_localize_script('va-api-client', 'va_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('va_appointment_nonce'),
        ));

        wp_enqueue_script('va-client-booking-wizard', VA_PLUGIN_URL . 'assets/js/modules/client-booking-wizard.js', ['jquery', 'va-api-client'], VA_PLUGIN_VERSION, true);

        // 1. Intenta obtener el ID desde los atributos del shortcode.
        $atts = shortcode_atts(['professional_id' => 0], $atts);
        $professional_id = intval($atts['professional_id']);

        // 2. Si no se pasÃ³ como atributo, intenta obtener el ID del post actual.
        if (empty($professional_id)) {
            $professional_id = get_the_ID();
        }

        // 3. Si despuÃ©s de ambos intentos no hay ID, muestra el error.
        if (empty($professional_id)) {
            return '<p>Error: No se ha podido determinar el ID del profesional.</p>';
        }

        $db_handler = Veterinalia_Appointment_Database::get_instance();
        $categories = $db_handler->get_categories_by_professional($professional_id);

        if (empty($categories)) {
            return '<p>Este profesional no tiene servicios disponibles para reservar en este momento.</p>';
        }

        foreach ($categories as $category) {
            $category->services = $db_handler->get_services_by_category($category->category_id);
        }

        // Detectar si el usuario actual es cliente con mascotas vinculadas
        $current_user_id = get_current_user_id();
        $client_pets = [];
        $is_client_with_pets = false;

        if ($current_user_id) {
            $crm = $this->crm();
            $client_repository = $crm->clients();
            $pet_repository    = $crm->pets();
            $client            = $client_repository->get_client_by_user_id($current_user_id);

            if (!$client) {
                $client = $crm->ensure_client_for_user($current_user_id);
            }

            if ($client) {
                $client_pets = $pet_repository->get_pets_by_client( (int) $client->client_id ) ?: [];
                $is_client_with_pets = !empty($client_pets);
            }
        }

        // Preparar datos del usuario para la plantilla (si estÃ¡ logueado)
        $current_user_info = null;
        if ( $current_user_id ) {
            $wp_user = get_user_by('ID', $current_user_id);
            // Preferir telÃ©fono desde el CRM si existe; si no, tomar user_meta
            $user_phone = '';
            if ( isset( $client ) && ! empty( $client->phone ) ) {
                $user_phone = $client->phone;
            } else {
                $user_phone = get_user_meta( $current_user_id, 'phone', true );
            }
            $current_user_info = [
                'wp_id' => $current_user_id,
                'name'  => $wp_user ? $wp_user->display_name : '',
                'email' => $wp_user ? $wp_user->user_email : '',
                'phone' => $user_phone ?: ''
            ];
        }

        // Hacer variables disponibles en la plantilla
        ob_start();
        include VA_PLUGIN_PATH . 'templates/client-booking.php';
        return ob_get_clean();
    }



    /**
     * Callback para el shortcode [directorist_professional_appointments].
     * Muestra la lista de citas para el profesional.
     */
    public function render_professional_appointments_dashboard( $atts ) {
        // Wrapper al nuevo dashboard maestro
        return do_shortcode('[vetapp_professional_dashboard]');
    }

    /**
     * Callback para el shortcode [vetapp_professional_services].
     * Muestra el panel de gestiÃ³n de servicios y categorÃ­as para el profesional.
     */
    public function render_professional_services_dashboard( $atts ) {
        // Wrapper al nuevo dashboard maestro
        return do_shortcode('[vetapp_professional_dashboard]');
    }

    /**
     * Callback para el shortcode maestro [vetapp_professional_dashboard].
     * Carga el nuevo panel unificado.
     */
   public function render_professional_dashboard( $atts ) {
    $current_user_id = get_current_user_id();

    if ( ! $current_user_id ) {
        return '<p>Debes iniciar sesiÃ³n para acceder a este panel.</p>';
    }

    // PRIORIDAD 1: Verificar si el usuario es un profesional usando _user_type meta
    $user_type = get_user_meta( $current_user_id, '_user_type', true );
    if ( $user_type === 'author' ) {
        // Es profesional (tiene _user_type = 'author')
        va_log('[Dashboard] Usuario ' . $current_user_id . ' tiene _user_type=author. Mostrando dashboard profesional.', 'debug');

        // Obtener listados para la plantilla
        $db_handler = Veterinalia_Appointment_Database::get_instance();
        $listing_ids = $db_handler->get_listings_by_author_id( $current_user_id );
        $professional_listings = array_values( array_filter( array_map( static function ( $listing_id ) {
            $post = get_post( $listing_id );
            if ( ! $post ) {
                return null;
            }

            return [
                'id'    => intval( $post->ID ),
                'title' => $post->post_title,
            ];
        }, $listing_ids ) ) );
        
        ob_start();
        include VA_PLUGIN_DIR . '/templates/professional-dashboard-view.php';
        return ob_get_clean();
    }

    // PRIORIDAD 2: Si es usuario general (_user_type=general), mostrar dashboard de cliente
    va_log('[Dashboard] Usuario ' . $current_user_id . ' tiene _user_type=' . $user_type . '. Mostrando dashboard de cliente.', 'debug');
    return $this->render_client_dashboard( $atts );
}

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 2.3 (Shortcode Logic) -->
    /**
     * Callback para el shortcode [vetapp_client_dashboard].
     * Muestra el panel del cliente para ver sus mascotas y citas.
     */
    public function render_client_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>Debes iniciar sesiÃ³n para ver tu panel.</p>';
        }

        // Solo cargar estilos si no estÃ¡n ya cargados (evitar duplicados cuando se llama desde vetapp_professional_dashboard)
        if (!wp_style_is('va-dashboard-base-styles', 'enqueued')) {
            wp_enqueue_style('va-dashboard-base-styles', VA_PLUGIN_URL . 'assets/css/dashboard-base.css', [], VA_PLUGIN_VERSION);
        }
        if (!wp_style_is('va-dashboard-components-styles', 'enqueued')) {
            wp_enqueue_style('va-dashboard-components-styles', VA_PLUGIN_URL . 'assets/css/dashboard-components.css', ['va-dashboard-base-styles'], VA_PLUGIN_VERSION);
        }
        if (!wp_style_is('va-unified-modals-styles', 'enqueued')) {
            wp_enqueue_style('va-unified-modals-styles', VA_PLUGIN_URL . 'assets/css/unified-modals.css', ['va-dashboard-components-styles'], VA_PLUGIN_VERSION);
        }

        // Encolar Dashboard Cliente v2 (CSS/JS + localize) para este shortcode
        if (!wp_style_is('va-client-dashboard-base', 'enqueued')) {
            wp_enqueue_style('va-client-dashboard-base', VA_PLUGIN_URL . 'assets/css/client-dashboard/base.css', [], VA_PLUGIN_VERSION);
            wp_enqueue_style('va-client-dashboard-components', VA_PLUGIN_URL . 'assets/css/client-dashboard/components.css', ['va-client-dashboard-base'], VA_PLUGIN_VERSION);
            wp_enqueue_style('va-client-dashboard-sheets', VA_PLUGIN_URL . 'assets/css/client-dashboard/sheets.css', ['va-client-dashboard-base'], VA_PLUGIN_VERSION);
        }
        if (!wp_script_is('phosphor-icons', 'enqueued')) {
            wp_enqueue_script('phosphor-icons', 'https://unpkg.com/@phosphor-icons/web', [], null, true);
        }
        if (!wp_script_is('va-client-dashboard-v2', 'enqueued')) {
            wp_enqueue_script('va-client-dashboard-v2', VA_PLUGIN_URL . 'assets/js/modules/client-dashboard-v2.js', [], VA_PLUGIN_VERSION, true);
            wp_enqueue_script('va-client-dashboard-v2-init', VA_PLUGIN_URL . 'assets/js/main-client-dashboard.js', ['va-client-dashboard-v2'], VA_PLUGIN_VERSION, true);
            // Solo obtener/crear client_id para usuarios con _user_type='general'
            $client_id_val = 0;
            $user_type = get_user_meta(get_current_user_id(), '_user_type', true);
            if ($user_type === 'general') {
                $client_id_val = $this->get_or_create_client_id_for_current_user();
            } else {
                va_log('[Appointment Shortcodes] Usuario tiene _user_type=' . $user_type . ', no se obtiene client_id para localize.', 'debug');
            }
            $user_name = is_user_logged_in() ? wp_get_current_user()->display_name : '';
            wp_localize_script('va-client-dashboard-v2', 'VA_Client_Dashboard', [
                'ajax_url'  => admin_url('admin-ajax.php'),
                'rest_url'  => rest_url('vetapp/v1/'),
                'nonce'     => wp_create_nonce('wp_rest'),
                'client_id' => $client_id_val,
                'user_name' => $user_name,
            ]);
        }

        // Solo cargar el script si no estÃ¡ ya cargado (evitar duplicados)
        // v2 enqueues activos; legacy client-dashboard.js eliminado
        // v2 enqueues below; legacy client-dashboard.js removed
        // Obtener el ID del usuario actual
        $current_user_id = get_current_user_id();
        $client_pets = [];
        $client_id = null;

        if ($current_user_id) {
            $crm = $this->crm();
            $client_repository = $crm->clients();
            $pet_repository    = $crm->pets();
            $client            = $client_repository->get_client_by_user_id($current_user_id);

            $user_type = get_user_meta($current_user_id, '_user_type', true);
            if ($user_type === 'general') {
                if (!$client) {
                    $client = $crm->ensure_client_for_user($current_user_id);
                }

                if ($client) {
                    $client_id = $client->client_id;
                    $client_pets = $pet_repository->get_pets_by_client( (int) $client_id ) ?: [];
                }
            } else {
                va_log('[Appointment Shortcodes] Usuario tiene _user_type=' . $user_type . ', no se obtienen datos de cliente para la plantilla.', 'debug');
            }
        }

        ob_start();
        // Hacemos que la variable $client_pets estÃ© disponible en la plantilla
        include VA_PLUGIN_DIR . '/templates/client-dashboard.php';
        return ob_get_clean();
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 2.3 (Shortcode Logic) -->

    /**
     * Obtiene el client_id del usuario actual o lo crea automáticamente si no existe
     * @return int El client_id o 0 si no se pudo resolver
     */
    private function get_or_create_client_id_for_current_user() {
        $client_id_val = 0;

        if ( ! is_user_logged_in() ) {
            return $client_id_val;
        }

        $user_id = get_current_user_id();
        $crm = $this->crm();
        $client_repository = $crm->clients();
        $client_obj = $client_repository->get_client_by_user_id($user_id);

        if (!empty($client_obj) && !empty($client_obj->client_id)) {
            $client_id_val = intval($client_obj->client_id);
        } else {
            $user_type = get_user_meta($user_id, '_user_type', true);
            if ($user_type === 'author') {
            va_log('[Appointment Shortcodes] Usuario ' . $user_id . ' tiene _user_type=author. NO se crea registro de cliente.', 'debug');
                return 0;
            } elseif ($user_type !== 'general') {
            va_log('[Appointment Shortcodes] Usuario ' . $user_id . ' tiene _user_type=' . $user_type . ' (no es general). NO se crea registro de cliente.', 'debug');
                return 0;
            }

            va_log('[Appointment Shortcodes] Usuario ' . $user_id . ' tiene _user_type=general y no tiene registro. Creando automáticamente...', 'info');

            $client_obj = $crm->ensure_client_for_user($user_id);

            if (!empty($client_obj) && !empty($client_obj->client_id)) {
                $client_id_val = intval($client_obj->client_id);
                va_log('[Appointment Shortcodes] Cliente creado automáticamente con ID: ' . $client_id_val . ' para user_id: ' . $user_id, 'info');
            } else {
                va_log('[Appointment Shortcodes] Error al crear cliente automático para user_id: ' . $user_id, 'error');
            }
        }

        return $client_id_val;
    }

}
