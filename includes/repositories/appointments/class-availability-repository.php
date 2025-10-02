<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-availability-repository-interface.php';

class VA_Appointment_Availability_Repository extends VA_Base_Repository implements VA_Appointment_Availability_Repository_Interface {
    /** @var string */
    protected $table_name_availability;

    /** @var string */
    protected $table_name_days;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;
        $this->table_name_availability = $prefix . 'va_professional_availability';
        $this->table_name_days         = $prefix . 'va_dias_semana';
    }

    public function delete_professional_availability( int $professional_id ) {
        return $this->wpdb->delete(
            $this->table_name_availability,
            [ 'professional_id' => $professional_id ],
            [ '%d' ]
        );
    }

    public function insert_professional_availability(
        int $professional_id,
        int $dia_semana_id,
        string $start_time,
        string $end_time,
        int $slot_duration
    ) {
        $data = [
            'professional_id' => $professional_id,
            'dia_semana_id'   => $dia_semana_id,
            'start_time'      => $start_time,
            'end_time'        => $end_time,
            'slot_duration'   => $slot_duration,
        ];

        $format = [ '%d', '%d', '%s', '%s', '%d' ];

        $result = $this->wpdb->insert( $this->table_name_availability, $data, $format );

        return $result ? (int) $this->wpdb->insert_id : false;
    }

    public function get_professional_availability( int $professional_id ): array {
        $table_availability = esc_sql( $this->table_name_availability );
        $table_days         = esc_sql( $this->table_name_days );

        $sql = $this->wpdb->prepare(
            "SELECT pa.*, ds.nombre_dia FROM {$table_availability} AS pa
             LEFT JOIN {$table_days} AS ds ON pa.dia_semana_id = ds.dia_id
             WHERE pa.professional_id = %d
             ORDER BY pa.dia_semana_id, pa.start_time ASC",
            $professional_id
        );

        $results = $this->wpdb->get_results( $sql );

        return is_array( $results ) ? $results : [];
    }
}
