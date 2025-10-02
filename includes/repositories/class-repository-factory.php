<?php
require_once VA_PLUGIN_DIR . '/includes/repositories/interfaces/interface-repository-factory.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-availability-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-availability-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-appointment-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-appointment-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-client-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-client-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-log-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-pet-log-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-form-repository-interface.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-form-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/class-product-migration-service.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/tables/class-crm-table-installer.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/crm/tables/class-crm-migration-runner.php';

class VA_Repository_Factory implements VA_Repository_Factory_Interface {
    /** @var self|null */
    protected static $instance = null;

    /** @var \wpdb */
    protected $wpdb;

    /** @var array<string,mixed> */
    protected $repositories = [];

    private function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
    }

    public static function boot(): self {
        global $wpdb;

        if ( null === self::$instance ) {
            self::$instance = new self( $wpdb );
            self::$instance->register_default_bindings();
        }

        return self::$instance;
    }

    public static function instance(): self {
        return self::boot();
    }

    public function bind( string $repository_key, $repository_instance ): void {
        $this->repositories[ $repository_key ] = $repository_instance;
    }

    public function get( string $repository_key ) {
        return $this->repositories[ $repository_key ] ?? null;
    }

    protected function register_default_bindings(): void {
        $table_names = [
            'clients'            => $this->wpdb->prefix . 'va_clients',
            'pets'               => $this->wpdb->prefix . 'va_pets',
            'pet_access'         => $this->wpdb->prefix . 'va_pet_access',
            'pet_logs'           => $this->wpdb->prefix . 'va_pet_logs',
            'entry_types'        => $this->wpdb->prefix . 'va_entry_types',
            'form_fields'        => $this->wpdb->prefix . 'va_form_fields',
            'pet_log_meta'       => $this->wpdb->prefix . 'va_pet_log_meta',
            'products'           => $this->wpdb->prefix . 'va_products',
            'pet_log_products'   => $this->wpdb->prefix . 'va_pet_log_products',
            'manufacturers'      => $this->wpdb->prefix . 'va_manufacturers',
            'active_ingredients' => $this->wpdb->prefix . 'va_active_ingredients',
        ];

        if ( ! isset( $this->repositories['crm.client'] ) ) {
            $this->bind( 'crm.client', new VA_CRM_Client_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['crm.product'] ) ) {
            $this->bind( 'crm.product', new VA_CRM_Product_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['crm.pet'] ) ) {
            $this->bind( 'crm.pet', new VA_CRM_Pet_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['crm.pet_log'] ) ) {
            $this->bind( 'crm.pet_log', new VA_CRM_Pet_Log_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['crm.forms'] ) ) {
            $this->bind( 'crm.forms', new VA_CRM_Form_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['crm.table_installer'] ) ) {
            $this->bind( 'crm.table_installer', new VA_CRM_Table_Installer( $this->wpdb, $table_names ) );
        }

        if ( ! isset( $this->repositories['crm.migration_runner'] ) ) {
            $product_repository = $this->repositories['crm.product'];

            if ( ! $product_repository instanceof VA_CRM_Product_Repository_Interface ) {
                $product_repository = new VA_CRM_Product_Repository( $this->wpdb );
                $this->bind( 'crm.product', $product_repository );
            }

            $migration_service = new VA_CRM_Product_Migration_Service( $this->wpdb, $table_names, $product_repository );

            $this->bind(
                'crm.migration_runner',
                new VA_CRM_Migration_Runner( $this->wpdb, $table_names, $migration_service )
            );
        }

        if ( ! isset( $this->repositories['appointment.availability'] ) ) {
            $this->bind( 'appointment.availability', new VA_Appointment_Availability_Repository( $this->wpdb ) );
        }

        if ( ! isset( $this->repositories['appointment.booking'] ) ) {
            $this->bind( 'appointment.booking', new VA_Appointment_Booking_Repository( $this->wpdb ) );
        }
    }
}
