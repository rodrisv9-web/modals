<?php
if (!defined('ABSPATH')) { exit; }
?>
<div class="va-professional-services-dashboard" data-professional-id="<?php echo esc_attr($professional_id); ?>">

    <div id="module-container" class="module-container" role="main">
        <header class="module-header">
            <div class="header-content">
                <div>
                    <h1>Administrar Servicios</h1>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn btn-secondary" id="va-import-from-template-btn"><i class="fas fa-file-import" aria-hidden="true"></i><span>De Plantilla</span></button>
                    <button type="button" id="manage-btn" class="btn btn-primary"><i class="fas fa-edit" aria-hidden="true"></i><span id="manage-btn-text">Gestionar</span></button>
                </div>
            </div>
        </header>

        <div class="module-body">
            <aside class="category-sidebar" role="navigation">
                <div class="category-sidebar-header">
                    <h2>Categorías</h2>
                    <button type="button" class="add-category-btn" id="va-add-category-btn" title="Añadir Categoría" aria-label="Añadir nueva categoría"><i class="fas fa-plus-circle fa-lg" aria-hidden="true"></i></button>
                </div>
                <div id="mobile-category-nav" class="mobile-category-nav"></div>
                <nav id="desktop-category-nav" class="desktop-category-nav" aria-label="Navegación de categorías"></nav>
            </aside>

            <main id="services-content-area" class="services-content-area" role="region" aria-live="polite"></main>
        </div>
    </div>

    <div id="modal-backdrop" class="modal-backdrop hidden fade-in" role="presentation"></div>
    <div id="service-modal" class="modal-container hidden" role="dialog" aria-modal="true"></div>
    <div id="category-modal" class="modal-container hidden" role="dialog" aria-modal="true"></div>
    <div id="template-import-modal" class="modal-container hidden" role="dialog" aria-modal="true"></div>
    <div id="confirmation-modal" class="modal-container hidden" role="dialog" aria-modal="true"></div>
    <div id="mobile-category-actions-modal" class="modal-container hidden" role="dialog" aria-modal="true"></div>

    <script type="application/json" id="va-services-initial-data">
    <?php
        $cats = [];
        foreach ($categories as $c) {
            $cats[] = [
                'id'   => (int) $c->category_id,
                'slug' => sanitize_title($c->name),
                'name' => $c->name,
                'color'=> '#4f46e5',
                'icon' => 'fa-circle'
            ];
        }
        $svcs = [];
        foreach ($categories as $c) {
            if (!empty($c->services)) {
                foreach ($c->services as $s) {
                    $svcs[] = [
                        'id' => (int) $s->service_id,
                        'category_slug' => sanitize_title($c->name),
                        'name' => $s->name,
                        'duration' => (int) $s->duration,
                        'price' => (float) $s->price,
                        'description' => isset($s->description) ? $s->description : '',
                        'entry_type_id' => isset($s->entry_type_id) ? intval($s->entry_type_id) : 0
                    ];
                }
            }
        }
        echo wp_json_encode([ 'categories' => $cats, 'services' => $svcs ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    </script>

</div>