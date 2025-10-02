// veterinalia-appointment/assets/js/main.js
/**
 * Archivo principal de JavaScript para Veterinalia Appointment.
 * Se encarga de inicializar los módulos correctos en la página correcta.
 */
jQuery(document).ready(function($) {
    // Iniciar el módulo de reserva del cliente si su contenedor existe
    if ($('.va-client-booking-form').length) {
        VA_Client_Booking.init();
    }

    // Iniciar el módulo de horarios del profesional si su contenedor existe
    if ($('.va-professional-schedule-form').length) {
        VA_Professional_Schedule.init();
    }

    // Iniciar el módulo del panel de citas si su contenedor existe
    if ($('.va-professional-dashboard').length) {
        VA_Professional_Dashboard.init();
    }

    // Iniciar el módulo de gestión de servicios si su contenedor existe
    if ($('.va-professional-services-dashboard').length) {
        VA_Professional_Services.init();
    }
});