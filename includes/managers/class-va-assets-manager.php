<?php
/**
 * Gestión de Assets (CSS y JavaScript) para Veterinalia Appointment
 * 
 * @package VeterinaliaAppointment
 * @subpackage Managers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar todos los assets del plugin
 */
class VA_Assets_Manager {
    
    /**
     * Instancia única de la clase
     * 
     * @var VA_Assets_Manager|null
     */
    private static $instance = null;
    
    /**
     * Obtiene la instancia única de la clase
     * 
     * @return VA_Assets_Manager
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado para el patrón Singleton
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializa los hooks de WordPress
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }
    
    /**
     * Encola los scripts y estilos necesarios para el frontend
     */
    public function enqueue_frontend_assets() {
        // Assets Globales
        $this->enqueue_global_assets();
        
        // Assets condicionales para el dashboard
        $this->enqueue_dashboard_assets_conditionally();
    }
    
    /**
     * Encola los assets globales que se usan en múltiples páginas
     */
    private function enqueue_global_assets() {
        // Estilos para el calendario de reserva
        wp_enqueue_style(
            'veterinalia-calendar-style',
            VA_PLUGIN_URL . 'assets/css/veterinalia-calendar.css',
            [],
            VA_PLUGIN_VERSION
        );
        
        // Scripts de librerías externas
        wp_enqueue_script(
            'moment-js',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment-with-locales.min.js',
            [],
            '2.29.1',
            true
        );
        wp_add_inline_script('moment-js', 'moment.locale(\'es\');');
        
        wp_enqueue_script(
            'tiny-slider',
            'https://cdnjs.cloudflare.com/ajax/libs/tiny-slider/2.9.3/min/tiny-slider.js',
            [],
            '2.9.3',
            true
        );
        
        // Cliente de API y script principal
        wp_enqueue_script(
            'va-api-client',
            VA_PLUGIN_URL . 'assets/js/api-client.js',
            ['jquery'],
            VA_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'va-main-script',
            VA_PLUGIN_URL . 'assets/js/main.js',
            ['jquery', 'va-api-client'],
            VA_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('va-main-script', 'va_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('va_appointment_nonce'),
        ]);
    }
    
    /**
     * Encola los assets del dashboard de manera condicional
     */
    private function enqueue_dashboard_assets_conditionally() {
        global $post;
        
        // Verificar si estamos en una página con el shortcode del dashboard
        if ( ! is_a($post, 'WP_Post') ) {
            return;
        }
        
        if ( ! has_shortcode($post->post_content, 'vetapp_professional_dashboard') ) {
            return;
        }
        
        // Cargar todos los assets específicos del dashboard
        $this->enqueue_dashboard_styles();
        $this->enqueue_dashboard_scripts();
        $this->enqueue_client_dashboard_assets();
        $this->localize_dashboard_scripts();
    }
    
    /**
     * Encola los estilos del dashboard profesional
     */
    private function enqueue_dashboard_styles() {
        // Google Fonts
        wp_enqueue_style(
            'google-fonts-poppins',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
            [],
            null
        );
        
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            [],
            '6.4.0'
        );
        
        // Estilos base del dashboard
        wp_enqueue_style(
            'va-dashboard-base-styles',
            VA_PLUGIN_URL . 'assets/css/dashboard-base.css',
            [],
            VA_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'va-dashboard-components-styles',
            VA_PLUGIN_URL . 'assets/css/dashboard-components.css',
            ['va-dashboard-base-styles'],
            VA_PLUGIN_VERSION
        );
        
        // Estilos de módulos específicos
        $modules_styles = [
            'agenda-module' => 'modules/agenda-module.css',
            'module-schedule' => 'module-schedule.css',
            'professional-services' => 'professional-services.css',
            'combos-module' => 'modules/combos-module.css',
            'patients-module' => 'modules/patients-module.css',
            'unified-modals' => 'unified-modals.css',
            'catalog-module' => 'modules/catalog-module.css',
        ];
        
        foreach ($modules_styles as $handle => $path) {
            wp_enqueue_style(
                'va-' . $handle . '-styles',
                VA_PLUGIN_URL . 'assets/css/' . $path,
                ['va-dashboard-components-styles'],
                VA_PLUGIN_VERSION
            );
        }
    }
    
    /**
     * Encola los scripts del dashboard profesional
     */
    private function enqueue_dashboard_scripts() {
        // Scripts de módulos
        $modules_scripts = [
            'professional-dashboard' => 'modules/professional-dashboard.js',
            'professional-services' => 'modules/professional-services.js',
            'professional-schedule' => 'modules/professional-schedule.js',
            'agenda-module' => 'modules/agenda-module.js',
            'combos-module' => 'modules/combos-module.js',
            'patients-module' => 'modules/patients-module.js',
            'catalog-module' => 'modules/catalog-module.js',
            'agenda-wizard' => 'modules/agenda-wizard.js',
        ];
        
        foreach ($modules_scripts as $handle => $path) {
            $deps = ($handle === 'professional-dashboard') ? ['va-api-client'] : ['jquery'];
            if (in_array($handle, ['professional-services', 'professional-schedule', 'catalog-module'])) {
                $deps = ['va-api-client'];
            }
            if ($handle === 'agenda-wizard') {
                $deps = ['jquery', 'va-api-client'];
            }
            
            wp_enqueue_script(
                'va-' . $handle,
                VA_PLUGIN_URL . 'assets/js/' . $path,
                $deps,
                VA_PLUGIN_VERSION,
                true
            );
        }
        
        // Dashboard controller
        wp_enqueue_script(
            'va-dashboard-controller',
            VA_PLUGIN_URL . 'assets/js/dashboard-controller.js',
            ['jquery', 'va-api-client'],
            VA_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Encola los assets del dashboard del cliente
     */
    private function enqueue_client_dashboard_assets() {
        // Estilos del dashboard del cliente v2
        wp_enqueue_style(
            'va-client-dashboard-base',
            VA_PLUGIN_URL . 'assets/css/client-dashboard/base.css',
            [],
            VA_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'va-client-dashboard-components',
            VA_PLUGIN_URL . 'assets/css/client-dashboard/components.css',
            ['va-client-dashboard-base'],
            VA_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'va-client-dashboard-sheets',
            VA_PLUGIN_URL . 'assets/css/client-dashboard/sheets.css',
            ['va-client-dashboard-base'],
            VA_PLUGIN_VERSION
        );
        
        // Phosphor Icons
        wp_enqueue_script(
            'phosphor-icons',
            'https://unpkg.com/@phosphor-icons/web',
            [],
            null,
            true
        );
        
        // Scripts del dashboard del cliente v2
        wp_enqueue_script(
            'va-client-dashboard-v2',
            VA_PLUGIN_URL . 'assets/js/modules/client-dashboard-v2.js',
            [],
            VA_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'va-client-dashboard-v2-init',
            VA_PLUGIN_URL . 'assets/js/main-client-dashboard.js',
            ['va-client-dashboard-v2'],
            VA_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Localiza los scripts del dashboard con datos necesarios
     */
    private function localize_dashboard_scripts() {
        // Obtener client_id para usuarios con _user_type='general'
        $client_id_val = $this->get_client_id_for_current_user();
        
        // Localización para el dashboard del cliente
        wp_localize_script('va-client-dashboard-v2', 'VA_Client_Dashboard', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'rest_url'  => rest_url('vetapp/v1/'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'client_id' => $client_id_val,
            'user_name' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
        ]);
        
        // Localización para el dashboard controller
        wp_localize_script('va-dashboard-controller', 'VA_REST', [
            'api_url'   => rest_url('vetapp/v1/'),
            'api_nonce' => wp_create_nonce('wp_rest')
        ]);
    }
    
    /**
     * Obtiene el client_id para el usuario actual
     * 
     * @return int
     */
    private function get_client_id_for_current_user() {
        if ( ! is_user_logged_in() ) {
            return 0;
        }
        
        $user_type = get_user_meta(get_current_user_id(), '_user_type', true);
        
        if ($user_type !== 'general') {
            error_log('[VA_Assets_Manager] Usuario tiene _user_type=' . $user_type . ', no se obtiene client_id');
            return 0;
        }
        
        // Aquí deberíamos obtener el client_id real desde el CRM
        // Por ahora retornamos el user_id como fallback
        $appointment_manager = Veterinalia_Appointment_Manager::get_instance();
        if (method_exists($appointment_manager, 'get_or_create_client_id_for_current_user')) {
            return $appointment_manager->get_or_create_client_id_for_current_user();
        }
        
        return get_current_user_id();
    }
    
    /**
     * Encola los scripts y estilos para el área de administración
     * 
     * @param string $hook El hook de la página actual de admin
     */
    public function enqueue_admin_assets( $hook ) {
        // Solo cargar en la página de plantillas
        if ( 'citas-veterinalia_page_va-service-templates' !== $hook ) {
            return;
        }
        
        // CSS del modal
        wp_enqueue_style(
            'veterinalia-professional-services-style',
            VA_PLUGIN_URL . 'assets/css/professional-services.css',
            [],
            VA_PLUGIN_VERSION
        );
        
        // JavaScript para el admin
        wp_enqueue_script(
            'va-admin-templates-script',
            VA_PLUGIN_URL . 'assets/js/admin-templates.js',
            ['jquery'],
            VA_PLUGIN_VERSION,
            true
        );
        
        // Localización del script
        wp_localize_script(
            'va-admin-templates-script',
            'va_ajax_object',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('va_admin_ajax_nonce'),
            ]
        );
    }
}
