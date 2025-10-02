<?php
// templates/admin-template-category-item.php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @var object $category El objeto de la categoría de la plantilla.
 * @var int $template_id El ID de la plantilla padre.
 */
?>
<div class="va-template-category-item" data-category-id="<?php echo esc_attr( $category->template_category_id ); ?>">
    <h4><?php echo esc_html( $category->category_name ); ?></h4>
    <div class="va-template-services-list">
        <?php if ( empty( $category->services ) ) : ?>
            <p>No hay servicios en esta categoría.</p>
        <?php else : ?>
            <ul>
            <?php foreach ( $category->services as $service ) : ?>
                <li><?php echo esc_html( $service->service_name ); ?> (<?php echo esc_html( $service->suggested_duration ); ?> min, $<?php echo esc_html( $service->suggested_price ); ?>)</li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="va-add-service-to-template-form">
        <form>
            <input type="hidden" name="template_category_id" value="<?php echo esc_attr( $category->template_category_id ); ?>">
            <div class="va-form-grid">
                <input type="text" name="service_name" placeholder="Nombre del servicio" required>
                <input type="number" name="suggested_duration" placeholder="Duración (min)" min="5" step="5" required>
                <input type="number" name="suggested_price" placeholder="Precio ($)" min="0" step="0.01" required>
                <button type="submit" class="button button-primary">Añadir Servicio</button>
            </div>
        </form>
    </div>
</div>