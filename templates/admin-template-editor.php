<?php
// templates/admin-template-editor.php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * @var object $template El objeto completo de la plantilla con sus categorías y servicios.
 */
?>
<div id="va-template-editor-<?php echo esc_attr( $template->template_id ); ?>" class="va-template-editor-container">
    <hr>
    <h3>Editando Plantilla: <?php echo esc_html( $template->template_name ); ?></h3>

    <div id="va-template-categories-container">
        <?php if ( empty( $template->categories ) ) : ?>
            <p class="va-no-items-message">Esta plantilla aún no tiene categorías. ¡Añade la primera!</p>
        <?php else : ?>
            <?php foreach ( $template->categories as $category ) : ?>
                <?php
                // Se añade esta línea para incluir el nuevo template
                include VA_PLUGIN_DIR . '/templates/admin-template-category-item.php';
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="va-add-category-to-template-form">
        <h4>Añadir Nueva Categoría a la Plantilla</h4>
        <form>
            <input type="text" name="category_name" placeholder="Nombre de la nueva categoría" required>
            <input type="hidden" name="template_id" value="<?php echo esc_attr( $template->template_id ); ?>">
            <button type="submit" class="button">Añadir Categoría</button>
        </form>
    </div>
</div> 