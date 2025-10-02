<?php

interface VA_Appointment_Booking_Repository_Interface {
    public function insert_appointment( array $appointment_data );

    public function get_appointments_by_professional_id( int $professional_id, array $args = [] ): array;

    public function update_appointment_status( int $appointment_id, string $new_status ): bool;

    public function get_appointment_at_time( int $professional_id, string $appointment_date, string $appointment_time );

    public function get_appointments_for_date( int $professional_id, string $date ): array;

    public function get_appointments_for_range( int $professional_id, string $date_start, string $date_end ): array;

    public function is_slot_already_booked( int $professional_id, string $start_time, string $end_time ): bool;

    public function get_appointment_by_id( int $appointment_id );

    public function get_next_appointment_for_pet( int $pet_id );

    /**
     * Obtiene las próximas citas para un conjunto de mascotas.
     *
     * @param int[] $pet_ids
     * @return array<int, object>
     */
    public function get_next_appointments_for_pets( array $pet_ids ): array;
}
