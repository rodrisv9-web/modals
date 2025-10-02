/**
 * Módulo "Mi Catálogo" - Gestión de Productos
 * Versión 1.0.0
 */
(function() {
    'use strict';

    const MODAL_TRANSITION_MS = 300;

    class CatalogModule {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            if (!this.container) return;

            this.state = {
                products: [],
                filteredProducts: [],
                editingProduct: null,
                professionalId: this.container.dataset.professionalId,
            };

            this.dom = {
                grid: this.container.querySelector('#products-grid-container'),
                searchInput: this.container.querySelector('#product-search'),
                typeFilter: this.container.querySelector('#product-type-filter'),
                addProductBtn: this.container.querySelector('#add-product-btn'),
                modal: this.container.querySelector('#product-modal'),
                modalTitle: this.container.querySelector('#product-modal-title'),
                productForm: this.container.querySelector('#product-form'),
                productIdInput: this.container.querySelector('#product-id'),
            };

            this.modalHideTimeout = null;

            this.init();
        }

        async init() {
            this.bindEvents();
            await this.loadProducts();
        }

        bindEvents() {
            this.dom.addProductBtn.addEventListener('click', () => this.showProductModal());
            this.dom.searchInput.addEventListener('input', () => this.filterProducts());
            this.dom.typeFilter.addEventListener('change', () => this.filterProducts());
            this.dom.productForm.addEventListener('submit', (e) => this.handleFormSubmit(e));

            this.dom.modal.querySelector('#close-product-modal').addEventListener('click', () => this.hideModal());
            this.dom.modal.querySelector('#cancel-product-btn').addEventListener('click', () => this.hideModal());

            this.dom.modal.addEventListener('click', (event) => {
                if (event.target === this.dom.modal) {
                    this.hideModal();
                }
            });

            this.dom.grid.addEventListener('click', (e) => {
                if (e.target.closest('.btn-edit-product')) {
                    const card = e.target.closest('.product-card');
                    const productId = parseInt(card.dataset.productId);
                    this.editProduct(productId);
                }
                if (e.target.closest('.btn-delete-product')) {
                    const card = e.target.closest('.product-card');
                    const productId = parseInt(card.dataset.productId);
                    this.deleteProduct(productId);
                }
            });
        }

        async loadProducts() {
            this.renderLoading(true);
            try {
                // Usar el nuevo endpoint que devuelve datos normalizados
                const response = await VAApi.getProductsFullByProfessional(this.state.professionalId);

                if (response && response.success) {
                    this.state.products = Array.isArray(response.data) ? response.data : [];
                    this.filterProducts();
                } else {
                    const message = response && response.message ? response.message : 'No se pudieron cargar los productos.';
                    throw new Error(message);
                }
            } catch (error) {
                this.renderError('Error al cargar productos.');
                console.error('[CatalogModule] Error al cargar productos:', error);
            } finally {
                this.renderLoading(false);
            }
        }

        filterProducts() {
            const searchTerm = this.dom.searchInput.value.toLowerCase();
            const typeFilter = this.dom.typeFilter.value;

            this.state.filteredProducts = this.state.products.filter(product => {
                const nameMatch = product.product_name.toLowerCase().includes(searchTerm);
                const typeMatch = typeFilter === 'all' || product.product_type === typeFilter;
                return nameMatch && typeMatch;
            });
            this.renderProducts();
        }

        renderProducts() {
            if (this.state.filteredProducts.length === 0) {
                this.renderEmpty();
                return;
            }

            this.dom.grid.innerHTML = this.state.filteredProducts.map(p => `
                <div class="product-card" data-product-id="${p.product_id}">
                    <div class="product-card-header">
                        <h4>${p.product_name}</h4>
                    </div>
                    <div class="product-card-body">
                        <div class="product-detail">
                            <p><strong>Tipo:</strong> <span>${p.product_type}</span></p>
                            <p><strong>Fabricante:</strong> <span>${p.manufacturer || 'N/A'}</span></p>
                            <p><strong>Presentación:</strong> <span>${p.presentation || 'N/A'}</span></p>
                        </div>
                    </div>
                    <div class="product-card-footer">
                        <button class="btn-edit-product">Editar</button>
                        <button class="btn-delete-product">Eliminar</button>
                    </div>
                </div>
            `).join('');
        }

        showProductModal(product = null) {
            this.state.editingProduct = product;
            if (product) {
                this.dom.modalTitle.textContent = 'Editar Producto';
                this.dom.productIdInput.value = product.product_id;
                this.dom.productForm.querySelector('#product-name').value = product.product_name;
                this.dom.productForm.querySelector('#product-type').value = product.product_type;
                this.dom.productForm.querySelector('#product-manufacturer').value = product.manufacturer || '';
                this.dom.productForm.querySelector('#product-presentation').value = product.presentation || '';
                this.dom.productForm.querySelector('#product-active-ingredient').value = product.active_ingredient || '';
                this.dom.productForm.querySelector('#product-notes').value = product.notes || '';
            } else {
                this.dom.modalTitle.textContent = 'Nuevo Producto';
                this.dom.productForm.reset();
                this.dom.productIdInput.value = '';
            }
            if (this.modalHideTimeout) {
                clearTimeout(this.modalHideTimeout);
                this.modalHideTimeout = null;
            }

            this.dom.modal.classList.remove('hidden');

            requestAnimationFrame(() => {
                this.dom.modal.classList.add('visible');
            });

            document.body.style.overflow = 'hidden';
        }
        
        editProduct(productId) {
            const product = this.state.products.find(p => p.product_id == productId);
            if(product) this.showProductModal(product);
        }

        async deleteProduct(productId) {
            if (!confirm('¿Estás seguro de que quieres eliminar este producto?')) return;

            try {
                const response = await VAApi.deleteProduct(productId, this.state.professionalId);

                if (response && response.success) {
                    await this.loadProducts();
                } else {
                    const message = response && response.message ? response.message : 'Error al eliminar';
                    throw new Error(message);
                }
            } catch (error) {
                alert(error.message || 'Error al eliminar');
                console.error('[CatalogModule] Error al eliminar producto:', error);
            }
        }
        
        async handleFormSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            const productId = this.dom.productIdInput.value;
            const productData = {
                professional_id: this.state.professionalId,
                product_name: form.querySelector('#product-name').value,
                product_type: form.querySelector('#product-type').value,
                manufacturer: form.querySelector('#product-manufacturer').value,
                presentation: form.querySelector('#product-presentation').value,
                active_ingredient: form.querySelector('#product-active-ingredient').value,
                notes: form.querySelector('#product-notes').value,
            };

            try {
                const response = productId
                    ? await VAApi.updateProduct(productId, productData)
                    : await VAApi.saveProduct(productData);

                if (response && response.success) {
                    this.hideModal();
                    await this.loadProducts();
                } else {
                    const message = response && response.message ? response.message : 'Error al guardar';
                    throw new Error(message);
                }
            } catch (error) {
                alert(error.message || 'Error al guardar');
                console.error('[CatalogModule] Error al guardar producto:', error);
            } finally {
                submitBtn.disabled = false;
            }
        }

        hideModal() {
            this.dom.modal.classList.remove('visible');

            document.body.style.overflow = '';

            this.modalHideTimeout = setTimeout(() => {
                this.dom.modal.classList.add('hidden');
                this.modalHideTimeout = null;
            }, MODAL_TRANSITION_MS);
        }

        renderLoading(isLoading) {
            if (isLoading) {
                this.dom.grid.innerHTML = `<div class="loading-placeholder"><div class="loader"></div><p>Cargando...</p></div>`;
            }
        }

        renderEmpty() {
            this.dom.grid.innerHTML = `<div class="empty-state"><p>No se encontraron productos. ¡Añade el primero!</p></div>`;
        }
        
        renderError(message) {
            this.dom.grid.innerHTML = `<div class="empty-state error"><p>${message}</p></div>`;
        }
    }

    // Inicializador global
    window.initVeterinaliaCatalogModule = function() {
        if (document.getElementById('catalog-module')) {
            new CatalogModule('catalog-module');
        }
    };
})();
