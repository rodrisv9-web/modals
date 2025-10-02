<?php
/**
 * Administración de Cache para Veterinalia Appointment Plugin
 * 
 * Proporciona una interfaz de administración para monitorear y gestionar el cache
 * 
 * @package Veterinalia_Appointment
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class VA_Cache_Admin {
    
    /**
     * Inicializa los hooks de administración
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('wp_ajax_va_flush_cache', [__CLASS__, 'handle_flush_cache']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }
    
    /**
     * Agrega el menú de administración del cache
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Veterinalia Cache',
            'VA Cache',
            'manage_options',
            'va-cache-admin',
            [__CLASS__, 'render_admin_page']
        );
    }
    
    /**
     * Encola scripts para la página de administración
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_va-cache-admin') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'va_cache_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('va_cache_admin_nonce'),
            'flush_text' => __('Limpiar Cache', 'veterinalia-appointment'),
            'flushing_text' => __('Limpiando...', 'veterinalia-appointment'),
        ]);
        
        // CSS inline simple para la página
        wp_add_inline_style('wp-admin', '
            .va-cache-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
            .va-cache-stat-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; }
            .va-cache-stat-number { font-size: 32px; font-weight: bold; color: #0073aa; }
            .va-cache-stat-label { color: #646970; margin-top: 5px; }
            .va-cache-actions { margin: 20px 0; }
            .va-cache-log { background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; }
        ');
    }
    
    /**
     * Renderiza la página de administración del cache
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.'));
        }
        
        $cache_stats = VA_Cache_Helper::get_stats();
        $config_stats = VA_Config::get_cache_stats();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="va-cache-stats">
                <div class="va-cache-stat-box">
                    <div class="va-cache-stat-number"><?php echo esc_html($cache_stats['services'] ?? 0); ?></div>
                    <div class="va-cache-stat-label">Servicios en Cache</div>
                </div>
                
                <div class="va-cache-stat-box">
                    <div class="va-cache-stat-number"><?php echo esc_html($cache_stats['categories'] ?? 0); ?></div>
                    <div class="va-cache-stat-label">Categorías en Cache</div>
                </div>
                
                <div class="va-cache-stat-box">
                    <div class="va-cache-stat-number"><?php echo esc_html($cache_stats['pet_access'] ?? 0); ?></div>
                    <div class="va-cache-stat-label">Accesos de Mascotas</div>
                </div>
                
                <div class="va-cache-stat-box">
                    <div class="va-cache-stat-number"><?php echo esc_html($cache_stats['config'] ?? 0); ?></div>
                    <div class="va-cache-stat-label">Configuraciones</div>
                </div>
                
                <div class="va-cache-stat-box">
                    <div class="va-cache-stat-number"><?php echo esc_html($config_stats['session_cache_count'] ?? 0); ?></div>
                    <div class="va-cache-stat-label">Cache de Sesión</div>
                </div>
            </div>
            
            <div class="va-cache-actions">
                <button type="button" class="button button-secondary" id="va-flush-cache">
                    <?php _e('Limpiar Todo el Cache', 'veterinalia-appointment'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="va-refresh-stats">
                    <?php _e('Actualizar Estadísticas', 'veterinaria-appointment'); ?>
                </button>
                
                <p class="description">
                    <?php _e('El cache se limpia automáticamente cuando se modifican datos. Solo usa esta opción para troubleshooting.', 'veterinalia-appointment'); ?>
                </p>
            </div>
            
            <h2><?php _e('Configuraciones Cacheadas', 'veterinalia-appointment'); ?></h2>
            <div class="va-cache-log">
                <?php
                foreach ($config_stats['session_cached_options'] as $option) {
                    echo esc_html($option) . "\n";
                }
                ?>
            </div>
            
            <h2><?php _e('Información del Sistema', 'veterinalia-appointment'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong><?php _e('Transients Soportados', 'veterinalia-appointment'); ?></strong></td>
                        <td><?php echo function_exists('get_transient') ? '✅ Sí' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Object Cache', 'veterinalia-appointment'); ?></strong></td>
                        <td><?php echo wp_using_ext_object_cache() ? '✅ Externo (Redis/Memcached)' : '⚠️ Base de datos'; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Debug Activo', 'veterinalia-appointment'); ?></strong></td>
                        <td><?php echo (defined('WP_DEBUG') && WP_DEBUG) ? '✅ Sí (logs detallados)' : '❌ No'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#va-flush-cache').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                
                button.prop('disabled', true).text('<?php _e('Limpiando...', 'veterinalia-appointment'); ?>');
                
                $.post(ajaxurl, {
                    action: 'va_flush_cache',
                    nonce: va_cache_admin.nonce
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Cache limpiado exitosamente', 'veterinalia-appointment'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Error al limpiar cache', 'veterinalia-appointment'); ?>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text(originalText);
                });
            });
            
            $('#va-refresh-stats').on('click', function() {
                location.reload();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Maneja la petición AJAX para limpiar cache
     */
    public static function handle_flush_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'va_cache_admin_nonce')) {
            wp_die(__('Nonce inválido'));
        }
        
        try {
            VA_Cache_Helper::flush_all();
            VA_Config::flush_cache();
            
            wp_send_json_success([
                'message' => __('Cache limpiado exitosamente', 'veterinalia-appointment')
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error al limpiar cache: ', 'veterinalia-appointment') . $e->getMessage()
            ]);
        }
    }
}
