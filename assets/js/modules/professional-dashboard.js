/**
 * professional-dashboard.js
 * VERSIÓN 4.9 - CON MODAL DE NOTAS ANIMADO
 */
const VA_Professional_Dashboard = {
    init: function() {
        console.log('Módulo de Agenda: Inicializado en modo de tiempo real (v4.8).');
        const moduleContainer = document.getElementById('professional-module-container');
        if (!moduleContainer) return;

        moduleContainer.addEventListener('click', this.handleModuleClick.bind(this));
    },

    handleModuleClick: function(e) {
        const target = e.target;
        const targetClosest = (selector) => target.closest(selector);

        // Manejo de Pestañas
        if (target.matches('.tab-link')) {
            e.preventDefault();
            this.switchTab(target);
        }

        // Manejo de Notas (Abrir)
        const viewNotesButton = targetClosest('.actions-bar__comments-button');
        if (viewNotesButton) {
            e.preventDefault();
            this.toggleNotes(viewNotesButton.dataset.notesId, true);
        }

        // Manejo de Notas (Cerrar)
        const modal = targetClosest('.appointment-notes-modal');
        if (modal && (target.matches('.modal-close-button') || target === modal)) {
             e.preventDefault();
             this.toggleNotes(modal.id, false);
        }
        
        // Manejo de Botones de Acción
        const actionButton = targetClosest('.actions-bar__cancel-button, .actions-bar__confirm-button');
        if (actionButton) {
            e.preventDefault();
            this.handleActionClick(actionButton);
        }
    },

    // FUNCIÓN DE NOTAS MEJORADA CON ANIMACIONES
    toggleNotes: function(notesId, show) {
        const notesModal = document.getElementById(notesId);
        if (!notesModal) return;

        const modalContent = notesModal.querySelector('.notes-content');
        if (!modalContent) return;

        if (show) {
            notesModal.classList.remove('hidden');
            // Retardo mínimo para que el navegador registre el cambio de 'display'
            setTimeout(() => {
                modalContent.classList.add('modal-enter-active');
            }, 10);
        } else {
            modalContent.classList.remove('modal-enter-active');
            modalContent.classList.add('modal-leave-active');
            // Ocultar el modal después de que termine la animación de salida
            setTimeout(() => {
                notesModal.classList.add('hidden');
                modalContent.classList.remove('modal-leave-active');
            }, 300); // 300ms, igual a la duración de la transición CSS
        }
    },

    switchTab: function(clickedTab) {
        const moduleContainer = document.getElementById('professional-module-container');
        if (!moduleContainer) return;
        
        moduleContainer.querySelectorAll('.tab-link').forEach(tab => tab.classList.remove('active'));
        moduleContainer.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));

        clickedTab.classList.add('active');
        const targetContent = moduleContainer.querySelector(clickedTab.dataset.tabTarget);
        if (targetContent) {
            targetContent.classList.remove('hidden');
        }
    },

    // FUNCIÓN CLAVE CORREGIDA
    handleActionClick: async function(actionButton) {
        const appointmentId = actionButton.dataset.appointmentId;
        const newStatus = actionButton.dataset.newStatus;
        if (!appointmentId || !newStatus) return;

        const card = actionButton.closest('.appointment-card-vision');
        if (!card) return;
        const buttonGroup = card.querySelector('.actions-bar__button-group');
        if (!buttonGroup) return;

        const originalButtonsHTML = buttonGroup.innerHTML;
        buttonGroup.innerHTML = '<span class="actions-bar__processing-text">Procesando...</span>';

        try {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : {};
            const response = await fetch(`${api.api_url}appointments/${appointmentId}/status`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': api.api_nonce },
                body: JSON.stringify({ status: newStatus })
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Error en la respuesta del servidor.');

            if (result.success) {
                // **LA CORRECCIÓN CLAVE ESTÁ AQUÍ**
                // Le pasamos el appointmentId directamente a la función que mueve la tarjeta.
                this.updateAndMoveCard(card, newStatus, appointmentId);
            } else {
                throw new Error(result.data.message || 'La API devolvió un error.');
            }

        } catch (error) {
            console.error('Error al actualizar estado:', error);
            alert(`Error: ${error.message}`);
            buttonGroup.innerHTML = originalButtonsHTML;
        }
    },
    
    // **FUNCIÓN ASISTENTE CORREGIDA**
    updateAndMoveCard: function(card, newStatus, appointmentId) { // Ahora recibe el ID
        const destinationContainer = document.getElementById(`tab-content-${newStatus}`);
        if (!destinationContainer) return;

        // Le pasamos el ID a la función que actualiza el contenido.
        this.updateCardContent(card, newStatus, appointmentId);

        const emptyMessage = destinationContainer.querySelector('.no-appointments-message');
        if (emptyMessage) emptyMessage.remove();

        let destinationGrid = destinationContainer.querySelector('.appointments-grid');
        if (!destinationGrid) {
            destinationGrid = document.createElement('div');
            destinationGrid.className = 'appointments-grid';
            destinationContainer.appendChild(destinationGrid);
        }

        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        
        setTimeout(() => {
            destinationGrid.prepend(card);
            void card.offsetWidth; 
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }, 400);
    },

    // **FUNCIÓN ASISTENTE CORREGIDA**
    updateCardContent: function(card, newStatus, appointmentId) { // Ahora recibe el ID
        const buttonGroup = card.querySelector('.actions-bar__button-group');
        if (!buttonGroup) return;

        let newButtonsHTML = '';

        if (newStatus === 'confirmed') {
            newButtonsHTML = `<button class="actions-bar__confirm-button" data-appointment-id="${appointmentId}" data-new-status="completed">Completar</button>`;
        }

        buttonGroup.innerHTML = newButtonsHTML;

        card.classList.remove('status-pending', 'status-confirmed', 'status-completed', 'status-cancelled');
        card.classList.add(`status-${newStatus}`);
    }
};