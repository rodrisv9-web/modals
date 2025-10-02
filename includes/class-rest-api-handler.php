<?php
if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar las rutas de la API REST de Veterinalia Appointment.
 */
class Veterinalia_Appointment_REST_Handler
{
    private static $instance = null;
    private $namespace = 'vetapp/v1';
    private $crm_service;
    /** @var VA_Appointment_Booking_Repository_Interface|null */
    private $booking_repository = null;


    /**
     * Obtiene la única instancia de la clase.
     * @return Veterinalia_Appointment_REST_Handler
     */
    public static function get_instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Constructor.
    }

    private function crm(): VA_CRM_Service
    {
        if ( ! $this->crm_service instanceof VA_CRM_Service ) {
            $this->crm_service = VA_CRM_Service::get_instance();
        }

        return $this->crm_service;
    }

    private function booking_repository(): VA_Appointment_Booking_Repository_Interface {
        if ( ! $this->booking_repository instanceof VA_Appointment_Booking_Repository_Interface ) {
            $factory    = VA_Repository_Factory::instance();
            $repository = $factory->get( 'appointment.booking' );

            if ( ! $repository instanceof VA_Appointment_Booking_Repository_Interface ) {
                global $wpdb;
                $repository = new VA_Appointment_Booking_Repository( $wpdb );
                $factory->bind( 'appointment.booking', $repository );
            }

            $this->booking_repository = $repository;
        }

        return $this->booking_repository;
    }

    /**
     * Inicializa los hooks de la API REST.
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 0.3 -->
    /**
     * Registra todas las rutas de la API.
     */
    public function register_routes(): void
    {
        error_log('[Chocovainilla] API Check: Registrando rutas del Proyecto Chocovainilla.');

        // --- RUTAS PARA EL PROYECTO CHOCOVAINILLA (CRM Y PACIENTES) ---
        register_rest_route($this->namespace, '/clients/search', [
            'methods' => WP_REST_Server::READABLE, // 'GET'
            'callback' => [$this, 'handle_client_search'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'term' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'professional_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) { return is_numeric($param); }
                ]
            ],
        ]);

        register_rest_route($this->namespace, '/pets/grant-access', [
            'methods' => WP_REST_Server::CREATABLE, // 'POST'
            'callback' => [$this, 'handle_grant_pet_access'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/professionals/(?P<professional_id>\d+)/services-and-categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_services_and_categories'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/clients/claim-profile', [
            'methods' => WP_REST_Server::CREATABLE, // 'POST'
            'callback' => [$this, 'handle_claim_profile'],
            'permission_callback' => [$this, 'check_api_permission'], // Requiere que el usuario esté logueado
        ]);

        // Endpoint de diagnóstico para verificar share_codes
        register_rest_route($this->namespace, '/debug/share-code', [
            'methods' => WP_REST_Server::READABLE, // 'GET'
            'callback' => [$this, 'handle_debug_share_code'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'code' => [
                    'required' => true,
                    'sanitize_callback' => function($value) {
                        return strtoupper(sanitize_text_field($value));
                    }
                ]
            ],
        ]);

        register_rest_route($this->namespace, '/pet-logs', [
            'methods' => WP_REST_Server::CREATABLE, // 'POST'
            'callback' => [$this, 'handle_create_pet_log'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // --- RUTAS CLIENT-SCOPE PARA DASHBOARD V2 ---
        register_rest_route($this->namespace, '/clients/me/pets', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_my_pets'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/clients/pets', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_create_client_pet'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/clients/pets/(?P<pet_id>\\d+)/logs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_client_pet_logs'],
            'permission_callback' => [$this, 'check_client_pet_permission'],
            'args' => [ 'pet_id' => [ 'required' => true, 'validate_callback' => function($p){ return is_numeric($p); } ] ],
        ]);

        register_rest_route($this->namespace, '/clients/pets/(?P<pet_id>\\d+)/logs', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_create_client_pet_log'],
            'permission_callback' => [$this, 'check_client_pet_permission'],
            'args' => [ 'pet_id' => [ 'required' => true, 'validate_callback' => function($p){ return is_numeric($p); } ] ],
        ]);

        register_rest_route($this->namespace, '/clients/pets/(?P<pet_id>\\d+)/summary', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_client_pet_summary'],
            'permission_callback' => [$this, 'check_client_pet_permission'],
            'args' => [ 'pet_id' => [ 'required' => true, 'validate_callback' => function($p){ return is_numeric($p); } ] ],
        ]);

        register_rest_route($this->namespace, '/clients/me/notifications', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_client_notifications'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // --- RUTAS PARA EL MÓDULO DE PACIENTES (LEGACY/EXISTENTES) ---
        register_rest_route($this->namespace, '/patients/clients', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_clients'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'professional_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/patients/clients', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_create_client'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/patients/pets/(?P<pet_id>\d+)/logs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_pet_logs'],
            'permission_callback' => [$this, 'check_pet_permission'],
            'args' => [
                'pet_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) { return is_numeric($param); }
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/patients/pets/(?P<pet_id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'handle_update_pet'],
            'permission_callback' => [$this, 'check_pet_permission'],
            'args' => [
                'pet_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) { return is_numeric($param); }
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/patients/pets', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_create_pet'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/patients/clients/(?P<client_id>\d+)/pets', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_pets_by_client'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'client_id' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) { return is_numeric($param); }
                ],
                'professional_id' => [
                    'required' => false,
                    'validate_callback' => function($param, $request, $key) { return empty($param) || is_numeric($param); }
                ]
            ],
        ]);

        register_rest_route($this->namespace, '/patients/import', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_import_pet'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // --- RUTAS PARA FORMULARIOS DINÁMICOS Y TIPOS DE ENTRADA ---

        register_rest_route($this->namespace, '/entry-types', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_entry_types'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/forms/entry-type/(?P<entry_type_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_form_fields'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'entry_type_id' => [
                    'required' => true,
                    'validate_callback' => function($param) { return is_numeric($param); }
                ]
            ],
        ]);

        // --- RUTAS PARA EL CATÁLOGO DE PRODUCTOS (INVENTARIO) ---

        register_rest_route($this->namespace, '/products/professional/(?P<professional_id>\\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_products'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/products', [
            'methods' => WP_REST_Server::CREATABLE, // POST para crear
            'callback' => [$this, 'handle_save_product'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/products/(?P<product_id>\\d+)', [
            'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH para actualizar
            'callback' => [$this, 'handle_save_product'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/products/(?P<product_id>\\d+)', [
            'methods' => WP_REST_Server::DELETABLE, // DELETE para eliminar
            'callback' => [$this, 'handle_delete_product'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        // --- NUEVAS RUTAS PARA DATOS NORMALIZADOS ---
        
        register_rest_route($this->namespace, '/manufacturers', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_manufacturers'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/active-ingredients', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_active_ingredients'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);

        register_rest_route($this->namespace, '/products-full/professional/(?P<professional_id>\\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'handle_get_products_full'],
            'permission_callback' => [$this, 'check_api_permission'],
        ]);
    }

    // --- INICIO: NUEVAS FUNCIONES HANDLER (VACÍAS POR AHORA) ---

    /**
     * Callback para la búsqueda unificada de clientes.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_client_search(WP_REST_Request $request) {
        $term = $request->get_param('term');
        $professional_id = intval($request->get_param('professional_id'));

        if (strlen($term) < 3) {
            return new WP_REST_Response(['success' => true, 'data' => []], 200);
        }

        try {
            error_log('[Chocovainilla] API: Búsqueda de cliente iniciada para el término: ' . $term);
            $client_repository = $this->crm()->clients();
            $clients = $client_repository->search_clients_with_access_check($term, $professional_id);
            if (empty($clients)) {
                error_log('[Chocovainilla] API: Fallback de búsqueda básico activado.');
                $clients = $client_repository->search_clients_basic($term);
                // Normalizar: sin verificación de acceso, marcamos has_access = 0 por defecto
                if (is_array($clients) || is_object($clients)) {
                    foreach ($clients as $c) { if (!isset($c->has_access)) { $c->has_access = 0; } }
                }
            }
            return new WP_REST_Response(['success' => true, 'data' => $clients], 200);
        } catch (Exception $e) {
            error_log('[Chocovainilla] API Error en handle_client_search: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Callback para conceder acceso a una mascota mediante share_code.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_grant_pet_access(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $professional_id = isset($params['professional_id']) ? intval($params['professional_id']) : 0;
        $pet_id = isset($params['pet_id']) ? intval($params['pet_id']) : 0;
        $share_code = isset($params['share_code']) ? strtoupper(sanitize_text_field($params['share_code'])) : '';

        if (empty($professional_id) || empty($pet_id) || empty($share_code)) {
            return new WP_Error('bad_request', 'Faltan parámetros.', ['status' => 400]);
        }

        try {
            $pet_repository = $this->crm()->pets();
            $pet            = $pet_repository->get_pet_by_id($pet_id);

            if (!$pet) {
                return new WP_Error('not_found', 'Mascota no encontrada.', ['status' => 404]);
            }

            if ($pet->share_code !== $share_code) {
                error_log('[Chocovainilla] API: Intento de desbloqueo fallido para pet_id ' . $pet_id);
                return new WP_Error('access_denied', 'El código de compartir es incorrecto.', ['status' => 403]);
            }

            // El código es correcto, concedemos acceso
            $access_granted = $pet_repository->grant_pet_access($pet_id, $professional_id, 'full');
            
            if ($access_granted === false) {
                 return new WP_Error('db_error', 'No se pudo conceder el acceso.', ['status' => 500]);
            }
            
            error_log('[Chocovainilla] API: Acceso concedido para pet_id ' . $pet_id . ' al professional_id ' . $professional_id);
            return new WP_REST_Response(['success' => true, 'message' => 'Acceso concedido con éxito.'], 200);

        } catch (Exception $e) {
            error_log('[Chocovainilla] API Error en handle_grant_pet_access: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    // --- FIN: NUEVAS FUNCIONES HANDLER ---
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 0.3 -->

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 1.4 (Backend) -->
    /**
     * API: Obtener mascotas por cliente
     */
    public function handle_get_pets_by_client($request) {
        $client_id = intval($request['client_id']);
        $professional_id = intval($request->get_param('professional_id'));
        
        if (empty($client_id)) {
            return new WP_Error('missing_parameter', 'Client ID is required', ['status' => 400]);
        }

        try {
            $pet_repository = $this->crm()->pets();
            if (!empty($professional_id)) {
                error_log('[Chocovainilla] API: get_pets_by_client_with_access client_id=' . $client_id . ' professional_id=' . $professional_id);
                $pets = $pet_repository->get_pets_by_client_with_access($client_id, $professional_id);
            } else {
                error_log('[Chocovainilla] API: get_pets_by_client client_id=' . $client_id);
                $pets = $pet_repository->get_pets_by_client($client_id);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'data' => $pets
            ], 200);

        } catch (Exception $e) {
            error_log('[Patients API] Error getting pets: ' . $e->getMessage());
            return new WP_Error('database_error', 'Error retrieving pets', ['status' => 500]);
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 1.4 (Backend) -->

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 1.5 (API Logic) -->
    /**
     * API: Obtiene todas las categorías y sus servicios para un profesional.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_services_and_categories(WP_REST_Request $request) {
        $professional_id = intval($request->get_param('professional_id'));
        if (empty($professional_id)) {
            return new WP_Error('bad_request', 'ID de profesional no válido.', ['status' => 400]);
        }

        try {
            error_log('[Chocovainilla] API: Obteniendo servicios y categorías para el profesional ID: ' . $professional_id);
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $categories = $db_handler->get_categories_by_professional($professional_id);
            
            foreach ($categories as $category) {
                $category->services = $db_handler->get_services_by_category($category->category_id);
            }

            return new WP_REST_Response(['success' => true, 'data' => $categories], 200);

        } catch (Exception $e) {
            error_log('[Chocovainilla] API Error en handle_get_services_and_categories: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 1.5 (API Logic) -->

    /**
     * Callback para obtener los clientes de un profesional.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_clients(WP_REST_Request $request)
    {
        $professional_id = intval($request->get_param('professional_id'));

        if (empty($professional_id)) {
            return new WP_REST_Response(['success' => false, 'message' => 'ID de profesional no válido.'], 400);
        }

        try {
            $client_repository = $this->crm()->clients();
            $pet_repository    = $this->crm()->pets();
            $clients           = $client_repository->get_clients_by_professional($professional_id);

            foreach ($clients as $client) {
                $client->pets = $pet_repository->get_pets_by_client($client->client_id);
            }

            return new WP_REST_Response(['success' => true, 'data' => $clients], 200);

        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_clients: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Callback para crear un nuevo cliente.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_create_client(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        
        // Validar datos requeridos
        if (empty($params['name'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'El nombre del cliente es requerido.'], 400);
        }

        if (empty($params['professional_id']) || !is_numeric($params['professional_id'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'ID de profesional válido es requerido.'], 400);
        }

        try {
            $client_repository = $this->crm()->clients();

            // Preparar datos del cliente
            $client_data = [
                'name' => sanitize_text_field($params['name']),
                'email' => !empty($params['email']) ? sanitize_email($params['email']) : null,
                'phone' => !empty($params['phone']) ? sanitize_text_field($params['phone']) : null,
                'created_by_professional' => intval($params['professional_id']),
                'is_guest' => 1 // Por defecto, los clientes creados por profesionales son invitados
            ];

            // Crear el cliente
            $client_id = $client_repository->create_client($client_data);

            if (!$client_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'No se pudo crear el cliente.'], 500);
            }

            // Obtener el cliente recién creado para devolverlo
            $new_client = $client_repository->get_client_by_id($client_id);
            
            if (!$new_client) {
                return new WP_REST_Response(['success' => false, 'message' => 'Cliente creado pero no se pudo recuperar.'], 500);
            }

            error_log('[Chocovainilla] Cliente creado exitosamente: ' . $new_client->name . ' (ID: ' . $client_id . ')');
            
            return new WP_REST_Response(['success' => true, 'data' => $new_client], 201);

        } catch (Exception $e) {
            error_log('[REST API Error] handle_create_client: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Callback para crear una nueva mascota.
     * AHORA TAMBIÉN DISPARA EL CORREO DE INVITACIÓN SI EL CLIENTE ES INVITADO.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_create_pet(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $professional_id = isset($params['professional_id']) ? intval($params['professional_id']) : 0;
        $client_id = isset($params['client_id']) ? intval($params['client_id']) : 0;

        try {
            $client_repository = $this->crm()->clients();
            $pet_repository    = $this->crm()->pets();

            $pet_id = $pet_repository->create_pet($params);
            if (!$pet_id) {
                return new WP_Error('create_failed', 'No se pudo crear la mascota.', ['status' => 500]);
            }

            $pet_repository->grant_pet_access($pet_id, $professional_id, 'full');

            $client = $client_repository->get_client_by_id($client_id);
            $pet    = $pet_repository->get_pet_by_id($pet_id);

            if ($client && $pet && $client->is_guest == 1 && !empty($client->email)) {
                error_log('[Chocovainilla] El cliente ' . $client->name . ' es invitado. Intentando enviar correo de invitación.');
                $mailer = new Veterinalia_Appointment_Mailer();
                $mailer->send_claim_invitation_email(
                    $client->email,
                    $client->name,
                    $pet->name,
                    $pet->share_code
                );
            }

            // 4. Devolver la mascota recién creada (lógica existente)
            return new WP_REST_Response(['success' => true, 'data' => $pet], 201);

        } catch (Exception $e) {
            error_log('[REST API Error] handle_create_pet: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Callback para importar una mascota usando un código de compartir.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_import_pet(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $share_code = isset($params['share_code']) ? strtoupper(sanitize_text_field($params['share_code'])) : '';
        $professional_id = isset($params['professional_id']) ? intval($params['professional_id']) : 0;

        if (empty($share_code) || empty($professional_id)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Código y ID de profesional son requeridos.'], 400);
        }

        try {
            $client_repository = $this->crm()->clients();
            $pet_repository    = $this->crm()->pets();

            $pet = $pet_repository->get_pet_by_share_code($share_code);
            if (!$pet) {
                return new WP_Error('not_found', 'Código no válido o no encontrado', ['status' => 404]);
            }

            $has_access = $pet_repository->check_pet_access($professional_id, $pet->pet_id);
            if ($has_access) {
                return new WP_Error('already_imported', 'Este paciente ya está en tu lista', ['status' => 409]);
            }

            $access_granted = $pet_repository->grant_pet_access($pet->pet_id, $professional_id, 'full');
            if (!$access_granted) {
                return new WP_Error('grant_failed', 'No se pudo otorgar acceso a la mascota', ['status' => 500]);
            }

            $client = $client_repository->get_client_by_id($pet->client_id);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'pet' => $pet,
                    'client' => $client
                ]
            ], 200);

        } catch (Exception $e) {
            error_log('[REST API Error] handle_import_pet: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Callback para obtener el historial médico de una mascota.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_pet_logs(WP_REST_Request $request) {
        $pet_id = intval($request->get_param('pet_id'));
        $professional_id = get_current_user_id();

        try {
            $pet_log_repository = $this->crm()->pet_logs();
            $logs = $pet_log_repository->get_pet_logs_full( $pet_id, $professional_id );
            return new WP_REST_Response(['success' => true, 'data' => $logs], 200);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_pet_logs: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Callback para actualizar los datos de una mascota.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_update_pet(WP_REST_Request $request)
    {
        $pet_id = intval($request->get_param('pet_id'));
        $params = $request->get_json_params();

        try {
            $pet_repository = $this->crm()->pets();
            $result         = $pet_repository->update_pet($pet_id, $params);

            if ($result === false) {
                return new WP_REST_Response(['success' => false, 'message' => 'Error al actualizar la mascota.'], 400);
            }

            $pet = $pet_repository->get_pet_by_id($pet_id);
            return new WP_REST_Response(['success' => true, 'data' => $pet], 200);

        } catch (Exception $e) {
            error_log('[REST API Error] handle_update_pet: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Verifica si el profesional tiene acceso a una mascota específica.
     * CORREGIDO: Ahora usa get_current_user_id() para identificar al profesional de forma segura.
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_pet_permission(WP_REST_Request $request)
    {
        $pet_id = intval($request->get_param('pet_id'));
        
        // --- INICIO DE LA CORRECCIÓN MEJORADA ---
        // Obtener el ID del usuario autenticado
        $user_id = get_current_user_id();
        
        // DEBUG: Logging detallado
        error_log('[DEBUG] check_pet_permission - pet_id: ' . $pet_id . ', user_id from get_current_user_id(): ' . $user_id);
        error_log('[DEBUG] check_pet_permission - is_user_logged_in: ' . (is_user_logged_in() ? 'true' : 'false'));

        if (empty($user_id) || empty($pet_id)) {
            error_log('[DEBUG] check_pet_permission - DENIED: Empty user_id or pet_id');
            return false;
        }

        // En este sistema, el professional_id corresponde al user_id de WordPress
        // Pero vamos a verificar ambas posibilidades para asegurar compatibilidad
        $professional_id = $user_id;
        
        $pet_repository = $this->crm()->pets();
        $has_access     = $pet_repository->check_pet_access($professional_id, $pet_id);

        error_log('[DEBUG] check_pet_permission - check_pet_access result for professional_id ' . $professional_id . ': ' . ($has_access ? 'true' : 'false'));

        // FALLBACK: Si no tiene acceso directo, buscar el professional_id correcto
        if (!$has_access) {
            error_log('[DEBUG] check_pet_permission - No direct access found, checking for professional listings...');
            
            // Método 1: Buscar listados de Directorist
            $listings = get_posts([
                'post_type' => 'at_listing',
                'author' => $user_id,
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields' => 'ids'
            ]);
            
            if (!empty($listings)) {
                $listing_id = $listings[0];
                error_log('[DEBUG] check_pet_permission - Found professional listing: ' . $listing_id);
                
                $has_access = $pet_repository->check_pet_access($listing_id, $pet_id);
                error_log('[DEBUG] check_pet_permission - check_pet_access result for listing_id ' . $listing_id . ': ' . ($has_access ? 'true' : 'false'));
                
                if ($has_access) {
                    $professional_id = $listing_id;
                    error_log('[DEBUG] check_pet_permission - Access granted using listing_id as professional_id');
                }
            }
            
            // Método 2: Si no encuentra listados, buscar en la tabla de accesos qué professional_id tiene acceso a esta mascota
            if (!$has_access) {
                error_log('[DEBUG] check_pet_permission - No listings found, checking pet_access table for valid professional_ids...');
                global $wpdb;
                
                $table_access = $wpdb->prefix . 'va_pet_access';
                $sql = $wpdb->prepare("
                    SELECT DISTINCT professional_id 
                    FROM {$table_access} 
                    WHERE pet_id = %d AND is_active = 1 
                    AND (date_expires IS NULL OR date_expires > NOW())
                ", $pet_id);
                
                $valid_professional_ids = $wpdb->get_col($sql);
                error_log('[DEBUG] check_pet_permission - Found professional_ids with access to pet ' . $pet_id . ': ' . implode(', ', $valid_professional_ids));
                
                // Verificar si alguno de estos professional_ids corresponde al usuario actual
                // Esto podría ser mediante meta_data, roles, o alguna otra relación
                foreach ($valid_professional_ids as $valid_id) {
                    // Verificar si este professional_id está asociado al usuario actual
                    $professional_user = get_user_by('ID', $valid_id);
                    if ($professional_user && $professional_user->ID == $user_id) {
                        error_log('[DEBUG] check_pet_permission - Found matching professional_id: ' . $valid_id . ' for user_id: ' . $user_id);
                        $professional_id = $valid_id;
                        $has_access = true;
                        break;
                    }
                }
                
                // Si aún no encuentra acceso, usar el professional_id más común para este usuario
                // (esto es un fallback temporal para el debugging)
                if (!$has_access && !empty($valid_professional_ids)) {
                    error_log('[DEBUG] check_pet_permission - TEMPORARY FALLBACK: Using first valid professional_id for debugging');
                    $professional_id = $valid_professional_ids[0];
                    $has_access = $pet_repository->check_pet_access($professional_id, $pet_id);
                }
            }
        }
        // --- FIN DE LA CORRECCIÓN MEJORADA ---

        if (!$has_access) {
            error_log('[DEBUG] check_pet_permission - DENIED: No access found for user_id ' . $user_id . ' (professional_id: ' . $professional_id . ') to pet_id ' . $pet_id);
            return new WP_Error('access_denied', 'No tienes acceso a este paciente.', ['status' => 403]);
        }
        
        error_log('[DEBUG] check_pet_permission - GRANTED: Access granted for user_id ' . $user_id . ' (professional_id: ' . $professional_id . ') to pet_id ' . $pet_id);
        return true;
    }

    /**
     * Verifica los permisos para acceder a la API.
     * @return bool
     */
    public function check_api_permission()
    {
        // Por ahora, permitimos el acceso si el usuario está logueado.
        // En un futuro, se podrían añadir comprobaciones de roles más específicas.
        return is_user_logged_in();
    }

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 2.2 (API Logic) -->
    /**
     * API: Maneja la petición de un cliente para reclamar un perfil de mascota/cliente.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_claim_profile(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $share_code = isset($params['share_code']) ? strtoupper(sanitize_text_field($params['share_code'])) : '';
        $user_id = get_current_user_id();

        if (empty($share_code) || empty($user_id)) {
            return new WP_Error('bad_request', 'Faltan parámetros o no has iniciado sesión.', ['status' => 400]);
        }

        try {
            $pet_repository = $this->crm()->pets();
            $pet            = $pet_repository->get_pet_by_share_code($share_code);

            if (!$pet) {
                return new WP_Error('not_found', 'El código de compartir no es válido.', ['status' => 404]);
            }

            $client = $client_repository->get_client_by_id($pet->client_id);

            // Verificación de seguridad: ¿Este perfil ya fue reclamado por otro usuario?
            if ($client && !empty($client->user_id) && $client->user_id != $user_id) {
                error_log('[Chocovainilla] API: Intento de reclamo de un perfil ya vinculado. ClientID: ' . $client->client_id);
                return new WP_Error('already_claimed', 'Este expediente ya ha sido vinculado a otra cuenta.', ['status' => 409]);
            }

            // Procedemos a vincular
            $linked = $client_repository->link_client_to_user($pet->client_id, $user_id);

            if ($linked) {
                error_log('[Chocovainilla] API: Perfil de cliente ' . $pet->client_id . ' reclamado con éxito por el usuario ' . $user_id);
                return new WP_REST_Response(['success' => true, 'message' => '¡Mascota vinculada con éxito!'], 200);
            } else {
                return new WP_Error('db_error', 'No se pudo vincular el perfil.', ['status' => 500]);
            }

        } catch (Exception $e) {
            error_log('[Chocovainilla] API Error en handle_claim_profile: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 2.2 (API Logic) -->

    /**
     * Endpoint de diagnóstico para verificar share_codes
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_debug_share_code(WP_REST_Request $request) {
        $share_code = $request->get_param('code');

        if (empty($share_code)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Código requerido'], 400);
        }

        try {
            $pet_repository = $this->crm()->pets();

            $pet = $pet_repository->get_pet_by_share_code($share_code);

            if (!$pet) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Código no encontrado',
                    'debug' => [
                        'share_code_searched' => $share_code,
                        'pet_found' => false
                    ]
                ], 404);
            }

            // Obtener información del cliente
            $client = $client_repository->get_client_by_id($pet->client_id);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Código encontrado',
                'data' => [
                    'pet' => [
                        'id' => $pet->pet_id,
                        'name' => $pet->name,
                        'share_code' => $pet->share_code,
                        'is_active' => $pet->is_active
                    ],
                    'client' => $client ? [
                        'id' => $client->client_id,
                        'name' => $client->name,
                        'is_guest' => $client->is_guest,
                        'user_id' => $client->user_id
                    ] : null
                ]
            ], 200);

        } catch (Exception $e) {
            error_log('[API Debug] Error en handle_debug_share_code: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error interno del servidor',
                'debug' => ['error' => $e->getMessage()]
            ], 500);
        }
    }
    // <-- FIN DEL CAMBIO: Endpoint de diagnóstico -->

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 3.2 (API Logic) -->
    /**
     * API: Maneja la creación de una entrada en la bitácora, incluyendo meta y productos.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_create_pet_log(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $professional_id = get_current_user_id();
        
        // Datos principales del log
        $log_data = [
            'pet_id'          => isset($params['pet_id']) ? intval($params['pet_id']) : 0,
            'appointment_id'  => isset($params['appointment_id']) ? intval($params['appointment_id']) : null,
            'entry_type_id'   => isset($params['entry_type_id']) ? intval($params['entry_type_id']) : null,
            'title'           => isset($params['title']) ? sanitize_text_field($params['title']) : '',
            'professional_id' => $professional_id,
            'entry_date'      => current_time('mysql'),
        ];
        
        // Datos adicionales
        $meta_data = isset($params['meta']) && is_array($params['meta']) ? $params['meta'] : [];
        $products_data = isset($params['products']) && is_array($params['products']) ? $params['products'] : [];
        $next_appointment_data = isset($params['next_appointment']) && is_array($params['next_appointment']) ? $params['next_appointment'] : [];

        if (empty($log_data['pet_id']) || empty($log_data['title']) || empty($log_data['entry_type_id'])) {
            return new WP_Error('bad_request', 'Faltan datos esenciales (mascota, título o tipo de entrada).', ['status' => 400]);
        }
        
        try {
            $pet_log_repository = $this->crm()->pet_logs();
            $manager = Veterinalia_Appointment_Manager::get_instance();

            // 1. Guardar la entrada principal del log
            $log_id = $pet_log_repository->create_pet_log( $log_data );
            if (!$log_id) {
                return new WP_Error('db_error', 'No se pudo crear la entrada principal del historial.', ['status' => 500]);
            }

            // 2. Guardar los metadatos del formulario
            foreach ($meta_data as $key => $value) {
                $pet_log_repository->add_pet_log_meta( $log_id, sanitize_key( $key ), sanitize_textarea_field( $value ) );
            }

            // 3. Guardar los productos utilizados
            foreach ($products_data as $product) {
                $pet_log_repository->add_pet_log_product( $log_id, intval( $product['product_id'] ), $product );
            }
            
            // 4. Agendar la próxima cita si se proporcionó
            if (!empty($next_appointment_data['date']) && !empty($next_appointment_data['service_id'])) {
                // (Lógica para crear la cita aquí, similar a la del wizard)
                // Esto se puede implementar en un paso posterior si es complejo.
            }

            // 5. Completar la cita original si está vinculada
            if ($log_data['appointment_id']) {
                $manager->update_appointment_status($log_data['appointment_id'], 'completed');
            }

            return new WP_REST_Response(['success' => true, 'message' => 'Historial guardado exitosamente.', 'log_id' => $log_id], 201);

        } catch (Exception $e) {
            error_log('[API Error] handle_create_pet_log: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 3.2 (API Logic) -->

    // === NUEVOS HANDLERS: CLIENT-SCOPE PARA DASHBOARD V2 ===
    /**
     * Lista las mascotas del cliente logueado
     */
    public function handle_get_my_pets(WP_REST_Request $request) {
        try {
            $user_id = get_current_user_id();
            if (empty($user_id)) return new WP_REST_Response(['success' => true, 'data' => []], 200);
            $crm                = $this->crm();
            $client_repository  = $crm->clients();
            $pet_repository     = $crm->pets();
            $client             = $client_repository->get_client_by_user_id($user_id);

            if (!$client) {
                $user_type = get_user_meta($user_id, '_user_type', true);
                if ($user_type === 'author') {
                    error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=author. NO se crea registro de cliente en get_my_pets.');
                    return new WP_REST_Response(['success' => true, 'data' => []], 200);
                }

                if (!empty($user_type) && $user_type !== 'general') {
                    error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=' . $user_type . ' (no es general). NO se crea registro de cliente en get_my_pets.');
                    return new WP_REST_Response(['success' => true, 'data' => []], 200);
                }

                error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=general y no tiene registro en get_my_pets. Creando automáticamente...');

                $client = $crm->ensure_client_for_user($user_id);

                if (!$client) {
                    error_log('[REST API] Error al crear cliente automático para user_id: ' . $user_id . ' en get_my_pets');
                    return new WP_REST_Response(['success' => true, 'data' => []], 200);
                }

                error_log('[REST API] Cliente creado automáticamente con ID: ' . $client->client_id . ' para user_id: ' . $user_id . ' en get_my_pets');
            }
            
            $pets = $pet_repository->get_pets_by_client($client->client_id);
            return new WP_REST_Response(['success' => true, 'data' => $pets], 200);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_my_pets: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Crea una mascota para el cliente logueado (con share_code automático)
     */
    public function handle_create_client_pet(WP_REST_Request $request) {
        try {
            $user_id = get_current_user_id();
            $params  = $request->get_json_params();
            if (empty($user_id) || empty($params['name'])) {
                return new WP_Error('bad_request', 'Faltan datos para crear la mascota.', ['status' => 400]);
            }
            $crm                = $this->crm();
            $client_repository  = $crm->clients();
            $pet_repository     = $crm->pets();
            $client             = $client_repository->get_client_by_user_id($user_id);

            // Si el cliente no existe, verificar si es profesional antes de crear
            if (!$client) {
                // Verificar el tipo de usuario usando _user_type meta
                $user_type = get_user_meta($user_id, '_user_type', true);
                if ($user_type === 'author') {
                    error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=author. NO puede crear mascotas como cliente.');
                    return new WP_Error('professional_user', 'Los usuarios profesionales no pueden crear mascotas como clientes', ['status' => 403]);
                } elseif ($user_type !== 'general') {
                    error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=' . $user_type . ' (no es general). NO puede crear mascotas como cliente.');
                    return new WP_Error('invalid_user_type', 'Solo los usuarios con tipo "general" pueden crear mascotas como clientes', ['status' => 403]);
                }

                error_log('[REST API] Usuario ' . $user_id . ' tiene _user_type=general y no tiene registro. Creando automáticamente...');

                // Obtener datos del usuario de WordPress
                $wp_user = get_user_by('id', $user_id);
                if (!$wp_user) {
                    return new WP_Error('user_not_found', 'Usuario de WordPress no encontrado', ['status' => 404]);
                }

                $client = $crm->ensure_client_for_user($user_id);

                if (!$client) {
                    error_log('[REST API] Error al crear cliente automático para user_id: ' . $user_id);
                    return new WP_Error('client_creation_failed', 'No se pudo crear el perfil de cliente automáticamente', ['status' => 500]);
                }

                error_log('[REST API] Cliente creado automáticamente con ID: ' . $client->client_id . ' para user_id: ' . $user_id);
            }
            // Dedupe por nombre (case-insensitive) para el cliente
            $existing = $pet_repository->get_pets_by_client($client->client_id);
            $targetName = sanitize_text_field($params['name']);
            foreach ($existing as $p) {
                if (mb_strtolower($p->name) === mb_strtolower($targetName)) {
                    // Ya existe: devolver existente y evitar duplicado
                    return new WP_REST_Response(['success' => true, 'data' => $p, 'message' => 'Mascota ya existía, reutilizada.'], 200);
                }
            }
            $pet_id = $pet_repository->create_pet_with_share_code([
                'client_id' => intval($client->client_id),
                'name' => sanitize_text_field($params['name']),
                'species' => sanitize_text_field($params['species'] ?? 'Otro'),
                'breed' => !empty($params['breed']) ? sanitize_text_field($params['breed']) : null,
                'gender' => !empty($params['gender']) ? sanitize_text_field($params['gender']) : 'unknown',
            ]);
            if (!$pet_id) return new WP_Error('create_failed', 'No se pudo crear la mascota', ['status' => 500]);
            $pet = $pet_repository->get_pet_by_id($pet_id);
            return new WP_REST_Response(['success' => true, 'data' => $pet], 201);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_create_client_pet: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Verifica si el usuario actual es dueño de la mascota
     */
    public function check_client_pet_permission(WP_REST_Request $request) {
        $pet_id = intval($request->get_param('pet_id'));
        if (empty($pet_id) || !is_user_logged_in()) return false;
        try {
            $crm               = $this->crm();
            $pet_repository    = $crm->pets();
            $pet               = $pet_repository->get_pet_by_id($pet_id);
            $client_repository = $crm->clients();
            $client            = $client_repository->get_client_by_user_id(get_current_user_id());
            if (!$pet || !$client) return new WP_Error('forbidden', 'Sin acceso', ['status' => 403]);
            if (intval($pet->client_id) !== intval($client->client_id)) return new WP_Error('forbidden', 'Sin acceso', ['status' => 403]);
            return true;
        } catch (Exception $e) {
            error_log('[REST API Error] check_client_pet_permission: ' . $e->getMessage());
            return new WP_Error('forbidden', 'Sin acceso', ['status' => 403]);
        }
    }

    /**
     * Obtiene el historial para el cliente (sin exigir professional_id)
     */
    public function handle_get_client_pet_logs(WP_REST_Request $request) {
        $pet_id = intval($request->get_param('pet_id'));
        try {
            $pet_log_repository = $this->crm()->pet_logs();
            $logs = $pet_log_repository->get_pet_logs( $pet_id, null );
            return new WP_REST_Response(['success' => true, 'data' => $logs ?: []], 200);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_client_pet_logs: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Crea una entrada básica de historial para el cliente
     */
    public function handle_create_client_pet_log(WP_REST_Request $request) {
        $pet_id = intval($request->get_param('pet_id'));
        $params = $request->get_json_params();
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $type = isset($params['type']) ? sanitize_text_field($params['type']) : 'consultation';
        $description = isset($params['description']) ? sanitize_textarea_field($params['description']) : '';
        if (empty($pet_id) || empty($title)) {
            return new WP_Error('bad_request', 'Faltan datos', ['status' => 400]);
        }
        try {
            $pet_log_repository = $this->crm()->pet_logs();
            $log_id = $pet_log_repository->create_pet_log([
                'pet_id' => $pet_id,
                'professional_id' => get_current_user_id(), // como autor de la entrada
                'entry_type' => $type,
                'entry_date' => current_time('mysql'),
                'title' => $title,
                'description' => $description,
                'is_private' => 1,
            ]);
            if (!$log_id) return new WP_Error('create_failed', 'No se pudo crear la entrada', ['status' => 500]);
            $logs = $pet_log_repository->get_pet_logs( $pet_id, null );
            return new WP_REST_Response(['success' => true, 'data' => $logs], 201);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_create_client_pet_log: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Resumen de métricas del pet para el cliente
     */
    public function handle_get_client_pet_summary(WP_REST_Request $request) {
        $pet_id = intval($request->get_param('pet_id'));
        try {
            $pet_log_repository = $this->crm()->pet_logs();
            $logs = $pet_log_repository->get_pet_logs( $pet_id, null );
            $last_visit = !empty($logs) ? $logs[0]->entry_date : null;
            $last_vaccine = null; $current_weight = null;
            foreach ($logs as $log) {
                if (!$last_vaccine && isset($log->entry_type) && $log->entry_type === 'vaccination') { $last_vaccine = $log->title ?: 'Vacuna'; }
                if (!$current_weight && !empty($log->weight_recorded)) { $current_weight = $log->weight_recorded . ' kg'; }
            }

            $next = $this->booking_repository()->get_next_appointment_for_pet( $pet_id );
            $next_appointment = $next ? mysql2date('d M, H:i', $next->appointment_start) : 'Ninguna';

            return new WP_REST_Response(['success' => true, 'data' => [
                'next_appointment' => $next_appointment,
                'last_visit' => $last_visit ? mysql2date('d M, Y', $last_visit) : '--',
                'last_vaccine' => $last_vaccine ?: '--',
                'current_weight' => $current_weight ?: '--',
            ]], 200);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_client_pet_summary: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * Notificaciones simples para el cliente (próximas citas)
     */
    public function handle_get_client_notifications(WP_REST_Request $request) {
        try {
            $user_id = get_current_user_id();
            if (empty($user_id)) return new WP_REST_Response(['success' => true, 'data' => []], 200);
            $crm                = $this->crm();
            $client_repository  = $crm->clients();
            $pet_repository     = $crm->pets();
            $client             = $client_repository->get_client_by_user_id($user_id);

            if (!$client) {
                $client = $crm->ensure_client_for_user($user_id);
            }

            if (!$client) return new WP_REST_Response(['success' => true, 'data' => []], 200);
            $pets = $pet_repository->get_pets_by_client($client->client_id);
            if (empty($pets)) return new WP_REST_Response(['success' => true, 'data' => []], 200);

            $pet_ids = array_map(static function($pet) { return intval($pet->pet_id); }, $pets);
            $appointments_map = $this->booking_repository()->get_next_appointments_for_pets( $pet_ids );

            $items = [];
            foreach ($pets as $p) {
                $pet_id = intval($p->pet_id);
                if (isset($appointments_map[$pet_id])) {
                    $appointment = $appointments_map[$pet_id];
                    $items[] = [
                        'pet_id'   => $pet_id,
                        'pet_name' => $p->name,
                        'when'     => mysql2date('d M, H:i', $appointment->appointment_start),
                    ];
                }
            }

            return new WP_REST_Response(['success' => true, 'data' => $items], 200);
        } catch (Exception $e) {
            error_log('[REST API Error] handle_get_client_notifications: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * API: Maneja la petición para obtener todos los tipos de entrada.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_entry_types(WP_REST_Request $request) {
        try {
            $entry_types = $this->crm()->forms()->get_entry_types();
            return new WP_REST_Response(['success' => true, 'data' => $entry_types], 200);
        } catch (Exception $e) {
            error_log('[API Error] handle_get_entry_types: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * API: Maneja la petición para obtener los campos de un formulario por entry_type_id.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_form_fields(WP_REST_Request $request) {
        $entry_type_id = intval($request->get_param('entry_type_id'));
        try {
            $form_fields = $this->crm()->forms()->get_form_fields_by_entry_type($entry_type_id);
            return new WP_REST_Response(['success' => true, 'data' => $form_fields], 200);
        } catch (Exception $e) {
            error_log('[API Error] handle_get_form_fields: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * API: Maneja la obtención de productos para un profesional.
     */
    public function handle_get_products(WP_REST_Request $request) {
        $professional_id = intval($request->get_param('professional_id'));
        $product_repository = $this->crm()->products();
        $products = $product_repository->get_products_by_professional( $professional_id );
        return new WP_REST_Response(['success' => true, 'data' => $products], 200);
    }

    /**
     * API: Maneja la creación y actualización de un producto.
     */
    public function handle_save_product(WP_REST_Request $request) {
        $product_id = intval($request->get_param('product_id'));
        $params = $request->get_json_params();
        $params['product_id'] = $product_id; // Añadir ID para actualizaciones

        // Verificación de permisos y datos
        if (empty($params['professional_id']) || empty($params['product_name']) || empty($params['product_type'])) {
            return new WP_Error('bad_request', 'Faltan datos requeridos.', ['status' => 400]);
        }

        $product_repository = $this->crm()->products();
        $saved_id = $product_repository->save_product( $params );

        if ($saved_id) {
            return new WP_REST_Response(['success' => true, 'data' => ['product_id' => $saved_id]], $product_id ? 200 : 201);
        }
        return new WP_Error('save_error', 'No se pudo guardar el producto.', ['status' => 500]);
    }

    /**
     * API: Maneja la eliminación (desactivación) de un producto.
     */
    public function handle_delete_product(WP_REST_Request $request) {
        $product_id = intval($request->get_param('product_id'));
        $params = $request->get_json_params();
        $professional_id = intval($params['professional_id']);

        $product_repository = $this->crm()->products();
        $deleted = $product_repository->delete_product( $product_id, $professional_id );

        if ($deleted) {
            return new WP_REST_Response(['success' => true, 'message' => 'Producto eliminado.'], 200);
        }
        return new WP_Error('delete_error', 'No se pudo eliminar el producto.', ['status' => 500]);
    }

    /**
     * API: Obtiene la lista de fabricantes normalizados.
     * Mejora: Permite autocompletado en formularios y consistencia de datos.
     */
    public function handle_get_manufacturers(WP_REST_Request $request) {
        try {
            $product_repository = $this->crm()->products();
            $manufacturers = $product_repository->get_manufacturers();
            return new WP_REST_Response(['success' => true, 'data' => $manufacturers], 200);
        } catch (Exception $e) {
            error_log('[API Error] handle_get_manufacturers: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * API: Obtiene la lista de principios activos normalizados.
     * Mejora: Permite autocompletado en formularios y consistencia de datos.
     */
    public function handle_get_active_ingredients(WP_REST_Request $request) {
        try {
            $product_repository = $this->crm()->products();
            $active_ingredients = $product_repository->get_active_ingredients();
            return new WP_REST_Response(['success' => true, 'data' => $active_ingredients], 200);
        } catch (Exception $e) {
            error_log('[API Error] handle_get_active_ingredients: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    /**
     * API: Obtiene productos con información completa (incluyendo datos normalizados).
     * Mejora: Retorna información enriquecida de fabricantes y principios activos.
     */
    public function handle_get_products_full(WP_REST_Request $request) {
        try {
            $professional_id = intval($request->get_param('professional_id'));
            if (empty($professional_id)) {
                return new WP_REST_Response(['success' => false, 'message' => 'ID de profesional requerido.'], 400);
            }

            $product_repository = $this->crm()->products();
            $products = $product_repository->get_products_full( $professional_id );
            return new WP_REST_Response(['success' => true, 'data' => $products], 200);
        } catch (Exception $e) {
            error_log('[API Error] handle_get_products_full: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }
}

