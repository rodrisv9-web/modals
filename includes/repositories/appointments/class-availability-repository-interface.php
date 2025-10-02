<?php

interface VA_Appointment_Availability_Repository_Interface {
    /**
     * Elimina la disponibilidad del profesional.
     */
    public function delete_professional_availability( int $professional_id );

    /**
     * Inserta un bloque de disponibilidad para el profesional.
     *
     * @return int|false Devuelve el ID insertado o false en error.
     */
    public function insert_professional_availability(
        int $professional_id,
        int $dia_semana_id,
        string $start_time,
        string $end_time,
        int $slot_duration
    );

    /**
     * Obtiene la disponibilidad del profesional.
     *
     * @return array Lista de objetos de disponibilidad.
     */
    public function get_professional_availability( int $professional_id ): array;
}
