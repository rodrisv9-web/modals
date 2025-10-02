<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="va-category-item" data-category-id="<?php echo esc_attr($category->category_id); ?>">
    <div class="va-category-header">
        <h5 class="va-category-title"><?php echo esc_html($category->name); ?></h5>
        <div class="va-category-actions">
            <button class="va-edit-category-btn">Editar</button>
            <button class="va-delete-category-btn">Eliminar</button>
        </div>
    </div>
    <div class="va-services-list">
        <?php if (empty($category->services)) : ?>
            <p class="va-no-services-message">No hay servicios en esta categoría.</p>
        <?php else : ?>
            <?php foreach ($category->services as $service) : ?>
                <div class="va-service-item" data-service-id="<?php echo esc_attr($service->service_id); ?>">
                    <div class="va-service-info">
                        <h6 class="va-service-name"><?php echo esc_html($service->name); ?></h6>
                        <p class="va-service-details">Duración: <?php echo esc_html($service->duration); ?> min | Precio: $<?php echo esc_html($service->price); ?></p>
                        <?php if (!empty($service->description)) : ?>
                            <p class="va-service-description"><?php echo esc_html($service->description); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="va-service-actions">
                        <button class="va-edit-service-btn">Editar</button>
                        <button class="va-delete-service-btn">Eliminar</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="va-add-service-form">
        <h6>Añadir un nuevo servicio a "<?php echo esc_html($category->name); ?>"</h6>
        <form class="va-new-service-form">
            <input type="hidden" name="professional_id" value="<?php echo esc_attr($professional_id); ?>">
            <input type="hidden" name="category_id" value="<?php echo esc_attr($category->category_id); ?>">
            <div class="va-form-grid">
                <input type="text" name="service_name" placeholder="Nombre del servicio" required>
                <input type="number" name="service_duration" placeholder="Duración (min)" min="5" step="5" required>
                <input type="number" name="service_price" placeholder="Precio ($)" min="0" step="0.01" required>
                <button type="submit">Añadir Servicio</button>
            </div>
        </form>
    </div>
</div>