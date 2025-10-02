<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository-interface.php';

class VA_CRM_Product_Migration_Service {
    /** @var \wpdb */
    protected $wpdb;

    /** @var string */
    protected $table_name_entry_types;

    /** @var string */
    protected $table_name_form_fields;

    /** @var string */
    protected $table_name_pet_log_meta;

    /** @var string */
    protected $table_name_pet_log_products;

    /** @var string */
    protected $table_name_pet_logs;

    /** @var string */
    protected $table_name_products;

    /** @var string */
    protected $table_name_manufacturers;

    /** @var string */
    protected $table_name_active_ingredients;

    /** @var VA_CRM_Product_Repository_Interface */
    protected $product_repository;

    /** @var callable|null */
    protected $logger;

    public function __construct( \wpdb $wpdb, array $table_names, VA_CRM_Product_Repository_Interface $product_repository, ?callable $logger = null ) {
        $this->wpdb                     = $wpdb;
        $this->table_name_entry_types   = $table_names['entry_types'] ?? '';
        $this->table_name_form_fields   = $table_names['form_fields'] ?? '';
        $this->table_name_pet_log_meta  = $table_names['pet_log_meta'] ?? '';
        $this->table_name_pet_log_products = $table_names['pet_log_products'] ?? '';
        $this->table_name_pet_logs      = $table_names['pet_logs'] ?? '';
        $this->table_name_products      = $table_names['products'] ?? '';
        $this->table_name_manufacturers = $table_names['manufacturers'] ?? '';
        $this->table_name_active_ingredients = $table_names['active_ingredients'] ?? '';
        $this->product_repository       = $product_repository;
        $this->logger                   = $logger ? \Closure::fromCallable( $logger ) : null;
    }

    public function apply_normalization_improvements(): void {
        $this->add_foreign_key_constraints();
        $this->migrate_manufacturer_data();
        $this->migrate_active_ingredient_data();
        $this->update_products_table_structure();
        $this->cleanup_redundant_columns();
    }

    public function apply_structure_improvements(): void {
        $form_fields_table = esc_sql( $this->table_name_form_fields );

        $indexes = $this->wpdb->get_results( "SHOW INDEX FROM {$form_fields_table}" );
        $has_unique_constraint = false;

        foreach ( $indexes as $index ) {
            if ( $index->Key_name === 'unique_entry_field' ) {
                $has_unique_constraint = true;
                break;
            }
        }

        if ( ! $has_unique_constraint ) {
            $this->wpdb->query(
                "ALTER TABLE {$form_fields_table}
                         ADD UNIQUE KEY unique_entry_field (entry_type_id, field_key)"
            );
            $this->log( '[Veterinalia] UNIQUE constraint añadido a va_form_fields' );
        }

        $columns = $this->wpdb->get_results( "DESCRIBE {$form_fields_table}" );
        foreach ( $columns as $column ) {
            if ( $column->Field === 'product_filter_type' && strpos( $column->Type, 'varchar' ) !== false ) {
                $this->wpdb->query(
                    "ALTER TABLE {$form_fields_table}
                             MODIFY COLUMN product_filter_type ENUM('Vacuna', 'Desparasitante', 'Antibiótico', 'Antiinflamatorio', 'Otro') DEFAULT NULL"
                );
                $this->log( '[Veterinalia] product_filter_type convertido a ENUM' );
            }
        }
    }

    public function apply_product_type_enum_update(): void {
        $table          = esc_sql( $this->table_name_products );
        $new_enum_values = "'Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna'";
        $sql            = "ALTER TABLE {$table} MODIFY COLUMN product_type ENUM({$new_enum_values}) NOT NULL";

        $original_suppress  = $this->wpdb->suppress_errors( true );
        $original_show      = $this->wpdb->show_errors;
        $this->wpdb->show_errors( false );

        $this->wpdb->query( $sql );

        $this->wpdb->suppress_errors( $original_suppress );
        $this->wpdb->show_errors = $original_show;

        $this->log( '[Veterinalia] ENUM de product_type en va_products actualizado.' );
    }

    public function apply_form_fields_enum_update(): void {
        $table           = esc_sql( $this->table_name_form_fields );
        $new_enum_values = "'Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna'";
        $sql             = "ALTER TABLE {$table} MODIFY COLUMN product_filter_type ENUM({$new_enum_values}) DEFAULT NULL";

        $original_suppress  = $this->wpdb->suppress_errors( true );
        $original_show      = $this->wpdb->show_errors;
        $this->wpdb->show_errors( false );

        $this->wpdb->query( $sql );

        $this->wpdb->suppress_errors( $original_suppress );
        $this->wpdb->show_errors = $original_show;

        $this->log( '[Veterinalia] ENUM de product_filter_type en va_form_fields actualizado.' );
    }

    public function force_cleanup_redundant_columns(): array {
        $table               = esc_sql( $this->table_name_products );
        $columns             = $this->wpdb->get_col( "DESCRIBE {$table}" );
        $has_manufacturer_id = in_array( 'manufacturer_id', $columns, true );
        $has_ingredient_id   = in_array( 'active_ingredient_id', $columns, true );
        $has_old_manufacturer = in_array( 'manufacturer', $columns, true );
        $has_old_ingredient   = in_array( 'active_ingredient', $columns, true );

        $results = [];

        if ( ! $has_manufacturer_id || ! $has_ingredient_id ) {
            $results['error'] = 'Las columnas normalizadas (manufacturer_id, active_ingredient_id) no existen. Ejecuta create_tables() primero.';
            return $results;
        }

        if ( $has_old_manufacturer ) {
            $this->migrate_remaining_manufacturer_data();
            $this->wpdb->query( "ALTER TABLE {$table} DROP COLUMN manufacturer" );
            $results['manufacturer'] = 'Columna "manufacturer" eliminada exitosamente';
        } else {
            $results['manufacturer'] = 'Columna "manufacturer" ya no existe';
        }

        if ( $has_old_ingredient ) {
            $this->migrate_remaining_ingredient_data();
            $this->wpdb->query( "ALTER TABLE {$table} DROP COLUMN active_ingredient" );
            $results['active_ingredient'] = 'Columna "active_ingredient" eliminada exitosamente';
        } else {
            $results['active_ingredient'] = 'Columna "active_ingredient" ya no existe';
        }

        update_option( 'va_products_columns_cleaned', true );
        $results['status'] = 'Limpieza completada';

        return $results;
    }

    public function cleanup_redundant_columns(): void {
        $option_key = 'va_products_columns_cleaned';
        if ( get_option( $option_key ) ) {
            return;
        }

        $table               = esc_sql( $this->table_name_products );
        $columns             = $this->wpdb->get_col( "DESCRIBE {$table}" );
        $has_manufacturer_id = in_array( 'manufacturer_id', $columns, true );
        $has_ingredient_id   = in_array( 'active_ingredient_id', $columns, true );
        $has_old_manufacturer = in_array( 'manufacturer', $columns, true );
        $has_old_ingredient   = in_array( 'active_ingredient', $columns, true );

        if ( $has_manufacturer_id && $has_ingredient_id ) {
            if ( $has_old_manufacturer ) {
                $this->migrate_remaining_manufacturer_data();
            }
            if ( $has_old_ingredient ) {
                $this->migrate_remaining_ingredient_data();
            }

            if ( $has_old_manufacturer ) {
                $this->wpdb->query( "ALTER TABLE {$table} DROP COLUMN manufacturer" );
                $this->log( "[Veterinalia] Columna redundante 'manufacturer' eliminada de va_products" );
            }

            if ( $has_old_ingredient ) {
                $this->wpdb->query( "ALTER TABLE {$table} DROP COLUMN active_ingredient" );
                $this->log( "[Veterinaria] Columna redundante 'active_ingredient' eliminada de va_products" );
            }

            update_option( $option_key, true );
            $this->log( '[Veterinalia] Limpieza de columnas redundantes completada en va_products' );
        }
    }

    protected function add_foreign_key_constraints(): void {
        $foreign_keys_to_add = [
            [
                'table'      => $this->table_name_form_fields,
                'constraint' => 'fk_form_fields_entry_type',
                'sql'        => "ALTER TABLE {$this->table_name_form_fields}
                         ADD CONSTRAINT fk_form_fields_entry_type
                         FOREIGN KEY (entry_type_id) REFERENCES {$this->table_name_entry_types}(entry_type_id)
                         ON DELETE CASCADE ON UPDATE CASCADE",
            ],
            [
                'table'      => $this->table_name_pet_log_meta,
                'constraint' => 'fk_pet_log_meta_log',
                'sql'        => "ALTER TABLE {$this->table_name_pet_log_meta}
                         ADD CONSTRAINT fk_pet_log_meta_log
                         FOREIGN KEY (log_id) REFERENCES {$this->table_name_pet_logs}(log_id)
                         ON DELETE CASCADE ON UPDATE CASCADE",
            ],
            [
                'table'      => $this->table_name_pet_log_products,
                'constraint' => 'fk_pet_log_products_log',
                'sql'        => "ALTER TABLE {$this->table_name_pet_log_products}
                         ADD CONSTRAINT fk_pet_log_products_log
                         FOREIGN KEY (log_id) REFERENCES {$this->table_name_pet_logs}(log_id)
                         ON DELETE CASCADE ON UPDATE CASCADE",
            ],
            [
                'table'      => $this->table_name_pet_log_products,
                'constraint' => 'fk_pet_log_products_product',
                'sql'        => "ALTER TABLE {$this->table_name_pet_log_products}
                         ADD CONSTRAINT fk_pet_log_products_product
                         FOREIGN KEY (product_id) REFERENCES {$this->table_name_products}(product_id)
                         ON DELETE CASCADE ON UPDATE CASCADE",
            ],
        ];

        foreach ( $foreign_keys_to_add as $fk ) {
            if ( ! $this->foreign_key_exists( $fk['table'], $fk['constraint'] ) ) {
                $this->wpdb->query( $fk['sql'] );
                $this->log( "[Veterinalia] Clave foránea añadida: {$fk['constraint']} en tabla {$fk['table']}" );
            }
        }
    }

    protected function foreign_key_exists( string $table, string $constraint_name ): bool {
        $db_name = $this->wpdb->dbname;

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT CONSTRAINT_NAME
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
                $db_name,
                $table,
                $constraint_name
            )
        );

        return ! empty( $result );
    }

    protected function migrate_manufacturer_data(): void {
        $option_key = 'va_manufacturers_migrated';
        if ( get_option( $option_key ) ) {
            return;
        }

        $products_table      = esc_sql( $this->table_name_products );
        $manufacturers_table = esc_sql( $this->table_name_manufacturers );

        $columns = $this->wpdb->get_col( "DESCRIBE {$products_table}" );
        if ( ! in_array( 'manufacturer', $columns, true ) ) {
            update_option( $option_key, true );
            $this->log( '[Veterinalia] Instalación nueva - no hay fabricantes que migrar' );
            return;
        }

        $unique_manufacturers = $this->wpdb->get_col(
            "SELECT DISTINCT manufacturer
             FROM {$products_table}
             WHERE manufacturer IS NOT NULL AND manufacturer != ''"
        );

        if ( ! empty( $unique_manufacturers ) ) {
            foreach ( $unique_manufacturers as $manufacturer ) {
                $this->wpdb->insert(
                    $manufacturers_table,
                    [ 'manufacturer_name' => $manufacturer ],
                    [ '%s' ]
                );
            }
            $this->log( '[Veterinalia] Migrados ' . count( $unique_manufacturers ) . ' fabricantes únicos' );
        } else {
            $this->log( '[Veterinalia] No se encontraron fabricantes que migrar' );
        }

        update_option( $option_key, true );
    }

    protected function migrate_active_ingredient_data(): void {
        $option_key = 'va_active_ingredients_migrated';
        if ( get_option( $option_key ) ) {
            return;
        }

        $products_table    = esc_sql( $this->table_name_products );
        $ingredients_table = esc_sql( $this->table_name_active_ingredients );

        $columns = $this->wpdb->get_col( "DESCRIBE {$products_table}" );
        if ( ! in_array( 'active_ingredient', $columns, true ) ) {
            update_option( $option_key, true );
            $this->log( '[Veterinalia] Instalación nueva - no hay principios activos que migrar' );
            return;
        }

        $unique_ingredients = $this->wpdb->get_col(
            "SELECT DISTINCT active_ingredient
             FROM {$products_table}
             WHERE active_ingredient IS NOT NULL AND active_ingredient != ''"
        );

        if ( ! empty( $unique_ingredients ) ) {
            foreach ( $unique_ingredients as $ingredient ) {
                $this->wpdb->insert(
                    $ingredients_table,
                    [ 'ingredient_name' => $ingredient ],
                    [ '%s' ]
                );
            }
            $this->log( '[Veterinalia] Migrados ' . count( $unique_ingredients ) . ' principios activos únicos' );
        } else {
            $this->log( '[Veterinalia] No se encontraron principios activos que migrar' );
        }

        update_option( $option_key, true );
    }

    protected function update_products_table_structure(): void {
        $table   = esc_sql( $this->table_name_products );
        $columns = $this->wpdb->get_col( "DESCRIBE {$table}" );

        if ( ! in_array( 'manufacturer_id', $columns, true ) ) {
            $this->wpdb->query( "ALTER TABLE {$table} ADD COLUMN manufacturer_id BIGINT(20) DEFAULT NULL" );
            $this->wpdb->query( "ALTER TABLE {$table} ADD KEY idx_manufacturer_id (manufacturer_id)" );
        }

        if ( ! in_array( 'active_ingredient_id', $columns, true ) ) {
            $this->wpdb->query( "ALTER TABLE {$table} ADD COLUMN active_ingredient_id BIGINT(20) DEFAULT NULL" );
            $this->wpdb->query( "ALTER TABLE {$table} ADD KEY idx_active_ingredient_id (active_ingredient_id)" );
        }

        $this->populate_normalized_product_references();

        if ( ! $this->foreign_key_exists( $table, 'fk_products_manufacturer' ) ) {
            $this->wpdb->query(
                "ALTER TABLE {$table}
                         ADD CONSTRAINT fk_products_manufacturer
                         FOREIGN KEY (manufacturer_id) REFERENCES {$this->table_name_manufacturers}(manufacturer_id)
                         ON DELETE SET NULL ON UPDATE CASCADE"
            );
        }

        if ( ! $this->foreign_key_exists( $table, 'fk_products_ingredient' ) ) {
            $this->wpdb->query(
                "ALTER TABLE {$table}
                         ADD CONSTRAINT fk_products_ingredient
                         FOREIGN KEY (active_ingredient_id) REFERENCES {$this->table_name_active_ingredients}(ingredient_id)
                         ON DELETE SET NULL ON UPDATE CASCADE"
            );
        }

        if ( ! $this->foreign_key_exists( $table, 'fk_products_professional' ) ) {
            $this->add_professional_fk_safely( $table );
        }
    }

    protected function populate_normalized_product_references(): void {
        $products_table      = esc_sql( $this->table_name_products );
        $manufacturers_table = esc_sql( $this->table_name_manufacturers );
        $ingredients_table   = esc_sql( $this->table_name_active_ingredients );

        $columns             = $this->wpdb->get_col( "DESCRIBE {$products_table}" );
        $has_old_manufacturer = in_array( 'manufacturer', $columns, true );
        $has_old_ingredient   = in_array( 'active_ingredient', $columns, true );

        if ( $has_old_manufacturer ) {
            $this->wpdb->query(
                "
                UPDATE {$products_table} p
                JOIN {$manufacturers_table} m ON p.manufacturer = m.manufacturer_name
                SET p.manufacturer_id = m.manufacturer_id
                WHERE p.manufacturer_id IS NULL AND p.manufacturer IS NOT NULL
            "
            );
            $this->log( '[Veterinalia] Migración manufacturer → manufacturer_id completada' );
        }

        if ( $has_old_ingredient ) {
            $this->wpdb->query(
                "
                UPDATE {$products_table} p
                JOIN {$ingredients_table} i ON p.active_ingredient = i.ingredient_name
                SET p.active_ingredient_id = i.ingredient_id
                WHERE p.active_ingredient_id IS NULL AND p.active_ingredient IS NOT NULL
            "
            );
            $this->log( '[Veterinalia] Migración active_ingredient → active_ingredient_id completada' );
        }

        if ( ! $has_old_manufacturer && ! $has_old_ingredient ) {
            $this->log( '[Veterinalia] Instalación nueva detectada - no hay datos que migrar' );
        }
    }

    protected function add_professional_fk_safely( string $table ): void {
        $users_table_info    = $this->wpdb->get_row( "SHOW CREATE TABLE {$this->wpdb->users}", ARRAY_A );
        $products_table_info = $this->wpdb->get_row( "SHOW CREATE TABLE {$table}", ARRAY_A );

        if ( ! $users_table_info || ! $products_table_info ) {
            $this->log( '[Veterinalia] No se pudo verificar estructura de tablas para FK professional_id' );
            return;
        }

        $original_suppress = $this->wpdb->suppress_errors( true );
        $original_show     = $this->wpdb->show_errors;
        $this->wpdb->show_errors( false );

        try {
            $sql = "ALTER TABLE {$table}
                    ADD CONSTRAINT fk_products_professional
                    FOREIGN KEY (professional_id) REFERENCES {$this->wpdb->users}(ID)
                    ON DELETE CASCADE ON UPDATE CASCADE";

            $result = $this->wpdb->query( $sql );

            if ( $result !== false && empty( $this->wpdb->last_error ) ) {
                $this->log( '[Veterinaria] FK professional_id añadida exitosamente' );
            } else {
                $this->create_professional_check_constraint( $table );
            }
        } catch ( \Exception $e ) {
            $this->log( '[Veterinaria] Error creando FK professional_id' );
            $this->create_professional_check_constraint( $table );
        } finally {
            $this->wpdb->suppress_errors( $original_suppress );
            $this->wpdb->show_errors = $original_show;
        }
    }

    protected function create_professional_check_constraint( string $table ): void {
        $this->log( '[Veterinaria] FK professional_id no compatible - usando validación alternativa' );

        $indexes      = $this->wpdb->get_results( "SHOW INDEX FROM {$table}" );
        $index_exists = false;

        foreach ( $indexes as $index ) {
            if ( $index->Key_name === 'idx_professional_validation' ) {
                $index_exists = true;
                break;
            }
        }

        if ( ! $index_exists ) {
            $original_suppress = $this->wpdb->suppress_errors( true );
            $original_show     = $this->wpdb->show_errors;
            $this->wpdb->show_errors( false );

            $this->wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_professional_validation (professional_id)" );

            $this->wpdb->suppress_errors( $original_suppress );
            $this->wpdb->show_errors = $original_show;
        }

        update_option( 'va_professional_validation_required', true );
    }

    protected function migrate_remaining_manufacturer_data(): void {
        $products_table      = esc_sql( $this->table_name_products );
        $products_to_migrate = $this->wpdb->get_results(
            "
            SELECT product_id, manufacturer
            FROM {$products_table}
            WHERE manufacturer IS NOT NULL
              AND manufacturer != ''
              AND manufacturer_id IS NULL
        "
        );

        foreach ( $products_to_migrate as $product ) {
            $manufacturer_id = $this->product_repository->create_or_get_manufacturer( $product->manufacturer );
            if ( $manufacturer_id ) {
                $this->wpdb->update(
                    $products_table,
                    [ 'manufacturer_id' => $manufacturer_id ],
                    [ 'product_id' => $product->product_id ]
                );
            }
        }
    }

    protected function migrate_remaining_ingredient_data(): void {
        $products_table      = esc_sql( $this->table_name_products );
        $products_to_migrate = $this->wpdb->get_results(
            "
            SELECT product_id, active_ingredient
            FROM {$products_table}
            WHERE active_ingredient IS NOT NULL
              AND active_ingredient != ''
              AND active_ingredient_id IS NULL
        "
        );

        foreach ( $products_to_migrate as $product ) {
            $ingredient_id = $this->product_repository->create_or_get_active_ingredient( $product->active_ingredient );
            if ( $ingredient_id ) {
                $this->wpdb->update(
                    $products_table,
                    [ 'active_ingredient_id' => $ingredient_id ],
                    [ 'product_id' => $product->product_id ]
                );
            }
        }
    }

    protected function log( string $message ): void {
        if ( $this->logger ) {
            ( $this->logger )( $message );
        }
    }
}
