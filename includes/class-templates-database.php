<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class Veterinalia_Templates_Database {

    private static $instance = null;
    const DB_VERSION = '1.1.0';
    private $charset_collate;
    private $table_name_templates;
    private $table_name_template_categories;
    private $table_name_template_services;
    private $table_name_dias_semana;
    private $table_name_plantillas_horarios;
    private $table_name_bloques_plantilla;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        $this->table_name_templates = $prefix . 'va_service_templates';
        $this->table_name_template_categories = $prefix . 'va_template_categories';
        $this->table_name_template_services = $prefix . 'va_template_services';
        $this->table_name_dias_semana = $prefix . 'va_dias_semana';
        $this->table_name_plantillas_horarios = $prefix . 'va_plantillas_horarios';
        $this->table_name_bloques_plantilla = $prefix . 'va_bloques_plantilla';
    }
    
    public function create_tables() {
        $this->create_table_templates();
        $this->create_table_template_categories();
        $this->create_table_template_services();
        $this->create_table_dias_semana();
        $this->create_table_plantillas_horarios();
        $this->create_table_bloques_plantilla();
        $this->maybe_populate_dias_semana();
        $this->maybe_fix_dias_semana_encoding();
        // Guardar la versión del esquema aplicado
        if ( function_exists( 'update_option' ) ) {
            update_option( 'va_templates_db_version', self::DB_VERSION );
        }
    }

    /**
     * Corrige posibles inserciones con caracteres mal codificados en nombres de días.
     */
    private function maybe_fix_dias_semana_encoding() {
        global $wpdb;
        $table = esc_sql( $this->table_name_dias_semana );
        // Reemplazar valores comunes mal codificados si existen
        $wpdb->query( "UPDATE {$table} SET nombre_dia = 'Miércoles' WHERE nombre_dia IN ('Mi�rcoles', 'Miercoles')" );
        $wpdb->query( "UPDATE {$table} SET nombre_dia = 'Sábado'    WHERE nombre_dia IN ('S�bado', 'Sabado')" );
    }

    /**
     * Verifica y aplica actualizaciones del esquema si la versión cambió.
     */
    public function maybe_upgrade() {
        if ( ! function_exists( 'get_option' ) ) {
            return;
        }
        $installed = get_option( 'va_templates_db_version', '' );
        if ( version_compare( (string) $installed, self::DB_VERSION, '<' ) ) {
            $this->create_tables();
        }
    }

    private function create_table_templates() {
        global $wpdb;
        $table = esc_sql( $this->table_name_templates );
        $sql = "CREATE TABLE {$table} (
            template_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            template_name VARCHAR(255) NOT NULL,
            professional_type VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT(20) NOT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (template_id),
            KEY professional_type (professional_type)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_template_categories() {
        global $wpdb;
        $table = esc_sql( $this->table_name_template_categories );
        $sql = "CREATE TABLE {$table} (
            template_category_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            template_id BIGINT(20) NOT NULL,
            category_name VARCHAR(255) NOT NULL,
            display_order INT DEFAULT 0,
            PRIMARY KEY (template_category_id),
            KEY template_id (template_id),
            KEY template_order (template_id, display_order)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_template_services() {
        global $wpdb;
        $table = esc_sql( $this->table_name_template_services );
        $sql = "CREATE TABLE {$table} (
            template_service_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            template_category_id BIGINT(20) NOT NULL,
            entry_type_id BIGINT(20) DEFAULT NULL,
            service_name VARCHAR(255) NOT NULL,
            suggested_duration INT NOT NULL,
            suggested_price DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            description TEXT,
            PRIMARY KEY (template_service_id),
            KEY template_category_id (template_category_id),
            KEY idx_entry_type_id (entry_type_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_dias_semana() {
        global $wpdb;
        $table = esc_sql( $this->table_name_dias_semana );
        $sql = "CREATE TABLE {$table} (
            dia_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            nombre_dia VARCHAR(15) NOT NULL,
            PRIMARY KEY (dia_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_plantillas_horarios() {
        global $wpdb;
        $table = esc_sql( $this->table_name_plantillas_horarios );
        $sql = "CREATE TABLE {$table} (
            plantilla_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            nombre_plantilla VARCHAR(255) NOT NULL,
            descripcion TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY nombre_plantilla (nombre_plantilla),
            PRIMARY KEY (plantilla_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_bloques_plantilla() {
        global $wpdb;
        $table = esc_sql( $this->table_name_bloques_plantilla );
        $sql = "CREATE TABLE {$table} (
            bloque_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            plantilla_id BIGINT(20) NOT NULL,
            dia_id BIGINT(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            PRIMARY KEY (bloque_id),
            KEY plantilla_id (plantilla_id),
            KEY dia_id (dia_id),
            KEY plantilla_dia_time (plantilla_id, dia_id, start_time)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function maybe_populate_dias_semana() {
        global $wpdb;
        $table = $this->table_name_dias_semana;
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM " . esc_sql( $table ) );
        if ( intval( $count ) === 0 ) {
            $dias = [ 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo' ];
            foreach ( $dias as $dia ) {
                $wpdb->insert( $table, [ 'nombre_dia' => $dia ], [ '%s' ] );
            }
        }
    }

    public function save_template( $template_data ) {
        global $wpdb;
        $table = $this->table_name_templates;
        $data = [
            'template_name'     => sanitize_text_field( $template_data['template_name'] ),
            'professional_type' => sanitize_text_field( $template_data['professional_type'] ),
            'description'       => sanitize_textarea_field( $template_data['description'] ),
            'is_active'         => isset( $template_data['is_active'] ) ? 1 : 0,
            'created_by'        => get_current_user_id(),
        ];
        $format = [ '%s', '%s', '%s', '%d', '%d' ];

        if ( ! empty( $template_data['template_id'] ) ) {
            $where = [ 'template_id' => intval( $template_data['template_id'] ) ];
            $wpdb->update( $table, $data, $where, $format, [ '%d' ] );
            return intval( $template_data['template_id'] );
        }

        $wpdb->insert( $table, $data, $format );
        return $wpdb->insert_id;
    }

    public function get_templates( $template_id = 0 ) {
        global $wpdb;
        $table = esc_sql( $this->table_name_templates );
        if ( $template_id > 0 ) {
            return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE template_id = %d", intval( $template_id ) ) );
        }
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY template_name ASC" );
    }

    public function delete_template( $template_id ) {
        global $wpdb;
        $template_id = intval( $template_id );
        if ( $template_id <= 0 ) return false;

        $table_templates = $this->table_name_templates;
        $table_categories = $this->table_name_template_categories;
        $table_services = $this->table_name_template_services;

        // Obtener IDs de categorías
        $category_ids = $wpdb->get_col( $wpdb->prepare( "SELECT template_category_id FROM {$table_categories} WHERE template_id = %d", $template_id ) );
        if ( ! empty( $category_ids ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $category_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_services} WHERE template_category_id IN ($placeholders)", $category_ids ) );
        }

        $wpdb->delete( $table_categories, [ 'template_id' => $template_id ], [ '%d' ] );
        $wpdb->delete( $table_templates, [ 'template_id' => $template_id ], [ '%d' ] );
        return true;
    }

    public function get_full_template_details( $template_id ) {
        $template = $this->get_templates( $template_id );
        if ( ! $template ) return null;
        global $wpdb;
        $table_categories = esc_sql( $this->table_name_template_categories );
        $table_services   = esc_sql( $this->table_name_template_services );

        $categories = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_categories} WHERE template_id = %d ORDER BY display_order ASC", intval( $template_id ) ) );
        foreach ( $categories as $category ) {
            $category->services = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_services} WHERE template_category_id = %d", intval( $category->template_category_id ) ) );
        }
        $template->categories = $categories;
        return $template;
    }

    public function get_template_category_by_id( $category_id ) {
        global $wpdb;
        $table = esc_sql( $this->table_name_template_categories );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE template_category_id = %d", intval( $category_id ) ) );
    }

    public function add_category_to_template( $template_id, $category_name ) {
        global $wpdb;
        $table = $this->table_name_template_categories;
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE template_id = %d AND category_name = %s", intval( $template_id ), $category_name ) );
        if ( $exists > 0 ) return false;
        $res = $wpdb->insert( $table, [ 'template_id' => intval( $template_id ), 'category_name' => sanitize_text_field( $category_name ), 'display_order' => 0 ], [ '%d', '%s', '%d' ] );
        return $res ? $wpdb->insert_id : false;
    }

    public function add_service_to_template_category( $service_data ) {
        global $wpdb;
        $table = $this->table_name_template_services;
        $defaults = [ 'template_category_id' => 0, 'service_name' => '', 'suggested_duration' => 30, 'suggested_price' => '0.00', 'description' => '' ];
        $data = wp_parse_args( $service_data, $defaults );
        if ( empty( $data['template_category_id'] ) || empty( $data['service_name'] ) ) return false;
        $res = $wpdb->insert( $table, [
            'template_category_id' => intval( $data['template_category_id'] ),
            'service_name' => sanitize_text_field( $data['service_name'] ),
            'suggested_duration' => intval( $data['suggested_duration'] ),
            'suggested_price' => number_format( floatval( $data['suggested_price'] ), 2, '.', '' ),
            'description' => sanitize_textarea_field( $data['description'] ),
        ], [ '%d', '%s', '%d', '%s', '%s' ] );
        return $res ? $wpdb->insert_id : false;
    }

    public function get_active_schedule_templates() {
        global $wpdb;
        $table = esc_sql( $this->table_name_plantillas_horarios );
        return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY nombre_plantilla ASC" );
    }

    public function get_schedule_template_details( $plantilla_id ) {
        global $wpdb;
        $plantilla_id = intval( $plantilla_id );
        $table_bloques = esc_sql( $this->table_name_bloques_plantilla );
        $table_dias    = esc_sql( $this->table_name_dias_semana );

        $sql = $wpdb->prepare(
            "SELECT b.dia_id AS dia_semana_id, d.nombre_dia, b.start_time, b.end_time
             FROM {$table_bloques} AS b
             LEFT JOIN {$table_dias} AS d ON b.dia_id = d.dia_id
             WHERE b.plantilla_id = %d
             ORDER BY b.dia_id, b.start_time ASC",
            $plantilla_id
        );

        return $wpdb->get_results( $sql );
    }

} 
