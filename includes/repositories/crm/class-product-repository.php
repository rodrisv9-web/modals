<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository-interface.php';

class VA_CRM_Product_Repository extends VA_Base_Repository implements VA_CRM_Product_Repository_Interface {
    /** @var string */
    protected $table_name_products;

    /** @var string */
    protected $table_name_manufacturers;

    /** @var string */
    protected $table_name_active_ingredients;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;
        $this->table_name_products           = $prefix . 'va_products';
        $this->table_name_manufacturers      = $prefix . 'va_manufacturers';
        $this->table_name_active_ingredients = $prefix . 'va_active_ingredients';
    }

    public function get_products_by_professional( int $professional_id ): array {
        $table = esc_sql( $this->table_name_products );

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE professional_id = %d AND is_active = 1 ORDER BY product_name ASC",
                $professional_id
            )
        );
    }

    public function save_product( array $product_data ) {
        $table = esc_sql( $this->table_name_products );

        $data = [
            'professional_id' => isset( $product_data['professional_id'] ) ? intval( $product_data['professional_id'] ) : 0,
            'product_name'    => isset( $product_data['product_name'] ) ? sanitize_text_field( $product_data['product_name'] ) : '',
            'product_type'    => isset( $product_data['product_type'] ) ? sanitize_text_field( $product_data['product_type'] ) : '',
            'presentation'    => isset( $product_data['presentation'] ) ? sanitize_text_field( $product_data['presentation'] ) : '',
            'notes'           => isset( $product_data['notes'] ) ? sanitize_textarea_field( $product_data['notes'] ) : '',
        ];

        if ( ! empty( $product_data['manufacturer'] ) ) {
            $manufacturer_id = $this->create_or_get_manufacturer( $product_data['manufacturer'] );
            if ( $manufacturer_id ) {
                $data['manufacturer_id'] = $manufacturer_id;
            }
        }

        if ( ! empty( $product_data['active_ingredient'] ) ) {
            $ingredient_id = $this->create_or_get_active_ingredient( $product_data['active_ingredient'] );
            if ( $ingredient_id ) {
                $data['active_ingredient_id'] = $ingredient_id;
            }
        }

        if ( ! empty( $product_data['product_id'] ) ) {
            $product_id = intval( $product_data['product_id'] );
            $this->wpdb->update( $table, $data, [ 'product_id' => $product_id ] );
            return $product_id;
        }

        $this->wpdb->insert( $table, $data );
        return $this->wpdb->insert_id;
    }

    public function delete_product( int $product_id, int $professional_id ) {
        $table = esc_sql( $this->table_name_products );

        return $this->wpdb->update(
            $table,
            [ 'is_active' => 0 ],
            [
                'product_id'      => $product_id,
                'professional_id' => $professional_id,
            ]
        );
    }

    public function get_products_full( int $professional_id ): array {
        $products_table      = esc_sql( $this->table_name_products );
        $manufacturers_table = esc_sql( $this->table_name_manufacturers );
        $ingredients_table   = esc_sql( $this->table_name_active_ingredients );

        $sql = $this->wpdb->prepare(
            "
            SELECT p.*,
                   m.manufacturer_name as manufacturer,
                   i.ingredient_name as active_ingredient
            FROM {$products_table} p
            LEFT JOIN {$manufacturers_table} m ON p.manufacturer_id = m.manufacturer_id
            LEFT JOIN {$ingredients_table} i ON p.active_ingredient_id = i.ingredient_id
            WHERE p.professional_id = %d AND p.is_active = 1
            ORDER BY p.product_name ASC
        ",
            $professional_id
        );

        return $this->wpdb->get_results( $sql );
    }

    public function get_manufacturers(): array {
        $table = esc_sql( $this->table_name_manufacturers );

        return $this->wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY manufacturer_name ASC"
        );
    }

    public function get_active_ingredients(): array {
        $table = esc_sql( $this->table_name_active_ingredients );

        return $this->wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY ingredient_name ASC"
        );
    }

    public function create_or_get_manufacturer( string $manufacturer_name, array $additional_data = [] ) {
        $manufacturer_name = sanitize_text_field( $manufacturer_name );
        if ( empty( $manufacturer_name ) ) {
            return false;
        }

        $table = esc_sql( $this->table_name_manufacturers );

        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT manufacturer_id FROM {$table} WHERE manufacturer_name = %s",
                $manufacturer_name
            )
        );

        if ( $existing_id ) {
            return intval( $existing_id );
        }

        $data = array_merge(
            [
                'manufacturer_name' => $manufacturer_name,
                'is_active'         => 1,
            ],
            $additional_data
        );

        $result = $this->wpdb->insert( $table, $data );

        return $result ? $this->wpdb->insert_id : false;
    }

    public function create_or_get_active_ingredient( string $ingredient_name, array $additional_data = [] ) {
        $ingredient_name = sanitize_text_field( $ingredient_name );
        if ( empty( $ingredient_name ) ) {
            return false;
        }

        $table = esc_sql( $this->table_name_active_ingredients );

        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT ingredient_id FROM {$table} WHERE ingredient_name = %s",
                $ingredient_name
            )
        );

        if ( $existing_id ) {
            return intval( $existing_id );
        }

        $data = array_merge(
            [
                'ingredient_name' => $ingredient_name,
                'is_active'       => 1,
            ],
            $additional_data
        );

        $result = $this->wpdb->insert( $table, $data );

        return $result ? $this->wpdb->insert_id : false;
    }
}
