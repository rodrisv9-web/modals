<?php
/**
 * Módulo "Mi Catálogo" - Gestión de Productos/Inventario
 * Permite al profesional gestionar su lista personal de productos.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$employee_id = isset($request) ? intval($request->get_param('employee_id')) : 0;
?>

<div class="catalog-module-container" id="catalog-module" data-professional-id="<?php echo esc_attr($employee_id); ?>">
    
    <div class="module-header">
        <a href="#" class="back-to-prof-main">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path></svg>
            <span>Volver</span>
        </a>
        <h2 class="dashboard-section-title">Mi Catálogo de Productos</h2>
        <button class="add-new-item-btn" id="add-product-btn" title="Añadir nuevo producto">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
        </button>
    </div>

    <div class="catalog-body">
        <div class="catalog-filters">
            <input type="text" id="product-search" placeholder="Buscar producto...">
            <select id="product-type-filter">
                <option value="all">Todos los tipos</option>
                <option value="Analgésico">Analgésicos</option>
                <option value="Antiinflamatorio">Antiinflamatorios</option>
                <option value="Antimicrobiano">Antimicrobianos</option>
                <option value="Antiparasitario">Antiparasitarios</option>
                <option value="Antibiótico">Antibióticos</option>
                <option value="Biológico">Biológicos</option>
                <option value="Dermatológico">Dermatológicos</option>
                <option value="Gastrointestinal">Gastrointestinales</option>
                <option value="Nutricional">Nutricionales</option>
                <option value="Ótico">Óticos</option>
                <option value="Otro">Otros</option>
                <option value="Salud y Belleza">Salud y Belleza</option>
                <option value="Vacuna">Vacunas</option>
            </select>
        </div>

        <div class="products-grid" id="products-grid-container">
            <div class="loading-placeholder">
                <div class="loader"></div>
                <p>Cargando productos...</p>
            </div>
        </div>
    </div>

    <div id="product-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="product-modal-title" class="modal-title">Nuevo Producto</h3>
                <button class="modal-close-btn" id="close-product-modal">&times;</button>
            </div>
            
            <form id="product-form" class="product-form">
                <input type="hidden" id="product-id" name="product_id">
                <div class="form-section">
                    <div class="form-group">
                        <label for="product-name" class="form-label">Nombre del Producto*</label>
                        <input type="text" id="product-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="product-type" class="form-label">Tipo de Producto*</label>
                        <select id="product-type" class="form-input" required>
                            <option value="">Selecciona un tipo</option>
                            <option value="Analgésico">Analgésico</option>
                            <option value="Antiinflamatorio">Antiinflamatorio</option>
                            <option value="Antimicrobiano">Antimicrobiano</option>
                            <option value="Antiparasitario">Antiparasitario</option>
                            <option value="Antibiótico">Antibiótico</option>
                            <option value="Biológico">Biológico</option>
                            <option value="Dermatológico">Dermatológico</option>
                            <option value="Gastrointestinal">Gastrointestinal</option>
                            <option value="Nutricional">Nutricional</option>
                            <option value="Ótico">Ótico</option>
                            <option value="Otro">Otro</option>
                            <option value="Salud y Belleza">Salud y Belleza</option>
                            <option value="Vacuna">Vacuna</option>
                        </select>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label for="product-manufacturer" class="form-label">Fabricante</label>
                            <input type="text" id="product-manufacturer" class="form-input">
                        </div>
                        <div class="form-group">
                            <label for="product-presentation" class="form-label">Presentación</label>
                            <input type="text" id="product-presentation" class="form-input" placeholder="Ej: Suspensión 1ml">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="product-active-ingredient" class="form-label">Principio Activo</label>
                        <input type="text" id="product-active-ingredient" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="product-notes" class="form-label">Notas Internas</label>
                        <textarea id="product-notes" class="form-input form-textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancel-product-btn" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>
