// assets/js/dashboard-controller.js

document.addEventListener('DOMContentLoaded', function () {
    const dashboardContainer = document.querySelector('.dashboard-container');
    if (!dashboardContainer) return;

    const mainProfView = document.getElementById('professional-main-view');
    const moduleContainer = document.getElementById('professional-module-container');
    
    let selectedProfessionalId = null;
    let isTransitioning = false;

    // --- LÓGICA DE AUTO-SELECCIÓN ---
    const employeeCards = document.querySelectorAll('.employee-item');
    if (employeeCards.length === 1) {
        // Si solo hay un empleado, lo seleccionamos automáticamente.
        handleEmployeeSelection(employeeCards[0]);
    }
    // --- FIN DE LA LÓGICA ---

    // Exponer una API global para que mdulos puedan volver programticamente
    window.returnToDashboard = function() {
        if (!isTransitioning) {
            showMainProfessionalView();
        }
    };

    // Capture-phase handler to ensure the back button works
    // even if a module stops propagation on bubbling phase.
    document.addEventListener('click', function(e) {
        const el = e.target && e.target.closest ? e.target.closest('.back-to-prof-main') : null;
        if (!el) return;
        if (!moduleContainer || !moduleContainer.contains(el)) return; // only when inside module container

        // If the button label is not "Volver", it may be an internal back (e.g., Services module)
        const labelEl = el.querySelector('span');
        const label = labelEl ? labelEl.textContent.trim().toLowerCase() : '';
        if (label && label !== 'volver') return; // let module handle its own navigation

        e.preventDefault();
        if (!isTransitioning) showMainProfessionalView();
    }, true);

    dashboardContainer.addEventListener('click', function(e) {
        const target = e.target;
        const targetClosest = (selector) => target.closest(selector);

        const employeeCard = targetClosest('.employee-item');
        if (employeeCard) {
            e.preventDefault();
            if (!isTransitioning) handleEmployeeSelection(employeeCard);
            return;
        }

        const quickActionCard = targetClosest('.quick-action-card[data-module]');
        if (quickActionCard) {
            e.preventDefault();
            if (!isTransitioning) loadModule(quickActionCard.dataset.module);
            return;
        }

        const backButton = targetClosest('.back-to-prof-main');
        if (backButton) {
            e.preventDefault();
            if (!isTransitioning) showMainProfessionalView();
            return;
        }
    });

    // Refuerzo local por si alg;n mdulo manipula el evento en otros contenedores
    if (moduleContainer) {
        moduleContainer.addEventListener('click', function(e) {
            const back = e.target && e.target.closest && e.target.closest('.back-to-prof-main');
            if (back) {
                e.preventDefault();
                if (!isTransitioning) showMainProfessionalView();
            }
        });
    }

    function handleEmployeeSelection(selectedCard) {
        selectedProfessionalId = selectedCard.dataset.listingId;
        document.querySelectorAll('.employee-item').forEach(card => card.classList.remove('employee-selected'));
        selectedCard.classList.add('employee-selected');
        const professionalContentBlocks = document.getElementById('professional-content-blocks');
        if (professionalContentBlocks) {
            professionalContentBlocks.classList.remove('opacity-25', 'pointer-events-none');
        }
        console.log(`Empleado seleccionado. ID del listado: ${selectedProfessionalId}`);
    }

    function showMainProfessionalView() {
        if (!mainProfView || !moduleContainer) return;
        if (isTransitioning) return;

        isTransitioning = true;
        dashboardContainer.classList.add('va-animating');
        dashboardContainer.setAttribute('aria-busy', 'true');

        // Prepare incoming main view
        mainProfView.classList.remove('hidden');
        mainProfView.classList.add('va-slide-in-left');

        // Animate module container out
        moduleContainer.classList.add('va-slide-out-right');

        const onModuleOutEnd = () => {
            moduleContainer.classList.add('hidden');
            moduleContainer.classList.remove('va-slide-out-right');
            moduleContainer.removeEventListener('animationend', onModuleOutEnd);
        };
        moduleContainer.addEventListener('animationend', onModuleOutEnd, { once: true });

        const onMainInEnd = () => {
            mainProfView.classList.remove('va-slide-in-left');
            mainProfView.removeEventListener('animationend', onMainInEnd);
            isTransitioning = false;
            dashboardContainer.classList.remove('va-animating');
            dashboardContainer.removeAttribute('aria-busy');
            // Focus main view heading for accessibility
            const focusTarget = mainProfView.querySelector('.dashboard-section-title') || mainProfView;
            if (focusTarget) {
                if (!focusTarget.hasAttribute('tabindex')) focusTarget.setAttribute('tabindex', '-1');
                try { focusTarget.focus({ preventScroll: true }); } catch (e) { try { focusTarget.focus(); } catch(_){} }
            }
        };
        mainProfView.addEventListener('animationend', onMainInEnd, { once: true });

        // Fallback in case animationend doesn't fire
        setTimeout(() => {
            if (isTransitioning) {
                mainProfView.classList.remove('va-slide-in-left');
                moduleContainer.classList.add('hidden');
                isTransitioning = false;
                dashboardContainer.classList.remove('va-animating');
                dashboardContainer.removeAttribute('aria-busy');
            }
        }, 450);
    }

    async function loadModule(moduleName) {
        if (!selectedProfessionalId) {
            alert('Por favor, selecciona un empleado primero.');
            return;
        }
        if (!mainProfView || !moduleContainer) return;
        if (isTransitioning) return;

        isTransitioning = true;
        dashboardContainer.classList.add('va-animating');
        dashboardContainer.setAttribute('aria-busy', 'true');

        mainProfView.classList.add('va-slide-out-left');
        moduleContainer.innerHTML = `<div class="card text-center"><p>Cargando módulo...</p></div>`;
        moduleContainer.classList.remove('hidden');
        moduleContainer.classList.add('va-slide-in-right');

        // Al terminar salida de la vista principal, ocultarla
        const onMainOutEnd = () => {
            mainProfView.classList.add('hidden');
            mainProfView.classList.remove('va-slide-out-left');
            mainProfView.removeEventListener('animationend', onMainOutEnd);
        };
        mainProfView.addEventListener('animationend', onMainOutEnd, { once: true });

        // Al terminar la entrada del módulo, liberar el guard
        const onModuleInEnd = () => {
            moduleContainer.classList.remove('va-slide-in-right');
            moduleContainer.removeEventListener('animationend', onModuleInEnd);
            isTransitioning = false;
            dashboardContainer.classList.remove('va-animating');
            dashboardContainer.removeAttribute('aria-busy');
            // Focus module heading if present
            const headerTitle = moduleContainer.querySelector('.module-header .dashboard-section-title') || moduleContainer;
            if (headerTitle) {
                if (!headerTitle.hasAttribute('tabindex')) headerTitle.setAttribute('tabindex', '-1');
                try { headerTitle.focus({ preventScroll: true }); } catch (e) { try { headerTitle.focus(); } catch(_){} }
            }
        };
        moduleContainer.addEventListener('animationend', onModuleInEnd, { once: true });

        // Fallback in case animationend doesn't fire
        setTimeout(() => {
            if (isTransitioning) {
                moduleContainer.classList.remove('va-slide-in-right');
                isTransitioning = false;
                dashboardContainer.classList.remove('va-animating');
                dashboardContainer.removeAttribute('aria-busy');
            }
        }, 450);

        try {
            const api = (typeof VA_REST !== 'undefined') ? VA_REST : VAApi;
            const url = new URL(`${api.api_url}dashboard/${moduleName}`);
            url.searchParams.append('employee_id', selectedProfessionalId);
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-WP-Nonce': api.api_nonce }
            });

            if (!response.ok) throw new Error((await response.json()).message || 'Error del servidor.');
            
            const result = await response.json();

            if (result.success) {
                moduleContainer.innerHTML = result.html;
                // Try to focus module header after injection
                setTimeout(() => {
                    const headerTitle = moduleContainer.querySelector('.module-header .dashboard-section-title');
                    if (headerTitle) {
                        if (!headerTitle.hasAttribute('tabindex')) headerTitle.setAttribute('tabindex', '-1');
                        try { headerTitle.focus({ preventScroll: true }); } catch (e) { try { headerTitle.focus(); } catch(_){} }
                    }
                }, 0);
                
                // El Orquestador
                console.log(`Módulo '${moduleName}' cargado. Inicializando su script...`);
                if (moduleName === 'appointments') {
                    // Inicializar el módulo de agenda después de que el HTML se cargue
                    console.log('Módulo de agenda cargado - llamando a inicialización...');
                    if (typeof window.initVeterinaliaAgendaModule === 'function') {
                        window.initVeterinaliaAgendaModule();
                    } else {
                        console.error('❌ Function initVeterinaliaAgendaModule no está disponible');
                    }
                } else if (moduleName === 'combos') {
                    // Inicializar el módulo de combos después de que el HTML se cargue
                    console.log('Módulo de combos cargado - llamando a inicialización...');
                    if (typeof window.initVeterinaliaCombosModule === 'function') {
                        window.initVeterinaliaCombosModule();
                    } else {
                        console.error('❌ Function initVeterinaliaCombosModule no está disponible');
                    }
                } else if (moduleName === 'patients') {
                    // Inicializar el módulo de pacientes explícitamente (más determinista)
                    console.log('Módulo de pacientes cargado - llamando a inicialización...');

                    // Intentar inicializar inmediatamente; si la función aún no está disponible,
                    // esperar a que el script que la define cargue o reintentar varias veces.
                    const tryInitPatients = (attempt = 0) => {
                        if (typeof window.initVeterinaliaPatientsModule === 'function') {
                            window.initVeterinaliaPatientsModule(selectedProfessionalId);
                            return;
                        }

                        // Buscar un script inyectado que coincida con patients-module.js y adjuntar onload
                        const script = moduleContainer.querySelector('script[src*="patients-module.js"]');
                        if (script && !script.dataset.initAttached) {
                            script.dataset.initAttached = '1';
                            script.addEventListener('load', () => {
                                if (typeof window.initVeterinaliaPatientsModule === 'function') {
                                    window.initVeterinaliaPatientsModule(selectedProfessionalId);
                                }
                            });

                            // Si el script ya está en estado cargado, intentar ahora
                            if (script.readyState === 'complete' || script.readyState === 'loaded') {
                                if (typeof window.initVeterinaliaPatientsModule === 'function') {
                                    window.initVeterinaliaPatientsModule(selectedProfessionalId);
                                    return;
                                }
                            }
                        }

                        if (attempt < 10) {
                            setTimeout(() => tryInitPatients(attempt + 1), 150);
                        } else {
                            console.error('❌ Function initVeterinaliaPatientsModule no está disponible después de reintentos');
                        }
                    };

                    tryInitPatients();
                } else if (moduleName === 'services' && typeof VA_Professional_Services !== 'undefined') {
                    VA_Professional_Services.init();
                } else if (moduleName === 'schedule' && typeof VA_Professional_Schedule !== 'undefined') {
                    VA_Professional_Schedule.init();
                } else if (moduleName === 'catalog' && typeof window.initVeterinaliaCatalogModule === 'function') {
                    window.initVeterinaliaCatalogModule();
                }

            } else {
                throw new Error(result.data.message || 'Ocurrió un error al cargar.');
            }
        } catch (error) {
            console.error(`Error al cargar el módulo ${moduleName}:`, error);
            moduleContainer.innerHTML = `<div class="card text-center text-red-500"><p><strong>Error:</strong> ${error.message}</p><button class="back-to-prof-main dashboard-button mt-4"><span>Volver</span></button></div>`;
        }
    }
});
