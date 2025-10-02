<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-repository-interface.php';

class VA_CRM_Pet_Repository extends VA_Base_Repository implements VA_CRM_Pet_Repository_Interface {
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

    public function create_pet( array $pet_data ) {
        $defaults = [
            'client_id'        => 0,
            'name'             => '',
            'species'          => '',
            'breed'            => null,
            'birth_date'       => null,
            'gender'           => 'unknown',
            'weight'           => null,
            'microchip_number' => null,
            'share_code'       => '',
            'notes'            => null,
        ];

        $data = wp_parse_args( $pet_data, $defaults );

        if ( empty( $data['client_id'] ) || empty( $data['name'] ) || empty( $data['share_code'] ) ) {
            return false;
        }

        $insert_data = [
            'client_id'        => intval( $data['client_id'] ),
            'name'             => sanitize_text_field( $data['name'] ),
            'species'          => sanitize_text_field( $data['species'] ),
            'breed'            => $data['breed'] ? sanitize_text_field( $data['breed'] ) : null,
            'birth_date'       => $data['birth_date'] ? sanitize_text_field( $data['birth_date'] ) : null,
            'gender'           => $data['gender'],
            'weight'           => $data['weight'] ? floatval( $data['weight'] ) : null,
            'microchip_number' => $data['microchip_number'] ? sanitize_text_field( $data['microchip_number'] ) : null,
            'share_code'       => strtoupper( sanitize_text_field( $data['share_code'] ) ),
            'notes'            => $data['notes'] ? sanitize_textarea_field( $data['notes'] ) : null,
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' ];

        $result = $this->wpdb->insert( $this->table_name_pets, $insert_data, $format );

        return $result ? $this->wpdb->insert_id : false;
    }

    public function create_pet_with_share_code( array $pet_data ) {
        error_log( '[Veterinalia CRM] Iniciando creación de mascota con share_code automático' );

        if ( empty( $pet_data['client_id'] ) || empty( $pet_data['name'] ) ) {
            error_log( '[Veterinalia CRM] ERROR: Faltan datos requeridos (client_id o name) para crear mascota' );
            return false;
        }

        $share_code = $this->generate_unique_share_code();
        if ( ! $share_code ) {
            error_log( '[Veterinalia CRM] ERROR: No se pudo generar un share_code único' );
            return false;
        }

        $pet_data_with_code = array_merge(
            $pet_data,
            [
                'share_code' => $share_code,
                'species'    => $pet_data['species'] ?? 'unknown',
                'gender'     => $pet_data['gender'] ?? 'unknown',
            ]
        );

        error_log( '[Veterinalia CRM] Creando mascota con share_code: ' . $share_code . ' para cliente: ' . $pet_data['client_id'] );

        $pet_id = $this->create_pet( $pet_data_with_code );

        if ( $pet_id ) {
            error_log( '[Veterinalia CRM] Mascota creada exitosamente con ID: ' . $pet_id . ' y share_code: ' . $share_code );

            if ( ! empty( $pet_data['professional_id'] ) ) {
                $this->grant_pet_access( $pet_id, $pet_data['professional_id'] );
                error_log( '[Veterinalia CRM] Acceso otorgado al profesional ID: ' . $pet_data['professional_id'] );
            }

            return $pet_id;
        }

        error_log( '[Veterinalia CRM] ERROR: Falló la creación de la mascota' );
        return false;
    }

    public function get_pets_by_client( int $client_id ) {
        $table = esc_sql( $this->table_name_pets );
        $sql   = $this->wpdb->prepare(
            "
            SELECT * FROM {$table}
            WHERE client_id = %d AND is_active = 1
            ORDER BY name ASC
        ",
            intval( $client_id )
        );

        return $this->wpdb->get_results( $sql );
    }

    public function get_pets_by_client_with_access( int $client_id, int $professional_id ) {
        $pets_table    = esc_sql( $this->table_name_pets );
        $access_table  = esc_sql( $this->table_name_pet_access );
        $clients_table = esc_sql( $this->table_name_clients );

        $client_id       = intval( $client_id );
        $professional_id = intval( $professional_id );

        error_log( "[CHOCOVAINILLA DEBUG] Verificando acceso para professional_id: {$professional_id} sobre mascotas del client_id: {$client_id}" );

        $cache_key = VA_Cache_Helper::pet_access_key( $professional_id, $client_id );

        $results = VA_Cache_Helper::get_or_set(
            $cache_key,
            function () use ( $access_table, $pets_table, $clients_table, $professional_id, $client_id ) {
                $sql = $this->wpdb->prepare(
                    "SELECT
                        p.*,
                        (EXISTS (SELECT 1 FROM {$access_table} pa WHERE pa.pet_id = p.pet_id AND pa.professional_id = %d)) AS access_direct,
                        (EXISTS (
                            SELECT 1
                            FROM {$pets_table} px
                            JOIN {$access_table} pax ON pax.pet_id = px.pet_id
                            WHERE px.client_id = p.client_id AND pax.professional_id = %d
                        )) AS access_inherited,
                        (EXISTS (
                            SELECT 1 FROM {$clients_table} c
                            WHERE c.client_id = p.client_id AND c.created_by_professional = %d
                        )) AS access_by_creation
                    FROM {$pets_table} p
                    WHERE p.client_id = %d AND p.is_active = 1
                    ORDER BY p.name ASC",
                    $professional_id,
                    $professional_id,
                    $professional_id,
                    $client_id
                );

                return $this->wpdb->get_results( $sql );
            },
            VA_Cache_Helper::SHORT_EXPIRATION
        );

        if ( $this->wpdb->last_error ) {
            error_log( '[CHOCOVAINILLA DB ERROR] ' . $this->wpdb->last_error );
            return null;
        }

        if ( is_array( $results ) ) {
            foreach ( $results as $pet ) {
                $access_direct     = intval( $pet->access_direct );
                $access_inherited  = intval( $pet->access_inherited );
                $access_by_creation = intval( $pet->access_by_creation );

                $pet->has_access = ( $access_direct || $access_inherited || $access_by_creation ) ? 1 : 0;

                error_log(
                    "[CHOCOVAINILLA DEBUG] Mascota '{$pet->name}' (ID: {$pet->pet_id}): " .
                    "Directo: {$access_direct}, Heredado: {$access_inherited}, Creación: {$access_by_creation} " .
                    "==> Final: " . ( $pet->has_access ? 'CON ACCESO' : 'SIN ACCESO' )
                );

                unset( $pet->access_direct, $pet->access_inherited, $pet->access_by_creation );
            }
        }

        error_log( '[CHOCOVAINILLA DEBUG] Datos finales a enviar a la API: ' . print_r( $results, true ) );

        return $results;
    }

    public function get_pet_by_share_code( string $share_code ) {
        $table = esc_sql( $this->table_name_pets );
        $sql   = $this->wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE share_code = %s AND is_active = 1
            LIMIT 1",
            strtoupper( sanitize_text_field( $share_code ) )
        );

        return $this->wpdb->get_row( $sql );
    }

    public function get_pet_by_id( int $pet_id ) {
        $table = esc_sql( $this->table_name_pets );
        $sql   = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE pet_id = %d", intval( $pet_id ) );

        return $this->wpdb->get_row( $sql );
    }

    public function update_pet( int $pet_id, array $data ) {
        if ( empty( $pet_id ) || empty( $data ) ) {
            return false;
        }

        $update_data = [];
        $format      = [];

        $allowed_fields = [
            'name'             => '%s',
            'species'          => '%s',
            'breed'            => '%s',
            'birth_date'       => '%s',
            'gender'           => '%s',
            'weight'           => '%f',
            'microchip_number' => '%s',
            'share_code'       => '%s',
            'notes'            => '%s',
        ];

        foreach ( $data as $key => $value ) {
            if ( array_key_exists( $key, $allowed_fields ) ) {
                $update_data[ $key ] = $value;
                $format[]            = $allowed_fields[ $key ];
            }
        }

        if ( empty( $update_data ) ) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table_name_pets,
            $update_data,
            [ 'pet_id' => intval( $pet_id ) ],
            $format,
            [ '%d' ]
        );

        return false !== $result;
    }

    public function get_pet_by_name_and_client( string $pet_name, int $client_id ) {
        if ( empty( $pet_name ) || empty( $client_id ) ) {
            return null;
        }

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name_pets}
             WHERE name = %s AND client_id = %d AND is_active = 1
             LIMIT 1",
            sanitize_text_field( $pet_name ),
            intval( $client_id )
        );

        return $this->wpdb->get_row( $query );
    }

    public function grant_pet_access( int $pet_id, int $professional_id, string $access_level = 'read' ) {
        $insert_data = [
            'pet_id'          => intval( $pet_id ),
            'professional_id' => intval( $professional_id ),
            'access_level'    => $access_level,
            'granted_by'      => get_current_user_id() ?: null,
        ];

        $format = [ '%d', '%d', '%s', '%d' ];

        $sql = $this->wpdb->prepare(
            "
            INSERT INTO {$this->table_name_pet_access}
            (pet_id, professional_id, access_level, granted_by, date_granted, is_active)
            VALUES (%d, %d, %s, %d, NOW(), 1)
            ON DUPLICATE KEY UPDATE
            access_level = VALUES(access_level),
            is_active = 1,
            date_granted = NOW()
        ",
            $insert_data['pet_id'],
            $insert_data['professional_id'],
            $insert_data['access_level'],
            $insert_data['granted_by']
        );

        $result = $this->wpdb->query( $sql ) !== false;

        if ( $result ) {
            $pet = $this->get_pet_by_id( $insert_data['pet_id'] );
            if ( $pet && $pet->client_id ) {
                $cache_key = VA_Cache_Helper::pet_access_key( $insert_data['professional_id'], $pet->client_id );
                VA_Cache_Helper::invalidate( $cache_key );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "[VA Cache] Invalidated pet access cache for professional {$insert_data['professional_id']} and client {$pet->client_id}" );
                }
            }
        }

        return $result;
    }

    public function check_pet_access( int $professional_id, int $pet_id ) {
        $table = esc_sql( $this->table_name_pet_access );
        $sql   = $this->wpdb->prepare(
            "
            SELECT access_level FROM {$table}
            WHERE professional_id = %d AND pet_id = %d
            AND is_active = 1
            AND (date_expires IS NULL OR date_expires > NOW())
            LIMIT 1
        ",
            intval( $professional_id ),
            intval( $pet_id )
        );

        return $this->wpdb->get_var( $sql );
    }

    private function generate_unique_share_code() {
        $attempts     = 0;
        $max_attempts = 10;

        do {
            $letters = strtoupper( substr( md5( uniqid( mt_rand(), true ) ), 0, 4 ) );
            $numbers = str_pad( mt_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
            $code    = $letters . $numbers;

            $table  = esc_sql( $this->table_name_pets );
            $exists = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE share_code = %s", $code ) );

            if ( ! $exists ) {
                error_log( '[Veterinalia CRM] Share_code único generado: ' . $code );
                return $code;
            }

            $attempts++;
        } while ( $attempts < $max_attempts );

        error_log( '[Veterinalia CRM] ERROR: No se pudo generar un share_code único después de ' . $max_attempts . ' intentos' );
        return false;
    }
}
