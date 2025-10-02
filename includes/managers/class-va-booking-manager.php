<?php
/**
 * Gestión de Reservas y Citas para Veterinalia Appointment
 * 
 * @package VeterinaliaAppointment
 * @subpackage Managers
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar las reservas y citas
 */
class VA_Booking_Manager {
    
    /**
     * Instancia única de la clase
     * 
     * @var VA_Booking_Manager|null
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
     * Repositorio de reservas
     * 
     * @var VA_Appointment_Booking_Repository_Interface|null
     */
    private $booking_repository = null;
    
    /**
     * Obtiene la instancia única de la clase
     * 
     * @return VA_Booking_Manager
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
     * Procesa la reserva de una cita
     *
     * @param array $booking_data Datos de la reserva
     * @return array Resultado de la operación
     */
    public function book_appointment($booking_data) {
        va_log('[VA_Booking_Manager] Iniciando proceso de reserva completo', 'info');
        
        // Validación de datos de entrada
        $service_id = isset($booking_data['service_id']) ? intval($booking_data['service_id']) : 0;
        $appointment_start_str = isset($booking_data['appointment_start']) ? sanitize_text_field($booking_data['appointment_start']) : '';
        
        if (empty($service_id) || empty($appointment_start_str)) {
            va_log('[VA_Booking_Manager] ERROR: Datos incompletos - service_id o appointment_start faltantes', 'error');
            return ['success' => false, 'data' => ['message' => 'Datos incompletos.']];
        }
        
        // Obtener detalles del servicio
        $service = $this->db_handler->get_service_by_id($service_id);
        if (!$service) {
            va_log('[VA_Booking_Manager] ERROR: Servicio no válido - ID: ' . $service_id, 'error');
            return ['success' => false, 'data' => ['message' => 'Servicio no válido.']];
        }
        
        $duration = intval($service->duration);
        $price = $service->price;
        $appointment_start_time = strtotime($appointment_start_str);
        $appointment_end_time = $appointment_start_time + ($duration * 60);
        
        // Gestión del cliente
        $client_result = $this->manage_client($booking_data);
        if (!$client_result['success']) {
            return $client_result;
        }
        
        $client_id = $client_result['client_id'];
        $is_new_client = $client_result['is_new_client'];
        
        // Gestión de la mascota
        $pet_result = $this->manage_pet($booking_data, $client_id);
        if (!$pet_result['success']) {
            return $pet_result;
        }
        
        $pet_id = $pet_result['pet_id'];
        
        // Validar relación mascota-cliente si se especifica pet_id existente
        if (!empty($booking_data['pet_id']) && !empty($client_id)) {
            $validation_result = $this->validate_pet_client_relationship($booking_data['pet_id'], $client_id);
            if (!$validation_result['success']) {
                return $validation_result;
            }
            $pet_id = intval($booking_data['pet_id']);
        }
        
        // Procesar la reserva con transacción
        $appointment_result = $this->process_booking_transaction(
            $booking_data,
            $client_id,
            $pet_id,
            $service,
            $appointment_start_time,
            $appointment_end_time,
            $price
        );
        
        if ($appointment_result['success']) {
            // Enviar emails de confirmación
            $this->send_booking_emails(
                $appointment_result['appointment_id'],
                $is_new_client,
                $client_id,
                $pet_id,
                $booking_data
            );
        }
        
        return $appointment_result;
    }
    
    /**
     * Gestiona la creación o identificación del cliente
     * 
     * @param array $booking_data Datos de la reserva
     * @return array Resultado con client_id e is_new_client
     */
    private function manage_client($booking_data) {
        $client_id = null;
        $client_email = isset($booking_data['client_email']) ? sanitize_email($booking_data['client_email']) : '';
        $is_new_client = false;
        $client_repository = $this->crm_service->clients();
        
        if (!empty($client_email)) {
            va_log('[VA_Booking_Manager] Buscando cliente existente por email: ' . $client_email, 'debug');
            
            // Buscar cliente existente por email
            $existing_client = $client_repository->get_client_by_email($client_email);
            
            if ($existing_client) {
                $client_id = $existing_client->client_id;
                va_log('[VA_Booking_Manager] Cliente existente encontrado: ID ' . $client_id, 'info');
            } else {
                va_log('[VA_Booking_Manager] Cliente no encontrado, creando registro invitado', 'info');
                
                // Crear cliente invitado
                $client_data = [
                    'name' => sanitize_text_field($booking_data['client_name']),
                    'email' => $client_email,
                    'phone' => isset($booking_data['client_phone']) ? sanitize_text_field($booking_data['client_phone']) : null,
                    'professional_id' => intval($booking_data['professional_id'])
                ];
                
                $client_id = $client_repository->create_guest_client($client_data);
                if (!$client_id) {
                    va_log('[VA_Booking_Manager] ERROR: Falló la creación del cliente invitado', 'error');
                    return [
                        'success' => false,
                        'data' => ['message' => 'Error al crear el perfil del cliente.', 'code' => 'client_creation_failed']
                    ];
                }

                $is_new_client = true;
                va_log('[VA_Booking_Manager] Cliente invitado creado exitosamente: ID ' . $client_id, 'info');
            }
        } else {
            // Usar cliente logueado si existe
            $wp_user_id = get_current_user_id();
            if ($wp_user_id) {
                va_log('[VA_Booking_Manager] No se proporcionó email; usando usuario WP logueado ID: ' . $wp_user_id, 'debug');

                $crm_client = $client_repository->get_client_by_user_id($wp_user_id);
                if ($crm_client) {
                    $client_id = $crm_client->client_id;
                    va_log('[VA_Booking_Manager] Cliente CRM asociado encontrado: ID ' . $client_id, 'info');
                } else {
                    $client_id = $wp_user_id;
                    va_log('[VA_Booking_Manager] No existe cliente CRM para el WP user; usando WP user ID como client_id temporal: ' . $client_id, 'debug');
                }
            } else {
                $client_id = 0;
                va_log('[VA_Booking_Manager] No hay email ni usuario logueado disponible para asociar cliente', 'error');
            }
        }
        
        return [
            'success' => true,
            'client_id' => $client_id,
            'is_new_client' => $is_new_client
        ];
    }
    
    /**
     * Gestiona la creación o identificación de la mascota
     * 
     * @param array $booking_data Datos de la reserva
     * @param int $client_id ID del cliente
     * @return array Resultado con pet_id
     */
    private function manage_pet($booking_data, $client_id) {
        $pet_id = null;
        $pet_name = isset($booking_data['pet_name']) ? sanitize_text_field($booking_data['pet_name']) : '';
        
        if (!empty($pet_name) && $client_id) {
            va_log('[VA_Booking_Manager] Creando o reutilizando mascota para cliente ID: ' . $client_id, 'debug');
            
            // Verificar si ya existe una mascota con el mismo nombre
            $existing_pet = $this->crm_service->get_pet_by_name_and_client($pet_name, $client_id);
            
            if ($existing_pet) {
                $pet_id = $existing_pet->pet_id;
                va_log('[VA_Booking_Manager] Mascota existente reutilizada: ID ' . $pet_id, 'debug');
                
                // Actualizar datos de la mascota si se proporcionaron nuevos
                $this->update_pet_if_needed($existing_pet, $booking_data);
            } else {
                // Crear nueva mascota
                $pet_data = [
                    'client_id' => $client_id,
                    'name' => $pet_name,
                    'species' => isset($booking_data['pet_species']) ? sanitize_text_field($booking_data['pet_species']) : 'unknown',
                    'breed' => isset($booking_data['pet_breed']) ? sanitize_text_field($booking_data['pet_breed']) : null,
                    'gender' => isset($booking_data['pet_gender']) ? sanitize_text_field($booking_data['pet_gender']) : 'unknown',
                    'professional_id' => intval($booking_data['professional_id'])
                ];
                
                $pet_id = $this->crm_service->create_pet_with_share_code($pet_data);
            }
            
            if (!$pet_id) {
                va_log('[VA_Booking_Manager] ERROR: Falló la creación de la mascota', 'error');
                return [
                    'success' => false,
                    'data' => ['message' => 'Error al crear el perfil de la mascota.', 'code' => 'pet_creation_failed']
                ];
            }

            va_log('[VA_Booking_Manager] Mascota asociada a la reserva: ID ' . $pet_id, 'info');
        }
        
        return [
            'success' => true,
            'pet_id' => $pet_id
        ];
    }
    
    /**
     * Actualiza los datos de la mascota si es necesario
     * 
     * @param object $existing_pet Mascota existente
     * @param array $booking_data Datos de la reserva
     */
    private function update_pet_if_needed($existing_pet, $booking_data) {
        $pet_species = isset($booking_data['pet_species']) ? sanitize_text_field($booking_data['pet_species']) : '';
        $pet_breed = isset($booking_data['pet_breed']) ? sanitize_text_field($booking_data['pet_breed']) : '';
        
        $update_data = [];
        
        if (!empty($pet_species) && $existing_pet->species !== $pet_species) {
            $update_data['species'] = $pet_species;
        }
        if (!empty($pet_breed) && $existing_pet->breed !== $pet_breed) {
            $update_data['breed'] = $pet_breed;
        }
        if (isset($booking_data['pet_gender']) && $existing_pet->gender !== $booking_data['pet_gender']) {
            $update_data['gender'] = $booking_data['pet_gender'];
        }
        
        if (!empty($update_data)) {
            $this->crm_service->update_pet($existing_pet->pet_id, $update_data);
            va_log('[VA_Booking_Manager] Mascota actualizada con campos: ' . implode(', ', array_keys($update_data)), 'debug');
        }
    }
    
    /**
     * Valida la relación entre mascota y cliente
     * 
     * @param int $pet_id ID de la mascota
     * @param int $client_id ID del cliente
     * @return array Resultado de la validación
     */
    private function validate_pet_client_relationship($pet_id, $client_id) {
        va_log('[VA_Booking_Manager] Validando relación mascota-cliente: pet_id=' . $pet_id . ', client_id=' . $client_id, 'debug');
        
        $pet = $this->crm_service->get_pet_by_id($pet_id);
        if (!$pet) {
            va_log('[VA_Booking_Manager] Mascota no encontrada: ' . $pet_id, 'error');
            return ['success' => false, 'data' => ['message' => 'Mascota no encontrada']];
        }

        if ($pet->client_id != $client_id) {
            va_log('[VA_Booking_Manager] ERROR: La mascota ' . $pet_id . ' pertenece al cliente ' . $pet->client_id . ', no al cliente ' . $client_id, 'error');
            return ['success' => false, 'data' => ['message' => 'La mascota seleccionada no pertenece al cliente especificado']];
        }

        va_log('[VA_Booking_Manager] Validación mascota-cliente exitosa', 'debug');
        return ['success' => true];
    }
    
    /**
     * Procesa la reserva dentro de una transacción
     * 
     * @param array $booking_data Datos de la reserva
     * @param int $client_id ID del cliente
     * @param int $pet_id ID de la mascota
     * @param object $service Objeto del servicio
     * @param int $appointment_start_time Timestamp de inicio
     * @param int $appointment_end_time Timestamp de fin
     * @param float $price Precio del servicio
     * @return array Resultado de la operación
     */
    private function process_booking_transaction($booking_data, $client_id, $pet_id, $service, $appointment_start_time, $appointment_end_time, $price) {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Verificación final de disponibilidad
            $is_slot_taken = $this->booking_repository->is_slot_already_booked(
                intval($booking_data['professional_id']),
                date('Y-m-d H:i:s', $appointment_start_time),
                date('Y-m-d H:i:s', $appointment_end_time)
            );
            
            if ($is_slot_taken) {
                $wpdb->query('ROLLBACK');
                va_log('[VA_Booking_Manager] Intento de reserva duplicada para el slot: ' . date('Y-m-d H:i:s', $appointment_start_time), 'info');
                return [
                    'success' => false,
                    'data' => ['message' => 'Lo sentimos, este horario acaba de ser reservado por otra persona.', 'code' => 'slot_taken']
                ];
            }
            
            // Preparar datos para insertar
            $data_to_insert = [
                'professional_id'  => intval($booking_data['professional_id']),
                'client_id'        => $client_id,
                'service_id'       => intval($booking_data['service_id']),
                'appointment_start'=> date('Y-m-d H:i:s', $appointment_start_time),
                'appointment_end'  => date('Y-m-d H:i:s', $appointment_end_time),
                'status'           => 'pending',
                'price_at_booking' => $price,
                'client_name'      => sanitize_text_field($booking_data['client_name']),
                'client_email'     => sanitize_email($booking_data['client_email']),
                'pet_name'         => isset($booking_data['pet_name']) ? sanitize_text_field($booking_data['pet_name']) : '',
                'pet_id'           => $pet_id,
                'client_phone'     => isset($booking_data['client_phone']) ? sanitize_text_field($booking_data['client_phone']) : '',
                'notes'            => isset($booking_data['notes']) ? sanitize_textarea_field($booking_data['notes']) : '',
            ];
            
            va_log('[VA_Booking_Manager] Insertando cita: profesional ' . $data_to_insert['professional_id'] . ' servicio ' . $data_to_insert['service_id'] . ' inicio ' . $data_to_insert['appointment_start'], 'debug');
            
            $inserted = $this->booking_repository->insert_appointment($data_to_insert);
            
            if ($inserted) {
                $appointment_id = $inserted;
                $wpdb->query('COMMIT');
                
                va_log('[VA_Booking_Manager] Cita creada exitosamente con ID: ' . $appointment_id, 'info');
                
                return [
                    'success' => true,
                    'data' => 'Cita reservada con éxito',
                    'appointment_id' => $appointment_id
                ];
            } else {
                $wpdb->query('ROLLBACK');
                va_log('[VA_Booking_Manager] Falló la inserción en la BD: ' . $wpdb->last_error, 'error');
                return [
                    'success' => false,
                    'data' => ['message' => 'Error al guardar la cita', 'code' => 'db_error']
                ];
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            va_log('[VA_Booking_Manager] Excepción en reserva: ' . $e->getMessage(), 'error');
            return [
                'success' => false,
                'data' => ['message' => 'Error en el proceso de reserva', 'code' => 'exception']
            ];
        }
    }
    
    /**
     * Obtiene todas las citas para un profesional específico
     *
     * @param int $professional_id ID del profesional
     * @param array $args Argumentos de la consulta
     * @return array Array de objetos de las citas
     */
    public function get_professional_appointments($professional_id, $args = []) {
        if (empty($professional_id)) {
            return [];
        }
        
        $appointments = $this->booking_repository->get_appointments_by_professional_id(intval($professional_id), $args);
        return is_array($appointments) ? $appointments : [];
    }
    
    /**
     * Actualiza el estado de una cita
     *
     * @param int $appointment_id ID de la cita
     * @param string $new_status El nuevo estado
     * @param int $professional_id ID del profesional (opcional)
     * @return bool True si la actualización fue exitosa
     */
    public function update_appointment_status($appointment_id, $new_status, $professional_id = 0) {
        $updated = $this->booking_repository->update_appointment_status(intval($appointment_id), (string)$new_status);
        
        if ($updated) {
            // Obtener detalles de la cita para el correo
            $appointment_from_db = $this->db_handler->get_appointment_by_id($appointment_id);
            
            if ($appointment_from_db) {
                $this->send_status_update_emails($appointment_from_db, $new_status);
            }
        }
        
        return $updated;
    }
    
    /**
     * Envía emails de actualización de estado
     * 
     * @param object $appointment Datos de la cita
     * @param string $new_status Nuevo estado
     */
    private function send_status_update_emails($appointment, $new_status) {
        $professional_user = get_user_by('ID', $appointment->professional_id);
        $professional_name = $professional_user ? $professional_user->display_name : 'Profesional no especificado';
        $professional_email = $professional_user ? $professional_user->user_email : '';
        
        $appointment_details = [
            'appointment_id'    => $appointment->id,
            'status'            => $new_status,
            'professional_id'   => $appointment->professional_id,
            'professional_name' => $professional_name,
            'professional_email' => $professional_email,
            'client_name'       => $appointment->client_name,
            'client_email'      => $appointment->client_email,
            'appointment_date'  => date('Y-m-d', strtotime($appointment->appointment_start)),
            'appointment_time'  => date('H:i', strtotime($appointment->appointment_start)),
            'notes'             => $appointment->notes,
        ];
        
        $this->mailer->send_appointment_status_email_to_client($appointment_details);
        $this->mailer->send_appointment_status_email_to_professional($appointment_details);
    }
    
    /**
     * Envía los emails correspondientes después de una reserva exitosa
     *
     * @param int $appointment_id ID de la cita creada
     * @param bool $is_new_client Si el cliente es nuevo
     * @param int $client_id ID del cliente
     * @param int $pet_id ID de la mascota
     * @param array $booking_data Datos originales de la reserva
     */
    private function send_booking_emails($appointment_id, $is_new_client, $client_id, $pet_id, $booking_data) {
        va_log('[VA_Booking_Manager] Iniciando envío de emails para cita ID: ' . $appointment_id, 'info');
        
        try {
            // Obtener información del profesional
            $professional_id = intval($booking_data['professional_id']);
            $professional_user = get_user_by('ID', $professional_id);
            $professional_name = $professional_user ? $professional_user->display_name : 'Profesional';
            $professional_email = $professional_user ? $professional_user->user_email : '';
            
            // Crear los detalles de la cita
            $appointment_details = [
                'appointment_id'    => $appointment_id,
                'status'            => 'pending',
                'professional_id'   => $professional_id,
                'professional_name' => $professional_name,
                'professional_email' => $professional_email,
                'client_name'       => sanitize_text_field($booking_data['client_name']),
                'client_email'      => sanitize_email($booking_data['client_email']),
                'appointment_date'  => $booking_data['date'],
                'appointment_time'  => $booking_data['time'],
                'notes'             => isset($booking_data['notes']) ? $booking_data['notes'] : '',
            ];
            
            // Enviar email de confirmación al cliente
            va_log('[VA_Booking_Manager] Enviando email de confirmación al cliente', 'debug');
            $this->mailer->send_new_appointment_email_to_client($appointment_details);
            
            // Enviar email de notificación al profesional
            if (!empty($professional_email)) {
                va_log('[VA_Booking_Manager] Enviando email de notificación al profesional ID ' . $professional_id, 'debug');
                $this->mailer->send_new_appointment_email_to_professional($appointment_details);
            }

            // Si es un cliente nuevo, enviar email de invitación
            if ($is_new_client && $pet_id) {
                va_log('[VA_Booking_Manager] Cliente nuevo detectado, enviando email de invitación', 'info');

                $pet = $this->crm_service->get_pet_by_id($pet_id);
                if ($pet && !empty($pet->share_code)) {
                    va_log('[VA_Booking_Manager] Enviando invitación para mascota ID ' . $pet_id, 'debug');

                    $this->mailer->send_claim_invitation_email(
                        $booking_data['client_email'],
                        $booking_data['client_name'],
                        $pet->name,
                        $pet->share_code
                    );
                }
            }

            va_log('[VA_Booking_Manager] Todos los emails enviados exitosamente', 'info');

        } catch (Exception $e) {
            va_log('[VA_Booking_Manager] ERROR al enviar emails: ' . $e->getMessage(), 'error');
            // No lanzamos la excepción para no romper el flujo de reserva
        }
    }
}
