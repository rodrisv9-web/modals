<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VA_CRM_Migration_Runner extends VA_Base_Repository {
    /**
     * @var array<string,string>
     */
    protected $table_names = [];

    /**
     * @var callable|null
     */
    protected $logger;

    /**
     * @var VA_CRM_Product_Migration_Service|null
     */
    protected $product_migration_service;

    public function __construct( \wpdb $wpdb, array $table_names = [], ?VA_CRM_Product_Migration_Service $product_migration_service = null, ?callable $logger = null ) {
        parent::__construct( $wpdb );
        $this->table_names               = $table_names ?: $this->build_default_table_names( $wpdb );
        $this->product_migration_service = $product_migration_service;
        $this->set_logger( $logger );
    }

    public function set_table_names( array $table_names ): void {
        $this->table_names = array_merge( $this->table_names, $table_names );
    }

    public function set_logger( ?callable $logger ): void {
        $this->logger = $logger ? \Closure::fromCallable( $logger ) : null;
    }

    public function set_product_migration_service( VA_CRM_Product_Migration_Service $service ): void {
        $this->product_migration_service = $service;
    }

    public function run_all(): void {
        if ( $this->product_migration_service instanceof VA_CRM_Product_Migration_Service ) {
            $this->product_migration_service->apply_normalization_improvements();
            $this->ensure_version_migration();
        }

        $this->maybe_populate_sample_patients_data();
        $this->migrate_existing_client_data();
        $this->maybe_populate_entry_types();
        $this->maybe_populate_form_fields();
    }

    protected function build_default_table_names( \wpdb $wpdb ): array {
        $prefix = $wpdb->prefix;

        return [
            'clients'            => $prefix . 'va_clients',
            'pets'               => $prefix . 'va_pets',
            'pet_access'         => $prefix . 'va_pet_access',
            'pet_logs'           => $prefix . 'va_pet_logs',
            'entry_types'        => $prefix . 'va_entry_types',
            'form_fields'        => $prefix . 'va_form_fields',
            'pet_log_meta'       => $prefix . 'va_pet_log_meta',
            'products'           => $prefix . 'va_products',
            'pet_log_products'   => $prefix . 'va_pet_log_products',
            'manufacturers'      => $prefix . 'va_manufacturers',
            'active_ingredients' => $prefix . 'va_active_ingredients',
        ];
    }

    private function table( string $key ): string {
        return esc_sql( $this->table_names[ $key ] ?? '' );
    }

    private function log( string $message ): void {
        if ( $this->logger ) {
            ( $this->logger )( $message );
        } else {
            error_log( $message );
        }
    }

    private function maybe_populate_sample_patients_data(): void {
        if ( get_option( 'va_sample_patients_populated' ) ) {
            return;
        }

        $clients_table = $this->table( 'clients' );

        $existing_clients = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$clients_table}" );
        if ( $existing_clients > 0 ) {
            update_option( 'va_sample_patients_populated', true );
            return;
        }

        $pets_table   = $this->table( 'pets' );
        $access_table = $this->table( 'pet_access' );
        $logs_table   = $this->table( 'pet_logs' );

        try {
            $this->wpdb->query( 'START TRANSACTION' );

            $sample_clients = [
                ['name' => 'Ana García Martínez', 'email' => 'ana.garcia@ejemplo.com', 'phone' => '+1 234 567 8901'],
                ['name' => 'Carlos López Ruiz', 'email' => 'carlos.lopez@ejemplo.com', 'phone' => '+1 234 567 8902'],
                ['name' => 'María Fernández Silva', 'email' => 'maria.fernandez@ejemplo.com', 'phone' => '+1 234 567 8903'],
                ['name' => 'José Rodríguez Torres', 'email' => 'jose.rodriguez@ejemplo.com', 'phone' => '+1 234 567 8904'],
                ['name' => 'Laura Martín Gómez', 'email' => 'laura.martin@ejemplo.com', 'phone' => '+1 234 567 8905'],
            ];

            $client_ids = [];

            foreach ( $sample_clients as $client ) {
                $result = $this->wpdb->insert(
                    $clients_table,
                    [
                        'name'         => $client['name'],
                        'email'        => $client['email'],
                        'phone'        => $client['phone'],
                        'date_created' => current_time( 'mysql' ),
                    ],
                    ['%s', '%s', '%s', '%s']
                );

                if ( false !== $result ) {
                    $client_ids[] = $this->wpdb->insert_id;
                }
            }

            $sample_pets = [
                ['client_idx' => 0, 'name' => 'Luna', 'species' => 'dog', 'breed' => 'Golden Retriever', 'share_code' => 'LUNA-G7K4'],
                ['client_idx' => 0, 'name' => 'Max', 'species' => 'cat', 'breed' => 'Persa', 'share_code' => 'MAX-H2L9'],
                ['client_idx' => 1, 'name' => 'Rocky', 'species' => 'dog', 'breed' => 'Pastor Alemán', 'share_code' => 'ROCKY-A1B3'],
                ['client_idx' => 2, 'name' => 'Mimi', 'species' => 'cat', 'breed' => 'Siamés', 'share_code' => 'MIMI-X9Y8'],
                ['client_idx' => 2, 'name' => 'Toby', 'species' => 'dog', 'breed' => 'Labrador', 'share_code' => 'TOBY-K5M7'],
                ['client_idx' => 3, 'name' => 'Bella', 'species' => 'dog', 'breed' => 'Bulldog Francés', 'share_code' => 'BELLA-R3T5'],
                ['client_idx' => 4, 'name' => 'Coco', 'species' => 'bird', 'breed' => 'Canario', 'share_code' => 'COCO-P8Q2'],
            ];

            $pet_ids = [];

            foreach ( $sample_pets as $pet ) {
                if ( isset( $client_ids[ $pet['client_idx'] ] ) ) {
                    $result = $this->wpdb->insert(
                        $pets_table,
                        [
                            'client_id'    => $client_ids[ $pet['client_idx'] ],
                            'name'         => $pet['name'],
                            'species'      => $pet['species'],
                            'breed'        => $pet['breed'],
                            'share_code'   => $pet['share_code'],
                            'date_created' => current_time( 'mysql' ),
                        ],
                        ['%d', '%s', '%s', '%s', '%s', '%s']
                    );

                    if ( false !== $result ) {
                        $pet_ids[] = $this->wpdb->insert_id;
                    }
                }
            }

            $admin_users     = get_users( ['role' => 'administrator', 'number' => 1] );
            $professional_id = ! empty( $admin_users ) ? $admin_users[0]->ID : 1;

            foreach ( array_slice( $pet_ids, 0, 3 ) as $pet_id ) {
                $this->wpdb->insert(
                    $access_table,
                    [
                        'pet_id'        => $pet_id,
                        'professional_id'=> $professional_id,
                        'access_level'  => 'full',
                        'granted_by'    => $professional_id,
                        'date_granted'  => current_time( 'mysql' ),
                    ],
                    ['%d', '%d', '%s', '%d', '%s']
                );
            }

            if ( ! empty( $pet_ids ) ) {
                $sample_logs = [
                    [
                        'pet_id'      => $pet_ids[0],
                        'title'       => 'Vacunación anual',
                        'entry_type'  => 'vaccination',
                        'description' => 'Aplicación de vacuna polivalente anual',
                        'entry_date'  => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
                    ],
                    [
                        'pet_id'      => $pet_ids[0],
                        'title'       => 'Revisión general',
                        'entry_type'  => 'consultation',
                        'description' => 'Checkup de rutina. Todo en orden.',
                        'entry_date'  => date( 'Y-m-d H:i:s', strtotime( '-15 days' ) ),
                    ],
                    [
                        'pet_id'      => $pet_ids[1],
                        'title'       => 'Esterilización',
                        'entry_type'  => 'surgery',
                        'description' => 'Procedimiento de esterilización exitoso',
                        'entry_date'  => date( 'Y-m-d H:i:s', strtotime( '-45 days' ) ),
                    ],
                ];

                foreach ( $sample_logs as $log ) {
                    $this->wpdb->insert(
                        $logs_table,
                        [
                            'pet_id'        => $log['pet_id'],
                            'professional_id'=> $professional_id,
                            'entry_type'    => $log['entry_type'],
                            'entry_date'    => $log['entry_date'],
                            'title'         => $log['title'],
                            'description'   => $log['description'],
                            'date_created'  => current_time( 'mysql' ),
                        ],
                        ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
                    );
                }
            }

            $this->wpdb->query( 'COMMIT' );

            update_option( 'va_sample_patients_populated', true );
            $this->log( '[Veterinalia CRM] Datos de prueba poblados exitosamente' );
        } catch ( Exception $e ) {
            $this->wpdb->query( 'ROLLBACK' );
            $this->log( '[Veterinalia CRM] Error poblando datos de prueba: ' . $e->getMessage() );
        }
    }

    private function migrate_existing_client_data(): void {
        $clients_table = $this->table( 'clients' );

        $clients_without_creator = $this->wpdb->get_var( "
            SELECT COUNT(*)
            FROM {$clients_table}
            WHERE created_by_professional IS NULL
        " );

        if ( $clients_without_creator > 0 ) {
            $admin_id = $this->wpdb->get_var( "
                SELECT ID 
                FROM {$this->wpdb->users} u
                INNER JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = 'wp_capabilities'
                AND um.meta_value LIKE '%administrator%'
                LIMIT 1
            " );

            if ( $admin_id ) {
                $updated = $this->wpdb->query( $this->wpdb->prepare( "
                    UPDATE {$clients_table} 
                    SET created_by_professional = %d
                    WHERE created_by_professional IS NULL
                ", intval( $admin_id ) ) );

                if ( $updated ) {
                    $this->log( "[Veterinalia DB] Migrados {$updated} clientes existentes al admin ID: {$admin_id}" );
                }
            }
        }
    }

    private function maybe_populate_entry_types(): void {
        $table = $this->table( 'entry_types' );
        $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM " . $table );

        if ( intval( $count ) === 0 ) {
            $types = [
                ['name' => 'Consulta', 'slug' => 'consultation', 'icon' => 'ph-stethoscope'],
                ['name' => 'Seguimiento / Control', 'slug' => 'follow_up', 'icon' => 'ph-clipboard-text'],
                ['name' => 'Urgencias', 'slug' => 'emergency', 'icon' => 'ph-heartbeat'],
                ['name' => 'Vacunación', 'slug' => 'vaccination', 'icon' => 'ph-syringe'],
                ['name' => 'Desparasitación', 'slug' => 'deworming', 'icon' => 'ph-bug'],
                ['name' => 'Control de Ectoparásitos', 'slug' => 'parasite_control', 'icon' => 'ph-shield-check'],
                ['name' => 'Laboratorio', 'slug' => 'lab_test', 'icon' => 'ph-test-tube'],
                ['name' => 'Imagenología', 'slug' => 'imaging', 'icon' => 'ph-camera'],
                ['name' => 'Cirugía', 'slug' => 'surgery', 'icon' => 'ph-first-aid-kit'],
                ['name' => 'Hospitalización', 'slug' => 'hospitalization', 'icon' => 'ph-bed'],
                ['name' => 'Odontología', 'slug' => 'dental', 'icon' => 'ph-tooth'],
                ['name' => 'Estética / Grooming', 'slug' => 'grooming', 'icon' => 'ph-scissors'],
                ['name' => 'Paseo', 'slug' => 'walking', 'icon' => 'ph-footprints'],
                ['name' => 'Pensión / Guardería', 'slug' => 'boarding', 'icon' => 'ph-house'],
                ['name' => 'Cremación', 'slug' => 'cremation', 'icon' => 'ph-fire'],
                ['name' => 'Reproductivo', 'slug' => 'reproductive', 'icon' => 'ph-gender-intersex'],
                ['name' => 'Microchip / Identificación', 'slug' => 'microchip', 'icon' => 'ph-qr-code'],
                ['name' => 'Nutrición', 'slug' => 'nutrition', 'icon' => 'ph-bowl-food'],
                ['name' => 'Conducta', 'slug' => 'behavior', 'icon' => 'ph-chats-circle'],
                ['name' => 'Fisioterapia', 'slug' => 'physiotherapy', 'icon' => 'ph-person-simple-walk'],
                ['name' => 'Otro', 'slug' => 'other', 'icon' => 'ph-paw-print'],
            ];

            foreach ( $types as $type ) {
                $this->wpdb->insert( $table, $type );
            }
        }
    }

    private function maybe_populate_form_fields(): void {
        $table_fields = $this->table( 'form_fields' );
        $table_types  = $this->table( 'entry_types' );

        $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM " . $table_fields );
        if ( intval( $count ) > 0 ) {
            return;
        }

        $entry_types_q = $this->wpdb->get_results( "SELECT entry_type_id, slug FROM " . $table_types, OBJECT_K );
        if ( empty( $entry_types_q ) ) {
            return;
        }

        $entry_types = [];
        foreach ( $entry_types_q as $slug => $data ) {
            $entry_types[ $slug ] = $data->entry_type_id;
        }

        $forms_structure = [
            'consultation'      => ['Motivo de la Consulta', 'Diagnóstico Diferencial', 'Tratamiento Indicado', 'Notas Adicionales'],
            'follow_up'         => ['Motivo del Control', 'Evolución del Paciente', 'Ajustes al Tratamiento', 'Próximas Acciones'],
            'emergency'         => ['Evaluación Inicial (Triage)', 'Maniobras de Estabilización', 'Plan de Acción Inmediato', 'Notas de Urgencia'],
            'vaccination'       => ['Biológico Aplicado', 'Número de Lote', 'Próxima Dosis (Fecha)'],
            'deworming'         => ['Producto Administrado', 'Vía de Administración', 'Próxima Dosis (Fecha)'],
            'parasite_control'  => ['Producto Aplicado', 'Zona de Aplicación', 'Próxima Aplicación (Fecha)'],
            'lab_test'          => ['Tipo de Prueba Realizada', 'Resumen de Resultados', 'Archivos Adjuntos', 'Interpretación y Notas'],
            'imaging'           => ['Estudio Realizado (RX, ECO, etc.)', 'Hallazgos Relevantes', 'Archivos Adjuntos', 'Conclusiones'],
            'surgery'           => ['Procedimiento Quirúrgico', 'Protocolo Anestésico', 'Complicaciones (Si hubo)', 'Indicaciones Postoperatorias'],
            'hospitalization'   => ['Motivo de Ingreso', 'Terapias Durante Hospitalización', 'Evolución Diaria', 'Plan de Alta Médica'],
            'dental'            => ['Procedimiento Dental Realizado', 'Hallazgos en Cavidad Oral', 'Recomendaciones de Cuidado', 'Notas'],
            'grooming'          => ['Servicio de Estética Realizado', 'Productos Utilizados', 'Observaciones de Comportamiento', 'Recomendaciones'],
            'walking'           => ['Duración del Paseo (minutos)', 'Ruta o Zona del Paseo', 'Comportamiento Social', 'Incidencias u Observaciones'],
            'boarding'          => ['Fecha de Ingreso y Egreso', 'Indicaciones de Alimentación', 'Medicación Administrada', 'Observaciones Generales'],
            'cremation'         => ['Tipo de Cremación', 'Proveedor del Servicio', 'Notas sobre Entrega de Cenizas'],
            'reproductive'      => ['Evento Reproductivo', 'Fecha del Evento', 'Método Utilizado', 'Observaciones y Seguimiento'],
            'microchip'         => ['Código del Microchip Implantado', 'Sitio de Implante', 'Fecha de Registro'],
            'nutrition'         => ['Tipo de Dieta Indicada', 'Cálculo Calórico (kcal/día)', 'Metas de Condición Corporal', 'Notas de Seguimiento'],
            'behavior'          => ['Problema Conductual Identificado', 'Evaluación Inicial', 'Plan de Modificación de Conducta', 'Seguimiento y Ajustes'],
            'physiotherapy'     => ['Técnica de Fisioterapia Aplicada', 'Número de Sesiones', 'Evolución del Paciente', 'Plan a Seguir en Casa'],
            'other'             => ['Descripción del Servicio', 'Detalles Relevantes', 'Observaciones Adicionales', 'Notas'],
        ];

        foreach ( $forms_structure as $slug => $fields ) {
            if ( ! isset( $entry_types[ $slug ] ) ) {
                continue;
            }

            $entry_type_id = (int) $entry_types[ $slug ];
            $order         = 1;

            foreach ( $fields as $label ) {
                $field_type         = 'textarea';
                $product_filter     = null;

                if ( stripos( $label, 'Biológico' ) !== false || stripos( $label, 'Producto' ) !== false ) {
                    $field_type = 'product_selector';
                    if ( $slug === 'vaccination' ) {
                        $product_filter = 'Vacuna';
                    }
                    if ( $slug === 'deworming' || $slug === 'parasite_control' ) {
                        $product_filter = 'Desparasitante';
                    }
                } elseif ( stripos( $label, 'Próxima' ) !== false ) {
                    $field_type = 'next_appointment';
                } elseif ( stripos( $label, 'Fecha' ) !== false ) {
                    $field_type = 'date';
                } elseif ( stripos( $label, 'Número de Lote' ) !== false || stripos( $label, 'Código' ) !== false || stripos( $label, 'Duración' ) !== false ) {
                    $field_type = 'text';
                }

                $this->wpdb->insert(
                    $table_fields,
                    [
                        'entry_type_id'        => $entry_type_id,
                        'field_key'            => sanitize_key( substr( $slug . '_' . $label, 0, 50 ) ),
                        'field_label'          => $label,
                        'field_type'           => $field_type,
                        'product_filter_type'  => $product_filter,
                        'is_required'          => 0,
                        'display_order'        => $order,
                    ]
                );

                $order++;
            }

            $this->wpdb->insert(
                $table_fields,
                [
                    'entry_type_id' => $entry_type_id,
                    'field_key'     => $slug . '_next_appointment',
                    'field_label'   => 'Agendar Próxima Cita',
                    'field_type'    => 'next_appointment',
                    'is_required'   => 0,
                    'display_order' => $order,
                ]
            );
        }
    }

    private function ensure_version_migration(): void {
        $current_version = get_option( 'va_database_version', '0.0.0' );
        $target_version  = '1.0.9.1';

        if ( version_compare( $current_version, $target_version, '<' ) && $this->product_migration_service instanceof VA_CRM_Product_Migration_Service ) {
            $this->product_migration_service->force_cleanup_redundant_columns();
            $this->product_migration_service->apply_structure_improvements();
            $this->product_migration_service->apply_product_type_enum_update();
            $this->product_migration_service->apply_form_fields_enum_update();

            update_option( 'va_database_version', $target_version );
            $this->log( "[Veterinalia] Base de datos migrada a versión {$target_version}" );
        }
    }
}
