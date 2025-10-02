<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase para manejar el envío de correos electrónicos para Veterinalia Appointment.
 */
class Veterinalia_Appointment_Mailer {

    public function __construct() {
        // Constructor. Se pueden añadir hooks para personalizar los correos.
    }

    /**
     * Envía un correo electrónico al profesional sobre una nueva cita agendada.
     *
     * @param array $appointment_details Detalles de la cita.
     * @return bool True si el correo se envía con éxito, false en caso contrario.
     */
    public function send_new_appointment_email_to_professional( $appointment_details ) {
        // Validar que el email del profesional esté disponible
        if (empty($appointment_details['professional_email'])) {
            error_log('[Veterinalia Mailer] Error: No hay email de profesional disponible para enviar notificación');
            return false;
        }

        $to = $appointment_details['professional_email'];
        $subject = sprintf( 'Nueva cita agendada con %s', $appointment_details['client_name'] );
        
        $dashboard_url = admin_url( 'admin.php?page=veterinalia-appointment-settings' ); // Usar la URL real de la página de ajustes

        $message = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Cita Agendada</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #f6f6f6; padding: 10px 20px; border-bottom: 1px solid #eee; text-align: center; }
                .content { padding: 20px 0; }
                .footer { font-size: 0.9em; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px; }
                ul { list-style: none; padding: 0; }
                li { margin-bottom: 10px; }
                .button { display: inline-block; background-color: #0073aa; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>¡Nueva Cita Agendada!</h2>
                </div>
                <div class="content">
                    <p>Hola <strong>' . esc_html( $appointment_details['professional_name'] ) . '</strong>,</p>
                    <p>Se ha agendado una nueva cita contigo. Aquí están los detalles:</p>
                    <ul>
                        <li><strong>Cliente:</strong> ' . esc_html( $appointment_details['client_name'] ) . '</li>
                        <li><strong>Email del Cliente:</strong> ' . esc_html( $appointment_details['client_email'] ) . '</li>
                        <li><strong>Fecha:</strong> ' . esc_html( date_i18n( VA_Config::get( 'date_format' ), strtotime( $appointment_details['appointment_date'] ) ) ) . '</li>
                        <li><strong>Hora:</strong> ' . esc_html( date_i18n( VA_Config::get( 'time_format' ), strtotime( $appointment_details['appointment_time'] ) ) ) . '</li>
                        <li><strong>Notas:</strong> ' . esc_html( $appointment_details['notes'] ) . '</li>
                    </ul>
                    <p>Puedes gestionar esta cita desde tu panel de profesional.</p>
                    <p style="text-align: center;"><a href="' . esc_url( $dashboard_url ) . '" class="button">Ir al Panel de Citas</a></p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Envía un correo electrónico al cliente sobre su nueva cita agendada.
     *
     * @param array $appointment_details Detalles de la cita.
     * @return bool True si el correo se envía con éxito, false en caso contrario.
     */
    public function send_new_appointment_email_to_client( $appointment_details ) {
        $to = $appointment_details['client_email'];
        $subject = sprintf( 'Tu cita con %s ha sido agendada', $appointment_details['professional_name'] );
        
        $message = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cita Agendada</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #f6f6f6; padding: 10px 20px; border-bottom: 1px solid #eee; text-align: center; }
                .content { padding: 20px 0; }
                .footer { font-size: 0.9em; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px; }
                ul { list-style: none; padding: 0; }
                li { margin-bottom: 10px; }
                .button { display: inline-block; background-color: #0073aa; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>¡Cita Agendada Correctamente!</h2>
                </div>
                <div class="content">
                    <p>Hola <strong>' . esc_html( $appointment_details['client_name'] ) . '</strong>,</p>
                    <p>Confirmamos que tu cita con <strong>' . esc_html( $appointment_details['professional_name'] ) . '</strong> ha sido agendada con éxito. Aquí están los detalles:</p>
                    <ul>
                        <li><strong>Profesional:</strong> ' . esc_html( $appointment_details['professional_name'] ) . '</li>
                        <li><strong>Fecha:</strong> ' . esc_html( date_i18n( VA_Config::get( 'date_format' ), strtotime( $appointment_details['appointment_date'] ) ) ) . '</li>
                        <li><strong>Hora:</strong> ' . esc_html( date_i18n( VA_Config::get( 'time_format' ), strtotime( $appointment_details['appointment_time'] ) ) ) . '</li>
                        <li><strong>Notas proporcionadas:</strong> ' . esc_html( $appointment_details['notes'] ) . '</li>
                    </ul>
                    <p>El profesional revisará tu solicitud y confirmará la cita pronto.</p>
                    <p>¡Gracias por usar nuestros servicios!</p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Envía un correo electrónico al cliente sobre una actualización del estado de su cita.
     *
     * @param array $appointment_details Detalles de la cita.
     * @return bool True si el correo se envía con éxito, false en caso contrario.
     */
    public function send_appointment_status_email_to_client( $appointment_details ) {
        $to = $appointment_details['client_email'];
        $status_text = '';
        $subject = '';

        switch ( $appointment_details['status'] ) {
            case 'confirmed':
                $status_text = 'confirmada';
                $subject = sprintf( 'Tu cita con %s ha sido confirmada', $appointment_details['professional_name'] );
                break;
            case 'cancelled':
                $status_text = 'cancelada';
                $subject = sprintf( 'Tu cita con %s ha sido cancelada', $appointment_details['professional_name'] );
                break;
            case 'completed':
                $status_text = 'completada';
                $subject = sprintf( 'Tu cita con %s ha sido completada', $appointment_details['professional_name'] );
                break;
            default:
                $status_text = 'actualizada';
                $subject = sprintf( 'Actualización de tu cita con %s', $appointment_details['professional_name'] );
                break;
        }
        
        $message = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Actualización de Cita</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #f6f6f6; padding: 10px 20px; border-bottom: 1px solid #eee; text-align: center; }
                .content { padding: 20px 0; }
                .footer { font-size: 0.9em; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px; }
                ul { list-style: none; padding: 0; }
                li { margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>¡Tu Cita ha sido ' . esc_html( $status_text ) . '!</h2>
                </div>
                <div class="content">
                    <p>Hola <strong>' . esc_html( $appointment_details['client_name'] ) . '</strong>,</p>
                    <p>Te informamos que tu cita con <strong>' . esc_html( $appointment_details['professional_name'] ) . '</strong> ha sido <strong>' . esc_html( $status_text ) . '</strong>.</p>
                    <ul>
                        <li><strong>Profesional:</strong> ' . esc_html( $appointment_details['professional_name'] ) . '</li>
                        <li><strong>Fecha:</strong> ' . esc_html( date_i18n( VA_Config::get( 'date_format' ), strtotime( $appointment_details['appointment_date'] ) ) ) . '</li>
                        <li><strong>Hora:</strong> ' . esc_html( date_i18n( VA_Config::get( 'time_format' ), strtotime( $appointment_details['appointment_time'] ) ) ) . '</li>
                    </ul>
                    <p>Si tienes alguna pregunta, por favor contacta al profesional directamente.</p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Envía un correo electrónico al profesional sobre una actualización del estado de una cita.
     *
     * @param array $appointment_details Detalles de la cita.
     * @return bool True si el correo se envía con éxito, false en caso contrario.
     */
    public function send_appointment_status_email_to_professional( $appointment_details ) {
        $to = $appointment_details['professional_email'];
        $status_text = '';
        $subject = '';

        switch ( $appointment_details['status'] ) {
            case 'confirmed':
                $status_text = 'confirmada';
                $subject = sprintf( 'Cita con %s confirmada', $appointment_details['client_name'] );
                break;
            case 'cancelled':
                $status_text = 'cancelada';
                $subject = sprintf( 'Cita con %s cancelada', $appointment_details['client_name'] );
                break;
            case 'completed':
                $status_text = 'completada';
                $subject = sprintf( 'Cita con %s completada', $appointment_details['client_name'] );
                break;
            default:
                $status_text = 'actualizada';
                $subject = sprintf( 'Actualización de cita con %s', $appointment_details['client_name'] );
                break;
        }

        $dashboard_url = admin_url( 'admin.php?page=veterinalia-appointment-settings' ); // Usar la URL real de la página de ajustes

        $message = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Actualización de Cita</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #f6f6f6; padding: 10px 20px; border-bottom: 1px solid #eee; text-align: center; }
                .content { padding: 20px 0; }
                .footer { font-size: 0.9em; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px; }
                ul { list-style: none; padding: 0; }
                li { margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>¡Estado de Cita Actualizado!</h2>
                </div>
                <div class="content">
                    <p>Hola <strong>' . esc_html( $appointment_details['professional_name'] ) . '</strong>,</p>
                    <p>La cita con <strong>' . esc_html( $appointment_details['client_name'] ) . '</strong> para el <strong>' . esc_html( date_i18n( VA_Config::get( 'date_format' ), strtotime( $appointment_details['appointment_date'] ) ) ) . '</strong> a las <strong>' . esc_html( date_i18n( VA_Config::get( 'time_format' ), strtotime( $appointment_details['appointment_time'] ) ) ) . '</strong> ha sido <strong>' . esc_html( $status_text ) . '</strong>.</p>
                    <p>Puedes revisar los detalles en tu panel de citas.</p>
                    <p style="text-align: center;"><a href="' . esc_url( $dashboard_url ) . '" class="button">Ir al Panel de Citas</a></p>
                </div>
                <div class="footer">
                    <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                </div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Envía un correo electrónico de confirmación para una nueva cita creada desde la agenda.
     *
     * @param int $appointment_id ID de la cita
     * @return bool True si el correo se envía con éxito, false en caso contrario.
     */
    public function send_appointment_confirmation($appointment_id) {
        $appointment = Veterinalia_Appointment_Database::get_instance()->get_appointment_by_id( $appointment_id );

        global $wpdb;
        if (!$appointment) {
            error_log('[Veterinalia Mailer] Cita no encontrada para ID: ' . $appointment_id);
            return false;
        }
        
        // Obtener información del servicio
        $table_services = $wpdb->prefix . 'va_services';
        $service = $wpdb->get_row($wpdb->prepare("
            SELECT name FROM {$table_services} WHERE service_id = %d
        ", $appointment->service_id));
        
        $service_name = $service ? $service->name : 'Servicio no especificado';
        
        // Obtener información del profesional (usuario)
        $professional_user = get_user_by('ID', $appointment->professional_id);
        $professional_name = $professional_user ? $professional_user->display_name : 'Profesional no especificado';
        $professional_email = $professional_user ? $professional_user->user_email : '';
        
        // Obtener información del cliente y la mascota (actualizada)
        $client_name = $appointment->client_name; // Fallback
        $client_email = $appointment->client_email; // Fallback
        $pet_name = $appointment->pet_name; // Fallback

        if ($appointment->client_id) {
            $client = $wpdb->get_row($wpdb->prepare("SELECT name, email FROM {$wpdb->prefix}va_clients WHERE client_id = %d", $appointment->client_id));
            if ($client) {
                $client_name = $client->name;
                $client_email = $client->email;
            }
        }

        if ($appointment->pet_id) {
            $pet = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}va_pets WHERE pet_id = %d", $appointment->pet_id));
            if ($pet) {
                $pet_name = $pet->name;
            }
        }
        
        // Preparar datos para el email
        $appointment_details = [
            'appointment_id' => $appointment->id,
            'professional_id' => $appointment->professional_id,
            'professional_name' => $professional_name,
            'professional_email' => $professional_email,
            'client_name' => $client_name, // Usar el nombre de cliente actualizado
            'client_email' => $client_email, // Usar el email de cliente actualizado
            'pet_name' => $pet_name, // Usar el nombre de mascota actualizado
            'appointment_date' => date('Y-m-d', strtotime($appointment->appointment_start)),
            'appointment_time' => date('H:i', strtotime($appointment->appointment_start)),
            'service_name' => $service_name,
            'notes' => $appointment->notes
        ];
        
        // Enviar email al cliente si tiene email
        if (!empty($appointment_details['client_email'])) {
            $this->send_new_appointment_email_to_client($appointment_details);
        } else {
            error_log('[Veterinalia Mailer] No se puede enviar email al cliente - email no disponible');
        }

        // Enviar email al profesional si tiene email válido
        if (!empty($appointment_details['professional_email'])) {
            $this->send_new_appointment_email_to_professional($appointment_details);
        } else {
            error_log('[Veterinalia Mailer] No se puede enviar email al profesional - email no disponible para ID: ' . ($appointment_details['professional_id'] ?? 'desconocido'));
        }
        
        return true;
    }

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 2.1 -->
    /**
     * Envía un correo a un nuevo cliente "invitado" con su share_code para reclamar su perfil.
     *
     * @param string $client_email Email del cliente.
     * @param string $client_name Nombre del cliente.
     * @param string $pet_name Nombre de la mascota registrada.
     * @param string $share_code Código de compartir de la mascota.
     * @return bool True si el correo se envía con éxito.
     */
    public function send_claim_invitation_email( $client_email, $client_name, $pet_name, $share_code ) {
        if ( ! is_email( $client_email ) ) {
            error_log('[Chocovainilla Mailer] Intento de envío a email no válido: ' . $client_email);
            return false;
        }

        $subject = '¡Tu Expediente Digital en Veterinalia está listo!';
        $registration_url = wp_registration_url(); // O la URL de tu página de registro personalizada

        $message = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Bienvenido a Veterinalia</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background-color: #f6f6f6; padding: 10px 20px; border-bottom: 1px solid #eee; text-align: center; }
                .content { padding: 20px 0; }
                .footer { font-size: 0.9em; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px; }
                .button { display: inline-block; background-color: #0073aa; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header"><h2>¡Bienvenido a la Red Veterinalia!</h2></div>
                <div class="content">
                    <p>Hola <strong>' . esc_html( $client_name ) . '</strong>,</p>
                    <p>Gracias por tu visita. Hemos creado un expediente digital para tu mascota, <strong>' . esc_html( $pet_name ) . '</strong>. Para acceder a su historial clínico, vacunas y próximas citas, solo necesitas crear tu cuenta gratuita.</p>
                    <p>Usa el siguiente código para vincular a tu mascota una vez te hayas registrado:</p>
                    <p style="text-align:center; font-size: 1.5em; font-weight: bold; letter-spacing: 2px; background: #f1f1f1; padding: 10px; border-radius: 8px;">' . esc_html( $share_code ) . '</p>
                    <p style="text-align: center; margin-top: 30px;"><a href="' . esc_url( $registration_url ) . '" class="button">Crear mi Cuenta Ahora</a></p>
                </div>
                <div class="footer"><p>Este es un mensaje automático.</p></div>
            </div>
        </body>
        </html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        error_log('[Chocovainilla Mailer] Enviando correo de invitación a: ' . $client_email);
        return wp_mail( $client_email, $subject, $message, $headers );
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 2.1 -->
} 