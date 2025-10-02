<?php if (!defined('ABSPATH')) { exit; } ?>

<div id="va-client-dashboard" class="va-client-dashboard" role="region" aria-label="Panel de cliente">
  <div class="dashboard-container">
    <main id="main-content">
      <div id="resumen-screen" class="screen active">
        <header class="dashboard-header">
          <div class="header-left">
            <h1 class="header-title">Hola, <span class="user-name" id="user-name">Cliente</span></h1>
          </div>
          <div class="header-right">
            <div class="header-actions">
              <button id="notifications-button" class="header-button" aria-haspopup="dialog" aria-controls="notifications-sheet" aria-expanded="false">
                <i class="ph ph-bell" aria-hidden="true"></i>
                <span id="notification-badge" aria-hidden="true"></span>
              </button>
              <button id="add-pet-button" class="header-button" aria-haspopup="dialog" aria-controls="add-pet-sheet" aria-expanded="false">
                <i class="ph ph-plus" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </header>

        <div class="pet-selector-wrapper">
          <button id="pet-select-button" class="pet-selector-button" aria-haspopup="dialog" aria-controls="pet-select-sheet" aria-expanded="false">
            <div id="current-pet-avatar" class="pet-avatar"><i class="ph-bold ph-paw-print" aria-hidden="true"></i></div>
            <div class="pet-info">
              <span id="current-pet-name-btn" class="pet-name-display">Seleccionar Mascota</span>
              <p id="current-pet-species-btn" class="pet-species-display">Toca para cambiar</p>
            </div>
            <div class="pet-selector-caret"><i class="ph ph-caret-down" aria-hidden="true"></i></div>
          </button>
        </div>

        <div class="card-wrapper">
          <div class="card" style="display:flex; align-items:center; justify-content:space-between;">
            <div>
              <p style="font-weight:700; color:var(--gray-800);">Bienestar General</p>
              <p style="font-size:0.875rem; color:var(--gray-500);">Estado de la mascota</p>
            </div>
            <div style="text-align:right;">
              <p style="font-size:1.125rem; font-weight:700; color:var(--primary-color);">Excelente</p>
            </div>
          </div>
        </div>

        <div class="card-wrapper">
          <div class="metrics-grid">
            <div class="metric-item">
              <i class="ph-fill ph-calendar-check" aria-hidden="true"></i>
              <p class="label">Próxima Cita</p>
              <p class="value" id="next-appointment">--</p>
            </div>
            <div class="metric-item">
              <i class="ph-fill ph-heartbeat" aria-hidden="true"></i>
              <p class="label">Última Visita</p>
              <p class="value" id="last-visit">--</p>
            </div>
            <div class="metric-item">
              <i class="ph-fill ph-syringe" aria-hidden="true"></i>
              <p class="label">Vacunación</p>
              <p class="value" id="last-vaccine">--</p>
            </div>
            <div class="metric-item">
              <i class="ph-fill ph-scaleweight" aria-hidden="true"></i>
              <p class="label">Peso Actual</p>
              <p class="value" id="current-weight">--</p>
            </div>
          </div>
        </div>

        <div class="quick-access-wrapper">
          <h2 class="quick-access-title">Acceso Rápido</h2>
          <div class="quick-access-links">
            <button id="quick-access-card" class="access-link" type="button">
              <div class="access-link-icon history"><i class="ph-bold ph-list-checks" aria-hidden="true"></i></div>
              <div class="access-link-text">
                <span class="title">Ver Historial Completo</span>
                <p class="subtitle">Revisa todas las entradas médicas</p>
              </div>
            </button>
            <button id="add-history-quick-access" class="access-link" type="button" aria-haspopup="dialog" aria-controls="add-history-sheet" aria-expanded="false">
              <div class="access-link-icon add-entry"><i class="ph-bold ph-plus-circle" aria-hidden="true"></i></div>
              <div class="access-link-text">
                <span class="title">Añadir Nueva Entrada</span>
                <p class="subtitle">Registra una nueva consulta o evento</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div id="historial-screen" class="screen" aria-live="polite">
        <header class="history-header">
          <button id="back-to-resumen-button" class="back-button" aria-label="Volver"><i class="ph ph-arrow-left" aria-hidden="true"></i></button>
          <h1 class="history-title">Historial Médico</h1>
          <div class="date-filter-wrapper">
            <button id="date-filter-toggle" aria-haspopup="true" aria-controls="date-filter-popup" aria-expanded="false"><i class="ph ph-calendar" aria-hidden="true"></i></button>
            <div id="date-filter-popup" role="dialog" aria-modal="false">
              <p style="font-size:0.875rem; font-weight:700; margin-bottom:0.5rem;">Filtrar por fecha</p>
              <div class="date-filter-inputs">
                <div>
                  <label for="start-date">Desde</label>
                  <input type="date" id="start-date">
                </div>
                <div>
                  <label for="end-date">Hasta</label>
                  <input type="date" id="end-date">
                </div>
              </div>
              <button id="clear-date-filter">Limpiar filtro</button>
            </div>
          </div>
        </header>
        <div class="filters-wrapper"><div id="filter-container"></div></div>
        <div class="timeline-wrapper">
          <div class="timeline">
            <div class="timeline-line" aria-hidden="true"></div>
            <div id="timeline-container"></div>
          </div>
          <div id="pagination-controls" class="pagination-controls"></div>
        </div>
      </div>
    </main>
  </div>

  <!-- Bottom Sheets -->
  <div class="bottom-sheet-overlay" id="sheet-overlay" aria-hidden="true"></div>

  <div class="bottom-sheet" id="pet-select-sheet" role="dialog" aria-modal="true" aria-label="Selecciona una Mascota">
    <div class="sheet-handle"><div class="sheet-handle-bar" aria-hidden="true"></div></div>
    <h2 class="sheet-title">Selecciona una Mascota</h2>
    <ul id="pet-list" class="sheet-content no-scrollbar"></ul>
  </div>

  <div class="bottom-sheet" id="add-pet-sheet" role="dialog" aria-modal="true" aria-label="Añadir Mascota">
    <div class="sheet-handle"><div class="sheet-handle-bar" aria-hidden="true"></div></div>
    <div class="sheet-content no-scrollbar">
      <div class="tab-buttons">
        <button id="tab-create-pet" class="tab-button active" type="button">Crear Nueva</button>
        <button id="tab-add-by-code" class="tab-button" type="button">Usar Código</button>
      </div>
      <div id="content-create-pet" class="tab-content active">
        <form id="add-pet-form">
          <div>
            <label for="pet-name">Nombre</label>
            <input type="text" id="pet-name" required>
          </div>
          <div>
            <label for="pet-species">Especie</label>
            <select id="pet-species" required>
              <option value="Perro">Perro</option>
              <option value="Gato">Gato</option>
              <option value="Pájaro">Pájaro</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <button type="submit">Guardar Mascota</button>
        </form>
      </div>
      <div id="content-add-by-code" class="tab-content">
        <form id="add-by-code-form">
          <div>
            <label for="share-code">Código para compartir</label>
            <input type="text" id="share-code" placeholder="Ingresa el código de la mascota" required>
          </div>
          <p class="share-code-info">Pide al dueño de la mascota que te comparta su código único.</p>
          <button type="submit">Añadir Mascota</button>
        </form>
      </div>
    </div>
  </div>

  <div class="bottom-sheet" id="add-history-sheet" role="dialog" aria-modal="true" aria-label="Añadir Entrada">
    <div class="sheet-handle"><div class="sheet-handle-bar" aria-hidden="true"></div></div>
    <div class="sheet-content no-scrollbar">
      <h2 class="sheet-title">Añadir Nueva Entrada</h2>
      <form id="add-history-form">
        <div>
          <label for="history-title">Título</label>
          <input type="text" id="history-title" required>
        </div>
        <div>
          <label for="history-type">Tipo de Entrada</label>
          <select id="history-type" required>
            <option value="consultation">Consulta</option>
            <option value="vaccination">Vacuna</option>
            <option value="surgery">Cirugía</option>
            <option value="procedure">Otro</option>
          </select>
        </div>
        <div>
          <label for="history-description">Descripción</label>
          <textarea id="history-description" rows="3"></textarea>
        </div>
        <button type="submit">Guardar Entrada</button>
      </form>
    </div>
  </div>

  <div class="bottom-sheet" id="notifications-sheet" role="dialog" aria-modal="true" aria-label="Notificaciones">
    <div class="sheet-handle"><div class="sheet-handle-bar" aria-hidden="true"></div></div>
    <h2 class="sheet-title">Notificaciones</h2>
    <ul id="notifications-list" class="sheet-content no-scrollbar"></ul>
  </div>

  <div id="toast" role="status" aria-live="polite"></div>
</div>

