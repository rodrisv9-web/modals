<!-- veterinalia-appointment/templates/professional-appointments.php -->
<div class="va-professional-dashboard"
     data-professional-id="<?php echo esc_attr( $professional_id ); ?>">
    <h3>Panel de Citas del Profesional</h3>
    <p>Aquí puedes ver todas tus citas agendadas.</p>

    <?php if ( ! empty( $user_listings ) && count( $user_listings ) > 1 ) : // Mostrar dropdown solo si hay más de un listado ?>
        <div class="va-listing-selector">
            <label for="va-dashboard-listing">Selecciona el listado:</label>
            <select id="va-dashboard-listing" name="va_dashboard_listing">
                <?php foreach ( $user_listings as $listing ) :
                    $listing_id = is_array( $listing ) ? intval( $listing['id'] ?? 0 ) : intval( $listing->ID ?? 0 );
                    $listing_title = is_array( $listing ) ? ( $listing['title'] ?? '' ) : ( $listing->post_title ?? '' );
                    ?>
                    <option value="<?php echo esc_attr( $listing_id ); ?>" <?php selected( $professional_id, $listing_id ); ?>>
                        <?php echo esc_html( $listing_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div id="va-appointments-list-container">
        <?php
        // Llama a la función desde su nueva ubicación en la clase AJAX_Handler
        echo Veterinalia_Appointment_AJAX_Handler::get_instance()->render_appointments_table_html( $appointments );
        ?>
    </div>
</div> 