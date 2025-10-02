<?php
/**
 * Gestión de API REST para Veterinalia Appointment
 * 
 * @package VeterinaliaAppointment
 * @subpackage Managers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar las rutas y endpoints de la API REST
 */
class VA_API_Manager {
    
    /**
     * Instancia única de la clase
     * 
     * @var VA_API_Manager|null
     */
    private static $instance = null;
    
    /**
     * Namespace de la API
     * 
     * @var string
     */
    private $namespace = 'vetapp/v1';
    
    /**
     * Manejador de base de datos
     * 
     * @var Veterinalia_Appointment_Database
     */
    private $db_handler;
    
    /**
     * Gestor de reservas
     * 
     * @var VA_Booking_Manager
     */
    private $booking_manager;
    
    /**
     * Obtiene la instancia única de la clase
     * 
     * @return VA_API_Manager
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
        $this->init_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Inicializa las dependencias necesarias
     */
    private function init_dependencies() {
        $this->db_handler = Veterinalia_Appointment_Database::get_instance();
        $this->booking_manager = VA_Booking_Manager::get_instance();
    }
    
    /**
     * Inicializa los hooks de WordPress
     */
    private function init_hooks() {
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }
    
    /**
     * Registra las rutas de la API REST
     */
    public function register_api_routes() {
        error_log('[VA_API_Manager] Registrando rutas de la API de Veterinalia...');
        
        // Ruta genérica para cargar los módulos del dashboard
        register_rest_route($this->namespace, '/dashboard/(?P<module_name>\w+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_dashboard_module_content'],
            'permission_callback' => function () {
                return true;
            },
            'args' => [
                'module_name' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param);
                    }
                ],
                'employee_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // Ruta para actualizar el estado de una cita específica
        register_rest_route($this->namespace, '/appointments/(?P<id>\d+)/status', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_appointment_status_api'],
            'permission_callback' => function () {
                return true;
            },
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'description' => 'El ID único de la cita.'
                ],
                'status' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return in_array($param, ['confirmed', 'completed', 'cancelled']);
                    },
                    'description' => 'El nuevo estado para la cita.'
                ]
            ]
        ]);
        
        // Ruta para obtener los servicios de una categoría específica
        register_rest_route($this->namespace, '/categories/(?P<category_id>\d+)/services', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_services_for_category_api'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => [
                'category_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'description' => 'El ID único de la categoría.'
                ]
            ]
        ]);
        
        // Registrar rutas adicionales según sea necesario
        $this->register_additional_routes();
    }
    
    /**
     * Registra rutas adicionales de la API
     */
    private function register_additional_routes() {
        // Aquí se pueden agregar más rutas en el futuro
        
        // Ejemplo: Ruta para obtener citas de un profesional
        register_rest_route($this->namespace, '/professionals/(?P<professional_id>\d+)/appointments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_professional_appointments_api'],
            'permission_callback' => [$this, 'check_professional_permission'],
            'args' => [
                'professional_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                    'description' => 'El ID del profesional.'
                ],
                'status' => [
                    'required' => false,
                    'validate_callback' => function($param) {
                        return in_array($param, ['pending', 'confirmed', 'completed', 'cancelled']);
                    },
                    'description' => 'Filtrar por estado de la cita.'
                ]
            ]
        ]);
        
        // Ruta para crear una nueva cita
        register_rest_route($this->namespace, '/appointments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_appointment_api'],
            'permission_callback' => function () {
                return true; // Permitir a usuarios no logueados crear citas
            }
        ]);
    }
    
    /**
     * Callback para obtener el contenido HTML de un módulo del dashboard
     *
     * @param WP_REST_Request $request La petición de la API
     * @return WP_REST_Response|WP_Error La respuesta de la API
     */
    public function get_dashboard_module_content($request) {
        $module_name = $request->get_param('module_name');
        $employee_id = $request->get_param('employee_id');
        
        error_log('[VA_API_Manager] Solicitando módulo: ' . $module_name . ' para empleado: ' . $employee_id);
        
        // Mapeo de nombres de módulos a archivos de plantilla
        $module_mapping = [
            'appointments' => 'modules/agenda-module.php',
            'patients' => 'modules/patients-module.php',
            'services' => 'modules/services-module.php',
            'schedule' => 'modules/schedule-module.php',
            'combos' => 'modules/combos-module.php',
            'catalog' => 'modules/catalog-module.php',
        ];
        
        // Determinar la ruta del archivo de plantilla
        if (isset($module_mapping[$module_name])) {
            $template_path = VA_PLUGIN_DIR . '/templates/' . $module_mapping[$module_name];
        } else {
            $template_path = VA_PLUGIN_DIR . "/templates/modules/{$module_name}-module.php";
        }
        
        // Verificar si el archivo existe
        if (!file_exists($template_path)) {
            error_log('[VA_API_Manager] Archivo de plantilla no encontrado: ' . $template_path);
            return new WP_Error(
                'module_not_found',
                "El módulo '{$module_name}' no se encontró.",
                ['status' => 404]
            );
        }
        
        // Capturar el contenido HTML del archivo de plantilla
        ob_start();
        // Pasar el ID del empleado para que la plantilla pueda usarlo
        $professional_id = $employee_id; // Alias para compatibilidad
        include $template_path;
        $html_content = ob_get_clean();
        
        // Devolver respuesta exitosa con el HTML del módulo
        return new WP_REST_Response([
            'success' => true,
            'html' => $html_content
        ], 200);
    }
    
    /**
     * Callback para actualizar el estado de una cita
     *
     * @param WP_REST_Request $request La petición de la API
     * @return WP_REST_Response|WP_Error La respuesta de la API
     */
    public function update_appointment_status_api($request) {
        $appointment_id = (int) $request['id'];
        $params = $request->get_json_params();
        $new_status = isset($params['status']) ? sanitize_text_field($params['status']) : null;
        
        error_log("[VA_API_Manager] Actualizando estado de cita ID: {$appointment_id} a: {$new_status}");
        
        // Verificación de datos necesarios
        if (empty($new_status)) {
            return new WP_Error(
                'bad_request',
                'El nuevo estado es requerido.',
                ['status' => 400]
            );
        }
        
        // Actualizar el estado usando el booking manager
        $updated = $this->booking_manager->update_appointment_status($appointment_id, $new_status);
        
        if ($updated) {
            error_log("[VA_API_Manager] Éxito al actualizar la cita ID: {$appointment_id}");
            
            return new WP_REST_Response([
                'success' => true,
                'message' => "Estado de la cita actualizado exitosamente a '{$new_status}'",
                'appointment_id' => $appointment_id,
                'new_status' => $new_status,
            ], 200);
        } else {
            error_log("[VA_API_Manager] Falló la actualización para la cita ID: {$appointment_id}");
            
            return new WP_Error(
                'update_failed',
                'No se pudo actualizar el estado de la cita.',
                ['status' => 500]
            );
        }
    }
    
    /**
     * Callback para obtener los servicios de una categoría
     *
     * @param WP_REST_Request $request La petición de la API
     * @return WP_REST_Response|WP_Error La respuesta de la API
     */
    public function get_services_for_category_api($request) {
        $category_id = (int) $request['category_id'];
        
        if (empty($category_id)) {
            return new WP_Error(
                'bad_request',
                'ID de categoría no válido.',
                ['status' => 400]
            );
        }
        
        error_log('[VA_API_Manager] Obteniendo servicios para categoría ID: ' . $category_id);
        
        // Obtener servicios de la base de datos
        $services = $this->db_handler->get_services_by_category($category_id);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $services
        ], 200);
    }
    
    /**
     * Callback para obtener las citas de un profesional
     *
     * @param WP_REST_Request $request La petición de la API
     * @return WP_REST_Response|WP_Error La respuesta de la API
     */
    public function get_professional_appointments_api($request) {
        $professional_id = (int) $request['professional_id'];
        $status = $request->get_param('status');
        
        error_log('[VA_API_Manager] Obteniendo citas para profesional ID: ' . $professional_id);
        
        $args = [];
        if (!empty($status)) {
            $args['status'] = $status;
        }
        
        // Obtener citas usando el booking manager
        $appointments = $this->booking_manager->get_professional_appointments($professional_id, $args);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $appointments,
            'count' => count($appointments)
        ], 200);
    }
    
    /**
     * Callback para crear una nueva cita
     *
     * @param WP_REST_Request $request La petición de la API
     * @return WP_REST_Response|WP_Error La respuesta de la API
     */
    public function create_appointment_api($request) {
        $params = $request->get_json_params();
        
        error_log('[VA_API_Manager] Creando nueva cita con datos: ' . json_encode($params));
        
        // Validar datos requeridos
        $required_fields = ['professional_id', 'service_id', 'client_name', 'appointment_start'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_Error(
                    'missing_field',
                    "Campo requerido faltante: {$field}",
                    ['status' => 400]
                );
            }
        }
        
        // Crear la cita usando el booking manager
        $result = $this->booking_manager->book_appointment($params);
        
        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'appointment_id' => $result['appointment_id'] ?? null
            ], 201);
        } else {
            return new WP_Error(
                'booking_failed',
                $result['data']['message'] ?? 'Error al crear la cita',
                ['status' => 400]
            );
        }
    }
    
    /**
     * Verifica permisos para acceder a datos de un profesional
     *
     * @param WP_REST_Request $request La petición de la API
     * @return bool True si tiene permisos
     */
    public function check_professional_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        $professional_id = (int) $request['professional_id'];
        
        // Verificar si el usuario actual es el profesional solicitado
        // o si es un administrador
        if ($current_user_id === $professional_id || current_user_can('manage_options')) {
            return true;
        }
        
        // Verificar si el usuario tiene el tipo correcto
        $user_type = get_user_meta($current_user_id, '_user_type', true);
        if ($user_type === 'author' && $current_user_id === $professional_id) {
            return true;
        }
        
        return false;
    }
}
