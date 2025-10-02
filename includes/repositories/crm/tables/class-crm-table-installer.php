<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VA_CRM_Table_Installer extends VA_Base_Repository {
    /**
     * @var array<string,string>
     */
    protected $table_names = [];

    /**
     * @var callable|null
     */
    protected $logger;

    public function __construct( \wpdb $wpdb, array $table_names = [], ?callable $logger = null ) {
        parent::__construct( $wpdb );
        $this->table_names = $table_names ?: $this->build_default_table_names( $wpdb );
        $this->set_logger( $logger );
    }

    public function set_table_names( array $table_names ): void {
        $this->table_names = array_merge( $this->table_names, $table_names );
    }

    public function set_logger( ?callable $logger ): void {
        $this->logger = $logger ? \Closure::fromCallable( $logger ) : null;
    }

    public function create_all(): void {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $this->create_table_entry_types();
        $this->create_table_manufacturers();
        $this->create_table_active_ingredients();

        $this->create_table_clients();
        $this->create_table_pets();
        $this->create_table_pet_access();
        $this->create_table_pet_logs();

        $this->create_table_form_fields();
        $this->create_table_pet_log_meta();
        $this->create_table_products();
        $this->create_table_pet_log_products();
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

    private function create_table_clients(): void {
        $table = $this->table( 'clients' );
        $sql   = "CREATE TABLE {$table} (
            client_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by_professional BIGINT(20) DEFAULT NULL,
            is_guest TINYINT(1) NOT NULL DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (client_id),
            KEY idx_user_id (user_id),
            KEY idx_email (email),
            KEY idx_name (name),
            KEY idx_created_by_professional (created_by_professional)
        ) {$this->charset_collate};";

        dbDelta( $sql );
        $this->log( '[Chocovainilla] DB Check: La tabla va_clients ahora incluye la columna is_guest.' );
    }

    private function create_table_pets(): void {
        $table = $this->table( 'pets' );
        $sql   = "CREATE TABLE {$table} (
            pet_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            client_id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            species VARCHAR(50) NOT NULL,
            breed VARCHAR(255) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            gender ENUM('male', 'female', 'unknown') DEFAULT 'unknown',
            weight DECIMAL(5,2) DEFAULT NULL,
            microchip_number VARCHAR(20) DEFAULT NULL,
            share_code VARCHAR(20) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT DEFAULT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (pet_id),
            UNIQUE KEY idx_share_code (share_code),
            KEY idx_client_id (client_id),
            KEY idx_species (species),
            KEY idx_microchip (microchip_number)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_pet_access(): void {
        $table = $this->table( 'pet_access' );
        $sql   = "CREATE TABLE {$table} (
            access_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            pet_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            access_level ENUM('read', 'write', 'full') DEFAULT 'read',
            granted_by BIGINT(20) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            date_granted DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_expires DATETIME DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (access_id),
            UNIQUE KEY idx_pet_professional (pet_id, professional_id),
            KEY idx_professional_id (professional_id),
            KEY idx_granted_by (granted_by)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_pet_logs(): void {
        $table = $this->table( 'pet_logs' );
        $sql   = "CREATE TABLE {$table} (
            log_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            pet_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            appointment_id BIGINT(20) DEFAULT NULL,
            entry_type_id BIGINT(20) DEFAULT NULL,
            entry_date DATETIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            diagnosis TEXT DEFAULT NULL,
            treatment TEXT DEFAULT NULL,
            medication TEXT DEFAULT NULL,
            next_visit_date DATE DEFAULT NULL,
            attachment_url VARCHAR(500) DEFAULT NULL,
            weight_recorded DECIMAL(5,2) DEFAULT NULL,
            temperature DECIMAL(4,1) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_private TINYINT(1) DEFAULT 0,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY idx_pet_id (pet_id),
            KEY idx_professional_id (professional_id),
            KEY idx_appointment_id (appointment_id),
            KEY idx_entry_date (entry_date),
            KEY idx_entry_type_id (entry_type_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_entry_types(): void {
        $table = $this->table( 'entry_types' );
        $sql   = "CREATE TABLE {$table} (
            entry_type_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            icon VARCHAR(50) DEFAULT NULL,
            color VARCHAR(20) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (entry_type_id),
            UNIQUE KEY slug (slug)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_form_fields(): void {
        $table = $this->table( 'form_fields' );
        $sql   = "CREATE TABLE {$table} (
            field_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            entry_type_id BIGINT(20) NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type ENUM('text', 'textarea', 'number', 'date', 'checkbox', 'product_selector', 'next_appointment') NOT NULL DEFAULT 'text',
            product_filter_type ENUM('Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna') DEFAULT NULL,
            is_required TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            PRIMARY KEY (field_id),
            KEY entry_type_id (entry_type_id),
            UNIQUE KEY unique_entry_field (entry_type_id, field_key)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_pet_log_meta(): void {
        $table = $this->table( 'pet_log_meta' );
        $sql   = "CREATE TABLE {$table} (
            meta_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            log_id BIGINT(20) NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            PRIMARY KEY (meta_id),
            KEY log_id (log_id),
            KEY meta_key (meta_key(191))
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_products(): void {
        $table = $this->table( 'products' );
        $sql   = "CREATE TABLE {$table} (
            product_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_type ENUM('Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna') NOT NULL,
            presentation VARCHAR(255) DEFAULT NULL,
            notes TEXT,
            is_active TINYINT(1) DEFAULT 1,
            manufacturer_id BIGINT(20) DEFAULT NULL,
            active_ingredient_id BIGINT(20) DEFAULT NULL,
            PRIMARY KEY (product_id),
            KEY professional_id (professional_id),
            KEY idx_manufacturer_id (manufacturer_id),
            KEY idx_active_ingredient_id (active_ingredient_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_pet_log_products(): void {
        $table = $this->table( 'pet_log_products' );
        $sql   = "CREATE TABLE {$table} (
            log_product_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            log_id BIGINT(20) NOT NULL,
            product_id BIGINT(20) NOT NULL,
            lot_number VARCHAR(100) DEFAULT NULL,
            expiration_date DATE DEFAULT NULL,
            quantity_used VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (log_product_id),
            KEY log_id (log_id),
            KEY product_id (product_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_manufacturers(): void {
        $table = $this->table( 'manufacturers' );
        $sql   = "CREATE TABLE {$table} (
            manufacturer_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            manufacturer_name VARCHAR(255) NOT NULL,
            contact_info TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (manufacturer_id),
            UNIQUE KEY unique_manufacturer_name (manufacturer_name)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_active_ingredients(): void {
        $table = $this->table( 'active_ingredients' );
        $sql   = "CREATE TABLE {$table} (
            ingredient_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            ingredient_name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            safety_notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ingredient_id),
            UNIQUE KEY unique_ingredient_name (ingredient_name)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }
}
