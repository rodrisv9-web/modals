<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-log-repository-interface.php';

class VA_CRM_Pet_Log_Repository extends VA_Base_Repository implements VA_CRM_Pet_Log_Repository_Interface {
    /** @var string */
    protected $table_name_pet_logs;

    /** @var string */
    protected $table_name_pet_log_meta;

    /** @var string */
    protected $table_name_pet_log_products;

    /** @var string */
    protected $table_name_products;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;

        $this->table_name_pet_logs         = $prefix . 'va_pet_logs';
        $this->table_name_pet_log_meta     = $prefix . 'va_pet_log_meta';
        $this->table_name_pet_log_products = $prefix . 'va_pet_log_products';
        $this->table_name_products         = $prefix . 'va_products';
    }

    public function create_pet_log( array $log_data ) {
        $defaults = [
            'pet_id'          => 0,
            'professional_id' => 0,
            'appointment_id'  => null,
            'entry_type_id'   => null,
            'entry_date'      => current_time( 'mysql' ),
            'title'           => '',
            'description'     => null,
            'diagnosis'       => null,
            'treatment'       => null,
            'medication'      => null,
            'next_visit_date' => null,
            'attachment_url'  => null,
            'weight_recorded' => null,
            'temperature'     => null,
            'notes'           => null,
            'is_private'      => 0,
        ];

        $data = wp_parse_args( $log_data, $defaults );

        if ( empty( $data['pet_id'] ) || empty( $data['professional_id'] ) || empty( $data['title'] ) ) {
            return false;
        }

        $insert_data = [
            'pet_id'          => intval( $data['pet_id'] ),
            'professional_id' => intval( $data['professional_id'] ),
            'appointment_id'  => $data['appointment_id'] ? intval( $data['appointment_id'] ) : null,
            'entry_type_id'   => $data['entry_type_id'] ? intval( $data['entry_type_id'] ) : null,
            'entry_date'      => sanitize_text_field( $data['entry_date'] ),
            'title'           => sanitize_text_field( $data['title'] ),
            'description'     => $data['description'] ? sanitize_textarea_field( $data['description'] ) : null,
            'diagnosis'       => $data['diagnosis'] ? sanitize_textarea_field( $data['diagnosis'] ) : null,
            'treatment'       => $data['treatment'] ? sanitize_textarea_field( $data['treatment'] ) : null,
            'medication'      => $data['medication'] ? sanitize_textarea_field( $data['medication'] ) : null,
            'next_visit_date' => $data['next_visit_date'] ? sanitize_text_field( $data['next_visit_date'] ) : null,
            'attachment_url'  => $data['attachment_url'] ? esc_url_raw( $data['attachment_url'] ) : null,
            'weight_recorded' => $data['weight_recorded'] ? floatval( $data['weight_recorded'] ) : null,
            'temperature'     => $data['temperature'] ? floatval( $data['temperature'] ) : null,
            'notes'           => $data['notes'] ? sanitize_textarea_field( $data['notes'] ) : null,
            'is_private'      => $data['is_private'] ? 1 : 0,
        ];

        $format = [
            '%d',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%f',
            '%f',
            '%s',
            '%d',
        ];

        $result = $this->wpdb->insert( $this->table_name_pet_logs, $insert_data, $format );

        return $result ? $this->wpdb->insert_id : false;
    }

    public function get_pet_logs( int $pet_id, $professional_id = null ) {
        $table_logs = esc_sql( $this->table_name_pet_logs );
        $table_users = $this->wpdb->users;

        $where_clause = 'pl.pet_id = %d';
        $params       = [ intval( $pet_id ) ];

        if ( $professional_id ) {
            $where_clause .= ' AND (pl.is_private = 0 OR pl.professional_id = %d)';
            $params[]      = intval( $professional_id );
        }

        $sql = $this->wpdb->prepare(
            "
            SELECT pl.*, u.display_name as professional_name
            FROM {$table_logs} pl
            LEFT JOIN {$table_users} u ON pl.professional_id = u.ID
            WHERE {$where_clause}
            ORDER BY pl.entry_date DESC, pl.date_created DESC
        ",
            $params
        );

        return $this->wpdb->get_results( $sql );
    }

    public function add_pet_log_meta( int $log_id, string $meta_key, string $meta_value ) {
        return $this->wpdb->insert(
            $this->table_name_pet_log_meta,
            [
                'log_id'     => $log_id,
                'meta_key'   => $meta_key,
                'meta_value' => $meta_value,
            ],
            [ '%d', '%s', '%s' ]
        );
    }

    public function add_pet_log_product( int $log_id, int $product_id, array $context_data = [] ) {
        $data = [
            'log_id'          => intval( $log_id ),
            'product_id'      => intval( $product_id ),
            'lot_number'      => isset( $context_data['lot_number'] ) ? sanitize_text_field( $context_data['lot_number'] ) : null,
            'expiration_date' => isset( $context_data['expiration_date'] ) ? sanitize_text_field( $context_data['expiration_date'] ) : null,
            'quantity_used'   => isset( $context_data['quantity_used'] ) ? sanitize_text_field( $context_data['quantity_used'] ) : null,
        ];

        return $this->wpdb->insert(
            $this->table_name_pet_log_products,
            $data,
            [ '%d', '%d', '%s', '%s', '%s' ]
        );
    }

    public function get_pet_logs_full( int $pet_id, $professional_id = null ) {
        $logs = $this->get_pet_logs( $pet_id, $professional_id );

        if ( empty( $logs ) ) {
            return [];
        }

        $log_ids      = wp_list_pluck( $logs, 'log_id' );
        $placeholders = implode( ', ', array_fill( 0, count( $log_ids ), '%d' ) );

        $meta_table = esc_sql( $this->table_name_pet_log_meta );
        $sql_meta   = $this->wpdb->prepare(
            "SELECT * FROM {$meta_table} WHERE log_id IN ($placeholders)",
            $log_ids
        );
        $all_meta   = $this->wpdb->get_results( $sql_meta );

        $products_table = esc_sql( $this->table_name_pet_log_products );
        $catalog_table  = esc_sql( $this->table_name_products );
        $sql_products   = $this->wpdb->prepare(
            "SELECT lp.*, p.product_name, p.product_type
             FROM {$products_table} lp
             JOIN {$catalog_table} p ON lp.product_id = p.product_id
             WHERE lp.log_id IN ($placeholders)",
            $log_ids
        );
        $all_products   = $this->wpdb->get_results( $sql_products );

        $logs_by_id = [];
        foreach ( $logs as $log ) {
            $log->meta     = [];
            $log->products = [];
            $logs_by_id[ $log->log_id ] = $log;
        }

        foreach ( $all_meta as $meta ) {
            if ( isset( $logs_by_id[ $meta->log_id ] ) ) {
                $logs_by_id[ $meta->log_id ]->meta[ $meta->meta_key ] = $meta->meta_value;
            }
        }

        foreach ( $all_products as $product ) {
            if ( isset( $logs_by_id[ $product->log_id ] ) ) {
                $logs_by_id[ $product->log_id ]->products[] = $product;
            }
        }

        return array_values( $logs_by_id );
    }
}
