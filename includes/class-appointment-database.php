<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase refactorizada para manejar la base de datos del plugin Veterinalia Appointment.
 * - Mantiene compatibilidad con la interfaz pública existente.
 * - Divide create_tables en métodos más pequeños.
 * - Usa siempre las propiedades $this->table_name_*
 * - Valida nombres de tabla con esc_sql() donde es necesario.
 * - Corrige consultas peligrosas y formatos de $wpdb->insert()/update().
 * - Añade recomendaciones para migraciones e índices.
 */
class Veterinalia_Appointment_Database {

    private static $instance = null;

    private $charset_collate;
    private $table_name_availability;
    private $table_name_appointments;
    private $table_name_categories;
    private $table_name_services;
    // (Las tablas del CRM han sido movidas a Veterinalia_CRM_Database)

    /** @var VA_Appointment_Availability_Repository_Interface|null */
    private $availability_repository = null;

    /** @var VA_Appointment_Booking_Repository_Interface|null */
    private $booking_repository = null;

    /**
     * Obtiene la única instancia de la clase.
     *
     * @return Veterinalia_Appointment_Database
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        // Definir nombres de tablas una sola vez
        $this->table_name_availability         = $prefix . 'va_professional_availability';
        $this->table_name_appointments         = $prefix . 'va_appointments';
        $this->table_name_categories           = $prefix . 'va_service_categories';
        $this->table_name_services             = $prefix . 'va_services';
        // (Las tablas del CRM se inicializan en Veterinalia_CRM_Database)
    }

    private function availability_repository(): VA_Appointment_Availability_Repository_Interface {
        if ( ! $this->availability_repository instanceof VA_Appointment_Availability_Repository_Interface ) {
            $factory     = VA_Repository_Factory::instance();
            $repository  = $factory->get( 'appointment.availability' );

            if ( ! $repository instanceof VA_Appointment_Availability_Repository_Interface ) {
                global $wpdb;
                $repository = new VA_Appointment_Availability_Repository( $wpdb );
                $factory->bind( 'appointment.availability', $repository );
            }

            $this->availability_repository = $repository;
        }

        return $this->availability_repository;
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
     * Crea todas las tablas (llama a métodos más pequeños, idempotente gracias a dbDelta).
     */
    public function create_tables(): void {
        $this->create_table_availability();
        $this->create_table_categories();
        $this->create_table_services();
        $this->create_table_appointments();
    }

    private function create_table_availability(): void {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_availability );
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            dia_semana_id BIGINT(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            slot_duration INT(11) NOT NULL,
            is_available TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY professional_id (professional_id),
            KEY dia_semana_id (dia_semana_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_categories(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_categories );
        $sql = "CREATE TABLE {$table} (
            category_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            display_order INT DEFAULT 0,
            PRIMARY KEY (category_id),
            KEY professional_id (professional_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_services(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_services );
        $sql = "CREATE TABLE {$table} (
            service_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            category_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            entry_type_id BIGINT(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            duration INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (service_id),
            KEY category_id (category_id),
            KEY professional_id (professional_id),
            KEY idx_entry_type_id (entry_type_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 0.1 -->
    private function create_table_appointments(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_appointments );
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            client_id BIGINT(20) DEFAULT NULL,
            service_id BIGINT(20) NOT NULL,
            pet_id BIGINT(20) DEFAULT NULL,
            appointment_start DATETIME NOT NULL,
            appointment_end DATETIME NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            price_at_booking DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            client_name VARCHAR(255) DEFAULT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            pet_name VARCHAR(255) DEFAULT NULL,
            client_phone VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_professional_start (professional_id, appointment_start),
            KEY idx_service (service_id),
            KEY idx_pet_id (pet_id),
            KEY idx_professional_status_start (professional_id, status, appointment_start)
        ) {$this->charset_collate};";
        
        // dbDelta es seguro, solo aplicará los cambios si la columna no existe.
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Mensaje de depuración (silenciado durante activación)
        if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
            error_log('[Chocovainilla] DB Check: La tabla va_appointments ahora incluye la columna pet_id.');
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 0.1 -->

    public function drop_tables(): void {
        global $wpdb;
        $tables = [
            $this->table_name_services,
            $this->table_name_categories,
            $this->table_name_appointments,
            $this->table_name_availability,
        ];

        foreach ( $tables as $t ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $t ) );
        }
    }

    // ----------------- Métodos CRUD y utilitarios (manteniendo firmas públicas) -----------------

    public function delete_professional_availability( $professional_id ) {
        return $this->availability_repository()->delete_professional_availability( intval( $professional_id ) );
    }

    public function insert_professional_availability( $professional_id, $dia_semana_id, $start_time, $end_time, $slot_duration ) {
        return $this->availability_repository()->insert_professional_availability(
            intval( $professional_id ),
            intval( $dia_semana_id ),
            $start_time,
            $end_time,
            intval( $slot_duration )
        );
    }

    public function get_professional_availability( $professional_id ) {
        return $this->availability_repository()->get_professional_availability( intval( $professional_id ) );
    }

    public function insert_appointment( $appointment_data ) {
        return $this->booking_repository()->insert_appointment( $appointment_data );
    }

    public function get_appointments_by_professional_id( $professional_id, $args = [] ) {
        return $this->booking_repository()->get_appointments_by_professional_id( intval( $professional_id ), $args );
    }


    public function update_appointment_status( $appointment_id, $new_status ) {
        return $this->booking_repository()->update_appointment_status( intval( $appointment_id ), (string) $new_status );
    }

    /**
     * Obtiene los IDs de listados publicados para un autor determinado.
     *
     * Los resultados se cachean usando VA_Cache_Helper::PREFIX_LISTINGS. Cuando un listado
     * cambie, invalida el cache con VA_Cache_Helper::invalidate_group( VA_Cache_Helper::PREFIX_LISTINGS . $author_id ).
     *
     * @param int   $user_id    ID del autor.
     * @param array $query_args Argumentos adicionales como posts_per_page o paged.
     * @return int[]
     */
    public function get_listings_by_author_id( $user_id, array $query_args = [] ) {
        if ( empty( $user_id ) ) {
            return [];
        }

        $author_id = intval( $user_id );
        $post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'listing';
        $per_page  = isset( $query_args['posts_per_page'] ) ? intval( $query_args['posts_per_page'] ) : intval( get_option( 'posts_per_page', 20 ) );
        if ( $per_page <= 0 ) {
            $per_page = intval( get_option( 'posts_per_page', 20 ) );
        }

        $paged = isset( $query_args['paged'] ) ? max( 1, intval( $query_args['paged'] ) ) : 1;

        $args = [
            'post_type'      => $post_type,
            'author'         => $author_id,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        $cache_key = sprintf( '%s%d_%d_%d', VA_Cache_Helper::PREFIX_LISTINGS, $author_id, $per_page, $paged );

        $listing_ids = VA_Cache_Helper::get_or_set(
            $cache_key,
            static function () use ( $args ) {
                $ids = get_posts( $args );
                if ( empty( $ids ) ) {
                    return [];
                }

                return array_map( 'intval', $ids );
            },
            VA_Cache_Helper::SHORT_EXPIRATION
        );

        return is_array( $listing_ids ) ? $listing_ids : [];
    }

    public function get_appointment_at_time( $professional_id, $appointment_date, $appointment_time ) {
        return $this->booking_repository()->get_appointment_at_time( intval( $professional_id ), $appointment_date, $appointment_time );
    }

    public function get_appointments_for_date( $professional_id, $date ) {
        return $this->booking_repository()->get_appointments_for_date( intval( $professional_id ), $date );
    }

    public function is_slot_already_booked( $professional_id, $start_time, $end_time ) {
        return $this->booking_repository()->is_slot_already_booked( intval( $professional_id ), $start_time, $end_time );
    }

    public function get_appointment_by_id( $appointment_id ) {
        return $this->booking_repository()->get_appointment_by_id( intval( $appointment_id ) );
    }

    public function get_next_appointment_for_pet( $pet_id ) {
        return $this->booking_repository()->get_next_appointment_for_pet( intval( $pet_id ) );
    }

    public function get_next_appointments_for_pets( array $pet_ids ) {
        return $this->booking_repository()->get_next_appointments_for_pets( $pet_ids );
    }

    // ----------------- Categorías y Servicios -----------------

    public function save_category( $category_data ) {
        global $wpdb;
        $defaults = [ 'professional_id' => 0, 'name' => '', 'description' => '', 'display_order' => 0 ];
        $data = wp_parse_args( $category_data, $defaults );

        if ( empty( $data['professional_id'] ) || empty( $data['name'] ) ) return false;

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$this->table_name_categories} WHERE professional_id = %d AND name = %s", intval( $data['professional_id'] ), $data['name'] ) );
        if ( $exists ) return false;

        $result = $wpdb->insert( $this->table_name_categories, [
            'professional_id' => intval( $data['professional_id'] ),
            'name' => sanitize_text_field( $data['name'] ),
            'description' => sanitize_textarea_field( $data['description'] ),
            'display_order' => intval( $data['display_order'] ),
        ], [ '%d', '%s', '%s', '%d' ] );

        // Invalidar cache de categorías cuando se crea una nueva
        if ($result) {
            $professional_id = intval( $data['professional_id'] );
            VA_Cache_Helper::invalidate(VA_Cache_Helper::categories_key($professional_id));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Cache] Invalidated categories cache for professional {$professional_id}");
            }
        }

        return $result ? $wpdb->insert_id : false;
    }

    public function get_categories_by_professional( $professional_id ) {
        global $wpdb;
        if ( empty( $professional_id ) ) return [];
        
        // Implementar cache para categorías por profesional
        $cache_key = VA_Cache_Helper::categories_key($professional_id);
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $professional_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE professional_id = %d ORDER BY display_order ASC, name ASC", intval( $professional_id ) );
            return $wpdb->get_results( $sql );
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }

    public function get_category_by_id( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE category_id = %d", intval( $category_id ) ) );
    }

    public function save_service( $service_data ) {
        global $wpdb;
        $defaults = [ 'professional_id' => 0, 'category_id' => 0, 'name' => '', 'description' => '', 'price' => '0.00', 'duration' => 30, 'is_active' => 1, 'entry_type_id' => 0 ];
        $data = wp_parse_args( $service_data, $defaults );

        if ( empty( $data['professional_id'] ) || empty( $data['category_id'] ) || empty( $data['name'] ) ) return false;

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT service_id FROM {$this->table_name_services} WHERE category_id = %d AND name = %s", intval( $data['category_id'] ), $data['name'] ) );
        if ( $exists ) return false;

        $result = $wpdb->insert( $this->table_name_services, [
            'professional_id' => intval( $data['professional_id'] ),
            'category_id'     => intval( $data['category_id'] ),
            'name'            => sanitize_text_field( $data['name'] ),
            'description'     => sanitize_textarea_field( $data['description'] ),
            'price'           => number_format( floatval( $data['price'] ), 2, '.', '' ),
            'duration'        => intval( $data['duration'] ),
            'is_active'       => $data['is_active'] ? 1 : 0,
            'entry_type_id'   => intval( $data['entry_type_id'] ),
        ], [ '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' ] );

        // Invalidar cache relacionado cuando se crea un nuevo servicio
        if ($result) {
            $professional_id = intval( $data['professional_id'] );
            $category_id = intval( $data['category_id'] );
            
            VA_Cache_Helper::invalidate(VA_Cache_Helper::services_key($professional_id, true));
            VA_Cache_Helper::invalidate('va_services_cat_' . $category_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Cache] Invalidated services cache for professional {$professional_id} and category {$category_id}");
            }
        }

        return $result ? $wpdb->insert_id : false;
    }

    public function get_services_by_category( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return [];
        
        // Implementar cache para servicios por categoría
        $cache_key = 'va_services_cat_' . $category_id;
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $category_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE category_id = %d AND is_active = 1 ORDER BY name ASC", intval( $category_id ) );
            return $wpdb->get_results( $sql );
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }
    
    /**
     * Obtiene todos los servicios activos para un profesional específico.
     *
     * @param int $professional_id ID del profesional.
     * @return array Array de objetos de servicio.
     */
    public function get_services_by_professional( $professional_id ) {
        global $wpdb;
        if ( empty( $professional_id ) ) return [];
        
        // Implementar cache para servicios por profesional (ALTA PRIORIDAD)
        $cache_key = VA_Cache_Helper::services_key($professional_id, true);
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $professional_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE professional_id = %d AND is_active = 1 ORDER BY name ASC", intval( $professional_id ) );
            va_log('[Veterinalia DB] Consulta get_services_by_professional para profesional ' . intval( $professional_id ), 'debug');

            $results = $wpdb->get_results( $sql );
            va_log('[Veterinalia DB] Servicios encontrados: ' . count( (array) $results ), 'debug');

            return $results;
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }

    public function get_service_by_id( $service_id ) {
        global $wpdb;
        if ( empty( $service_id ) ) return null;

        $query = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE service_id = %d", intval( $service_id ) );
        va_log('[Veterinalia DB] Consulta get_service_by_id para servicio ' . intval( $service_id ), 'debug');

        $result = $wpdb->get_row( $query );
        va_log('[Veterinalia DB] Resultado get_service_by_id: ' . ( $result ? 'encontrado' : 'no encontrado' ), 'debug');

        return $result;
    }

    public function get_category_by_name_and_professional( $professional_id, $category_name ) {
        global $wpdb;
        if ( empty( $professional_id ) || empty( $category_name ) ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE professional_id = %d AND name = %s LIMIT 1", intval( $professional_id ), $category_name ) );
    }

    public function update_category( $category_id, $data ) {
        global $wpdb;
        if ( empty( $category_id ) || empty( $data ) ) return false;
        $allowed = [ 'name', 'description', 'display_order' ];
        $update = [];
        $format = [];
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                $update[ $k ] = $v;
                $format[] = $k === 'display_order' ? '%d' : '%s';
            }
        }
        if ( empty( $update ) ) return false;
        $res = $wpdb->update( $this->table_name_categories, $update, [ 'category_id' => intval( $category_id ) ], $format, [ '%d' ] );
        return false !== $res;
    }

    public function delete_category( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return false;
        // Eliminar servicios asociados
        $wpdb->delete( $this->table_name_services, [ 'category_id' => intval( $category_id ) ], [ '%d' ] );
        $res = $wpdb->delete( $this->table_name_categories, [ 'category_id' => intval( $category_id ) ], [ '%d' ] );
        return false !== $res;
    }

    public function update_service( $service_id, $data ) {
        global $wpdb;
        if ( empty( $service_id ) || empty( $data ) ) return false;
        $allowed = [ 'name', 'description', 'price', 'duration', 'is_active', 'entry_type_id' ];
        $update = [];
        $format = [];
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                $update[ $k ] = in_array( $k, [ 'price' ], true ) ? number_format( floatval( $v ), 2, '.', '' ) : ( in_array( $k, [ 'duration', 'is_active' ], true ) ? intval( $v ) : sanitize_text_field( $v ) );
                if ( in_array( $k, [ 'price' ], true ) ) {
                    $format[] = '%s';
                } elseif ( in_array( $k, [ 'duration', 'is_active' ], true ) ) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
        }
        if ( empty( $update ) ) return false;
        $res = $wpdb->update( $this->table_name_services, $update, [ 'service_id' => intval( $service_id ) ], $format, [ '%d' ] );
        return false !== $res;
    }

    public function delete_service( $service_id ) {
        global $wpdb;
        if ( empty( $service_id ) ) return false;
        $res = $wpdb->delete( $this->table_name_services, [ 'service_id' => intval( $service_id ) ], [ '%d' ] );
        return false !== $res;
    }

}

// Fin de clase


