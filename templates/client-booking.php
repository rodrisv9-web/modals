<?php
if (!defined('ABSPATH')) { exit; }
/**
 * Plantilla para el formulario de reserva del cliente (V2 - Wizard por Pasos).
 *
 * Si el usuario es cliente con mascotas vinculadas, se usa el wizard simplificado.
 */
?>

<div class="booking-wizard" data-professional-id="<?php echo esc_attr( $professional_id ); ?>">
    <!-- Indicador de Pasos -->
    <div class="booking-steps">
        <div class="step active" data-step="1"><div class="step-circle">1</div><div class="step-title">Servicio</div></div>
        <div class="step" data-step="2"><div class="step-circle">2</div><div class="step-title">Fecha y Hora</div></div>
        <div class="step" data-step="3"><div class="step-circle">3</div><div class="step-title">Tus Datos</div></div>
        <div class="step" data-step="4"><div class="step-circle"><i class="fas fa-check"></i></div><div class="step-title">Confirmar</div></div>
    </div>

    <!-- Contenido de los Pasos -->
    <div class="booking-content">
        <!-- Paso 1: Seleccionar Servicio -->
        <div class="booking-step active" id="step-1">
            <div class="step-header">
                <h3>Selecciona un Servicio</h3>
                <p>Elige el servicio que deseas agendar.</p>
            </div>
            <?php if ( empty( $categories ) ) : ?>
                <p>Este profesional no tiene servicios disponibles para reservar.</p>
            <?php else : ?>
                <div class="service-selection-layout">
                    <div class="category-tabs">
                        <?php $first_category = true; foreach ( $categories as $category ) : if ( !empty( $category->services ) ) : ?>
                            <div class="category-tab <?php echo $first_category ? 'active' : ''; ?>" data-category="cat-<?php echo esc_attr($category->category_id); ?>">
                                <i class="fas fa-tag"></i><span><?php echo esc_html($category->name); ?></span>
                            </div>
                        <?php $first_category = false; endif; endforeach; ?>
                    </div>
                    <div class="service-list-content">
                        <?php $first_category = true; foreach ( $categories as $category ) : if ( !empty( $category->services ) ) : ?>
                            <div class="service-list <?php echo $first_category ? 'active' : ''; ?>" id="services-cat-<?php echo esc_attr($category->category_id); ?>">
                                <?php foreach ( $category->services as $service ) : ?>
                                    <div class="service-item" data-service-id="<?php echo esc_attr( $service->service_id ); ?>" data-duration="<?php echo esc_attr( $service->duration ); ?>" data-name="<?php echo esc_attr( $service->name ); ?>">
                                        <div>
                                            <div class="service-name"><?php echo esc_html( $service->name ); ?></div>
                                            <div class="service-details"><?php echo esc_html( $service->duration ); ?> min - $<?php echo esc_html( $service->price ); ?></div>
                                        </div>
                                        <button class="btn btn-primary va-select-service-btn">Seleccionar</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php $first_category = false; endif; endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paso 2: Elegir Fecha y Hora -->
        <div class="booking-step" id="step-2">
            <div class="step-header">
                <h3>Elige Fecha y Hora</h3>
                <p>Servicio: <strong id="va-selected-service-name-step2">-</strong></p>
            </div>
            <div class="va-calendar-wrapper">
                <div id="va-calendar-header"></div>
                <div id="va-calendar-grid"></div>
            </div>
            <div id="va-slots-container" class="time-slots-wrapper">
                <p class="initial-message">Selecciona una fecha para ver los horarios.</p>
            </div>
        </div>

        <!-- Paso 3: Tus Datos -->
        <div class="booking-step" id="step-3">
            <div class="step-header">
                <h3>Completa tus Datos</h3>
                <p>Necesitamos algunos datos para confirmar tu cita.</p>
            </div>

            <?php if ($is_client_with_pets): ?>
                <!-- Selector de mascota para clientes con mascotas vinculadas -->
                <div class="client-pet-selector-section">
                    <h4>Selecciona tu Mascota</h4>
                    <p>Elige la mascota para la que quieres agendar la cita:</p>
                    <div class="client-pet-selector">
                        <?php foreach ($client_pets as $pet): ?>
                            <div class="pet-selector-item"
                                 data-pet-id="<?php echo esc_attr($pet->pet_id); ?>"
                                 data-pet-name="<?php echo esc_attr($pet->name); ?>"
                                 data-pet-species="<?php echo esc_attr($pet->species); ?>"
                                 data-pet-breed="<?php echo esc_attr($pet->breed ?: ''); ?>"
                                 data-pet-gender="<?php echo esc_attr($pet->gender ?: 'unknown'); ?>">
                                <div class="pet-selector-avatar">
                                    <img src="https://placehold.co/50x50/EBF8FF/3182CE?text=<?php echo urlencode(substr($pet->name, 0, 2)); ?>"
                                         alt="Mascota <?php echo esc_attr($pet->name); ?>">
                                </div>
                                <div class="pet-selector-info">
                                    <div class="pet-selector-name"><?php echo esc_html($pet->name); ?></div>
                                    <div class="pet-selector-details">
                                        <?php echo esc_html(ucfirst($pet->species)); ?>
                                        <?php if($pet->breed): ?> - <?php echo esc_html($pet->breed); ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="pet-selector-indicator">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form id="va-client-details-form" class="<?php echo $is_client_with_pets ? 'client-has-pets' : ''; ?>">
                <div class="pet-fields">
                    <div class="form-grid">
                        <div class="form-group"><label for="va-pet-name">Nombre de tu Mascota</label><input type="text" id="va-pet-name" name="pet_name" required></div>
                        <div class="form-group">
                            <label for="va-pet-species">Tipo de Animal</label>
                            <select id="va-pet-species" name="pet_species" required>
                                <option value="">Selecciona el tipo</option>
                                <option value="Perro">Perro</option>
                                <option value="Gato">Gato</option>
                                <option value="Ave">Ave</option>
                                <option value="Conejo">Conejo</option>
                                <option value="Hurón">Hurón</option>
                                <option value="Pez">Pez</option>
                                <option value="Reptil">Reptil</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label for="va-pet-breed">Raza</label><input type="text" id="va-pet-breed" name="pet_breed" placeholder="Ej: Labrador, Siamés, etc."></div>
                        <div class="form-group">
                            <label for="va-pet-gender">Género</label>
                            <select id="va-pet-gender" name="pet_gender">
                                <option value="unknown">No especificado</option>
                                <option value="male">Macho</option>
                                <option value="female">Hembra</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if ( ! empty( $current_user_id ) && ! empty( $current_user_info ) ) : ?>
                    <div class="client-summary">
                        <p>Reservando como: <strong><?php echo esc_html( $current_user_info['name'] ); ?></strong>
                        — <?php echo esc_html( $current_user_info['email'] ); ?>
                        <?php if ( ! empty( $current_user_info['phone'] ) ) : ?> — <?php echo esc_html( $current_user_info['phone'] ); ?><?php endif; ?></p>
                        <p><a href="<?php echo esc_url( get_edit_profile_url( $current_user_id ) ); ?>" target="_blank">Editar mi perfil</a></p>
                    </div>

                    <!-- Enviar los datos del usuario logueado como inputs ocultos -->
                    <input type="hidden" id="va-current-user-wp-id" name="current_user_wp_id" value="<?php echo esc_attr( $current_user_info['wp_id'] ); ?>">
                    <input type="hidden" id="va-client-name" name="client_name" value="<?php echo esc_attr( $current_user_info['name'] ); ?>">
                    <input type="hidden" id="va-client-email" name="client_email" value="<?php echo esc_attr( $current_user_info['email'] ); ?>">
                    <input type="hidden" id="va-client-phone" name="client_phone" value="<?php echo esc_attr( $current_user_info['phone'] ); ?>">

                    <div class="form-group"><label for="va-notes">Notas (opcional)</label><textarea id="va-notes" name="notes" rows="3"></textarea></div>

                <?php else : ?>
                    <div class="form-grid">
                        <div class="form-group"><label for="va-client-name">Tu Nombre</label><input type="text" id="va-client-name" name="client_name" required></div>
                        <div class="form-group"><label for="va-client-email">Correo Electrónico</label><input type="email" id="va-client-email" name="client_email" required></div>
                    </div>

                    <div class="form-group"><label for="va-client-phone">Teléfono</label><input type="tel" id="va-client-phone" name="client_phone"></div>
                    <div class="form-group"><label for="va-notes">Notas (opcional)</label><textarea id="va-notes" name="notes" rows="3"></textarea></div>
                <?php endif; ?>

                <script>
                (function(){
                    try {
                        <?php if ( ! empty( $current_user_id ) && ! empty( $current_user_info ) ) : ?>
                            console.log('VA: Usuario logueado (ID <?php echo esc_js( $current_user_info['wp_id'] ); ?>) - campos de contacto ocultos y enviados como hidden inputs.');
                        <?php else : ?>
                            console.log('VA: Usuario no logueado - mostrando campos de contacto para completar.');
                        <?php endif; ?>
                    } catch(e) {}
                })();
                </script>
            </form>
        </div>

        <!-- Paso 4: Confirmar -->
        <div class="booking-step" id="step-4">
            <div class="step-header">
                <h3>Confirma tu Cita</h3>
                <p>Por favor, revisa que todos los datos sean correctos.</p>
            </div>
            <div class="summary" id="va-booking-summary"></div>
        </div>
    </div>

    <!-- Navegación -->
    <div class="booking-navigation">
        <button class="btn btn-secondary hidden" id="va-prev-step-btn">Anterior</button>
        <button class="btn btn-primary hidden" id="va-next-step-btn">Siguiente</button>
        <button class="btn btn-success hidden" id="va-confirm-booking-btn">Confirmar Cita</button>
    </div>
</div>
