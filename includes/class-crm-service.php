<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class VA_CRM_Service {

    /** @var self|null */
    private static $instance = null;

    /** @var VA_Repository_Factory */
    private $factory;

    /** @var array<string,string> */
    private $table_names = [];

    /** @var bool */
    private $silent_mode = false;

    /** @var array */
    private $original_wpdb_settings = [];

    /** @var VA_CRM_Client_Repository_Interface */
    private $client_repository;

    /** @var VA_CRM_Pet_Repository_Interface */
    private $pet_repository;

    /** @var VA_CRM_Pet_Log_Repository_Interface */
    private $pet_log_repository;

    /** @var VA_CRM_Product_Repository_Interface */
    private $product_repository;

    /** @var VA_CRM_Form_Repository_Interface */
    private $form_repository;

    /** @var VA_CRM_Product_Migration_Service */
    private $product_migration_service;

    /** @var VA_CRM_Table_Installer */
    private $table_installer;

    /** @var VA_CRM_Migration_Runner */
    private $migration_runner;

    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        $prefix = $wpdb->prefix;
        $this->table_names = [
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

        $this->silent_mode = defined( 'VA_PLUGIN_ACTIVATING' ) && VA_PLUGIN_ACTIVATING;
        $this->factory     = VA_Repository_Factory::boot();

        $this->client_repository          = $this->resolve_client_repository();
        $this->pet_repository             = $this->resolve_pet_repository();
        $this->pet_log_repository         = $this->resolve_pet_log_repository();
        $this->product_repository         = $this->resolve_product_repository();
        $this->form_repository            = $this->resolve_form_repository();
        $this->product_migration_service  = $this->create_product_migration_service();
        $this->table_installer            = $this->resolve_table_installer();
        $this->migration_runner           = $this->resolve_migration_runner();
    }

    private function resolve_client_repository(): VA_CRM_Client_Repository_Interface {
        $repository = $this->factory->get( 'crm.client' );

        if ( ! $repository instanceof VA_CRM_Client_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_CRM_Client_Repository( $wpdb );
            $this->factory->bind( 'crm.client', $repository );
        }

        return $repository;
    }

    private function resolve_pet_repository(): VA_CRM_Pet_Repository_Interface {
        $repository = $this->factory->get( 'crm.pet' );

        if ( ! $repository instanceof VA_CRM_Pet_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_CRM_Pet_Repository( $wpdb );
            $this->factory->bind( 'crm.pet', $repository );
        }

        return $repository;
    }

    private function resolve_pet_log_repository(): VA_CRM_Pet_Log_Repository_Interface {
        $repository = $this->factory->get( 'crm.pet_log' );

        if ( ! $repository instanceof VA_CRM_Pet_Log_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_CRM_Pet_Log_Repository( $wpdb );
            $this->factory->bind( 'crm.pet_log', $repository );
        }

        return $repository;
    }

    private function resolve_product_repository(): VA_CRM_Product_Repository_Interface {
        $repository = $this->factory->get( 'crm.product' );

        if ( ! $repository instanceof VA_CRM_Product_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_CRM_Product_Repository( $wpdb );
            $this->factory->bind( 'crm.product', $repository );
        }

        return $repository;
    }

    private function resolve_form_repository(): VA_CRM_Form_Repository_Interface {
        $repository = $this->factory->get( 'crm.forms' );

        if ( ! $repository instanceof VA_CRM_Form_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_CRM_Form_Repository( $wpdb );
            $this->factory->bind( 'crm.forms', $repository );
        }

        return $repository;
    }

    private function resolve_table_installer(): VA_CRM_Table_Installer {
        $service = $this->factory->get( 'crm.table_installer' );

        if ( ! $service instanceof VA_CRM_Table_Installer ) {
            global $wpdb;
            $service = new VA_CRM_Table_Installer(
                $wpdb,
                $this->get_table_name_map(),
                function ( $message ) {
                    $this->log_message( $message );
                }
            );
            $this->factory->bind( 'crm.table_installer', $service );
        }

        $service->set_table_names( $this->get_table_name_map() );
        $service->set_logger(
            function ( $message ) {
                $this->log_message( $message );
            }
        );

        return $service;
    }

    private function resolve_migration_runner(): VA_CRM_Migration_Runner {
        $runner = $this->factory->get( 'crm.migration_runner' );

        if ( ! $runner instanceof VA_CRM_Migration_Runner ) {
            global $wpdb;
            $runner = new VA_CRM_Migration_Runner(
                $wpdb,
                $this->get_table_name_map(),
                $this->product_migration_service,
                function ( $message ) {
                    $this->log_message( $message );
                }
            );
            $this->factory->bind( 'crm.migration_runner', $runner );
        }

        $runner->set_table_names( $this->get_table_name_map() );
        $runner->set_product_migration_service( $this->product_migration_service );
        $runner->set_logger(
            function ( $message ) {
                $this->log_message( $message );
            }
        );

        return $runner;
    }

    private function create_product_migration_service(): VA_CRM_Product_Migration_Service {
        global $wpdb;

        return new VA_CRM_Product_Migration_Service(
            $wpdb,
            $this->get_table_name_map(),
            $this->product_repository,
            function ( $message ) {
                $this->log_message( $message );
            }
        );
    }

    private function get_table_name_map(): array {
        return $this->table_names;
    }

    public function clients(): VA_CRM_Client_Repository_Interface {
        return $this->client_repository;
    }

    public function pets(): VA_CRM_Pet_Repository_Interface {
        return $this->pet_repository;
    }

    public function pet_logs(): VA_CRM_Pet_Log_Repository_Interface {
        return $this->pet_log_repository;
    }

    public function products(): VA_CRM_Product_Repository_Interface {
        return $this->product_repository;
    }

    public function forms(): VA_CRM_Form_Repository_Interface {
        return $this->form_repository;
    }

    public function table_installer(): VA_CRM_Table_Installer {
        return $this->table_installer;
    }

    public function migration_runner(): VA_CRM_Migration_Runner {
        return $this->migration_runner;
    }

    public function product_migrations(): VA_CRM_Product_Migration_Service {
        return $this->product_migration_service;
    }

    public function create_tables(): void {
        if ( $this->silent_mode ) {
            $this->setup_silent_sql_mode();
        }

        try {
            $this->table_installer->set_table_names( $this->get_table_name_map() );
            $this->table_installer->set_logger(
                function ( $message ) {
                    $this->log_message( $message );
                }
            );
            $this->table_installer->create_all();

            $this->migration_runner->set_table_names( $this->get_table_name_map() );
            $this->migration_runner->set_product_migration_service( $this->product_migration_service );
            $this->migration_runner->set_logger(
                function ( $message ) {
                    $this->log_message( $message );
                }
            );
            $this->migration_runner->run_all();
        } finally {
            if ( $this->silent_mode ) {
                $this->restore_sql_mode();
            }
        }
    }

    public function ensure_client_for_user( int $user_id ) {
        $user_id = intval( $user_id );

        if ( $user_id <= 0 ) {
            return null;
        }

        $client = $this->client_repository->get_client_by_user_id( $user_id );
        if ( $client ) {
            return $client;
        }

        $user_type = get_user_meta( $user_id, '_user_type', true );
        if ( 'author' === $user_type || ( ! empty( $user_type ) && 'general' !== $user_type ) ) {
            return null;
        }

        $wp_user = get_user_by( 'id', $user_id );
        if ( ! $wp_user ) {
            return null;
        }

        $client_data = [
            'user_id' => $user_id,
            'name'    => $wp_user->display_name ?: $wp_user->user_login,
            'email'   => $wp_user->user_email,
            'phone'   => get_user_meta( $user_id, 'phone', true ),
            'notes'   => 'Cliente creado automáticamente por VA_CRM_Service::ensure_client_for_user',
        ];

        $client_id = $this->client_repository->create_client( $client_data );
        if ( ! $client_id ) {
            return null;
        }

        return $this->client_repository->get_client_by_id( $client_id );
    }

    public function create_client( $client_data ) {
        return $this->client_repository->create_client( (array) $client_data );
    }

    public function create_guest_client( $client_data ) {
        return $this->client_repository->create_guest_client( (array) $client_data );
    }

    public function get_clients_by_professional( $professional_id ) {
        return $this->client_repository->get_clients_by_professional( (int) $professional_id );
    }

    public function get_client_by_id( $client_id ) {
        return $this->client_repository->get_client_by_id( (int) $client_id );
    }

    public function get_client_by_email( $email ) {
        return $this->client_repository->get_client_by_email( (string) $email );
    }

    public function link_client_to_user( $client_id, $user_id ) {
        return $this->client_repository->link_client_to_user( (int) $client_id, (int) $user_id );
    }

    public function search_clients_with_access_check( $term, $professional_id ) {
        return $this->client_repository->search_clients_with_access_check( (string) $term, (int) $professional_id );
    }

    public function search_clients_basic( $term ) {
        return $this->client_repository->search_clients_basic( (string) $term );
    }

    public function create_pet( $pet_data ) {
        return $this->pet_repository->create_pet( (array) $pet_data );
    }

    public function create_pet_with_share_code( $pet_data ) {
        return $this->pet_repository->create_pet_with_share_code( (array) $pet_data );
    }

    public function get_pets_by_client( $client_id ) {
        return $this->pet_repository->get_pets_by_client( (int) $client_id );
    }

    public function get_pets_by_client_with_access( $client_id, $professional_id ) {
        return $this->pet_repository->get_pets_by_client_with_access( (int) $client_id, (int) $professional_id );
    }

    public function get_pet_by_share_code( $share_code ) {
        return $this->pet_repository->get_pet_by_share_code( (string) $share_code );
    }

    public function get_pet_by_id( $pet_id ) {
        return $this->pet_repository->get_pet_by_id( (int) $pet_id );
    }

    public function update_pet( $pet_id, $data ) {
        return $this->pet_repository->update_pet( (int) $pet_id, (array) $data );
    }

    public function get_pet_by_name_and_client( $pet_name, $client_id ) {
        return $this->pet_repository->get_pet_by_name_and_client( (string) $pet_name, (int) $client_id );
    }

    public function grant_pet_access( $pet_id, $professional_id, $access_level = 'read' ) {
        return $this->pet_repository->grant_pet_access( (int) $pet_id, (int) $professional_id, (string) $access_level );
    }

    public function check_pet_access( $professional_id, $pet_id ) {
        return $this->pet_repository->check_pet_access( (int) $professional_id, (int) $pet_id );
    }

    public function create_pet_log( $log_data ) {
        return $this->pet_log_repository->create_pet_log( $log_data );
    }

    public function get_pet_logs( $pet_id, $professional_id = null ) {
        return $this->pet_log_repository->get_pet_logs( $pet_id, $professional_id );
    }

    public function get_client_by_user_id( $user_id ) {
        return $this->client_repository->get_client_by_user_id( (int) $user_id );
    }

    public function get_entry_types() {
        return $this->form_repository->get_entry_types();
    }

    public function get_form_fields_by_entry_type( $entry_type_id ) {
        return $this->form_repository->get_form_fields_by_entry_type( (int) $entry_type_id );
    }

    public function get_products_by_professional( $professional_id ) {
        return $this->product_repository->get_products_by_professional( intval( $professional_id ) );
    }

    public function save_product( $product_data ) {
        return $this->product_repository->save_product( $product_data );
    }

    public function delete_product( $product_id, $professional_id ) {
        return $this->product_repository->delete_product( intval( $product_id ), intval( $professional_id ) );
    }

    public function add_pet_log_meta( $log_id, $meta_key, $meta_value ) {
        return $this->pet_log_repository->add_pet_log_meta( $log_id, $meta_key, $meta_value );
    }

    public function add_pet_log_product( $log_id, $product_id, $context_data = [] ) {
        return $this->pet_log_repository->add_pet_log_product( $log_id, $product_id, $context_data );
    }

    public function get_pet_logs_full( $pet_id, $professional_id = null ) {
        return $this->pet_log_repository->get_pet_logs_full( $pet_id, $professional_id );
    }

    public function get_manufacturers() {
        return $this->product_repository->get_manufacturers();
    }

    public function get_active_ingredients() {
        return $this->product_repository->get_active_ingredients();
    }

    public function create_or_get_manufacturer( $manufacturer_name, $additional_data = [] ) {
        return $this->product_repository->create_or_get_manufacturer( $manufacturer_name, $additional_data );
    }

    public function create_or_get_active_ingredient( $ingredient_name, $additional_data = [] ) {
        return $this->product_repository->create_or_get_active_ingredient( $ingredient_name, $additional_data );
    }

    public function get_products_full( $professional_id ) {
        return $this->product_repository->get_products_full( intval( $professional_id ) );
    }

    private function setup_silent_sql_mode() {
        global $wpdb;

        $this->original_wpdb_settings = [
            'suppress_errors' => $wpdb->suppress_errors(),
            'show_errors'     => $wpdb->show_errors,
            'print_error'     => isset( $wpdb->print_error ) ? $wpdb->print_error : true,
        ];

        $wpdb->suppress_errors( true );
        $wpdb->show_errors = false;
        if ( isset( $wpdb->print_error ) ) {
            $wpdb->print_error = false;
        }
    }

    private function restore_sql_mode() {
        global $wpdb;

        if ( $this->original_wpdb_settings ) {
            $wpdb->suppress_errors( $this->original_wpdb_settings['suppress_errors'] );
            $wpdb->show_errors = $this->original_wpdb_settings['show_errors'];
            if ( isset( $wpdb->print_error ) ) {
                $wpdb->print_error = $this->original_wpdb_settings['print_error'];
            }
        }
    }

    private function log_message( $message ) {
        if ( ! $this->silent_mode ) {
            error_log( $message );
        }
    }

    public function force_cleanup_redundant_columns() {
        return $this->product_migration_service->force_cleanup_redundant_columns();
    }
}
