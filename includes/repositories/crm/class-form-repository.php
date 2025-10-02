<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-form-repository-interface.php';

class VA_CRM_Form_Repository extends VA_Base_Repository implements VA_CRM_Form_Repository_Interface {
    /** @var string */
    protected $table_name_entry_types;

    /** @var string */
    protected $table_name_form_fields;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;
        $this->table_name_entry_types = $prefix . 'va_entry_types';
        $this->table_name_form_fields = $prefix . 'va_form_fields';
    }

    public function get_entry_types(): array {
        $table = esc_sql( $this->table_name_entry_types );
        $results = $this->wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC" );

        return is_array( $results ) ? $results : [];
    }

    public function get_form_fields_by_entry_type( int $entry_type_id ): array {
        $entry_type_id = intval( $entry_type_id );

        if ( $entry_type_id <= 0 ) {
            return [];
        }

        $table   = esc_sql( $this->table_name_form_fields );
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE entry_type_id = %d ORDER BY display_order ASC",
                $entry_type_id
            )
        );

        return is_array( $results ) ? $results : [];
    }
}
