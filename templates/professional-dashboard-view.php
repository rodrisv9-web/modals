<div class="dashboard-container">
    <main class="dashboard-main">
        <div class="dashboard-content">
            <?php 
            $user_type = get_user_meta( get_current_user_id(), '_user_type', true );
            if ( $user_type === 'author' ) : ?>
                <?php // ----- INICIO: El usuario ES un profesional (_user_type = author) ----- ?>
                <div id="view-professional">
                    <div id="professional-main-view">
                        <div class="space-y-8">
                            <div>
                                <h2 class="dashboard-section-title">Seleccionar Empleado</h2>
                                <div class="card">
                                    <?php if ( ! empty( $professional_listings ) ) : ?>
                                        <div id="employee-list" class="employees-container">
                                            <?php foreach ( $professional_listings as $listing ) : ?>
                                                <?php
                                                $listing_id = is_array( $listing ) ? intval( $listing['id'] ?? 0 ) : intval( $listing->ID ?? 0 );
                                                $title_value = is_array( $listing ) ? ( $listing['title'] ?? '' ) : ( $listing->post_title ?? '' );
                                                $title_sanitized = esc_html( $title_value );
                                                $words = preg_split( '/\s+/', trim( $title_value ) );
                                                $words = is_array( $words ) ? array_values( array_filter( $words ) ) : [];
                                                $initials = 'PR';
                                                if ( ! empty( $words ) ) {
                                                    $substring = static function ( $text, $start, $length ) {
                                                        if ( function_exists( 'mb_substr' ) ) {
                                                            return mb_substr( $text, $start, $length );
                                                        }

                                                        return substr( $text, $start, $length );
                                                    };

                                                    if ( count( $words ) >= 2 ) {
                                                        $initials = strtoupper( $substring( $words[0], 0, 1 ) . $substring( $words[1], 0, 1 ) );
                                                    } else {
                                                        $initials = strtoupper( $substring( $words[0], 0, 2 ) );
                                                    }
                                                }
                                                ?>
                                                <div class="employee-item" data-listing-id="<?php echo esc_attr( $listing_id ); ?>">
                                                    <img src="https://placehold.co/120x120/D6BCFA/805AD5?text=<?php echo urlencode( $initials ); ?>" alt="Empleado <?php echo esc_attr( $title_value ); ?>" class="avatar" loading="lazy" decoding="async" width="120" height="120">
                                                    <p class="employee-name" title="<?php echo esc_attr( $title_value ); ?>"><?php echo $title_sanitized; ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="no-listings-message">
                                            <p><strong>¡Bienvenido al panel profesional!</strong></p>
                                            <p>Aún no tienes listados de empleados configurados. Para comenzar a usar todas las funcionalidades del dashboard profesional, necesitas crear al menos un listado de empleado.</p>
                                            <p>Contacta al administrador del sitio para configurar tus listados profesionales.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div id="professional-content-blocks" class="space-y-8 opacity-25 pointer-events-none transition-opacity duration-300">
                                <div>
                                    <h2 class="dashboard-section-title">Acciones Rápidas</h2>
                                    <div class="quick-actions-grid">
                                        <div id="action-agenda" class="quick-action-card" data-module="appointments">
                                            <div class="quick-action-icon-wrapper" style="background-color: #FEF3C7;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #F59E0B;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                                <span class="notification-badge">2</span>
                                            </div>
                                            <p class="quick-action-title">Gestionar Agenda</p>
                                        </div>
                                        <div id="action-services" class="quick-action-card" data-module="services">
                                            <div class="quick-action-icon-wrapper" style="background-color: #D1FAE5;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #10B981;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                            </div>
                                            <p class="quick-action-title">Administrar Servicios</p>
                                        </div>
                                        <div id="action-combos" class="quick-action-card" data-module="combos">
                                            <div class="quick-action-icon-wrapper" style="background-color: #E0E7FF;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #4F46E5;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                            </div>
                                            <p class="quick-action-title">Combos de Servicios</p>
                                        </div>
                                        <div id="action-schedule" class="quick-action-card" data-module="schedule">
                                            <div class="quick-action-icon-wrapper" style="background-color: #E9D5FF;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #7C3AED;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </div>
                                            <p class="quick-action-title">Configurar Horario</p>
                                        </div>
                                        <div id="action-patients" class="quick-action-card" data-module="patients">
                                            <div class="quick-action-icon-wrapper" style="background-color: #FED7AA;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #F97316;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                            </div>
                                            <p class="quick-action-title">Mis Pacientes</p>
                                        </div>
                                        <div id="action-catalog" class="quick-action-card" data-module="catalog">
                                            <div class="quick-action-icon-wrapper" style="background-color: #D1D5DB;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #4B5563;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                            </div>
                                            <p class="quick-action-title">Mi Catálogo</p>
                                        </div>
                                        <div id="action-cancelled" class="quick-action-card">
                                            <div class="quick-action-icon-wrapper" style="background-color: #FEE2E2;">
                                                <svg class="h-8 w-8 mx-auto" style="color: #EF4444;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </div>
                                            <p class="quick-action-title">Ver Canceladas</p>
                                        </div>
                                    </div>
                                </div>
                                 <div>
                                    <h2 class="dashboard-section-title">Próximas Citas</h2>
                                    <div class="card">
                                        <div class="professional-appointments-list">
                                           <div class="professional-appointment-item">
                                                <div class="appointment-info">
                                                    <p class="appointment-service">Consulta General</p>
                                                    <p class="appointment-client">Ana García (Mascota: Luna)</p>
                                                </div>
                                                <div class="appointment-status">
                                                    <p class="appointment-time">Mañana, 10:00 AM</p>
                                                    <span class="status-badge status-confirmed">Confirmada</span>
                                                </div>
                                           </div>
                                           <div class="professional-appointment-item">
                                                <div class="appointment-info">
                                                    <p class="appointment-service">Corte de Pelo</p>
                                                    <p class="appointment-client">Carlos López (Mascota: Max)</p>
                                                </div>
                                                <div class="appointment-status">
                                                    <p class="appointment-time">Mañana, 11:30 AM</p>
                                                    <span class="status-badge status-pending">Pendiente</span>
                                                </div>
                                           </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="professional-module-container" class="hidden space-y-8">
                    </div>
                </div>
                <?php // ----- FIN: El usuario ES un profesional ----- ?>

            <?php else : ?>

                <?php // ----- INICIO: El usuario NO es profesional (_user_type != author) ----- ?>
                <div id="view-client" class="space-y-8">
                    <div>
                        <h2 class="dashboard-section-title">Acceso Restringido</h2>
                        <div class="card">
                            <p>No tienes permisos suficientes para acceder al dashboard profesional.</p>
                            <p>Este dashboard está reservado para usuarios profesionales (con _user_type = author).</p>
                            <?php if ($user_type === 'general'): ?>
                                <p><strong>Sugerencia:</strong> Como usuario general, deberías acceder al dashboard de clientes.</p>
                            <?php elseif (empty($user_type)): ?>
                                <p><strong>Nota:</strong> Tu cuenta no tiene un tipo de usuario asignado. Contacta al administrador.</p>
                            <?php else: ?>
                                <p><strong>Tu tipo de usuario actual:</strong> <?php echo esc_html($user_type); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                 <?php // ----- FIN: El usuario NO es profesional ----- ?>

            <?php endif; ?>

        </div>
    </main>
</div>
