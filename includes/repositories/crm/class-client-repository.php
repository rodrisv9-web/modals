<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-client-repository-interface.php';

class VA_CRM_Client_Repository extends VA_Base_Repository implements VA_CRM_Client_Repository_Interface {
    /** @var string */
    protected $table_name_clients;

    /** @var string */
    protected $table_name_pets;

    /** @var string */
    protected $table_name_pet_access;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;
        $this->table_name_clients    = $prefix . 'va_clients';
        $this->table_name_pets       = $prefix . 'va_pets';
        $this->table_name_pet_access = $prefix . 'va_pet_access';
    }

    public function create_client( array $client_data ): int {
        $defaults = [
            'user_id' => null,
            'name' => '',
            'email' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
            'created_by_professional' => null,
        ];

        $data = wp_parse_args( $client_data, $defaults );

        if ( empty( $data['name'] ) ) {
            return 0;
        }

        $insert_data = [
            'user_id' => $data['user_id'] ? intval( $data['user_id'] ) : null,
            'name' => sanitize_text_field( $data['name'] ),
            'email' => $data['email'] ? sanitize_email( $data['email'] ) : null,
            'phone' => $data['phone'] ? sanitize_text_field( $data['phone'] ) : null,
            'address' => $data['address'] ? sanitize_textarea_field( $data['address'] ) : null,
            'notes' => $data['notes'] ? sanitize_textarea_field( $data['notes'] ) : null,
            'created_by_professional' => $data['created_by_professional'] ? intval( $data['created_by_professional'] ) : null,
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d' ];

        $result = $this->wpdb->insert( $this->table_name_clients, $insert_data, $format );

        return $result ? (int) $this->wpdb->insert_id : 0;
    }

    public function create_guest_client( array $client_data ): int {
        error_log( '[Veterinalia CRM] Iniciando creación de cliente invitado' );

        if ( empty( $client_data['name'] ) || empty( $client_data['email'] ) ) {
            error_log( '[Veterinalia CRM] ERROR: Faltan datos requeridos (name o email) para crear cliente invitado' );
            return 0;
        }

        if ( empty( $client_data['professional_id'] ) ) {
            error_log( '[Veterinalia CRM] ERROR: Falta professional_id para crear cliente invitado' );
            return 0;
        }

        $existing_client = $this->get_client_by_email( $client_data['email'] );
        if ( $existing_client ) {
            error_log( '[Veterinalia CRM] Cliente ya existe con este email, usando cliente existente: ' . $existing_client->client_id );
            return (int) $existing_client->client_id;
        }

        $insert_data = [
            'user_id' => null,
            'name' => sanitize_text_field( $client_data['name'] ),
            'email' => sanitize_email( $client_data['email'] ),
            'phone' => ! empty( $client_data['phone'] ) ? sanitize_text_field( $client_data['phone'] ) : null,
            'address' => null,
            'notes' => 'Cliente creado automáticamente desde formulario de reserva',
            'created_by_professional' => intval( $client_data['professional_id'] ),
            'is_guest' => 1,
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ];

        error_log( '[Veterinalia CRM] Insertando cliente invitado en BD: ' . json_encode( $insert_data ) );

        $result = $this->wpdb->insert( $this->table_name_clients, $insert_data, $format );

        if ( $result ) {
            $client_id = (int) $this->wpdb->insert_id;
            error_log( '[Veterinalia CRM] Cliente invitado creado exitosamente con ID: ' . $client_id );
            return $client_id;
        }

        error_log( '[Veterinalia CRM] ERROR: Falló la inserción del cliente invitado: ' . $this->wpdb->last_error );
        return 0;
    }

    public function get_clients_by_professional( int $professional_id ): array {
        $table_clients = esc_sql( $this->table_name_clients );
        $table_pets    = esc_sql( $this->table_name_pets );
        $table_access  = esc_sql( $this->table_name_pet_access );

        $sql = $this->wpdb->prepare(
            "
            SELECT *
            FROM {$table_clients} c
            WHERE c.created_by_professional = %d
            UNION
            SELECT c.*
            FROM {$table_clients} c
            INNER JOIN {$table_pets} p ON c.client_id = p.client_id
            INNER JOIN {$table_access} a ON p.pet_id = a.pet_id
            WHERE a.professional_id = %d AND a.is_active = 1 AND p.is_active = 1
            ORDER BY name ASC
        ",
            intval( $professional_id ),
            intval( $professional_id )
        );

        $results = $this->wpdb->get_results( $sql ) ?: [];

        error_log( "[CRM Debug] get_clients_by_professional for ID {$professional_id}: " . count( $results ) . " clients found" );
        if ( $this->wpdb->last_error ) {
            error_log( '[CRM Error] SQL Error: ' . $this->wpdb->last_error );
        }

        return $results;
    }

    public function get_client_by_id( int $client_id ) {
        $table = esc_sql( $this->table_name_clients );
        $sql   = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %d", intval( $client_id ) );

        return $this->wpdb->get_row( $sql );
    }

    public function get_client_by_email( string $email ) {
        if ( empty( $email ) ) {
            error_log( '[Veterinalia CRM] ERROR: Email vacío en get_client_by_email()' );
            return null;
        }

        $table = esc_sql( $this->table_name_clients );
        $sql   = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", sanitize_email( $email ) );

        error_log( "[Veterinalia CRM] Buscando cliente por email: {$email}" );
        $result = $this->wpdb->get_row( $sql );

        if ( $result ) {
            error_log( "[Veterinalia CRM] Cliente encontrado: ID {$result->client_id}, Nombre: {$result->name}" );
        } else {
            error_log( "[Veterinalia CRM] Cliente NO encontrado para email: {$email}" );
        }

        return $result;
    }

    public function link_client_to_user( int $client_id, int $user_id ): bool {
        if ( empty( $client_id ) || empty( $user_id ) ) {
            return false;
        }

        error_log( "[Chocovainilla] DB: Vinculando client_id {$client_id} con user_id {$user_id}." );

        $result = $this->wpdb->update(
            $this->table_name_clients,
            [
                'user_id'  => intval( $user_id ),
                'is_guest' => 0,
            ],
            [ 'client_id' => intval( $client_id ) ],
            [ '%d', '%d' ],
            [ '%d' ]
        );

        return false !== $result;
    }

    public function search_clients_with_access_check( string $term, int $professional_id ): array {
        $term_like = '%' . $this->wpdb->esc_like( $term ) . '%';
        $sql       = $this->wpdb->prepare(
            "
            SELECT
                c.client_id,
                c.name,
                c.email,
                EXISTS (
                    SELECT 1
                    FROM {$this->table_name_pets} p
                    JOIN {$this->table_name_pet_access} pa ON p.pet_id = pa.pet_id
                    WHERE p.client_id = c.client_id
                    AND pa.professional_id = %d
                ) AS has_access
            FROM {$this->table_name_clients} c
            WHERE c.name LIKE %s OR c.email LIKE %s
            LIMIT 10
        ",
            $professional_id,
            $term_like,
            $term_like
        );

        error_log( '[Chocovainilla] DB Query: Búsqueda de clientes ejecutada.' );
        $results = $this->wpdb->get_results( $sql ) ?: [];
        if ( empty( $results ) && ! empty( $this->wpdb->last_error ) ) {
            error_log( '[Chocovainilla][DB ERROR] search_clients_with_access_check: ' . $this->wpdb->last_error );
        }

        return $results;
    }

    public function search_clients_basic( string $term ): array {
        $term_like = '%' . $this->wpdb->esc_like( $term ) . '%';
        $sql       = $this->wpdb->prepare(
            "SELECT client_id, name, email FROM {$this->table_name_clients} WHERE name LIKE %s OR email LIKE %s LIMIT 10",
            $term_like,
            $term_like
        );

        return $this->wpdb->get_results( $sql ) ?: [];
    }

    public function get_client_by_user_id( int $user_id ) {
        if ( empty( $user_id ) ) {
            return null;
        }

        $table = esc_sql( $this->table_name_clients );
        $sql   = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", intval( $user_id ) );

        return $this->wpdb->get_row( $sql );
    }
}
