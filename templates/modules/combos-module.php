<?php
/**
 * Módulo "Combos de Servicios" v1.0 - Gestión de paquetes de servicios
 * Permite crear, editar y gestionar combos de servicios veterinarios
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : (isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0);
$db_handler = Veterinalia_Appointment_Database::get_instance();

// Verificar que las clases existan
if (!$db_handler) {
    echo '<div class="error-message">Error: No se pudieron inicializar las clases del plugin</div>';
    return;
}

// Obtener combos y servicios del profesional
try {
    // Aquí se pueden agregar métodos específicos para combos en el futuro
    $services = $db_handler->get_services_by_professional($employee_id);
    if ($services === false) {
        $services = [];
    }
} catch (Exception $e) {
    error_log('Error al obtener servicios: ' . $e->getMessage());
    $services = [];
}

// Datos simulados de combos para el desarrollo inicial
$sample_combos = [
    [
        'id' => 1,
        'name' => 'Combo Bienestar Completo',
        'description' => 'Revisión general + Vacunación + Desparasitación',
        'services' => ['Consulta General', 'Vacuna Anual', 'Desparasitación'],
        'original_price' => 120.00,
        'combo_price' => 90.00,
        'savings' => 30.00,
        'duration' => 90,
        'status' => 'active'
    ],
    [
        'id' => 2,
        'name' => 'Paquete Dental',
        'description' => 'Limpieza dental + Revisión bucal + Fluorización',
        'services' => ['Limpieza Dental', 'Revisión Bucal', 'Fluorización'],
        'original_price' => 80.00,
        'combo_price' => 65.00,
        'savings' => 15.00,
        'duration' => 60,
        'status' => 'active'
    ],
    [
        'id' => 3,
        'name' => 'Combo Cachorro',
        'description' => 'Plan completo para cachorros menores de 1 año',
        'services' => ['Primera Consulta', 'Vacunas Básicas', 'Desparasitación', 'Microchip'],
        'original_price' => 150.00,
        'combo_price' => 110.00,
        'savings' => 40.00,
        'duration' => 120,
        'status' => 'active'
    ]
];

// Preparar datos para JavaScript
$services_data = [];
if (!empty($services)) {
    foreach ($services as $service) {
        $services_data[] = [
            'id' => $service->service_id,
            'name' => $service->name,
            'duration' => $service->duration ?? 60,
            'price' => $service->price ?? 0
        ];
    }
}
?>

<div class="combos-module-container" id="combos-module" data-professional-id="<?php echo esc_attr($employee_id); ?>">
    
    <!-- ======================================================= -->
    <!-- === MODULE HEADER                                    === -->
    <!-- ======================================================= -->
    <div class="module-header">
        <!-- ESTRUCTURA DESKTOP (>480px) -->
        <div class="header-left-section">
            <a href="#" class="back-to-prof-main">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                </svg>
                <span>Volver</span>
            </a>
        </div>
        
        <div class="header-center-content">
            <h2 class="dashboard-section-title">Combos de Servicios</h2>
            <div class="combos-stats">
                <span class="stat-item">
                    <i class="fas fa-box"></i>
                    <span id="active-combos-count"><?php echo count($sample_combos); ?></span> combos activos
                </span>
            </div>
        </div>
        
        <button class="add-new-item-btn" id="add-combo-btn" title="Crear nuevo combo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path>
            </svg>
        </button>

        <!-- ESTRUCTURA MÓVIL (<=480px) -->
        <div class="mobile-top-controls">
            <div class="header-left-section">
                <a href="#" class="back-to-prof-main">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                    </svg>
                    <span>Volver</span>
                </a>
            </div>
            
            <div class="mobile-center-content">
                <h2 class="dashboard-section-title">Combos</h2>
            </div>
            
            <button class="add-new-item-btn" id="mobile-add-combo-btn" title="Crear combo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path>
                </svg>
            </button>
        </div>

        <div class="mobile-stats-row">
            <div class="combos-stats">
                <span class="stat-item">
                    <i class="fas fa-box"></i>
                    <span id="mobile-active-combos-count"><?php echo count($sample_combos); ?></span> activos
                </span>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- === VISTA DESKTOP - GRID DE COMBOS                  === -->
    <!-- ======================================================= -->
    <div class="combos-body desktop-only-view" id="combos-body-container">
        <div class="combos-grid">
            <?php foreach ($sample_combos as $combo): ?>
            <div class="combo-card" data-combo-id="<?php echo esc_attr($combo['id']); ?>">
                <div class="combo-card-header">
                    <h3 class="combo-name"><?php echo esc_html($combo['name']); ?></h3>
                    <div class="combo-actions">
                        <button class="combo-action-btn edit-combo-btn" title="Editar combo">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="combo-action-btn duplicate-combo-btn" title="Duplicar combo">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="combo-action-btn delete-combo-btn" title="Eliminar combo">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="combo-card-body">
                    <p class="combo-description"><?php echo esc_html($combo['description']); ?></p>
                    
                    <div class="combo-services">
                        <h4>Servicios incluidos:</h4>
                        <ul class="services-list">
                            <?php foreach ($combo['services'] as $service): ?>
                            <li class="service-item">
                                <i class="fas fa-check"></i>
                                <?php echo esc_html($service); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="combo-pricing">
                        <div class="price-breakdown">
                            <span class="original-price">$<?php echo number_format($combo['original_price'], 2); ?></span>
                            <span class="combo-price">$<?php echo number_format($combo['combo_price'], 2); ?></span>
                        </div>
                        <div class="savings-badge">
                            <i class="fas fa-tag"></i>
                            Ahorras $<?php echo number_format($combo['savings'], 2); ?>
                        </div>
                    </div>
                    
                    <div class="combo-meta">
                        <span class="duration-info">
                            <i class="fas fa-clock"></i>
                            <?php echo $combo['duration']; ?> min
                        </span>
                        <span class="status-badge status-<?php echo $combo['status']; ?>">
                            <?php echo ucfirst($combo['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="combo-card-footer">
                    <button class="btn-primary use-combo-btn" data-combo-id="<?php echo esc_attr($combo['id']); ?>">
                        <i class="fas fa-calendar-plus"></i>
                        Agendar Combo
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- === VISTA MÓVIL - NAVEGACIÓN CON PESTAÑAS           === -->
    <!-- ======================================================= -->
    <div class="mobile-only-view">
        <div class="mobile-views-wrapper">
            <!-- Vista Lista de Combos -->
            <div id="combos-list-view-container" class="mobile-view is-active">
                <div class="mobile-list-container">
                    <?php if ( empty( $sample_combos ) ) : ?>
                        <p class="empty-list-message">Aún no tienes combos. Toca el botón '+' para crear el primero.</p>
                    <?php else : ?>
                        <?php foreach ($sample_combos as $combo): ?>
                            <div class="mobile-list-item combo-item" data-combo-id="<?php echo esc_attr($combo['id']); ?>">
                                <div class="item-content">
                                    <div class="item-header">
                                        <span class="item-name"><?php echo esc_html($combo['name']); ?></span>
                                    </div>
                                    <div class="item-description"><?php echo esc_html($combo['description']); ?></div>
                                    <div class="item-meta">
                                        <span class="item-duration">
                                            <i class="fas fa-clock"></i> <?php echo $combo['duration']; ?> min
                                        </span>
                                        <span class="item-savings">
                                            <i class="fas fa-tag"></i> Ahorras $<?php echo number_format($combo['savings'], 2); ?>
                                        </span>
                                        <span class="item-price">
                                            <i class="fas fa-dollar-sign"></i> $<?php echo number_format($combo['combo_price'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="item-actions-btn" aria-label="Acciones para <?php echo esc_attr($combo['name']); ?>">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vista Detalles de Combo -->
            <div id="combo-details-view-container" class="mobile-view">
                <div class="mobile-detail-container" id="combo-details-target">
                    <p class="loading-message">Cargando detalles del combo...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- === MODALES                                          === -->
    <!-- ======================================================= -->
    
    <!-- Modal para crear/editar combo -->
    <div id="combo-modal" class="modal-overlay hidden">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="combo-modal-title" class="modal-title">Crear Nuevo Combo</h3>
                <button class="modal-close-btn" id="close-combo-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="combo-form" class="combo-form">
                <div class="form-section">
                    <h4 class="section-title">Información Básica</h4>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label for="combo-name" class="form-label">Nombre del Combo</label>
                            <input type="text" id="combo-name" class="form-input" required placeholder="Ej: Combo Bienestar Completo">
                        </div>
                        <div class="form-group">
                            <label for="combo-duration" class="form-label">Duración Total (minutos)</label>
                            <input type="number" id="combo-duration" class="form-input" required min="15" step="15" placeholder="90">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="combo-description" class="form-label">Descripción</label>
                        <textarea id="combo-description" class="form-input form-textarea" rows="3" placeholder="Describe qué incluye este combo..."></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Servicios del Combo</h4>
                    <div class="services-selector">
                        <div class="available-services">
                            <h5>Servicios Disponibles</h5>
                            <div class="services-search">
                                <input type="text" id="services-search" class="form-input" placeholder="Buscar servicios...">
                            </div>
                            <div class="services-list" id="available-services-list">
                                <?php foreach ($services_data as $service): ?>
                                <div class="service-option" data-service-id="<?php echo esc_attr($service['id']); ?>" 
                                     data-service-name="<?php echo esc_attr($service['name']); ?>"
                                     data-service-price="<?php echo esc_attr($service['price']); ?>"
                                     data-service-duration="<?php echo esc_attr($service['duration']); ?>">
                                    <span class="service-name"><?php echo esc_html($service['name']); ?></span>
                                    <span class="service-price">$<?php echo number_format($service['price'], 2); ?></span>
                                    <button type="button" class="btn-small add-service-btn">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="selected-services">
                            <h5>Servicios en el Combo</h5>
                            <div class="selected-services-list" id="selected-services-list">
                                <div class="empty-selection">
                                    <i class="fas fa-arrow-left"></i>
                                    <p>Selecciona servicios de la izquierda</p>
                                </div>
                            </div>
                            <div class="combo-totals" id="combo-totals" style="display: none;">
                                <div class="totals-row">
                                    <span>Precio individual:</span>
                                    <span id="total-individual-price">$0.00</span>
                                </div>
                                <div class="totals-row">
                                    <span>Duración total:</span>
                                    <span id="total-duration">0 min</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Precio del Combo</h4>
                    <div class="pricing-section">
                        <div class="form-group">
                            <label for="combo-price" class="form-label">Precio del Combo</label>
                            <div class="price-input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="combo-price" class="form-input price-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="savings-display" id="savings-display" style="display: none;">
                            <div class="savings-amount">
                                <span class="label">Ahorro:</span>
                                <span class="amount" id="savings-amount">$0.00</span>
                            </div>
                            <div class="discount-percentage">
                                <span id="discount-percentage">0%</span> de descuento
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" id="cancel-combo-btn" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Combo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div id="delete-combo-modal" class="modal-overlay hidden">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3 class="modal-title">Eliminar Combo</h3>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres eliminar este combo?</p>
                <p class="warning-text">Esta acción no se puede deshacer.</p>
            </div>
            <div class="form-actions">
                <button type="button" id="cancel-delete-btn" class="btn-secondary">Cancelar</button>
                <button type="button" id="confirm-delete-btn" class="btn-danger">
                    <i class="fas fa-trash"></i>
                    Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="combos-initial-data">
{
    "professional_id": <?php echo intval($employee_id); ?>,
    "combos": <?php echo json_encode($sample_combos); ?>,
    "services": <?php echo json_encode($services_data); ?>,
    "nonce": "<?php echo wp_create_nonce('va_combos_nonce'); ?>",
    "ajax_url": "<?php echo admin_url('admin-ajax.php'); ?>"
}
</script>

<?php
// Los archivos CSS y JS se cargan desde el manager principal
?>
