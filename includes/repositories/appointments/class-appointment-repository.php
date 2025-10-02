<?php

require_once VA_PLUGIN_DIR . '/includes/repositories/class-base-repository.php';
require_once VA_PLUGIN_DIR . '/includes/repositories/appointments/class-appointment-repository-interface.php';

class VA_Appointment_Booking_Repository extends VA_Base_Repository implements VA_Appointment_Booking_Repository_Interface {
    /** @var string */
    protected $table_name_appointments;

    /** @var string */
    protected $table_name_services;

    /** @var string */
    protected $table_name_clients;

    /** @var string */
    protected $table_name_pets;

    public function __construct( \wpdb $wpdb ) {
        parent::__construct( $wpdb );

        $prefix = $wpdb->prefix;
        $this->table_name_appointments = $prefix . 'va_appointments';
        $this->table_name_services     = $prefix . 'va_services';
        $this->table_name_clients      = $prefix . 'va_clients';
        $this->table_name_pets         = $prefix . 'va_pets';
    }

    public function insert_appointment( array $appointment_data ) {
        $defaults = [
            'professional_id'   => 0,
            'client_id'         => null,
            'service_id'        => 0,
            'pet_id'            => null,
            'appointment_start' => null,
            'appointment_end'   => null,
            'status'            => 'pending',
            'price_at_booking'  => '0.00',
            'client_name'       => null,
            'client_email'      => null,
            'pet_name'          => null,
            'client_phone'      => null,
            'notes'             => null,
        ];

        $data = wp_parse_args( $appointment_data, $defaults );

        $format = [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        $insert_data = [
            'professional_id'   => intval( $data['professional_id'] ),
            'client_id'         => null !== $data['client_id'] ? intval( $data['client_id'] ) : null,
            'service_id'        => intval( $data['service_id'] ),
            'pet_id'            => null !== $data['pet_id'] ? intval( $data['pet_id'] ) : null,
            'appointment_start' => $data['appointment_start'],
            'appointment_end'   => $data['appointment_end'],
            'status'            => sanitize_text_field( $data['status'] ),
            'price_at_booking'  => number_format( floatval( $data['price_at_booking'] ), 2, '.', '' ),
            'client_name'       => $data['client_name'],
            'client_email'      => $data['client_email'],
            'pet_name'          => $data['pet_name'],
            'client_phone'      => $data['client_phone'],
            'notes'             => $data['notes'],
        ];

        $result = $this->wpdb->insert( $this->table_name_appointments, $insert_data, $format );

        return $result ? (int) $this->wpdb->insert_id : false;
    }

    public function get_appointments_by_professional_id( int $professional_id, array $args = [] ): array {
        error_log('[Veterinalia Repo] Recuperando citas para profesional: ' . $professional_id);

        $table_app     = esc_sql( $this->table_name_appointments );
        $table_ser     = esc_sql( $this->table_name_services );
        $table_clients = esc_sql( $this->table_name_clients );
        $table_pets    = esc_sql( $this->table_name_pets );

        $allowed_orderby = [
            'app.appointment_start',
            'app.appointment_end',
            'app.date_created',
            'app.status',
            'ser.name',
            'c.name',
            'p.name',
        ];

        $sql = "SELECT app.*, ser.name AS service_name, ser.entry_type_id AS entry_type_id,
                       c.name AS client_name_actual, c.email AS client_email_actual,
                       p.name AS pet_name_actual, p.species AS pet_species_actual, p.breed AS pet_breed_actual
                FROM {$table_app} AS app
                LEFT JOIN {$table_ser} AS ser ON app.service_id = ser.service_id
                LEFT JOIN {$table_clients} AS c ON app.client_id = c.client_id
                LEFT JOIN {$table_pets} AS p ON app.pet_id = p.pet_id
                WHERE app.professional_id = %d";

        $params = [ $professional_id ];

        if ( ! empty( $args['status'] ) ) {
            $sql      .= " AND app.status = %s";
            $params[] = sanitize_text_field( $args['status'] );
        }

        $orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'app.appointment_start';
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'app.appointment_start';
        }

        $order = isset( $args['order'] ) ? strtoupper( $args['order'] ) : 'ASC';
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'ASC';
        }

        $sql .= " ORDER BY {$orderby} {$order}";

        $prepared = $this->wpdb->prepare( $sql, $params );

        $results = $this->wpdb->get_results( $prepared );

        if ( $this->wpdb->last_error ) {
            error_log('[Veterinalia Repo] Error en get_appointments_by_professional_id: ' . $this->wpdb->last_error);
        }

        return is_array( $results ) ? $results : [];
    }

    public function update_appointment_status( int $appointment_id, string $new_status ): bool {
        $result = $this->wpdb->update(
            $this->table_name_appointments,
            [ 'status' => sanitize_text_field( $new_status ) ],
            [ 'id' => $appointment_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return false !== $result;
    }

    public function get_appointment_at_time( int $professional_id, string $appointment_date, string $appointment_time ) {
        $formatted_time = date( 'H:i:s', strtotime( $appointment_time ) );
        $table          = esc_sql( $this->table_name_appointments );

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE professional_id = %d AND DATE(appointment_start) = %s AND TIME(appointment_start) = %s AND status IN ('pending','confirmed')",
            $professional_id,
            $appointment_date,
            $formatted_time
        );

        return $this->wpdb->get_row( $sql );
    }

    public function get_appointments_for_date( int $professional_id, string $date ): array {
        $table        = esc_sql( $this->table_name_appointments );
        $start_of_day = $date . ' 00:00:00';
        $end_of_day   = $date . ' 23:59:59';

        $sql = $this->wpdb->prepare(
            "SELECT appointment_start, appointment_end FROM {$table} WHERE professional_id = %d AND appointment_start BETWEEN %s AND %s AND status IN ('pending', 'confirmed') ORDER BY appointment_start ASC",
            $professional_id,
            $start_of_day,
            $end_of_day
        );

        $results = $this->wpdb->get_results( $sql );

        return is_array( $results ) ? $results : [];
    }

    public function get_appointments_for_range( int $professional_id, string $date_start, string $date_end ): array {
        $table        = esc_sql( $this->table_name_appointments );
        $range_start  = $date_start . ' 00:00:00';
        $range_end    = $date_end . ' 23:59:59';

        $sql = $this->wpdb->prepare(
            "SELECT appointment_start, appointment_end FROM {$table} WHERE professional_id = %d AND appointment_start BETWEEN %s AND %s AND status IN ('pending', 'confirmed') ORDER BY appointment_start ASC",
            $professional_id,
            $range_start,
            $range_end
        );

        $results = $this->wpdb->get_results( $sql );

        return is_array( $results ) ? $results : [];
    }

    public function is_slot_already_booked( int $professional_id, string $start_time, string $end_time ): bool {
        $table = esc_sql( $this->table_name_appointments );

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE professional_id = %d
             AND status IN ('pending','confirmed')
             AND (%s < appointment_end) AND (%s > appointment_start) LIMIT 1",
            $professional_id,
            $start_time,
            $end_time
        );

        $found = $this->wpdb->get_var( $sql );

        return ! empty( $found );
    }

    public function get_appointment_by_id( int $appointment_id ) {
        $table = esc_sql( $this->table_name_appointments );
        $sql   = $this->wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $appointment_id );

        return $this->wpdb->get_row( $sql );
    }

    public function get_next_appointment_for_pet( int $pet_id ) {
        if ( $pet_id <= 0 ) {
            return null;
        }

        $table = esc_sql( $this->table_name_appointments );

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE pet_id = %d
             AND appointment_start > NOW()
             AND status IN ('pending','confirmed')
             ORDER BY appointment_start ASC
             LIMIT 1",
            $pet_id
        );

        return $this->wpdb->get_row( $sql );
    }

    public function get_next_appointments_for_pets( array $pet_ids ): array {
        $pet_ids = array_unique( array_filter( array_map( 'intval', $pet_ids ) ) );

        if ( empty( $pet_ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $pet_ids ), '%d' ) );
        $table        = esc_sql( $this->table_name_appointments );

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE pet_id IN ({$placeholders})
             AND appointment_start > NOW()
             AND status IN ('pending','confirmed')
             ORDER BY appointment_start ASC",
            $pet_ids
        );

        $results = $this->wpdb->get_results( $sql );

        if ( ! is_array( $results ) ) {
            return [];
        }

        $grouped = [];
        foreach ( $results as $row ) {
            $pet_id = intval( $row->pet_id );
            if ( ! isset( $grouped[ $pet_id ] ) ) {
                $grouped[ $pet_id ] = $row;
            }
        }

        return $grouped;
    }
}
