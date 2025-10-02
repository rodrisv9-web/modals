<?php
/**
 * Gestión del Módulo de Agenda para Veterinalia Appointment
 * 
 * @package VeterinaliaAppointment
 * @subpackage Managers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar el módulo de agenda
 */
class VA_Agenda_Manager {
    
    /**
     * Instancia única de la clase
     * 
     * @var VA_Agenda_Manager|null
     */
    private static $instance = null;
    
    /**
     * Manejador de base de datos
     * 
     * @var Veterinalia_Appointment_Database
     */
    private $db_handler;
    
    /**
     * Servicio CRM
     * 
     * @var VA_CRM_Service
     */
    private $crm_service;
    
    /**
     * Servicio de correo
     * 
     * @var Veterinalia_Appointment_Mailer
     */
    private $mailer;
    
    /**
     * Gestor de reservas
     * 
     * @var VA_Booking_Manager
     */
    private $booking_manager;
    
    /**
     * Gestor de horarios
     * 
     * @var VA_Schedule_Manager
     */
    private $schedule_manager;
    
    /**
     * Repositorio de reservas
     * 
     * @var VA_Appointment_Booking_Repository_Interface|null
     */
    private $booking_repository = null;
    
    /**
     * Obtiene la instancia única de la clase
     * 
     * @return VA_Agenda_Manager
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
        $this->init_dependencies();
    }
    
    /**
     * Inicializa las dependencias necesarias
     */
    private function init_dependencies() {
        $this->db_handler = Veterinalia_Appointment_Database::get_instance();
        $this->crm_service = VA_CRM_Service::get_instance();
        $this->mailer = new Veterinalia_Appointment_Mailer();
        $this->booking_manager = VA_Booking_Manager::get_instance();
        $this->schedule_manager = VA_Schedule_Manager::get_instance();
        $this->booking_repository = $this->get_booking_repository();
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
     * Crea una nueva cita desde el módulo de agenda
     * Incluye validaciones y notificaciones
     *
     * @param array $appointment_data Datos de la cita
     * @return array Resultado de la operación
     */
    public function create_appointment_from_agenda($appointment_data) {
        va_log('[VA_Agenda_Manager] Iniciando creación de cita desde agenda', 'info');
        if (!empty($appointment_data) && is_array($appointment_data)) {
            va_log('[VA_Agenda_Manager] Campos recibidos: ' . implode(', ', array_keys($appointment_data)), 'debug');
        }
        
        // Validar datos requeridos
        $validation_result = $this->validate_appointment_data($appointment_data);
        if (!$validation_result['success']) {
            return $validation_result;
        }
        
        // Validar profesional
        if (!$this->validate_professional($appointment_data['professional_id'])) {
            return [
                'success' => false,
                'message' => 'Profesional no encontrado'
            ];
        }
        
        // Validar servicio
        if (!$this->validate_service($appointment_data['service_id'], $appointment_data['professional_id'])) {
            return [
                'success' => false,
                'message' => 'Servicio no válido para este profesional'
            ];
        }
        
        // Validar relación mascota-cliente si aplica
        if (!empty($appointment_data['client_id']) && !empty($appointment_data['pet_id'])) {
            $pet_validation = $this->validate_pet_client_relationship(
                $appointment_data['pet_id'],
                $appointment_data['client_id']
            );
            if (!$pet_validation['success']) {
                return $pet_validation;
            }
        }
        
        // Construir fecha y hora completa
        $appointment_start = $appointment_data['date'] . ' ' . $appointment_data['start_time'] . ':00';
        $appointment_end = $appointment_data['date'] . ' ' . $appointment_data['end_time'] . ':00';
        
        // Verificar disponibilidad
        if (!$this->schedule_manager->check_time_slot_availability(
            $appointment_data['professional_id'],
            $appointment_start,
            $appointment_end
        )) {
            return [
                'success' => false,
                'message' => 'El horario seleccionado no está disponible'
            ];
        }
        
        // Insertar la cita
        $appointment_id = $this->insert_appointment($appointment_data, $appointment_start, $appointment_end);
        
        if (!$appointment_id) {
            va_log('[VA_Agenda_Manager] Error en inserción de cita desde la agenda', 'error');
            return [
                'success' => false,
                'message' => 'Error al crear la cita en la base de datos'
            ];
        }

        va_log('[VA_Agenda_Manager] Cita creada desde agenda con ID: ' . $appointment_id, 'info');
        
        // Enviar notificación por email si está configurado
        $this->send_appointment_notifications($appointment_id, $appointment_data);
        
        return [
            'success' => true,
            'message' => 'Cita creada exitosamente',
            'appointment_id' => $appointment_id
        ];
    }
    
    /**
     * Valida los datos de la cita
     * 
     * @param array $appointment_data Datos de la cita
     * @return array Resultado de la validación
     */
    private function validate_appointment_data($appointment_data) {
        $required_fields = [
            'service_id',
            'client_name',
            'pet_name',
            'pet_id',
            'date',
            'start_time',
            'professional_id'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($appointment_data[$field])) {
                va_log('[VA_Agenda_Manager] Campo requerido faltante: ' . $field, 'error');
                return [
                    'success' => false,
                    'message' => "Campo requerido faltante: {$field}"
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Valida que el profesional existe
     * 
     * @param int $professional_id ID del profesional
     * @return bool
     */
    private function validate_professional($professional_id) {
        va_log('[VA_Agenda_Manager] Validando profesional ID: ' . $professional_id, 'debug');
        return $this->db_handler->professional_exists($professional_id);
    }
    
    /**
     * Valida que el servicio pertenece al profesional
     * 
     * @param int $service_id ID del servicio
     * @param int $professional_id ID del profesional
     * @return bool
     */
    private function validate_service($service_id, $professional_id) {
        va_log('[VA_Agenda_Manager] Validando servicio ID: ' . $service_id, 'debug');
        return $this->db_handler->service_belongs_to_professional($service_id, $professional_id);
    }
    
    /**
     * Valida la relación entre mascota y cliente
     * 
     * @param int $pet_id ID de la mascota
     * @param int $client_id ID del cliente
     * @return array Resultado de la validación
     */
    private function validate_pet_client_relationship($pet_id, $client_id) {
        va_log('[VA_Agenda_Manager] Validando relación mascota-cliente: pet_id=' . $pet_id . ', client_id=' . $client_id, 'debug');
        
        $pet = $this->crm_service->get_pet_by_id($pet_id);
        if (!$pet) {
            va_log('[VA_Agenda_Manager] Mascota no encontrada: ' . $pet_id, 'error');
            return [
                'success' => false,
                'message' => 'Mascota no encontrada'
            ];
        }

        if ($pet->client_id != $client_id) {
            va_log('[VA_Agenda_Manager] ERROR: La mascota ' . $pet_id . ' pertenece al cliente ' . $pet->client_id . ', no al cliente ' . $client_id, 'error');
            return [
                'success' => false,
                'message' => 'La mascota seleccionada no pertenece al cliente especificado'
            ];
        }

        va_log('[VA_Agenda_Manager] Validación mascota-cliente exitosa', 'debug');
        return ['success' => true];
    }
    
    /**
     * Inserta la cita en la base de datos
     * 
     * @param array $appointment_data Datos de la cita
     * @param string $appointment_start Fecha y hora de inicio
     * @param string $appointment_end Fecha y hora de fin
     * @return int|false ID de la cita insertada o false en caso de error
     */
    private function insert_appointment($appointment_data, $appointment_start, $appointment_end) {
        $insert_data = [
            'professional_id' => $appointment_data['professional_id'],
            'service_id' => $appointment_data['service_id'],
            'client_id' => isset($appointment_data['client_id']) ? intval($appointment_data['client_id']) : null,
            'client_name' => sanitize_text_field($appointment_data['client_name']),
            'client_email' => sanitize_email($appointment_data['email'] ?? ''),
            'client_phone' => sanitize_text_field($appointment_data['phone'] ?? ''),
            'pet_name' => sanitize_text_field($appointment_data['pet_name']),
            'pet_id' => intval($appointment_data['pet_id']),
            'appointment_start' => $appointment_start,
            'appointment_end' => $appointment_end,
            'status' => 'pending',
            'notes' => sanitize_textarea_field($appointment_data['notes'] ?? ''),
            'created_at' => current_time('mysql')
        ];
        
        return $this->booking_repository->insert_appointment($insert_data);
    }
    
    /**
     * Envía notificaciones por email
     * 
     * @param int $appointment_id ID de la cita
     * @param array $appointment_data Datos de la cita
     */
    private function send_appointment_notifications($appointment_id, $appointment_data) {
        if (!empty($appointment_data['email'])) {
            try {
                $this->mailer->send_appointment_confirmation($appointment_id);
                va_log('[VA_Agenda_Manager] Email de confirmación enviado', 'info');
            } catch (Exception $e) {
                va_log('[VA_Agenda_Manager] Error enviando email: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Completa una cita y registra su bitácora
     *
     * @param array $log_data Datos del formulario de la bitácora
     * @return array Resultado de la operación
     */
    public function complete_appointment_with_log($log_data) {
        // Validación de datos esenciales
        $appointment_id = isset($log_data['appointment_id']) ? intval($log_data['appointment_id']) : 0;
        $pet_id = isset($log_data['pet_id']) ? intval($log_data['pet_id']) : 0;
        
        if (empty($appointment_id) || empty($pet_id)) {
            return [
                'success' => false,
                'message' => 'Faltan datos de la cita o mascota.'
            ];
        }
        
        // Guardar la entrada en la bitácora
        $log_id = $this->crm_service->pet_logs()->create_pet_log($log_data);
        
        if (!$log_id) {
            va_log('[VA_Agenda_Manager] Error: No se pudo guardar la entrada en la bitácora para la cita ' . $appointment_id, 'error');
            return [
                'success' => false,
                'message' => 'No se pudo guardar la entrada en el historial.'
            ];
        }

        va_log('[VA_Agenda_Manager] Entrada de bitácora creada con ID ' . $log_id, 'info');
        
        // Actualizar el estado de la cita a "completada"
        $status_updated = $this->booking_manager->update_appointment_status($appointment_id, 'completed');
        
        if (!$status_updated) {
            va_log('[VA_Agenda_Manager] Advertencia: Se guardó la bitácora pero no se pudo actualizar el estado de la cita ' . $appointment_id, 'error');
        }
        
        return [
            'success' => true,
            'message' => 'Cita completada y registrada en la bitácora.'
        ];
    }
    
    /**
     * Obtiene las citas de un profesional para el módulo de agenda
     * 
     * @param int $professional_id ID del profesional
     * @param array $args Argumentos adicionales de filtrado
     * @return array Array de citas
     */
    public function get_agenda_appointments($professional_id, $args = []) {
        if (empty($professional_id)) {
            return [];
        }
        
        // Usar el booking manager para obtener las citas
        return $this->booking_manager->get_professional_appointments($professional_id, $args);
    }
    
    /**
     * Obtiene la disponibilidad de un profesional para un rango de fechas
     * 
     * @param int $professional_id ID del profesional
     * @param string $date_from Fecha de inicio
     * @param string $date_to Fecha de fin
     * @return array Disponibilidad por día
     */
    public function get_availability_for_range($professional_id, $date_from, $date_to) {
        return $this->schedule_manager->get_professional_availability_for_range(
            $professional_id,
            $date_from,
            $date_to
        );
    }
    
    /**
     * Obtiene los slots disponibles para una fecha específica
     * 
     * @param int $professional_id ID del profesional
     * @param string $date Fecha en formato Y-m-d
     * @param int $service_duration Duración del servicio en minutos
     * @return array Slots disponibles
     */
    public function get_available_slots($professional_id, $date, $service_duration) {
        return $this->schedule_manager->get_available_slots_for_date(
            $professional_id,
            $date,
            $service_duration
        );
    }
}
