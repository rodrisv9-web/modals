<?php
/**
 * Gestión de Horarios y Disponibilidad para Veterinalia Appointment
 * 
 * @package VeterinaliaAppointment
 * @subpackage Managers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar horarios y disponibilidad de profesionales
 */
class VA_Schedule_Manager {
    
    /**
     * Instancia única de la clase
     * 
     * @var VA_Schedule_Manager|null
     */
    private static $instance = null;
    
    /**
     * Repositorio de disponibilidad
     * 
     * @var VA_Appointment_Availability_Repository_Interface|null
     */
    private $availability_repository = null;
    
    /**
     * Repositorio de reservas
     * 
     * @var VA_Appointment_Booking_Repository_Interface|null
     */
    private $booking_repository = null;
    
    /**
     * Obtiene la instancia única de la clase
     * 
     * @return VA_Schedule_Manager
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado para el patrón Singleton
     */
    private function __construct() {
        $this->init_repositories();
    }
    
    /**
     * Inicializa los repositorios necesarios
     */
    private function init_repositories() {
        $this->availability_repository = $this->get_availability_repository();
        $this->booking_repository = $this->get_booking_repository();
    }
    
    /**
     * Obtiene el repositorio de disponibilidad
     * 
     * @return VA_Appointment_Availability_Repository_Interface
     */
    private function get_availability_repository() {
        $factory = VA_Repository_Factory::instance();
        $repository = $factory->get('appointment.availability');
        
        if ( ! $repository instanceof VA_Appointment_Availability_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_Appointment_Availability_Repository($wpdb);
            $factory->bind('appointment.availability', $repository);
        }
        
        return $repository;
    }
    
    /**
     * Obtiene el repositorio de reservas
     * 
     * @return VA_Appointment_Booking_Repository_Interface
     */
    private function get_booking_repository() {
        $factory = VA_Repository_Factory::instance();
        $repository = $factory->get('appointment.booking');
        
        if ( ! $repository instanceof VA_Appointment_Booking_Repository_Interface ) {
            global $wpdb;
            $repository = new VA_Appointment_Booking_Repository($wpdb);
            $factory->bind('appointment.booking', $repository);
        }
        
        return $repository;
    }
    
    /**
     * Guarda el horario de disponibilidad para un profesional
     *
     * @param int $professional_id ID del profesional
     * @param array $schedule_data Array de datos del horario
     * @return bool True si se guarda correctamente, false en caso contrario
     */
    public function save_professional_schedule($professional_id, $schedule_data) {
        if (empty($professional_id) || empty($schedule_data) || !is_array($schedule_data)) {
            va_log('[VA_Schedule_Manager] Datos inválidos para guardar horario', 'error');
            return false;
        }
        
        $professional_id = intval($professional_id);
        
        // Eliminar horarios existentes
        $this->availability_repository->delete_professional_availability($professional_id);
        
        // Insertar nuevos horarios
        $success = true;
        foreach ($schedule_data as $schedule_item) {
            $dia_semana_id = isset($schedule_item['dia_semana_id']) ? intval($schedule_item['dia_semana_id']) : 0;
            $start_time = sanitize_text_field($schedule_item['start_time'] ?? '');
            $end_time = sanitize_text_field($schedule_item['end_time'] ?? '');
            $slot_duration = isset($schedule_item['slot_duration']) ? intval($schedule_item['slot_duration']) : 0;
            
            if (!$dia_semana_id || empty($start_time) || empty($end_time)) {
                va_log('[VA_Schedule_Manager] Datos incompletos para horario: dia=' . $dia_semana_id, 'error');
                $success = false;
                continue;
            }

            if (!$this->availability_repository->insert_professional_availability(
                $professional_id,
                $dia_semana_id,
                $start_time,
                $end_time,
                $slot_duration
            )) {
                va_log('[VA_Schedule_Manager] Error al insertar disponibilidad para dia ' . $dia_semana_id, 'error');
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Obtiene el horario de disponibilidad para un profesional
     *
     * @param int $professional_id ID del profesional
     * @return array Array de objetos de los horarios
     */
    public function get_professional_schedule($professional_id) {
        if (empty($professional_id)) {
            return [];
        }
        
        $schedule = $this->availability_repository->get_professional_availability(intval($professional_id));
        return is_array($schedule) ? $schedule : [];
    }
    
    /**
     * Calcula los slots de tiempo disponibles usando la lógica HÍBRIDA
     *
     * @param int $professional_id ID del profesional
     * @param string $selected_date Fecha seleccionada en formato YYYY-MM-DD
     * @param int $service_duration Duración del servicio en minutos
     * @return array Un array de slots de tiempo disponibles en formato 'H:i'
     */
    public function get_available_slots_for_date($professional_id, $selected_date, $service_duration, $working_hours_rules = null, $booked_appointments = null) {
        if (empty($professional_id) || empty($selected_date) || empty($service_duration)) {
            va_log('[VA_Schedule_Manager] Parámetros inválidos para obtener slots', 'error');
            return [];
        }

        $professional_id = intval($professional_id);
        $selected_timestamp = strtotime($selected_date);
        if (false === $selected_timestamp) {
            va_log('[VA_Schedule_Manager] Fecha inválida recibida: ' . $selected_date, 'error');
            return [];
        }

        $dia_semana_id = intval(date('N', $selected_timestamp));

        if (null === $working_hours_rules) {
            $working_hours_rules = $this->availability_repository->get_professional_availability($professional_id);
        }

        if (!is_array($working_hours_rules)) {
            $working_hours_rules = [];
        }

        $todays_working_blocks = [];
        $presentation_interval = 15;

        foreach ($working_hours_rules as $rule) {
            $rule_day = $dia_semana_id;
            $start_time = '';
            $end_time = '';
            $slot_duration = null;

            if (is_object($rule)) {
                $rule_day = isset($rule->dia_semana_id) ? intval($rule->dia_semana_id) : $rule_day;
                $start_time = $rule->start_time ?? '';
                $end_time = $rule->end_time ?? '';
                $slot_duration = $rule->slot_duration ?? null;
            } elseif (is_array($rule)) {
                $rule_day = isset($rule['dia_semana_id']) ? intval($rule['dia_semana_id']) : $rule_day;
                $start_time = $rule['start_time'] ?? '';
                $end_time = $rule['end_time'] ?? '';
                $slot_duration = $rule['slot_duration'] ?? null;
            }

            if ($rule_day !== $dia_semana_id) {
                continue;
            }

            if (empty($start_time) || empty($end_time)) {
                continue;
            }

            $todays_working_blocks[] = [
                'start' => strtotime($selected_date . ' ' . $start_time),
                'end'   => strtotime($selected_date . ' ' . $end_time),
            ];

            if (null !== $slot_duration && $slot_duration > 0) {
                $presentation_interval = intval($slot_duration);
            }
        }

        if (empty($todays_working_blocks)) {
            va_log('[VA_Schedule_Manager] No hay bloques de trabajo para el día ' . $dia_semana_id, 'debug');
            return [];
        }

        if (null === $booked_appointments) {
            $booked_appointments = $this->booking_repository->get_appointments_for_date($professional_id, $selected_date);
        }

        if (!is_array($booked_appointments)) {
            $booked_appointments = [];
        }

        $busy_slots = [];
        foreach ($booked_appointments as $app) {
            $appointment_start = '';
            $appointment_end = '';

            if (is_object($app)) {
                $appointment_start = $app->appointment_start ?? '';
                $appointment_end = $app->appointment_end ?? '';
            } elseif (is_array($app)) {
                $appointment_start = $app['appointment_start'] ?? '';
                $appointment_end = $app['appointment_end'] ?? '';
            }

            if (empty($appointment_start) || empty($appointment_end)) {
                continue;
            }

            $start_timestamp = strtotime($appointment_start);
            $end_timestamp = strtotime($appointment_end);

            if (false === $start_timestamp || false === $end_timestamp) {
                continue;
            }

            $busy_slots[] = [
                'start' => $start_timestamp,
                'end'   => $end_timestamp,
            ];
        }

        return $this->calculate_hybrid_slots(
            $todays_working_blocks,
            $busy_slots,
            $service_duration,
            $presentation_interval
        );
    }
    
    /**
     * Calcula los slots disponibles usando el algoritmo híbrido
     * 
     * @param array $working_blocks Bloques de trabajo del día
     * @param array $busy_slots Slots ocupados
     * @param int $service_duration Duración del servicio en minutos
     * @param int $presentation_interval Intervalo de presentación en minutos
     * @return array Slots disponibles
     */
    private function calculate_hybrid_slots($working_blocks, $busy_slots, $service_duration, $presentation_interval) {
        $available_slots = [];
        $duration_in_seconds = $service_duration * 60;
        $interval_in_seconds = max(5, $presentation_interval) * 60;

        foreach ($working_blocks as $block) {
            $cursor = $block['start'];

            while ($cursor < $block['end']) {
                $potential_slot_start = $cursor;
                $potential_slot_end = $potential_slot_start + $duration_in_seconds;
                
                // Verificar si el slot cabe en el bloque de trabajo
                if ($potential_slot_end > $block['end']) {
                    break;
                }
                
                // Verificar si el slot está disponible
                $is_available = true;
                foreach ($busy_slots as $busy_slot) {
                    if ($potential_slot_start < $busy_slot['end'] && $potential_slot_end > $busy_slot['start']) {
                        $is_available = false;
                        $cursor = $busy_slot['end'];
                        break;
                    }
                }
                
                if ($is_available) {
                    $available_slots[] = date('H:i', $potential_slot_start);
                    $cursor += $interval_in_seconds;
                    
                    // Ajustar cursor si cae en un slot ocupado
                    foreach ($busy_slots as $busy_slot) {
                        if ($cursor > $busy_slot['start'] && $cursor < $busy_slot['end']) {
                            $cursor = $busy_slot['end'];
                            break;
                        }
                    }
                } else {
                    $cursor += $interval_in_seconds;
                }
            }
        }
        
        // Eliminar duplicados y ordenar
        $final_slots = array_unique($available_slots);
        sort($final_slots);
        
        return $final_slots;
    }

    public function get_available_slots_for_range($professional_id, $date_start, $date_end, $service_duration) {
        if (empty($professional_id) || empty($date_start) || empty($date_end) || empty($service_duration)) {
            va_log('[VA_Schedule_Manager] Parámetros inválidos para disponibilidad por rango', 'error');
            return [];
        }

        $professional_id = intval($professional_id);

        try {
            $start = new DateTime($date_start);
            $end   = new DateTime($date_end);
        } catch (Exception $e) {
            va_log('[VA_Schedule_Manager] Error al crear rango de fechas: ' . $e->getMessage(), 'error');
            return [];
        }

        if ($end < $start) {
            va_log('[VA_Schedule_Manager] Fecha final anterior a la inicial para profesional ' . $professional_id, 'error');
            return [];
        }

        $weekly_rules = $this->availability_repository->get_professional_availability($professional_id);
        if (!is_array($weekly_rules)) {
            $weekly_rules = [];
        }

        $rules_by_day = [];
        foreach ($weekly_rules as $rule) {
            $day_id = 0;
            if (is_object($rule) && isset($rule->dia_semana_id)) {
                $day_id = intval($rule->dia_semana_id);
            } elseif (is_array($rule) && isset($rule['dia_semana_id'])) {
                $day_id = intval($rule['dia_semana_id']);
            }

            if ($day_id <= 0) {
                continue;
            }

            if (!isset($rules_by_day[$day_id])) {
                $rules_by_day[$day_id] = [];
            }

            $rules_by_day[$day_id][] = $rule;
        }

        $appointments = $this->booking_repository->get_appointments_for_range($professional_id, $date_start, $date_end);
        $appointments_by_date = [];

        foreach ($appointments as $appointment) {
            $start_value = '';
            if (is_object($appointment)) {
                $start_value = $appointment->appointment_start ?? '';
            } elseif (is_array($appointment)) {
                $start_value = $appointment['appointment_start'] ?? '';
            }

            if (empty($start_value)) {
                continue;
            }

            $date_key = substr($start_value, 0, 10);
            if (!isset($appointments_by_date[$date_key])) {
                $appointments_by_date[$date_key] = [];
            }

            $appointments_by_date[$date_key][] = $appointment;
        }

        $range_slots = [];
        $current = clone $start;

        while ($current <= $end) {
            $current_date = $current->format('Y-m-d');
            $day_id = intval($current->format('N'));
            $daily_rules = $rules_by_day[$day_id] ?? [];
            $daily_appointments = $appointments_by_date[$current_date] ?? [];

            $slots = $this->get_available_slots_for_date(
                $professional_id,
                $current_date,
                $service_duration,
                $daily_rules,
                $daily_appointments
            );

            $range_slots[$current_date] = array_map(
                static function ($slot_time) {
                    return [
                        'time'   => $slot_time,
                        'status' => 'available',
                    ];
                },
                $slots
            );

            $current->modify('+1 day');
        }

        return $range_slots;
    }
    
    /**
     * Obtiene la disponibilidad de un profesional para un rango de fechas
     *
     * @param int $professional_id ID del profesional
     * @param string $date_from Fecha de inicio (Y-m-d)
     * @param string $date_to Fecha de fin (Y-m-d)
     * @return array Array con disponibilidad por día
     */
    public function get_professional_availability_for_range($professional_id, $date_from, $date_to) {
        if (empty($professional_id) || empty($date_from) || empty($date_to)) {
            return [];
        }
        
        $professional_id = intval($professional_id);
        $schedule = $this->availability_repository->get_professional_availability($professional_id);
        
        // Organizar horario por día de la semana
        $schedule_by_day = [];
        foreach ($schedule as $slot) {
            $day_index = isset($slot->dia_semana_id) ? intval($slot->dia_semana_id) : 0;
            if ($day_index > 0) {
                $schedule_by_day[$day_index] = $slot;
            }
        }
        
        // Generar disponibilidad para cada día en el rango
        $availability = [];
        $current_date = new DateTime($date_from);
        $end_date = new DateTime($date_to);
        
        while ($current_date <= $end_date) {
            $day_index = intval($current_date->format('N'));
            $date_key = $current_date->format('Y-m-d');
            
            if (isset($schedule_by_day[$day_index])) {
                $slot = $schedule_by_day[$day_index];
                $availability[$date_key] = [
                    'day_of_week' => strtolower($current_date->format('l')),
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_available' => intval($slot->is_available) === 1,
                ];
            } else {
                $availability[$date_key] = [
                    'day_of_week' => strtolower($current_date->format('l')),
                    'is_available' => false,
                ];
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $availability;
    }
    
    /**
     * Verifica si un slot de tiempo está disponible
     *
     * @param int $professional_id ID del profesional
     * @param string $start_time Hora de inicio
     * @param string $end_time Hora de fin
     * @return bool True si está disponible
     */
    public function check_time_slot_availability($professional_id, $start_time, $end_time) {
        $professional_id = intval($professional_id);
        
        va_log('[VA_Schedule_Manager] Verificando disponibilidad para profesional ' . $professional_id . ' desde ' . $start_time . ' hasta ' . $end_time, 'debug');
        
        $is_taken = $this->booking_repository->is_slot_already_booked(
            $professional_id,
            $start_time,
            $end_time
        );
        
        va_log('[VA_Schedule_Manager] Slot disponible: ' . ($is_taken ? 'NO' : 'SÍ'), 'debug');
        
        return !$is_taken;
    }
    
    /**
     * Ejecuta la migración de la base de datos para la estructura de horarios
     */
    public function run_database_migration() {
        // Verificar si la migración ya se ejecutó
        if (get_option('va_schedule_migration_done')) {
            return;
        }
        
        global $wpdb;
        $table_availability = $wpdb->prefix . 'va_professional_availability';
        
        // Añadir temporalmente la nueva columna si no existe
        $wpdb->query("ALTER TABLE $table_availability ADD COLUMN dia_semana_id BIGINT(20) NOT NULL DEFAULT 0 AFTER professional_id");
        
        // Mapeo de días de la semana a IDs
        $day_map = [
            'monday'    => 1, 'tuesday'   => 2, 'wednesday' => 3,
            'thursday'  => 4, 'friday'    => 5, 'saturday'  => 6, 'sunday'    => 7,
        ];
        
        // Actualizar filas existentes en lotes
        $batch_size = 100;
        $offset = 0;
        
        while ($schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT id, day_of_week FROM $table_availability WHERE dia_semana_id = 0 LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ))) {
            foreach ($schedules as $schedule) {
                $day_text = strtolower(trim($schedule->day_of_week));
                if (isset($day_map[$day_text])) {
                    $wpdb->update(
                        $table_availability,
                        ['dia_semana_id' => $day_map[$day_text]],
                        ['id' => $schedule->id],
                        ['%d'],
                        ['%d']
                    );
                }
            }
            $offset += $batch_size;
        }
        
        // Marcar la migración como completada
        update_option('va_schedule_migration_done', true);
        
        va_log('[VA_Schedule_Manager] Migración de base de datos completada', 'info');
    }
}
