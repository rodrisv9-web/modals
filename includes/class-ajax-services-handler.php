<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Maneja acciones AJAX relacionadas con categorías y servicios.
 */
class Veterinalia_Appointment_AJAX_Services_Handler {
    private static $instance = null;

    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'wp_ajax_va_save_category', [ $this, 'handle_save_category' ] );
        add_action( 'wp_ajax_nopriv_va_save_category', [ $this, 'handle_save_category' ] );
        add_action( 'wp_ajax_va_save_service', [ $this, 'handle_save_service' ] );
        add_action( 'wp_ajax_nopriv_va_save_service', [ $this, 'handle_save_service' ] );
        add_action( 'wp_ajax_va_inherit_categories_services', [ $this, 'handle_inherit_categories_services' ] );
        add_action( 'wp_ajax_nopriv_va_inherit_categories_services', [ $this, 'handle_inherit_categories_services' ] );
        add_action( 'wp_ajax_va_get_listing_categories_services', [ $this, 'handle_get_listing_categories_services' ] );
        add_action( 'wp_ajax_nopriv_va_get_listing_categories_services', [ $this, 'handle_get_listing_categories_services' ] );
        add_action( 'wp_ajax_va_edit_category', [ $this, 'handle_edit_category' ] );
        add_action( 'wp_ajax_nopriv_va_edit_category', [ $this, 'handle_edit_category' ] );
        add_action( 'wp_ajax_va_delete_category', [ $this, 'handle_delete_category' ] );
        add_action( 'wp_ajax_nopriv_va_delete_category', [ $this, 'handle_delete_category' ] );
        add_action( 'wp_ajax_va_edit_service', [ $this, 'handle_edit_service' ] );
        add_action( 'wp_ajax_nopriv_va_edit_service', [ $this, 'handle_edit_service' ] );
        add_action( 'wp_ajax_va_delete_service', [ $this, 'handle_delete_service' ] );
        add_action( 'wp_ajax_nopriv_va_delete_service', [ $this, 'handle_delete_service' ] );
    }

    /**
     * Manejador AJAX para guardar una categoría de servicios.
     */
    // =======================================================
    // === MANEJADORES PARA SERVICIOS Y CATEGORÍAS
    // =======================================================

    public function handle_save_category() {
        // Verificación de nonce de seguridad
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
        $current_user_id = get_current_user_id();

        // Logging para debugging
        error_log('VA Save Category - Data received: professional_id=' . $professional_id . ', category_name=' . $category_name . ', user_id=' . $current_user_id);

        // Validaciones de datos
        if (empty($professional_id)) {
            wp_send_json_error(['message' => 'ID de profesional no válido.']);
            return;
        }

        if (empty($category_name)) {
            wp_send_json_error(['message' => 'El nombre de la categoría es requerido.']);
            return;
        }

        if (empty($current_user_id)) {
            wp_send_json_error(['message' => 'Debes estar logueado para realizar esta acción.']);
            return;
        }

        // Verificación de seguridad: el usuario debe ser el autor del listado
        $post_author_id = get_post_field('post_author', $professional_id);
        if ($post_author_id != $current_user_id) {
            error_log('VA Save Category - Permission denied: post_author=' . $post_author_id . ', current_user=' . $current_user_id);
            wp_send_json_error(['message' => 'No tienes permiso para añadir categorías a este listado.']);
            return;
        }

        // Verificar que el post existe y es del tipo correcto
        $post = get_post($professional_id);
        if (!$post || $post->post_type !== ATBDP_POST_TYPE) {
            wp_send_json_error(['message' => 'El listado especificado no existe o no es válido.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $category_id = $db_handler->save_category([
                'professional_id' => $professional_id,
                'name' => $category_name,
            ]);

            if ($category_id) {
                error_log('VA Save Category - Success: category_id=' . $category_id);
                // Devolvemos el ID y el nombre de la categoría para añadirla dinámicamente al frontend
                wp_send_json_success([
                    'message' => 'Categoría creada con éxito.',
                    'category' => [
                        'id' => $category_id,
                        'name' => $category_name,
                    ]
                ]);
            } else {
                error_log('VA Save Category - Failed: save_category returned false');
                wp_send_json_error(['message' => 'Error al guardar la categoría. Posiblemente ya existe una categoría con ese nombre.']);
            }
        } catch (Exception $e) {
            error_log('VA Save Category - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para guardar un servicio.
     */
    public function handle_save_service() {
        // Verificación de nonce de seguridad
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $service_name = isset($_POST['service_name']) ? sanitize_text_field($_POST['service_name']) : '';
        $service_duration = isset($_POST['service_duration']) ? intval($_POST['service_duration']) : 0;
        $service_price = isset($_POST['service_price']) ? floatval($_POST['service_price']) : 0.00;
        $entry_type_id = isset($_POST['entry_type_id']) ? intval($_POST['entry_type_id']) : null;
        $current_user_id = get_current_user_id();

        // Logging para debugging
        error_log('VA Save Service - Data received: professional_id=' . $professional_id . ', category_id=' . $category_id . ', service_name=' . $service_name . ', duration=' . $service_duration . ', price=' . $service_price . ', user_id=' . $current_user_id);

        // Validaciones de datos
        if (empty($professional_id)) {
            wp_send_json_error(['message' => 'ID de profesional no válido.']);
            return;
        }

        if (empty($category_id)) {
            wp_send_json_error(['message' => 'ID de categoría no válido.']);
            return;
        }

        if (empty($service_name)) {
            wp_send_json_error(['message' => 'El nombre del servicio es requerido.']);
            return;
        }

        if (empty($entry_type_id)) {
            wp_send_json_error(['message' => 'El tipo de entrada es requerido.']);
            return;
        }

        if ($service_duration < 5) {
            wp_send_json_error(['message' => 'La duración mínima del servicio es 5 minutos.']);
            return;
        }

        if ($service_price < 0) {
            wp_send_json_error(['message' => 'El precio no puede ser negativo.']);
            return;
        }

        if (empty($current_user_id)) {
            wp_send_json_error(['message' => 'Debes estar logueado para realizar esta acción.']);
            return;
        }

        // Verificación de seguridad: el usuario debe ser el autor del listado
        $post_author_id = get_post_field('post_author', $professional_id);
        if ($post_author_id != $current_user_id) {
            error_log('VA Save Service - Permission denied: post_author=' . $post_author_id . ', current_user=' . $current_user_id);
            wp_send_json_error(['message' => 'No tienes permiso para añadir servicios a este listado.']);
            return;
        }

        // Verificar que la categoría pertenece al profesional
        $db_handler = Veterinalia_Appointment_Database::get_instance();
        $category = $db_handler->get_category_by_id($category_id);
        if (!$category || $category->professional_id != $professional_id) {
            wp_send_json_error(['message' => 'La categoría especificada no existe o no te pertenece.']);
            return;
        }

        try {
            $service_id = $db_handler->save_service([
                'professional_id' => $professional_id,
                'category_id' => $category_id,
                'name' => $service_name,
                'duration' => $service_duration,
                'price' => $service_price,
                'entry_type_id' => $entry_type_id,
            ]);

            if ($service_id) {
                error_log('VA Save Service - Success: service_id=' . $service_id);
                // Devolvemos el ID y los datos del servicio para añadirlo dinámicamente al frontend
                wp_send_json_success([
                    'message' => 'Servicio creado con éxito.',
                    'service' => [
                        'id' => $service_id,
                        'name' => $service_name,
                        'duration' => $service_duration,
                        'price' => $service_price,
                        'category_id' => $category_id,
                        'entry_type_id' => $entry_type_id,
                    ]
                ]);
            } else {
                error_log('VA Save Service - Failed: save_service returned false');
                wp_send_json_error(['message' => 'Error al guardar el servicio. Posiblemente ya existe un servicio con ese nombre en esta categoría.']);
            }
        } catch (Exception $e) {
            error_log('VA Save Service - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

/**
 * Manejador AJAX para heredar categorías y servicios entre listados.
 * SOLUCIÓN: Ahora devuelve el HTML actualizado al tener éxito.
 */
    // =======================================================
    // === MANEJADORES PARA HERENCIA DE DATOS
    // =======================================================

public function handle_inherit_categories_services() {
    if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
        wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
        return;
    }

    $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
    $inheritance_data = isset($_POST['inheritance_data']) ? json_decode(wp_unslash($_POST['inheritance_data']), true) : [];
    $current_user_id = get_current_user_id();

    // Validaciones (se mantienen igual)...
    if (empty($professional_id) || empty($inheritance_data) || empty($current_user_id)) {
        wp_send_json_error(['message' => 'Datos incompletos para la herencia.']);
        return;
    }
    $post_author_id = get_post_field('post_author', $professional_id);
    if ($post_author_id != $current_user_id) {
        wp_send_json_error(['message' => 'No tienes permiso para heredar datos a este listado.']);
        return;
    }

    try {
        $db_handler = Veterinalia_Appointment_Database::get_instance();
        
        // El proceso de importación se mantiene igual...
        foreach ($inheritance_data['categories'] as $category_data) {
            $db_handler->save_category([
                'professional_id' => $professional_id,
                'name' => $category_data['name']
            ]);
        }
        foreach ($inheritance_data['services'] as $service_data) {
            $original_service = $db_handler->get_service_by_id($service_data['original_id']);
            if ($original_service) {
                $original_category = $db_handler->get_category_by_id($original_service->category_id);
                if ($original_category) {
                    $new_category = $db_handler->get_category_by_name_and_professional($professional_id, $original_category->name);
                    if ($new_category) {
                        $db_handler->save_service([
                            'professional_id' => $professional_id,
                            'category_id' => $new_category->category_id,
                            'name' => $service_data['name'],
                            'duration' => $original_service->duration,
                            'price' => $service_data['new_price']
                        ]);
                    }
                }
            }
        }

        // <<-- INICIO DE LA SOLUCIÓN -->>
        // Después de importar, obtenemos los datos frescos y renderizamos el HTML.
        $categories = $db_handler->get_categories_by_professional($professional_id);
        foreach ($categories as $category) {
            $category->services = $db_handler->get_services_by_category($category->category_id);
        }

        ob_start();
        if (empty($categories)) {
            echo '<p class="va-no-categories-message">Aún no has creado ninguna categoría. ¡Añade una para empezar!</p>';
        } else {
            foreach ($categories as $category) {
                // Asegúrate de que esta ruta sea correcta para tu estructura de plantillas
                include VA_PLUGIN_DIR . '/templates/category-item.php';
            }
        }
        $updated_html = ob_get_clean();
        // <<-- FIN DE LA SOLUCIÓN -->>

        wp_send_json_success([
            'message' => 'Herencia completada exitosamente.',
            'html' => $updated_html // Devolvemos el HTML en la respuesta.
        ]);

    } catch (Exception $e) {
        error_log('VA Inherit - Exception: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
    }
}

    /**
     * Manejador AJAX para obtener categorías y servicios de un listado específico.
     */
    public function handle_get_listing_categories_services() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
        $current_user_id = get_current_user_id();

        if (empty($professional_id) || empty($current_user_id)) {
            wp_send_json_error(['message' => 'Datos incompletos.']);
            return;
        }

        // Verificar permisos
        $post_author_id = get_post_field('post_author', $professional_id);
        if ($post_author_id != $current_user_id) {
            wp_send_json_error(['message' => 'No tienes permiso para ver datos de este listado.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $categories = $db_handler->get_categories_by_professional($professional_id);
            
            // Para cada categoría, cargar también sus servicios
            foreach ($categories as $category) {
                $category->services = $db_handler->get_services_by_category($category->category_id);
            }

            // Generar HTML para las categorías
            ob_start();
            if (empty($categories)) {
                echo '<p class="va-no-categories-message">Aún no has creado ninguna categoría. ¡Añade una para empezar!</p>';
            } else {
                foreach ($categories as $category) {
                    include VA_PLUGIN_DIR . '/templates/category-item.php';
                }
            }
            $html = ob_get_clean();

            wp_send_json_success([
                'html' => $html,
                'categories_count' => count($categories)
            ]);

        } catch (Exception $e) {
            error_log('VA Get Listing Data - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para editar una categoría.
     */
    public function handle_edit_category() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $current_user_id = get_current_user_id();

        if (empty($category_id) || empty($name) || empty($current_user_id)) {
            wp_send_json_error(['message' => 'Datos incompletos para editar categoría.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $category = $db_handler->get_category_by_id($category_id);
            
            if (!$category) {
                wp_send_json_error(['message' => 'Categoría no encontrada.']);
                return;
            }

            // Verificar permisos
            $post_author_id = get_post_field('post_author', $category->professional_id);
            if ($post_author_id != $current_user_id) {
                wp_send_json_error(['message' => 'No tienes permiso para editar esta categoría.']);
                return;
            }

            $result = $db_handler->update_category($category_id, ['name' => $name]);
            
            if ($result) {
                wp_send_json_success([
                    'message' => 'Categoría actualizada exitosamente.',
                    'category' => [
                        'id' => $category_id,
                        'name' => $name
                    ]
                ]);
            } else {
                wp_send_json_error(['message' => 'Error al actualizar la categoría.']);
            }

        } catch (Exception $e) {
            error_log('VA Edit Category - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para eliminar una categoría.
     */
    public function handle_delete_category() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0; // AÑADIDO para validación adicional
        $current_user_id = get_current_user_id();

        if (empty($category_id) || empty($current_user_id)) {
            wp_send_json_error(['message' => 'Datos incompletos para eliminar categoría.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $category = $db_handler->get_category_by_id($category_id);
            
            if (!$category) {
                wp_send_json_error(['message' => 'Categoría no encontrada.']);
                return;
            }

            // VALIDACIÓN ADICIONAL: Si se proporciona professional_id, verificar que coincida
            if ($professional_id && $category->professional_id != $professional_id) {
                error_log('VA Delete Category - Professional ID mismatch: expected=' . $category->professional_id . ', provided=' . $professional_id);
                wp_send_json_error(['message' => 'Error de consistencia de datos.']);
                return;
            }

            // Verificar permisos
            $post_author_id = get_post_field('post_author', $category->professional_id);
            if ($post_author_id != $current_user_id) {
                wp_send_json_error(['message' => 'No tienes permiso para eliminar esta categoría.']);
                return;
            }

            $result = $db_handler->delete_category($category_id);
            
            if ($result) {
                error_log('VA Delete Category - Success: category_id=' . $category_id . ', professional_id=' . $category->professional_id);
                wp_send_json_success(['message' => 'Categoría eliminada exitosamente.']);
            } else {
                wp_send_json_error(['message' => 'Error al eliminar la categoría.']);
            }

        } catch (Exception $e) {
            error_log('VA Delete Category - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para editar un servicio.
     */
    public function handle_edit_service() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $entry_type_id = isset($_POST['entry_type_id']) ? intval($_POST['entry_type_id']) : null;
        $current_user_id = get_current_user_id();

        if (empty($service_id) || empty($name) || $duration < 5 || $price < 0 || empty($current_user_id)) {
            wp_send_json_error(['message' => 'Datos incompletos o inválidos para editar servicio.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $service = $db_handler->get_service_by_id($service_id);
            
            if (!$service) {
                wp_send_json_error(['message' => 'Servicio no encontrado.']);
                return;
            }

            // Verificar permisos
            $post_author_id = get_post_field('post_author', $service->professional_id);
            if ($post_author_id != $current_user_id) {
                wp_send_json_error(['message' => 'No tienes permiso para editar este servicio.']);
                return;
            }

            $result = $db_handler->update_service($service_id, [
                'name' => $name,
                'duration' => $duration,
                'price' => $price,
                'entry_type_id' => $entry_type_id
            ]);
            
            if ($result) {
                wp_send_json_success(['message' => 'Servicio actualizado exitosamente.']);
            } else {
                wp_send_json_error(['message' => 'Error al actualizar el servicio.']);
            }

        } catch (Exception $e) {
            error_log('VA Edit Service - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    /**
     * Manejador AJAX para eliminar un servicio.
     */
    public function handle_delete_service() {
        if ( ! check_ajax_referer('va_appointment_nonce', 'nonce', false) ) {
            wp_send_json_error(['message' => 'Error de seguridad: nonce inválido.']);
            return;
        }

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0; // AÑADIDO para validación adicional
        $current_user_id = get_current_user_id();

        if (empty($service_id) || empty($current_user_id)) {
            wp_send_json_error(['message' => 'Datos incompletos para eliminar servicio.']);
            return;
        }

        try {
            $db_handler = Veterinalia_Appointment_Database::get_instance();
            $service = $db_handler->get_service_by_id($service_id);
            
            if (!$service) {
                wp_send_json_error(['message' => 'Servicio no encontrado.']);
                return;
            }

            // VALIDACIÓN ADICIONAL: Si se proporciona professional_id, verificar que coincida
            if ($professional_id && $service->professional_id != $professional_id) {
                error_log('VA Delete Service - Professional ID mismatch: expected=' . $service->professional_id . ', provided=' . $professional_id);
                wp_send_json_error(['message' => 'Error de consistencia de datos.']);
                return;
            }

            // Verificar permisos
            $post_author_id = get_post_field('post_author', $service->professional_id);
            if ($post_author_id != $current_user_id) {
                wp_send_json_error(['message' => 'No tienes permiso para eliminar este servicio.']);
                return;
            }

            $result = $db_handler->delete_service($service_id);
            
            if ($result) {
                error_log('VA Delete Service - Success: service_id=' . $service_id . ', professional_id=' . $service->professional_id);
                wp_send_json_success(['message' => 'Servicio eliminado exitosamente.']);
            } else {
                wp_send_json_error(['message' => 'Error al eliminar el servicio.']);
            }

        } catch (Exception $e) {
            error_log('VA Delete Service - Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }
}
