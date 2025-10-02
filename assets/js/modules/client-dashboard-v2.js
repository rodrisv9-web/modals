(function(window, document) {
  'use strict';

  function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
  function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  function apiFetch(path, options) {
    const cfg = window.VA_Client_Dashboard || {};
    const url = (cfg.rest_url || '').replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');
    const headers = Object.assign({ 'Content-Type': 'application/json' }, (options && options.headers) || {});
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    return fetch(url, Object.assign({}, options, { headers })).then(r => {
      if (!r.ok) return r.json().then(j => Promise.reject({ status: r.status, data: j }));
      return r.json();
    });
  }

  function formatDate(dtStr) {
    try { return new Date(dtStr).toLocaleDateString('es-ES', { month: 'short', day: 'numeric' }); } catch(e) { return dtStr; }
  }

  function showToast(msg) {
    const toast = qs('#va-client-dashboard #toast');
    if (!toast) return;
    clearTimeout(toast.__tid);
    toast.textContent = msg;
    toast.classList.add('show');
    toast.__tid = setTimeout(() => toast.classList.remove('show'), 3000);
  }

  function initClientDashboard(rootSelector, cfg) {
    const root = typeof rootSelector === 'string' ? qs(rootSelector) : (rootSelector || qs('#va-client-dashboard'));
    if (!root) return;

    const state = {
      pets: [],
      logsByPet: {},
      selectedPetId: null,
      activeScreen: 'resumen',
      activeFilter: 'all',
      currentPage: 1,
      itemsPerPage: 4,
      dateFilter: { start: null, end: null },
      __creatingPet: false
    };

    const refs = {
      userName: qs('#va-client-dashboard #user-name'),
      petSelectBtn: qs('#va-client-dashboard #pet-select-button'),
      petAvatar: qs('#va-client-dashboard #current-pet-avatar'),
      petNameBtn: qs('#va-client-dashboard #current-pet-name-btn'),
      petSpeciesBtn: qs('#va-client-dashboard #current-pet-species-btn'),
      nextAppointment: qs('#va-client-dashboard #next-appointment'),
      lastVisit: qs('#va-client-dashboard #last-visit'),
      lastVaccine: qs('#va-client-dashboard #last-vaccine'),
      currentWeight: qs('#va-client-dashboard #current-weight'),
      filterContainer: qs('#va-client-dashboard #filter-container'),
      timelineContainer: qs('#va-client-dashboard #timeline-container'),
      pagination: qs('#va-client-dashboard #pagination-controls'),
      badge: qs('#va-client-dashboard #notification-badge'),
      notificationsList: qs('#va-client-dashboard #notifications-list')
    };

    const speciesIcon = { 'Perro': 'ph-dog', 'Gato': 'ph-cat', 'Pájaro': 'ph-bird', 'Ave': 'ph-bird', 'Otro': 'ph-paw-print', 'unknown': 'ph-paw-print', 'dog': 'ph-dog', 'cat': 'ph-cat', 'bird': 'ph-bird', 'other': 'ph-paw-print' };
    const filters = { 'all': { label: 'Todo' }, 'consultation': { label: 'Consultas' }, 'vaccination': { label: 'Vacunas' }, 'surgery': { label: 'Cirugías' }, 'procedure': { label: 'Otros' } };

    // Set user name ASAP
    if (refs.userName && cfg && cfg.user_name) {
      refs.userName.textContent = cfg.user_name;
    }

    function navigateTo(screen) {
      qsa('#va-client-dashboard .screen').forEach(s => s.classList.remove('active'));
      const node = qs(`#va-client-dashboard #${screen}-screen`);
      if (node) node.classList.add('active');
      state.activeScreen = screen;
      if (screen === 'historial') {
        state.currentPage = 1;
        renderTimeline();
      }
    }

    function openSheet(id) {
      const overlay = qs('#va-client-dashboard #sheet-overlay');
      const sheet = qs(`#va-client-dashboard #${id}`);
      if (overlay) { overlay.classList.add('active'); overlay.setAttribute('aria-hidden', 'false'); }
      if (sheet) { sheet.classList.add('active'); sheet.setAttribute('aria-hidden', 'false'); }
    }
    function closeSheets() {
      const overlay = qs('#va-client-dashboard #sheet-overlay');
      if (overlay) { overlay.classList.remove('active'); overlay.setAttribute('aria-hidden', 'true'); }
      qsa('#va-client-dashboard .bottom-sheet').forEach(s => { s.classList.remove('active'); s.setAttribute('aria-hidden', 'true'); });
    }

    function renderFilters() {
      if (!refs.filterContainer) return;
      refs.filterContainer.innerHTML = Object.entries(filters).map(([key, cfg]) => (
        `<button class="filter-button ${key === state.activeFilter ? 'active' : ''}" data-filter="${key}">${cfg.label}</button>`
      )).join('');
    }

    function updateDashboard() {
      if (refs.userName && cfg && cfg.user_name) { refs.userName.textContent = cfg.user_name; }
      const pet = state.pets.find(p => String(p.pet_id) === String(state.selectedPetId)) || state.pets[0];
      if (!pet) return;
      state.selectedPetId = pet.pet_id;
      const icon = speciesIcon[pet.species] || 'ph-paw-print';
      if (refs.petAvatar) { refs.petAvatar.innerHTML = `<i class="ph-bold ${icon}" aria-hidden="true"></i>`; }
      if (refs.petNameBtn) refs.petNameBtn.textContent = pet.name || '';
      if (refs.petSpeciesBtn) refs.petSpeciesBtn.textContent = pet.species || '';
      if (refs.nextAppointment) refs.nextAppointment.textContent = '--';
      if (refs.lastVisit) refs.lastVisit.textContent = '--';
      if (refs.lastVaccine) refs.lastVaccine.textContent = '--';
      if (refs.currentWeight) refs.currentWeight.textContent = '--';
      fetchSummary(pet.pet_id);
    }

    function renderPetListInSheet() {
      const list = qs('#va-client-dashboard #pet-list');
      if (!list) return;
      list.innerHTML = state.pets.map(pet => {
        const selected = String(pet.pet_id) === String(state.selectedPetId);
        return `<li class="pet-list-item${selected ? ' selected' : ''}" data-id="${pet.pet_id}" aria-selected="${selected}">
           <div class="pet-avatar"><i class="ph-bold ${speciesIcon[pet.species] || 'ph-paw-print'}" aria-hidden="true"></i></div>
           <span class="pet-name">${pet.name}</span>
         </li>`;
      }).join('');
    }

    function renderNotifications(items) {
      if (!refs.notificationsList || !refs.badge) return;
      if (!items || !items.length) {
        refs.badge.classList.remove('visible');
        refs.notificationsList.innerHTML = `<p class="no-notifications">No tienes notificaciones.</p>`;
        return;
      }
      refs.badge.classList.add('visible');
      refs.notificationsList.innerHTML = items.map(n => (
        `<li class="notification-item">
            <div class="notification-icon"><i class="ph-bold ph-calendar-check" aria-hidden="true"></i></div>
            <div class="notification-text">
              <p class="title">${n.pet_name} tiene una cita</p>
              <p class="subtitle">${n.when}</p>
            </div>
         </li>`
      )).join('');
    }

    function renderTimeline() {
      const container = refs.timelineContainer; if (!container) return;
      const history = state.logsByPet[state.selectedPetId] || [];
      let filtered = (state.activeFilter === 'all' ? history : history.filter(it => it.entry_type === state.activeFilter));
      if (state.dateFilter.start) filtered = filtered.filter(it => new Date(it.entry_date) >= new Date(state.dateFilter.start));
      if (state.dateFilter.end) { const end = new Date(state.dateFilter.end); end.setHours(23,59,59,999); filtered = filtered.filter(it => new Date(it.entry_date) <= end); }
      filtered.sort((a,b) => new Date(b.entry_date) - new Date(a.entry_date));
      const totalPages = Math.ceil(filtered.length / state.itemsPerPage) || 0;
      const start = (state.currentPage - 1) * state.itemsPerPage;
      const pageItems = filtered.slice(start, start + state.itemsPerPage);
      if (!pageItems.length) {
        container.innerHTML = `<p class="no-records-message">No hay registros para este filtro.</p>`;
        renderPagination(0,0);
        return;
      }
      container.innerHTML = pageItems.map(item => (
        `<div class="timeline-item" data-id="${item.log_id}">
           <div class="timeline-marker" aria-hidden="true"></div>
           <div class="timeline-card">
             <p class="date">${formatDate(item.entry_date)}</p>
             <p class="title">${item.title}</p>
             <p class="description">${item.description || ''}</p>
           </div>
         </div>`
      )).join('');
      renderPagination(state.currentPage, totalPages);
    }

    function renderPagination(page, total) {
      const el = refs.pagination; if (!el) return;
      if (total <= 1) { el.innerHTML = ''; return; }
      el.innerHTML = `
        <button id="prev-page" class="pagination-button" ${page === 1 ? 'disabled' : ''}>Anterior</button>
        <span class="page-info">Página ${page} de ${total}</span>
        <button id="next-page" class="pagination-button" ${page === total ? 'disabled' : ''}>Siguiente</button>
      `;
    }

    // REST
    function fetchPets() { return apiFetch('clients/me/pets', { method: 'GET' })
      .then(res => { const pets = Array.isArray(res?.data) ? res.data : []; state.pets = pets; if (!state.selectedPetId && pets[0]) state.selectedPetId = pets[0].pet_id; updateDashboard(); renderPetListInSheet(); return pets; })
      .catch(() => { state.pets = []; return []; }); }

    function fetchLogs(petId) {
      if (!petId) return Promise.resolve([]);
      const key = String(petId);
      return apiFetch(`clients/pets/${key}/logs`, { method: 'GET' })
        .then(res => { const logs = Array.isArray(res?.data) ? res.data : []; state.logsByPet[key] = logs; if (state.activeScreen==='historial') renderTimeline(); return logs; })
        .catch(() => { state.logsByPet[petId] = []; return []; });
    }

    function fetchSummary(petId) {
      return apiFetch(`clients/pets/${petId}/summary`, { method: 'GET' })
        .then(res => {
          const d = res?.data || {};
          if (refs.nextAppointment) refs.nextAppointment.textContent = d.next_appointment || 'Ninguna';
          if (refs.lastVisit) refs.lastVisit.textContent = d.last_visit || '--';
          if (refs.lastVaccine) refs.lastVaccine.textContent = d.last_vaccine || '--';
          if (refs.currentWeight) refs.currentWeight.textContent = d.current_weight || '--';
        })
        .catch(() => {});
    }

    function fetchNotifications() {
      return apiFetch('clients/me/notifications', { method: 'GET' })
        .then(res => { renderNotifications(res?.data || []); })
        .catch(() => { renderNotifications([]); });
    }

    // Handlers
    function onPetListClick(e) {
      const item = e.target.closest('li[data-id]');
      if (!item) return;
      state.selectedPetId = item.getAttribute('data-id');
      updateDashboard();
      renderPetListInSheet();
      fetchLogs(state.selectedPetId);
      closeSheets();
    }

    function onAddPetSubmit(e) {
      e.preventDefault();
      if (state.__creatingPet) return;
      const form = e.target;
      const name = form.querySelector('#pet-name')?.value?.trim();
      const species = form.querySelector('#pet-species')?.value || 'Otro';
      if (!name) { showToast('Faltan datos'); return; }
      const submitBtn = form.querySelector('[type="submit"]'); if (submitBtn) submitBtn.disabled = true;
      state.__creatingPet = true;
      apiFetch('clients/pets', { method: 'POST', body: JSON.stringify({ name, species }) })
        .then(res => { const pet = res?.data; if (!pet) return; state.pets.push(pet); state.selectedPetId = pet.pet_id; updateDashboard(); renderPetListInSheet(); closeSheets(); form.reset(); showToast(`${pet.name} añadido.`); })
        .catch(err => { showToast(err?.data?.message || 'Error al crear mascota'); })
        .finally(() => { state.__creatingPet = false; if (submitBtn) submitBtn.disabled = false; });
    }

    function onAddByCodeSubmit(e) {
      e.preventDefault();
      const input = e.target.querySelector('#share-code');
      const code = (input?.value || '').trim().toUpperCase();
      if (!code) { showToast('Ingresa un código'); return; }
      apiFetch('clients/claim-profile', { method: 'POST', body: JSON.stringify({ share_code: code }) })
        .then(() => { showToast('Mascota vinculada'); return fetchPets(); })
        .then(() => closeSheets())
        .catch(err => { showToast(err?.data?.message || 'Código no válido'); });
    }

    function onAddHistorySubmit(e) {
      e.preventDefault();
      const form = e.target;
      const petId = state.selectedPetId;
      const title = form.querySelector('#history-title')?.value?.trim();
      const type = form.querySelector('#history-type')?.value || 'consultation';
      const description = form.querySelector('#history-description')?.value?.trim() || '';
      if (!petId || !title) { showToast('Completa el formulario'); return; }
      apiFetch(`clients/pets/${petId}/logs`, { method: 'POST', body: JSON.stringify({ title, type, description }) })
        .then(() => fetchLogs(petId))
        .then(() => { if (state.activeScreen==='historial') renderTimeline(); closeSheets(); form.reset(); showToast('Entrada guardada.'); })
        .catch(err => { showToast(err?.data?.message || 'Error al guardar'); });
    }

    function bindEvents() {
      qs('#va-client-dashboard #back-to-resumen-button')?.addEventListener('click', () => navigateTo('resumen'));
      qs('#va-client-dashboard #notifications-button')?.addEventListener('click', () => openSheet('notifications-sheet'));
      refs.petSelectBtn?.addEventListener('click', () => { renderPetListInSheet(); openSheet('pet-select-sheet'); });
      qs('#va-client-dashboard #add-pet-button')?.addEventListener('click', () => {
        const sheet = qs('#va-client-dashboard #add-pet-sheet');
        sheet?.querySelector('#tab-create-pet')?.classList.add('active');
        sheet?.querySelector('#tab-add-by-code')?.classList.remove('active');
        sheet?.querySelector('#content-create-pet')?.classList.add('active');
        sheet?.querySelector('#content-add-by-code')?.classList.remove('active');
        openSheet('add-pet-sheet');
      });
      qs('#va-client-dashboard #add-history-quick-access')?.addEventListener('click', () => openSheet('add-history-sheet'));

      const addPetSheet = qs('#va-client-dashboard #add-pet-sheet');
      addPetSheet?.querySelector('#tab-create-pet')?.addEventListener('click', (e) => {
        e.currentTarget.classList.add('active');
        addPetSheet.querySelector('#tab-add-by-code')?.classList.remove('active');
        addPetSheet.querySelector('#content-create-pet')?.classList.add('active');
        addPetSheet.querySelector('#content-add-by-code')?.classList.remove('active');
      });
      addPetSheet?.querySelector('#tab-add-by-code')?.addEventListener('click', (e) => {
        e.currentTarget.classList.add('active');
        addPetSheet.querySelector('#tab-create-pet')?.classList.remove('active');
        addPetSheet.querySelector('#content-add-by-code')?.classList.add('active');
        addPetSheet.querySelector('#content-create-pet')?.classList.remove('active');
      });

      qs('#va-client-dashboard #pet-list')?.addEventListener('click', onPetListClick);
      qs('#va-client-dashboard #add-pet-form')?.addEventListener('submit', onAddPetSubmit);
      qs('#va-client-dashboard #add-by-code-form')?.addEventListener('submit', onAddByCodeSubmit);
      qs('#va-client-dashboard #add-history-form')?.addEventListener('submit', onAddHistorySubmit);
      const overlay = qs('#va-client-dashboard #sheet-overlay');
      overlay?.addEventListener('click', closeSheets);
      qsa('#va-client-dashboard .bottom-sheet .sheet-handle').forEach(h => h.addEventListener('click', closeSheets));
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheets(); });

      qs('#va-client-dashboard #quick-access-card')?.addEventListener('click', () => navigateTo('historial'));

      refs.filterContainer?.addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-button'); if (!btn) return;
        state.activeFilter = btn.getAttribute('data-filter') || 'all';
        state.currentPage = 1; renderFilters(); renderTimeline();
      });

      refs.pagination?.addEventListener('click', (e) => {
        if (e.target.id === 'prev-page') { if (state.currentPage > 1) { state.currentPage--; renderTimeline(); } }
        if (e.target.id === 'next-page') {
          const history = state.logsByPet[state.selectedPetId] || [];
          const totalPages = Math.ceil(history.length / state.itemsPerPage) || 0;
          if (state.currentPage < totalPages) { state.currentPage++; renderTimeline(); }
        }
      });

      const datePopup = qs('#va-client-dashboard #date-filter-popup');
      qs('#va-client-dashboard #date-filter-toggle')?.addEventListener('click', (e) => { e.stopPropagation(); datePopup?.classList.toggle('visible'); });
      qs('#va-client-dashboard #start-date')?.addEventListener('change', (e) => { state.dateFilter.start = e.target.value || null; state.currentPage=1; renderTimeline(); });
      qs('#va-client-dashboard #end-date')?.addEventListener('change', (e) => { state.dateFilter.end = e.target.value || null; state.currentPage=1; renderTimeline(); });
      qs('#va-client-dashboard #clear-date-filter')?.addEventListener('click', () => {
        const s = qs('#va-client-dashboard #start-date'); const t = qs('#va-client-dashboard #end-date');
        if (s) s.value = ''; if (t) t.value='';
        state.dateFilter = { start: null, end: null }; state.currentPage = 1; renderTimeline(); datePopup?.classList.remove('visible');
      });
      document.body.addEventListener('click', (e) => { if (datePopup && !datePopup.contains(e.target) && !e.target.closest('#date-filter-toggle')) datePopup.classList.remove('visible'); });
    }

    // Init
    renderFilters();
    bindEvents();
    fetchPets().then(pets => {
      if (pets[0]) { fetchLogs(pets[0].pet_id); }
      fetchNotifications();
    });
  }

  window.VAClientDashboardV2 = { init: initClientDashboard };
})(window, document);
