<?php
/**
 * Módulo "services" v3: Unificado para móvil y escritorio.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : 0;
$db_handler  = Veterinalia_Appointment_Database::get_instance();
$categories  = $db_handler->get_categories_by_professional( $employee_id );
?>

<div class="services-module-container" id="services-module-v2" data-professional-id="<?php echo esc_attr($employee_id); ?>">
    
    <!-- ======================================================= -->
    <!-- === 1. ENCABEZADO COMÚN PARA AMBAS VISTAS           === -->
    <!-- ======================================================= -->
    <div class="module-header">
        <a href="#" class="back-to-prof-main">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg>
            <span>Volver</span>
        </a>
        <h2 class="dashboard-section-title">Administrar Servicios</h2>
        <button class="add-new-item-btn" id="show-add-actions-modal-btn" title="Añadir nuevo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
        </button>
    </div>

    <!-- ======================================================= -->
    <!-- === 2. VISTA DE ESCRITORIO (SIDEBAR + CONTENIDO)    === -->
    <!-- ======================================================= -->
    <div class="module-body desktop-only-view">
        <aside class="category-sidebar" role="navigation">
            <div class="category-sidebar-header">
                <h2>Categorías</h2>
            </div>
            <nav id="desktop-category-nav" class="desktop-category-nav" aria-label="Navegación de categorías">
                <?php if ( empty( $categories ) ) : ?>
                    <p class="empty-list-message">Sin categorías.</p>
                <?php else : ?>
                    <?php foreach ( $categories as $category ) : ?>
                        <div class="category-tab" data-category-id="<?php echo esc_attr($category->category_id); ?>">
                            <div class="tab-content">
                                <i class="fas fa-tag fa-fw"></i>
                                <span><?php echo esc_html($category->name); ?></span>
                            </div>
                            <div class="category-actions">
                                <button class="action-icon edit-category-btn" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                                <button class="action-icon delete-category-btn delete-icon" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>
        </aside>

        <main id="services-content-area" class="services-content-area" role="region" aria-live="polite">
            <!-- El contenido de los servicios se cargará aquí con JavaScript -->
            <div class="empty-state">
                <i class="fas fa-mouse-pointer"></i>
                <h3>Selecciona una categoría</h3>
                <p>Elige una categoría de la izquierda para ver sus servicios.</p>
            </div>
        </main>
    </div>

    <!-- ======================================================= -->
    <!-- === 3. VISTA MÓVIL (LA QUE YA FUNCIONABA)           === -->
    <!-- ======================================================= -->
    <div class="mobile-only-view">
        <div class="mobile-views-wrapper">
            <div id="category-view-container" class="mobile-view is-active">
                <div class="mobile-list-container">
                    <?php if ( empty( $categories ) ) : ?>
                        <p class="empty-list-message">Aún no tienes categorías. Toca el botón '+' para crear la primera.</p>
                    <?php else : ?>
                        <?php foreach ( $categories as $category ) : ?>
                            <div class="mobile-list-item category-item" data-category-id="<?php echo esc_attr($category->category_id); ?>" data-category-name="<?php echo esc_attr($category->name); ?>">
                                <span class="item-name"><?php echo esc_html($category->name); ?></span>
                                <button class="item-actions-btn" aria-label="Acciones para <?php echo esc_attr($category->name); ?>">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="service-view-container" class="mobile-view">
                <div class="mobile-list-container" id="services-list-target">
                    <p class="loading-message">Cargando servicios...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- === 4. MODALES Y ELEMENTOS OCULTOS (COMUNES)        === -->
    <!-- ======================================================= -->
    <div id="mobile-add-actions-container" class="modal-overlay action-sheet">
        <div class="modal-content">
            <div class="action-sheet-header">
                <h3>¿Qué quieres hacer?</h3>
            </div>
            <div class="action-sheet-options">
                <button class="action-option" id="action-create-category">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Crear Nueva Categoría</span>
                        <span class="action-subtitle">Añadir una nueva categoría de servicios</span>
                    </div>
                </button>
                <button class="action-option" id="action-import-template">
                    <div class="action-option-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                    <div class="action-option-text">
                        <span class="action-title">Importar desde Plantilla</span>
                        <span class="action-subtitle">Usar plantillas predefinidas</span>
                    </div>
                </button>
            </div>
            <button class="action-sheet-cancel" id="action-cancel-add">Cancelar</button>
        </div>
    </div>
</div>