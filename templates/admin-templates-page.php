<?php
// templates/admin-templates-page.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Lógica para obtener las plantillas de la base de datos.
// (Las plantillas deben estar cargadas por el controlador que incluye esta plantilla: `render_templates_page()`)
if ( ! isset( $templates ) ) {
    $db_handler = Veterinalia_Templates_Database::get_instance();
    $templates = $db_handler->get_templates();
}
?>

<div class="wrap">
    <h1>
        <?php echo esc_html( get_admin_page_title() ); ?>
        <a href="#" id="va-add-new-template-btn" class="page-title-action">Crear Nueva Plantilla</a>
    </h1>

    <p>Aquí puedes crear y gestionar las plantillas de servicios predefinidas para los diferentes roles de profesionales.</p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column">Nombre de la Plantilla</th>
                <th scope="col" class="manage-column">Tipo de Profesional</th>
                <th scope="col" class="manage-column">Descripción</th>
                <th scope="col" class="manage-column">Estado</th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if ( ! empty( $templates ) ) : ?>
                <?php foreach ( $templates as $template ) : ?>
                    <tr data-template-id="<?php echo esc_attr( $template->template_id ); ?>">
                        <td>
                            <strong><a href="#"><?php echo esc_html( $template->template_name ); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="#">Editar</a> | </span>
                                <span class="trash"><a href="#" class="submitdelete">Eliminar</a></span>
                            </div>
                        </td>
                        <td><?php echo esc_html( $template->professional_type ); ?></td>
                        <td><?php echo esc_html( $template->description ); ?></td>
                        <td><?php echo $template->is_active ? 'Activa' : 'Inactiva'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">No se han encontrado plantillas. ¡Crea la primera!</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div> 