/**
 * PoolStream - Kasir & Billing (F&B Standalone) Engine
 * Integrated POS, Order Queue, History Filter & 80mm Thermal Receipt Printing
 */

const state = {
    activeOrders: [],
    historyOrders: [],
    fnbItems: [],
    categories: [],
    activeTab: 'active',
    activeSearch: '',
    historySearch: '',
    filterPreset: 'hari_ini',
    filterPresetLabel: 'Hari Ini',
    // Draft New Order
    draftCustomerName: '',
    draftItems: [],
    catalogSearch: '',
    catalogCategory: 'all',
    // Order Detail Modal
    selectedOrder: null,
    detailCatalogSearch: '',
    detailCatalogCategory: 'all',
    isLoading: false,
    isSubmitting: false
};

// ─── Formatters & Utilities ─────────────────────────────────
function formatRupiah(val) {
    const num = Number(val) || 0;
    return 'Rp ' + num.toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr.replace(/-/g, '/'));
    if (isNaN(d.getTime())) {
        const dIso = new Date(dateStr);
        if (isNaN(dIso.getTime())) return dateStr;
        return dIso.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Data Fetching ──────────────────────────────────────────
async function fetchKasirData(preserveLoading = false) {
    if (!preserveLoading && state.isLoading) return;
    state.isLoading = true;

    try {
        const url = `api/fnb_orders.php?action=list&filter=${encodeURIComponent(state.filterPreset)}&active_search=${encodeURIComponent(state.activeSearch)}&history_search=${encodeURIComponent(state.historySearch)}`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.success && json.data) {
            state.activeOrders = json.data.activeOrders || [];
            state.historyOrders = json.data.historyOrders || [];
            state.fnbItems = json.data.fnbItems || [];
            state.categories = json.data.categories || [];

            updateBadges();
            renderCategoryPills();
            if (state.activeTab === 'active') {
                renderActiveOrders();
            } else {
                renderHistoryOrders();
            }
        }
    } catch (err) {
        console.error('Error fetching kasir data:', err);
    } finally {
        state.isLoading = false;
    }
}

function updateBadges() {
    const activeBadge = document.getElementById('activeCountBadge');
    const historyBadge = document.getElementById('historyCountBadge');
    if (activeBadge) activeBadge.textContent = state.activeOrders.length;
    if (historyBadge) historyBadge.textContent = state.historyOrders.length;
}

// ─── Tab Switching ──────────────────────────────────────────
function switchOrderTab(tab) {
    state.activeTab = tab;
    const btnActive = document.getElementById('tabBtnActive');
    const btnHistory = document.getElementById('tabBtnHistory');
    const activeSec = document.getElementById('activeOrdersSection');
    const historySec = document.getElementById('historyOrdersSection');
    const presetWrap = document.getElementById('historyPresetWrapper');
    const searchInput = document.getElementById('orderSearchInput');

    if (tab === 'active') {
        btnActive.classList.add('kasir-tab-btn--active');
        btnHistory.classList.remove('kasir-tab-btn--active');
        activeSec.classList.remove('d-none');
        historySec.classList.add('d-none');
        if (presetWrap) presetWrap.classList.add('d-none');
        if (searchInput) {
            searchInput.placeholder = 'Cari pesanan aktif...';
            searchInput.value = state.activeSearch;
        }
        renderActiveOrders();
    } else {
        btnHistory.classList.add('kasir-tab-btn--active');
        btnActive.classList.remove('kasir-tab-btn--active');
        historySec.classList.remove('d-none');
        activeSec.classList.add('d-none');
        if (presetWrap) presetWrap.classList.remove('d-none');
        if (searchInput) {
            searchInput.placeholder = 'Cari riwayat pesanan...';
            searchInput.value = state.historySearch;
        }
        renderHistoryOrders();
    }
}

// Search with Debounce
let searchTimeout = null;
function onSearchInput(val) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (state.activeTab === 'active') {
            state.activeSearch = val.trim();
        } else {
            state.historySearch = val.trim();
        }
        fetchKasirData(true);
    }, 280);
}

// History Presets
function setHistoryPreset(preset, label, btnEl) {
    state.filterPreset = preset;
    state.filterPresetLabel = label;
    const labelEl = document.getElementById('currentPresetLabel');
    if (labelEl) labelEl.textContent = label;

    if (btnEl && btnEl.parentElement && btnEl.parentElement.parentElement) {
        btnEl.parentElement.parentElement.querySelectorAll('.dropdown-item').forEach(el => el.classList.remove('active'));
        btnEl.classList.add('active');
    }

    fetchKasirData(true);
}

// ─── Render Active Orders ───────────────────────────────────
function renderActiveOrders() {
    const grid = document.getElementById('activeOrdersGrid');
    if (!grid) return;

    if (state.activeOrders.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5 text-muted opacity-75 border rounded-4 p-4" style="border-color: rgba(255,255,255,0.06) !important;">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-warning"></i>
                <h6 class="fw-semibold">Belum Ada Pesanan Aktif</h6>
                <p class="small text-muted mb-3">Klik tombol "Pesanan Baru" untuk membuat transaksi F&B kasir.</p>
                <button type="button" class="bb-btn bb-btn--sm bb-btn--warning" onclick="openNewOrderModal()">
                    <i class="bi bi-plus-lg me-1"></i> Buat Pesanan Baru
                </button>
            </div>
        `;
        return;
    }

    grid.innerHTML = state.activeOrders.map((order, idx) => {
        const queueNum = idx + 1;
        const itemCount = order.items ? order.items.length : 0;
        const totalQty = order.items ? order.items.reduce((s, i) => s + Number(i.quantity), 0) : 0;
        const totalCost = Number(order.fnb_cost) || 0;

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="bb-order-card bb-order-card--active h-100" onclick="openDetailModal(${order.id})">
                    <div class="p-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="bb-queue-number">
                                #${queueNum}
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-bold text-truncate text-light" style="font-size: 1.05rem;">
                                    ${escapeHtml(order.customer_name)}
                                </div>
                                <div class="text-muted small" style="font-size: 0.78rem;">
                                    <i class="bi bi-clock me-1"></i>${formatDate(order.start_time || order.created_at)}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="fw-bold fs-5 text-warning">${formatRupiah(totalCost)}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: rgba(255,255,255,0.08) !important;">
                            <div class="d-flex align-items-center gap-3 text-muted small">
                                <span><i class="bi bi-cup-hot me-1"></i>${itemCount} menu</span>
                                <span><i class="bi bi-basket me-1"></i>${totalQty} porsi</span>
                            </div>
                            <span class="text-warning small fw-semibold">
                                Kelola & Bayar <i class="bi bi-chevron-right ms-1"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ─── Render History Orders ──────────────────────────────────
function renderHistoryOrders() {
    const grid = document.getElementById('historyOrdersGrid');
    if (!grid) return;

    if (state.historyOrders.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5 text-muted opacity-75 border rounded-4 p-4" style="border-color: rgba(255,255,255,0.06) !important;">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                <h6 class="fw-semibold">Tidak Ada Riwayat Transaksi</h6>
                <p class="small text-muted mb-0">Belum ada transaksi F&B selesai pada periode ${escapeHtml(state.filterPresetLabel)}.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = state.historyOrders.map(order => {
        const isCompleted = order.status === 'completed';
        const itemCount = order.items ? order.items.length : 0;
        const totalQty = order.items ? order.items.reduce((s, i) => s + Number(i.quantity), 0) : 0;
        const totalCost = Number(order.fnb_cost) || 0;

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="bb-order-card ${isCompleted ? 'bb-order-card--completed' : 'bb-order-card--cancelled'} h-100" onclick="openDetailModal(${order.id})">
                    <div class="p-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                                 style="width: 38px; height: 38px; background: ${isCompleted ? 'rgba(16,185,129,0.15)' : 'rgba(148,163,184,0.15)'}; color: ${isCompleted ? '#10b981' : '#94a3b8'};">
                                <i class="bi ${isCompleted ? 'bi-check-lg' : 'bi-x-lg'} fs-5"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-bold text-truncate text-light" style="font-size: 1.02rem;">
                                    ${escapeHtml(order.customer_name)}
                                </div>
                                <div class="text-muted small" style="font-size: 0.76rem;">
                                    <i class="bi bi-calendar3 me-1"></i>${formatDate(order.end_time || order.updated_at || order.created_at)}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="fw-bold ${isCompleted ? 'text-emerald' : 'text-muted'}">${formatRupiah(totalCost)}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: rgba(255,255,255,0.06) !important;">
                            <div class="d-flex align-items-center gap-3 text-muted small" style="font-size: 0.75rem;">
                                <span><i class="bi bi-cup-hot me-1"></i>${itemCount} menu</span>
                                <span><i class="bi bi-basket me-1"></i>${totalQty} porsi</span>
                            </div>
                            <span class="badge ${isCompleted ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                ${isCompleted ? 'Selesai' : 'Dibatalkan'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ─── Modal 1: Pesanan Baru (New Order) ──────────────────────
function openNewOrderModal() {
    state.draftCustomerName = '';
    state.draftItems = [];
    state.catalogSearch = '';
    state.catalogCategory = 'all';

    const inputName = document.getElementById('newCustomerName');
    const inputSearch = document.getElementById('catalogSearchInput');
    if (inputName) inputName.value = '';
    if (inputSearch) inputSearch.value = '';

    renderCategoryPills();
    renderNewOrderCatalog();
    renderDraftCart();

    const modal = document.getElementById('newOrderModal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => {
            const inner = modal.querySelector('.bb-modal');
            if (inner) inner.style.opacity = '1';
            if (inputName) inputName.focus();
        }, 10);
    }
}

function closeNewOrderModal() {
    const modal = document.getElementById('newOrderModal');
    if (!modal) return;
    const inner = modal.querySelector('.bb-modal');
    if (inner) inner.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

function renderCategoryPills() {
    const container = document.getElementById('catalogCategoryPills');
    if (!container) return;

    let html = `<button type="button" class="category-pill ${state.catalogCategory === 'all' ? 'category-pill--active' : ''}" onclick="filterCatalogCat('all', this)">Semua</button>`;
    state.categories.forEach(cat => {
        html += `<button type="button" class="category-pill ${state.catalogCategory === cat ? 'category-pill--active' : ''}" onclick="filterCatalogCat('${escapeHtml(cat)}', this)">${escapeHtml(cat)}</button>`;
    });
    container.innerHTML = html;
}

function filterCatalogCat(cat, btnEl) {
    state.catalogCategory = cat;
    if (btnEl && btnEl.parentElement) {
        btnEl.parentElement.querySelectorAll('.category-pill').forEach(b => b.classList.remove('category-pill--active'));
        btnEl.classList.add('category-pill--active');
    }
    renderNewOrderCatalog();
}

function onCatalogSearch(val) {
    state.catalogSearch = val.toLowerCase().trim();
    renderNewOrderCatalog();
}

function renderNewOrderCatalog() {
    const grid = document.getElementById('newOrderCatalogGrid');
    if (!grid) return;

    let items = state.fnbItems;
    if (state.catalogCategory !== 'all') {
        items = items.filter(i => i.category === state.catalogCategory);
    }
    if (state.catalogSearch) {
        items = items.filter(i => (i.name || '').toLowerCase().includes(state.catalogSearch));
    }

    if (items.length === 0) {
        grid.innerHTML = `
            <div class="text-center py-4 text-muted col-span-2" style="grid-column: 1 / -1;">
                <i class="bi bi-search fs-3 d-block mb-1"></i>
                <span class="small">Menu tidak ditemukan.</span>
            </div>
        `;
        return;
    }

    grid.innerHTML = items.map(item => {
        const imgSrc = item.image_url || (item.image_path ? 'storage/' + item.image_path : 'assets/images/fnb_default.png');
        return `
            <div class="fnb-mini-card" onclick="addDraftItem(${item.id})">
                <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(item.name)}" onerror="this.src='assets/images/logo.png'">
                <div class="fnb-mini-info">
                    <div class="name">${escapeHtml(item.name)}</div>
                    <div class="price">${formatRupiah(item.price)}</div>
                </div>
                <button type="button" class="fnb-mini-btn" title="Tambah ke keranjang">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        `;
    }).join('');
}

function addDraftItem(itemId) {
    const item = state.fnbItems.find(i => i.id == itemId);
    if (!item) return;

    const existing = state.draftItems.find(i => i.fnb_item_id == itemId);
    if (existing) {
        existing.quantity += 1;
        existing.subtotal = existing.price * existing.quantity;
    } else {
        state.draftItems.push({
            fnb_item_id: item.id,
            name: item.name,
            price: Number(item.price),
            quantity: 1,
            subtotal: Number(item.price)
        });
    }

    renderDraftCart();
}

function updateDraftItemQty(idx, change) {
    const it = state.draftItems[idx];
    if (!it) return;
    it.quantity += change;
    if (it.quantity <= 0) {
        state.draftItems.splice(idx, 1);
    } else {
        it.subtotal = it.price * it.quantity;
    }
    renderDraftCart();
}

function removeDraftItem(idx) {
    state.draftItems.splice(idx, 1);
    renderDraftCart();
}

function renderDraftCart() {
    const container = document.getElementById('newOrderCartList');
    const totalDisplay = document.getElementById('newOrderTotalDisplay');
    const countBadge = document.getElementById('cartItemsCountBadge');

    if (!container) return;

    const totalCost = state.draftItems.reduce((sum, i) => sum + i.subtotal, 0);
    const totalQty = state.draftItems.reduce((sum, i) => sum + i.quantity, 0);

    if (totalDisplay) totalDisplay.textContent = formatRupiah(totalCost);
    if (countBadge) countBadge.textContent = `${totalQty} item`;

    if (state.draftItems.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted small opacity-75 border rounded-3 p-3" style="border-color: rgba(255,255,255,0.08) !important;">
                <i class="bi bi-cart-x fs-3 d-block mb-1"></i>
                Keranjang masih kosong.<br>Pilih item menu di sebelah kiri untuk menambahkan.
            </div>
        `;
        return;
    }

    container.innerHTML = state.draftItems.map((it, idx) => `
        <div class="p-2 mb-2 rounded-3 d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
            <div class="flex-grow-1 overflow-hidden me-2">
                <div class="fw-semibold small text-truncate text-light">${escapeHtml(it.name)}</div>
                <div class="text-muted" style="font-size: 0.75rem;">${formatRupiah(it.price)} × ${it.quantity}</div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="bb-btn bb-btn--ghost px-2 py-0 text-muted" onclick="updateDraftItemQty(${idx}, -1)" style="font-size: 0.75rem; height: 26px;">
                    <i class="bi bi-dash-lg"></i>
                </button>
                <span class="px-2 fw-bold text-warning small">${it.quantity}</span>
                <button type="button" class="bb-btn bb-btn--ghost px-2 py-0 text-warning" onclick="updateDraftItemQty(${idx}, 1)" style="font-size: 0.75rem; height: 26px;">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button type="button" class="btn btn-sm text-danger p-1 ms-1" onclick="removeDraftItem(${idx})" title="Hapus">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function submitNewOrder(e) {
    e.preventDefault();
    if (state.isSubmitting) return;

    const inputName = document.getElementById('newCustomerName');
    const customerName = inputName ? inputName.value.trim() : '';

    if (!customerName) {
        alert('Nama pelanggan wajib diisi!');
        if (inputName) inputName.focus();
        return;
    }

    if (state.draftItems.length === 0) {
        alert('Keranjang pesanan F&B masih kosong! Silakan pilih minimal 1 item menu.');
        return;
    }

    state.isSubmitting = true;
    try {
        const payload = {
            customer_name: customerName,
            items: state.draftItems.map(i => ({
                fnb_item_id: i.fnb_item_id,
                quantity: i.quantity
            }))
        };

        const res = await fetch('api/fnb_orders.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            closeNewOrderModal();
            fetchKasirData(true);
        } else {
            alert(json.message || 'Gagal membuat pesanan F&B.');
        }
    } catch (err) {
        console.error('Submit order error:', err);
        alert('Terjadi kesalahan jaringan.');
    } finally {
        state.isSubmitting = false;
    }
}

// ─── Modal 2: Detail / Kelola / Checkout Modal ─────────────
async function openDetailModal(orderId) {
    try {
        const res = await fetch(`api/fnb_orders.php?action=detail&id=${orderId}`);
        const json = await res.json();
        if (!json.success || !json.transaction) {
            alert('Pesanan tidak ditemukan.');
            return;
        }

        const order = json.transaction;
        state.selectedOrder = order;

        const titleEl = document.getElementById('detailModalTitle');
        const subEl = document.getElementById('detailModalSubtitle');

        if (titleEl) titleEl.textContent = `Pesanan #${order.id} - ${order.customer_name}`;
        if (subEl) subEl.textContent = order.status === 'active' ? 'Pesanan Aktif / Berjalan' : `Riwayat Transaksi (${order.status})`;

        if (order.status === 'active') {
            renderActiveOrderDetailBody(order);
        } else {
            renderHistoryOrderDetailBody(order);
        }

        const modal = document.getElementById('detailModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                const inner = modal.querySelector('.bb-modal');
                if (inner) inner.style.opacity = '1';
            }, 10);
        }
    } catch (err) {
        console.error('Error opening detail modal:', err);
    }
}

function closeDetailModal() {
    const modal = document.getElementById('detailModal');
    if (!modal) return;
    const inner = modal.querySelector('.bb-modal');
    if (inner) inner.style.opacity = '0';
    setTimeout(() => {
        modal.style.display = 'none';
        state.selectedOrder = null;
    }, 200);
}

function renderActiveOrderDetailBody(order) {
    const bodyEl = document.getElementById('detailModalBody');
    if (!bodyEl) return;

    const totalCost = Number(order.fnb_cost) || 0;
    const items = order.items || [];

    bodyEl.innerHTML = `
        <div class="modal-split-grid">
            <!-- Left: Add More F&B Items -->
            <div>
                <div class="fw-semibold text-warning small mb-2">
                    <i class="bi bi-plus-circle-fill me-1"></i> Tambah Menu F&B ke Pesanan
                </div>

                <div class="position-relative mb-2">
                    <input type="text" class="custom-input" placeholder="Cari menu..." style="height: 36px; padding-left: 32px;" oninput="onDetailCatalogSearch(this.value)">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y text-muted" style="left: 10px; font-size: 0.85rem;"></i>
                </div>

                <div class="category-pill-scroll mb-2" id="detailCategoryPills">
                    <button type="button" class="category-pill category-pill--active" onclick="filterDetailCatalogCat('all', this)">Semua</button>
                    ${state.categories.map(c => `<button type="button" class="category-pill" onclick="filterDetailCatalogCat('${escapeHtml(c)}', this)">${escapeHtml(c)}</button>`).join('')}
                </div>

                <div class="fnb-catalog-grid" id="detailCatalogGrid" style="max-height: 280px; overflow-y: auto;"></div>
            </div>

            <!-- Right: Current Items List & Checkout Action -->
            <div class="d-flex flex-column h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold small">Daftar Item Pesanan</span>
                    <span class="badge bg-warning text-dark rounded-pill">${items.length} menu</span>
                </div>

                <div class="kasir-cart-list flex-grow-1 mb-3" style="max-height: 250px; overflow-y: auto;">
                    ${items.length === 0 ? `
                        <div class="text-center py-4 text-muted small border rounded-3 p-3">
                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                            Belum ada item pesanan.
                        </div>
                    ` : items.map(it => `
                        <div class="p-2 mb-2 rounded-3 d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            <div class="flex-grow-1 overflow-hidden me-2">
                                <div class="fw-semibold small text-truncate text-light">${escapeHtml(it.fnb_name || 'Item')}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">${formatRupiah(it.price)} × ${it.quantity}</div>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="bb-btn bb-btn--ghost px-2 py-0 text-muted" onclick="changeOrderItemQty(${it.id}, -1)" style="font-size: 0.75rem; height: 26px;">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <span class="px-2 fw-bold text-warning small">${it.quantity}</span>
                                <button type="button" class="bb-btn bb-btn--ghost px-2 py-0 text-warning" onclick="changeOrderItemQty(${it.id}, 1)" style="font-size: 0.75rem; height: 26px;">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <button type="button" class="btn btn-sm text-danger p-1 ms-1" onclick="deleteOrderItem(${it.id})" title="Hapus">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <!-- Total Box -->
                <div class="p-3 rounded-3 mb-3 kasir-total-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-warning">Total Tagihan</span>
                        <span class="fw-bold fs-5 text-warning">${formatRupiah(totalCost)}</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 mt-auto">
                    <button type="button" class="bb-btn bb-btn--ghost flex-grow-1" onclick="closeDetailModal()">
                        Tutup
                    </button>
                    <button type="button" class="bb-btn bb-btn--danger flex-grow-1" onclick="executeFnbCheckout(${order.id})">
                        <i class="bi bi-receipt me-1"></i> Checkout & Struk
                    </button>
                </div>
            </div>
        </div>
    `;

    renderDetailCatalogGrid();
}

function onDetailCatalogSearch(val) {
    state.detailCatalogSearch = val.toLowerCase().trim();
    renderDetailCatalogGrid();
}

function filterDetailCatalogCat(cat, btnEl) {
    state.detailCatalogCategory = cat;
    if (btnEl && btnEl.parentElement) {
        btnEl.parentElement.querySelectorAll('.category-pill').forEach(b => b.classList.remove('category-pill--active'));
        btnEl.classList.add('category-pill--active');
    }
    renderDetailCatalogGrid();
}

function renderDetailCatalogGrid() {
    const grid = document.getElementById('detailCatalogGrid');
    if (!grid) return;

    let items = state.fnbItems;
    if (state.detailCatalogCategory !== 'all') {
        items = items.filter(i => i.category === state.detailCatalogCategory);
    }
    if (state.detailCatalogSearch) {
        items = items.filter(i => (i.name || '').toLowerCase().includes(state.detailCatalogSearch));
    }

    if (items.length === 0) {
        grid.innerHTML = '<div class="text-center py-3 text-muted small col-span-2">Menu tidak ditemukan.</div>';
        return;
    }

    grid.innerHTML = items.map(item => {
        const imgSrc = item.image_url || (item.image_path ? 'storage/' + item.image_path : 'assets/images/fnb_default.png');
        return `
            <div class="fnb-mini-card" onclick="addOrderItemToActive(${item.id})">
                <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(item.name)}" onerror="this.src='assets/images/logo.png'">
                <div class="fnb-mini-info">
                    <div class="name">${escapeHtml(item.name)}</div>
                    <div class="price">${formatRupiah(item.price)}</div>
                </div>
                <button type="button" class="fnb-mini-btn" title="Tambah ke pesanan">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        `;
    }).join('');
}

async function addOrderItemToActive(fnbId) {
    if (!state.selectedOrder) return;
    try {
        const res = await fetch('api/fnb_orders.php?action=add_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transaction_id: state.selectedOrder.id,
                fnb_item_id: fnbId,
                quantity: 1
            })
        });
        const json = await res.json();
        if (json.success && json.transaction) {
            state.selectedOrder = json.transaction;
            renderActiveOrderDetailBody(json.transaction);
            fetchKasirData(true);
        }
    } catch (err) {
        console.error('Error adding item:', err);
    }
}

async function changeOrderItemQty(itemId, change) {
    try {
        const res = await fetch('api/fnb_orders.php?action=update_item_qty', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, change: change })
        });
        const json = await res.json();
        if (json.success && json.transaction) {
            state.selectedOrder = json.transaction;
            renderActiveOrderDetailBody(json.transaction);
            fetchKasirData(true);
        }
    } catch (err) {
        console.error('Error updating qty:', err);
    }
}

async function deleteOrderItem(itemId) {
    if (!confirm('Hapus item ini dari pesanan?')) return;
    try {
        const res = await fetch('api/fnb_orders.php?action=delete_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        });
        const json = await res.json();
        if (json.success && json.transaction) {
            state.selectedOrder = json.transaction;
            renderActiveOrderDetailBody(json.transaction);
            fetchKasirData(true);
        }
    } catch (err) {
        console.error('Error deleting item:', err);
    }
}

async function executeFnbCheckout(orderId) {
    if (!confirm('Konfirmasi checkout dan cetak struk nota pesanan?')) return;

    try {
        const res = await fetch('api/fnb_orders.php?action=checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: orderId })
        });
        const json = await res.json();

        if (json.success && json.transaction) {
            printReceipt(json.transaction);
            closeDetailModal();
            fetchKasirData(true);
        } else {
            alert(json.message || 'Gagal checkout pesanan.');
        }
    } catch (err) {
        console.error('Checkout error:', err);
        alert('Terjadi kesalahan jaringan.');
    }
}

// History Read-Only Modal View
function renderHistoryOrderDetailBody(order) {
    const bodyEl = document.getElementById('detailModalBody');
    if (!bodyEl) return;

    const totalCost = Number(order.fnb_cost) || 0;
    const items = order.items || [];
    const isCompleted = order.status === 'completed';

    bodyEl.innerHTML = `
        <div style="max-width: 580px; margin: 0 auto;">
            <!-- Status Pill Banner -->
            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3"
                 style="background: ${isCompleted ? 'rgba(16,185,129,0.1)' : 'rgba(148,163,184,0.1)'}; border: 1px solid ${isCompleted ? 'rgba(16,185,129,0.2)' : 'rgba(148,163,184,0.2)'};">
                <div>
                    <span class="badge ${isCompleted ? 'bg-success' : 'bg-secondary'} rounded-pill px-2 py-1 mb-1">
                        ${isCompleted ? 'TRANSAKSI SELESAI' : 'DIBATALKAN'}
                    </span>
                    <div class="small text-muted">Waktu Selesai: ${formatDate(order.end_time || order.updated_at)}</div>
                </div>
                <div class="text-end">
                    <span class="fw-bold fs-5 ${isCompleted ? 'text-emerald' : 'text-muted'}">${formatRupiah(totalCost)}</span>
                </div>
            </div>

            <!-- Items List -->
            <h6 class="fw-semibold small mb-2 text-muted">Rincian Menu Dipesan:</h6>
            <div class="kasir-cart-list mb-3" style="max-height: 280px; overflow-y: auto;">
                ${items.length === 0 ? '<div class="text-center py-3 text-muted small">Tidak ada item</div>' : items.map(it => `
                    <div class="p-3 mb-2 rounded-3 d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-cup-hot fs-5 text-warning opacity-75"></i>
                            <div>
                                <div class="fw-semibold text-light">${escapeHtml(it.fnb_name || 'Item')}</div>
                                <div class="text-muted small">${formatRupiah(it.price)} × ${it.quantity}</div>
                            </div>
                        </div>
                        <span class="fw-bold text-warning">${formatRupiah(it.subtotal)}</span>
                    </div>
                `).join('')}
            </div>

            <!-- Buttons -->
            <div class="d-flex gap-2">
                <button type="button" class="bb-btn bb-btn--ghost flex-grow-1" onclick="closeDetailModal()">
                    Tutup
                </button>
                <button type="button" class="bb-btn bb-btn--warning flex-grow-1" onclick="printReceipt(state.selectedOrder)">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Ulang Struk
                </button>
            </div>
        </div>
    `;
}

// ─── 80mm Thermal Receipt Generator ─────────────────────────
function printReceipt(transaction, cashierName = 'Kasir') {
    if (!transaction) return;

    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    const fmtPrice = (val) => {
        const n = Number(val) || 0;
        return n.toLocaleString('id-ID');
    };

    let itemsHtml = '';
    const items = transaction.items || [];
    items.forEach(item => {
        const name = item.fnb_name || item.name || 'Item';
        const sub = Number(item.subtotal) || (Number(item.price) * Number(item.quantity));
        itemsHtml += `
            <tr>
                <td style="padding: 2px 0;">${escapeHtml(name)} x${item.quantity}</td>
                <td style="padding: 2px 0; text-align: right;">${fmtPrice(sub)}</td>
            </tr>
        `;
    });

    const totalCost = Number(transaction.fnb_cost) || Number(transaction.total_cost) || 0;

    const receiptHtml = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk #${transaction.id || ''}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            padding: 8px;
            color: #000;
            background: #fff;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }
        .store-name { font-size: 18px; font-weight: bold; letter-spacing: 2px; }
        .total-row { font-size: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .footer { margin-top: 10px; font-size: 11px; }
        @media print {
            body { width: 80mm; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="center">
        <div class="store-name">POOLSTREAM</div>
        <div style="font-size: 10px; margin-top: 2px;">Billiard & Lounge (Kasir F&B)</div>
    </div>
    <div class="double-divider"></div>

    <table>
        <tr><td>No. Transaksi</td><td class="right">#${transaction.id || '-'}</td></tr>
        <tr><td>Tanggal</td><td class="right">${dateStr} ${timeStr}</td></tr>
        <tr><td>Kasir</td><td class="right">${escapeHtml(transaction.cashier_name || cashierName)}</td></tr>
        <tr><td>Pelanggan</td><td class="right">${escapeHtml(transaction.customer_name || '-')}</td></tr>
    </table>

    <div class="divider"></div>
    <div class="bold" style="margin-bottom: 4px;">PESANAN F&B</div>
    <table>
        ${itemsHtml || '<tr><td colspan="2" class="center">Tidak ada item</td></tr>'}
    </table>

    <div class="divider"></div>
    <table>
        <tr><td>Subtotal F&B</td><td class="right">${fmtPrice(totalCost)}</td></tr>
    </table>
    <div class="double-divider"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">Rp ${fmtPrice(totalCost)}</td>
        </tr>
    </table>
    <div class="double-divider"></div>

    <div class="center footer">
        <div>Terima kasih atas pesanan Anda!</div>
        <div>Selamat menikmati &#127865;</div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    <\/script>
</body>
</html>`;

    const printWin = window.open('', '_blank', 'width=420,height=600');
    if (printWin) {
        printWin.document.write(receiptHtml);
        printWin.document.close();
    }
}

// ─── Navbar Clock & Theme Toggle ────────────────────────────
function initHeaderClock() {
    const clockEl = document.getElementById('headerClock');
    if (!clockEl) return;

    function updateTime() {
        const now = new Date();
        const opts = { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        clockEl.textContent = now.toLocaleDateString('id-ID', opts);
    }
    updateTime();
    setInterval(updateTime, 1000);
}

function initThemeToggle() {
    const btn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const saved = localStorage.getItem('poolstream_theme') || 'dark';

    function setTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        if (icon) {
            icon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        localStorage.setItem('poolstream_theme', t);
    }

    setTheme(saved);

    if (btn) {
        btn.addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            setTheme(cur === 'dark' ? 'light' : 'dark');
        });
    }
}

// ─── Initialization ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initHeaderClock();
    initThemeToggle();
    fetchKasirData();

    // Auto-refresh active orders every 15s
    setInterval(() => {
        if (state.activeTab === 'active' && !state.selectedOrder) {
            fetchKasirData(true);
        }
    }, 15000);
});
