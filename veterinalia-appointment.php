<?php
/**
 * Plugin Name: Veterinalia Appointment
 * Plugin URI:  https://example.com/veterinalia-appointment
 * Description: Un plugin de agendamiento de citas para profesionales de Veterinalia que se integra con Directorist.
 * Version:     Ajules 1.0.0.E
 * Author:      Tu Nombre/Tu Equipo
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: veterinalia-appointment
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

// Define VA_PLUGIN_DIR y VA_PLUGIN_VERSION
if ( ! defined( 'VA_PLUGIN_DIR' ) ) {
    define( 'VA_PLUGIN_DIR', __DIR__ );
}
if ( ! defined( 'VA_PLUGIN_PATH' ) ) {
    define( 'VA_PLUGIN_PATH', __DIR__ . '/' );
}
if ( ! defined( 'VA_PLUGIN_URL' ) ) {
    define( 'VA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'VA_PLUGIN_VERSION' ) ) {
    define( 'VA_PLUGIN_VERSION', '1.0.0' ); // Versión del plugin para cache busting
}

// Utilidades comunes.
require_once VA_PLUGIN_DIR . '/includes/helpers/logging.php';

// Cargar las clases necesarias en el orden correcto
// Cache y configuración (deben cargarse primero)
require_once VA_PLUGIN_DIR . '/includes/class-cache-helper.php';
require_once VA_PLUGIN_DIR . '/includes/class-config-cache.php';
require_once VA_PLUGIN_DIR . '/includes/class-cache-admin.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/interfaces/interface-repository-factory.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-availability-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-availability-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-appointment-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-appointment-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-client-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-client-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-log-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-log-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-form-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-form-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-migration-service.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/tables/class-crm-table-installer.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/tables/class-crm-migration-runner.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/class-repository-factory.php';

// Clases de base de datos y lógica principal
require_once VA_PLUGIN_DIR . '/includes/class-appointment-database.php';
require_once VA_PLUGIN_DIR . '/includes/class-crm-service.php';
require_once VA_PLUGIN_DIR . '/includes/class-crm-database.php';
require_once VA_PLUGIN_DIR . '/includes/class-templates-database.php';
require_once VA_PLUGIN_DIR . '/includes/class-appointment-mailer.php'; // Movido antes del Manager
require_once VA_PLUGIN_DIR . '/includes/class-appointment-manager.php';
require_once VA_PLUGIN_DIR . '/includes/class-appointment-shortcodes.php';
require_once VA_PLUGIN_DIR . '/includes/class-admin-settings.php';
require_once VA_PLUGIN_DIR . '/includes/class-ajax-handler.php';
require_once VA_PLUGIN_DIR . '/includes/class-rest-api-handler.php'; // Incluir el nuevo manejador de la API REST

/**
 * Función que se ejecuta al activar el plugin.
 * Crea las tablas de la base de datos necesarias.
 */
function va_activate_plugin() {
    // Definir que estamos en proceso de activación para silenciar logs
    if ( ! defined( 'VA_PLUGIN_ACTIVATING' ) ) {
        define( 'VA_PLUGIN_ACTIVATING', true );
    }
    
    // Inicializar y crear tablas de agendamiento
    $db_handler = Veterinalia_Appointment_Database::get_instance();
    $db_handler->create_tables();

    // Inicializar y crear tablas del CRM
    $crm_service = VA_CRM_Service::get_instance();
    $crm_service->create_tables();

    // Inicializar y crear tablas de Plantillas
    $templates_db_handler = Veterinalia_Templates_Database::get_instance();
    $templates_db_handler->create_tables();
}
register_activation_hook( __FILE__, 'va_activate_plugin' );

// Desactivación del plugin (opcional, para limpieza de tablas si es necesario)
/*
function va_deactivate_plugin() {
    $db_handler = new Veterinalia_Appointment_Database();
    $db_handler->drop_tables(); // Solo si quieres eliminar las tablas al desactivar
}
register_deactivation_hook( __FILE__, 'va_deactivate_plugin' );
*/

/**
 * Función que se ejecuta al desactivar el plugin.
 * Limpia el cache y realiza tareas de limpieza.
 */
function va_deactivate_plugin_cache() {
    // Limpiar todo el cache del plugin
    if (class_exists('VA_Cache_Helper')) {
        VA_Cache_Helper::flush_all();
        // Log silenciado durante activación para evitar salida inesperada
        if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
            error_log('[VA Plugin] Cache flushed on deactivation');
        }
    }
    
    // Limpiar cache de configuración
    if (class_exists('VA_Config')) {
        VA_Config::flush_cache();
        // Log silenciado durante activación para evitar salida inesperada
        if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
            error_log('[VA Plugin] Configuration cache flushed on deactivation');
        }
    }
}
register_deactivation_hook( __FILE__, 'va_deactivate_plugin_cache' );

// Inicializar el plugin
function va_init_plugin() {
    // Inicializar el manejador de shortcodes
    $shortcodes = Veterinalia_Appointment_Shortcodes::get_instance();
    $shortcodes->init();
    
    // Inicializar el manejador principal (que carga assets y otros hooks)
    $manager = Veterinalia_Appointment_Manager::get_instance();

    // Inicializar el manejador de AJAX
    $ajax_handler = Veterinalia_Appointment_AJAX_Handler::get_instance();
    $ajax_handler->init();

    // Inicializar el manejador de la API REST
    $rest_handler = Veterinalia_Appointment_REST_Handler::get_instance();
    $rest_handler->init();
    
    // Inicializar administración de cache (solo en admin)
    if (is_admin()) {
        VA_Cache_Admin::init();
    }
}
add_action( 'init', 'va_init_plugin' ); 