<!-- veterinalia-appointment/templates/professional-schedule.php -->
<div class="va-schedule-content va-schedule-new-ui"
     data-professional-id="<?php echo esc_attr( $professional_id ); ?>"
     data-existing-schedule="<?php echo esc_attr( json_encode( $existing_schedule ) ); ?>">

    <?php // Solo el contenido principal, sin headers ni modales duplicados ?>

    <!-- Navigation States Container -->
    <div class="va-schedule-nav-container">
        <!-- Days List View (Default) -->
        <div class="va-schedule-view va-days-view" id="va-days-view">
            <div class="va-days-list">
                <?php
                $daysOfWeek = [
                    1 => 'Lunes',
                    2 => 'Martes',
                    3 => 'Miércoles',
                    4 => 'Jueves',
                    5 => 'Viernes',
                    6 => 'Sábado',
                    7 => 'Domingo'
                ];

                foreach ($daysOfWeek as $dayId => $dayName): ?>
                    <div class="va-day-item" data-day-id="<?php echo $dayId; ?>" data-day-name="<?php echo $dayName; ?>">
                        <div class="va-day-header">
                            <span class="va-day-name"><?php echo $dayName; ?></span>
                            <span class="va-day-status">Sin configurar</span>
                        </div>
                        <div class="va-day-slots-preview">
                            <!-- Las franjas horarias se mostrarán aquí -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Schedule Slots View (Hidden by default) -->
        <div class="va-schedule-view va-slots-view" id="va-slots-view" style="display: none;">
            <form id="va-schedule-form" method="post">
                <div id="va-schedule-entries">
                    <!-- Los bloques de horario se añadirán aquí dinámicamente por JavaScript -->
                    <p id="va-no-schedule-message" style="display: none;">No tienes horarios configurados para este día. Añade uno para empezar.</p>
                </div>

                <div class="va-form-actions">
                    <button type="submit" id="va-save-schedule-btn" class="va-manual-save-btn">Guardar Manualmente</button>
                    <small class="va-autosave-notice">Los cambios se guardan automáticamente</small>
                </div>

                <span id="va-schedule-feedback"></span>
            </form>
        </div>
    </div>
</div>