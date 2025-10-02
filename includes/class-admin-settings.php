<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar la pagina de ajustes de administracion del plugin Veterinalia Appointment.
 */
class Veterinalia_Appointment_Admin_Settings {

    private static $instance = null;
    private $settings_page_slug = 'veterinalia-appointment-settings';
    private $settings_group = 'va_appointment_settings_group';
    private $settings_section = 'va_appointment_main_section';

    /**
     * Obtiene la unica instancia de la clase.
     *
     * @return Veterinalia_Appointment_Admin_Settings
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
     * Inicializa los hooks para la pagina de administracion.
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Anade la pagina de menu de administracion.
     */
    public function add_admin_menu_page(): void {
        add_menu_page(
            __( 'Veterinalia Appointments', 'veterinalia-appointment' ), // Titulo de la pagina
            __( 'Citas Veterinalia', 'veterinalia-appointment' ),        // Titulo del menu
            'manage_options',                                            // Capacidad requerida
            $this->settings_page_slug,                                   // Slug del menu
            [ $this, 'render_settings_page' ],                           // Funcion de callback
            'dashicons-calendar-alt',                                    // Icono
            6                                                            // Posicion
        );

        // Opcional: anadir una sub-pagina si hay muchas opciones
        add_submenu_page(
            $this->settings_page_slug,
            __( 'Ajustes Generales', 'veterinalia-appointment' ),
            __( 'Ajustes', 'veterinalia-appointment' ),
            'manage_options',
            $this->settings_page_slug,
            [ $this, 'render_settings_page' ]
        );

        // Anadir sub-pagina para la gestion de plantillas del Proyecto Quiz
        add_submenu_page(
            $this->settings_page_slug,
            __( 'Plantillas de Servicios', 'veterinalia-appointment' ),
            __( 'Plantillas de Servicios', 'veterinalia-appointment' ),
            'manage_options',
            'va-service-templates',
            [ $this, 'render_templates_page' ]
        );
    }

    /**
     * Registra los ajustes del plugin.
     */
    public function register_settings(): void {
        register_setting(
            $this->settings_group, // Nombre del grupo de ajustes
            'va_appointment_guest_booking_enabled', // Nombre de la opcion a guardar
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
                'show_in_rest'      => true,
            ]
        );

        add_settings_section(
            $this->settings_section,          // ID de la seccion
            __( 'Opciones Generales de Citas', 'veterinalia-appointment' ), // Titulo de la seccion
            [ $this, 'render_section_info' ], // Funcion de callback para la descripcion de la seccion
            $this->settings_page_slug         // Pagina a la que pertenece
        );

        add_settings_field(
            'va_appointment_guest_booking_enabled', // ID del campo
            __( 'Permitir reservas de invitados', 'veterinalia-appointment' ), // Titulo del campo
            [ $this, 'render_guest_booking_field' ], // Funcion de callback para renderizar el campo
            $this->settings_page_slug,               // Pagina a la que pertenece
            $this->settings_section                  // Seccion a la que pertenece
        );
    }

    /**
     * Renderiza la informacion de la seccion de ajustes.
     */
    public function render_section_info(): void {
        echo '<p>' . __( 'Configura las opciones generales para el sistema de agendamiento de citas.', 'veterinalia-appointment' ) . '</p>';
    }

    /**
     * Renderiza el campo para permitir reservas de invitados.
     */
    public function render_guest_booking_field(): void {
        $guest_booking_enabled = VA_Config::get( 'va_appointment_guest_booking_enabled', false );
        echo '<label for="va_appointment_guest_booking_enabled">';
        echo '<input type="checkbox" id="va_appointment_guest_booking_enabled" name="va_appointment_guest_booking_enabled" value="1" ' . checked( 1, $guest_booking_enabled, false ) . '>';
        echo __( 'Permitir que los usuarios no registrados puedan reservar citas.', 'veterinalia-appointment' );
        echo '</label>';
    }

    /**
     * Renderiza la pagina de ajustes completa.
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->settings_group );
                do_settings_sections( $this->settings_page_slug );
                submit_button( __( 'Guardar cambios', 'veterinalia-appointment' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renderiza la pagina de gestion de plantillas de servicios (Proyecto Quiz).
     */
    public function render_templates_page(): void {
        // Cargar datos de plantillas desde el nuevo manejador de base de datos de plantillas.
        $db_handler = Veterinalia_Templates_Database::get_instance();
        $templates = $db_handler->get_templates();
        include_once VA_PLUGIN_DIR . '/templates/admin-templates-page.php';
    }
}

