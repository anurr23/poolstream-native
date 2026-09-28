/**
 * PoolStream Dashboard Client-side Logic (Vanilla JS)
 */

let state = {
    tables: [],
    packages: [],
    fnbItems: [],
    filter: 'all',
    selectedTable: null,
    selectedActiveTx: null,
    selectedTableHistory: null,
    draftItems: [],
    fnbSearch: '',
    fnbCategory: 'all',
    sessionTab: 'edit',
    historySearch: ''
};

// Formatting helpers
function formatRupiah(amount) {
    const n = Number(amount) || 0;
    return 'Rp ' + n.toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function calculateDurationText(startTimeStr, endTimeStr) {
    if (!startTimeStr || !endTimeStr) return '-';
    const start = new Date(startTimeStr);
    const end = new Date(endTimeStr);
    const diffMs = end - start;
    if (isNaN(diffMs) || diffMs <= 0) return '-';
    const diffMinutes = Math.round(diffMs / (1000 * 60));
    if (diffMinutes < 60) return `${diffMinutes} Mnt`;
    const hours = Math.floor(diffMinutes / 60);
    const mins = diffMinutes % 60;
    return mins === 0 ? `${hours} Jam` : `${hours} Jam ${mins} Mnt`;
}

// Fetch tables & data from API
async function loadDashboardData() {
    try {
        const res = await fetch('api/tables.php?action=list');
        const json = await res.json();
        if (json.success) {
            state.tables = json.data.tables;
            state.packages = json.data.packages;
            state.fnbItems = json.data.fnbItems;
            renderTables();
        }
    } catch (e) {
        console.error("Gagal memuat data meja:", e);
    }
}

// Render 16 Table Cards
function renderTables() {
    const grid = document.getElementById('tablesGrid');
    if (!grid) return;

    let filtered = state.tables;
    if (state.filter === 'active') {
        filtered = state.tables.filter(t => t.status === 'active');
    } else if (state.filter === 'ready') {
        filtered = state.tables.filter(t => t.status !== 'active');
    }

    if (filtered.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; opacity: 0.5;">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p style="margin-top: 10px;">Tidak ada meja dalam kategori ini.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = filtered.map(t => {
        const isActive = t.status === 'active';
        const tx = (t.transactions && t.transactions.length > 0) ? t.transactions[0] : null;
        const fnbCount = tx && tx.items ? tx.items.reduce((sum, i) => sum + Number(i.quantity), 0) : 0;

        return `
            <div class="bb-table-card ${isActive ? 'bb-table-card--active' : ''}" onclick="onTableClick('${t.id}')">
                <div class="table-card-header">
                    <div class="table-name-wrapper">
                        <h4 class="table-title">${t.name}</h4>
                        <i class="bi ${isActive ? 'bi-lightbulb-fill table-bulb--on' : 'bi-lightbulb table-bulb'}" 
                           title="${isActive ? 'Lampu Menyala' : 'Lampu Mati'}"></i>
                    </div>
                    <span class="bb-status-badge ${isActive ? 'bb-status-badge--active' : 'bb-status-badge--ready'}">
                        <i class="bi ${isActive ? 'bi-circle-fill' : 'bi-circle'}" style="font-size: 0.45rem;"></i>
                        ${isActive ? 'In Use' : 'Ready'}
                    </span>
                </div>

                <div class="table-card-body">
                    ${isActive ? `
                        <div class="timer-label">Sisa Waktu</div>
                        <div class="bb-timer" id="timer-${t.id}">00:00:00</div>
                        ${fnbCount > 0 ? `
                            <div class="table-fnb-badge">
                                <i class="bi bi-cup-hot-fill"></i> ${fnbCount} item F&B
                            </div>
                        ` : ''}
                    ` : `
                        <div class="available-state">
                            <i class="bi bi-clock"></i>
                            <div style="font-weight: 600; font-size: 0.95rem;">Available</div>
                        </div>
                    `}
                </div>

                <div class="table-card-footer">
                    <span>Relay #${t.relay_channel}</span>
                    <span>${isActive && tx ? tx.customer_name : 'Klik untuk order'}</span>
                </div>
            </div>
        `;
    }).join('');

    updateCountdowns();
}

// Helper parse timezone server (Asia/Jakarta +07:00)
function parseEndTime(tx) {
    if (!tx) return null;
    if (tx.expected_end_time_iso) {
        return new Date(tx.expected_end_time_iso);
    }
    if (tx.expected_end_time) {
        const str = tx.expected_end_time.replace(' ', 'T');
        return new Date(str.includes('+') || str.includes('Z') ? str : str + '+07:00');
    }
    return null;
}

// Countdown loop
function updateCountdowns() {
    state.tables.forEach(t => {
        if (t.status === 'active' && t.transactions && t.transactions.length > 0) {
            const tx = t.transactions[0];
            const elem = document.getElementById(`timer-${t.id}`);
            const end = parseEndTime(tx);

            if (elem && end) {
                const now = new Date();
                const diffMs = end.getTime() - now.getTime();

                if (diffMs <= 0) {
                    elem.textContent = '00:00:00';
                    elem.classList.add('bb-timer--expired');
                    // Auto-stop trigger jika habis
                    triggerAutoStop(t.id);
                } else {
                    const totalSec = Math.floor(diffMs / 1000);
                    const h = String(Math.floor(totalSec / 3600)).padStart(2, '0');
                    const m = String(Math.floor((totalSec % 3600) / 60)).padStart(2, '0');
                    const s = String(totalSec % 60).padStart(2, '0');
                    elem.textContent = `${h}:${m}:${s}`;
                    elem.classList.remove('bb-timer--expired');
                }
            }
        } else {
            // Reset state jika meja sudah tidak aktif
            if (autoStopped.has(t.id)) {
                autoStopped.delete(t.id);
            }
        }
    });
}

let autoStopped = new Set();
async function triggerAutoStop(tableId) {
    if (autoStopped.has(tableId)) return;
    autoStopped.add(tableId);

    try {
        await fetch('api/tables.php?action=stop', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: tableId })
        });
        loadDashboardData();
    } catch (e) {
        console.error("Auto stop gagal:", e);
    }
}

// Click Table Card -> Open History Modal
function onTableClick(tableId) {
    const table = state.tables.find(t => t.id === tableId);
    if (!table) return;

    state.selectedTableHistory = table;
    openHistoryModal(table);
}

// Table History Modal
function openHistoryModal(table) {
    const modal = document.getElementById('historyModal');
    if (!modal) return;

    document.getElementById('historyModalTitle').textContent = `Riwayat ${table.name}`;
    renderHistoryCards();
    modal.style.display = 'flex';
}

function animateCloseModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal || modal.style.display === 'none') return;

    modal.classList.add('bb-modal-backdrop--closing');
    setTimeout(() => {
        modal.style.display = 'none';
        modal.classList.remove('bb-modal-backdrop--closing');
    }, 220);
}

function closeHistoryModal() {
    animateCloseModal('historyModal');
}

function renderHistoryCards() {
    const container = document.getElementById('historyListContainer');
    const table = state.selectedTableHistory;
    if (!container || !table) return;

    let list = table.recent_transactions || [];
    const q = state.historySearch.toLowerCase().trim();
    if (q) {
        list = list.filter(tx => tx.customer_name && tx.customer_name.toLowerCase().includes(q));
    }

    const actionBtnContainer = document.getElementById('historyModalActionBtn');
    if (table.status === 'active') {
        actionBtnContainer.innerHTML = `
            <button class="bb-btn bb-btn--primary" onclick="closeHistoryModal(); openSessionEditModal('${table.id}');">
                <i class="bi bi-pencil-square"></i> Kelola Sesi & Checkout
            </button>
        `;
    } else {
        actionBtnContainer.innerHTML = `
            <button class="bb-btn bb-btn--success" onclick="closeHistoryModal(); openStartOrderModal('${table.id}');">
                <i class="bi bi-play-circle"></i> Mulai Sesi Baru
            </button>
        `;
    }

    if (list.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 30px; opacity: 0.5;">Belum ada riwayat transaksi.</div>`;
        return;
    }

    container.innerHTML = list.map(tx => {
        const isTxActive = tx.status === 'active';
        return `
            <div class="history-card ${isTxActive ? 'history-card--active' : ''}">
                <div class="history-card-header">
                    <div>
                        <div style="font-weight: 700; font-size: 1rem;">${tx.customer_name}</div>
                        <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 2px;">
                            <i class="bi bi-calendar3"></i> ${formatDate(tx.start_time)}
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="bb-status-badge ${isTxActive ? 'bb-status-badge--active' : 'bb-status-badge--ready'}">
                            ${isTxActive ? 'AKTIF' : 'SELESAI'}
                        </span>
                        <button class="bb-btn bb-btn--ghost bb-btn--sm" onclick='printThermalReceipt(${JSON.stringify(tx)}, "${table.name}")' title="Cetak Struk">
                            <i class="bi bi-printer"></i>
                        </button>
                    </div>
                </div>

                <div style="font-size: 0.85rem; margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span><i class="bi bi-play-circle me-1" style="color: #10b981;"></i>Paket ${tx.package_name || 'Biliar'}</span>
                        <span style="font-weight: 600;">${formatRupiah(tx.billiard_cost)}</span>
                    </div>
                    ${tx.items && tx.items.length > 0 ? `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span><i class="bi bi-cup-hot me-1" style="color: #f59e0b;"></i>Pesanan F&B (${tx.items.length} item)</span>
                            <span style="font-weight: 600;">${formatRupiah(tx.fnb_cost)}</span>
                        </div>
                    ` : ''}
                </div>

                <div style="display: flex; justify-content: space-between; font-weight: 700; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 8px; color: #10b981;">
                    <span>Total</span>
                    <span>${formatRupiah(tx.total_cost)}</span>
                </div>
            </div>
        `;
    }).join('');
}

// Start Session / Order Modal
function openStartOrderModal(tableId) {
    const table = state.tables.find(t => t.id === tableId);
    if (!table) return;

    state.selectedTable = table;
    state.draftItems = [];
    document.getElementById('orderModalTitle').textContent = `Mulai Sesi - ${table.name}`;
    document.getElementById('orderCustomerName').value = '';
    document.getElementById('orderDuration').value = '1';

    // Populate packages
    const pkgSelect = document.getElementById('orderPackageSelect');
    pkgSelect.innerHTML = state.packages.map(p => `
        <option value="${p.id}" data-price="${p.price}">${p.name} — ${formatRupiah(p.price)}/jam</option>
    `).join('');

    renderFnbCatalog('orderFnbCatalog', addDraftItem);
    renderOrderSummary();
    document.getElementById('orderModal').style.display = 'flex';
}

function closeStartOrderModal() {
    animateCloseModal('orderModal');
}

// Render FnB Catalog
function renderFnbCatalog(containerId, onSelect) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let items = state.fnbItems;
    if (state.fnbCategory !== 'all') {
        items = items.filter(i => i.category === state.fnbCategory);
    }
    const q = state.fnbSearch.toLowerCase().trim();
    if (q) {
        items = items.filter(i => i.name.toLowerCase().includes(q));
    }

    container.innerHTML = items.map(item => `
        <div class="fnb-mini-card" onclick='selectFnbItem(${JSON.stringify(item)}, "${containerId}")'>
            <div>
                ${item.image_url ? `<img src="${item.image_url}" class="fnb-mini-img" alt="${item.name}">` : `<i class="bi bi-cup-hot" style="font-size: 2rem; opacity: 0.3;"></i>`}
                <div class="fnb-mini-title">${item.name}</div>
            </div>
            <div class="fnb-mini-price">${formatRupiah(item.price)}</div>
        </div>
    `).join('');
}

window.selectFnbItem = function(item, containerId) {
    if (containerId === 'orderFnbCatalog') {
        addDraftItem(item);
    } else {
        addEditItem(item);
    }
};

function addDraftItem(item) {
    const existing = state.draftItems.find(i => i.fnb_item_id === item.id);
    if (existing) {
        existing.quantity++;
    } else {
        state.draftItems.push({
            fnb_item_id: item.id,
            name: item.name,
            price: Number(item.price),
            quantity: 1
        });
    }
    renderOrderSummary();
}

function updateDraftQty(index, delta) {
    state.draftItems[index].quantity += delta;
    if (state.draftItems[index].quantity <= 0) {
        state.draftItems.splice(index, 1);
    }
    renderOrderSummary();
}

function renderOrderSummary() {
    const container = document.getElementById('orderSelectedItems');
    const pkgSelect = document.getElementById('orderPackageSelect');
    const durationInput = document.getElementById('orderDuration');
    if (!container || !pkgSelect || !durationInput) return;

    const selectedPkgOption = pkgSelect.options[pkgSelect.selectedIndex];
    const pkgPrice = selectedPkgOption ? Number(selectedPkgOption.getAttribute('data-price')) : 0;
    const duration = parseFloat(durationInput.value) || 1;
    const billiardCost = pkgPrice * duration;

    let fnbCost = 0;
    const itemsHtml = state.draftItems.map((item, idx) => {
        const subtotal = item.price * item.quantity;
        fnbCost += subtotal;
        return `
            <div class="order-row">
                <div>
                    <div>${item.name}</div>
                    <div style="font-size: 0.75rem; color: #f59e0b;">${formatRupiah(item.price)}</div>
                </div>
                <div class="qty-counter">
                    <button type="button" class="qty-btn" onclick="updateDraftQty(${idx}, -1)">-</button>
                    <span>${item.quantity}</span>
                    <button type="button" class="qty-btn" onclick="updateDraftQty(${idx}, 1)">+</button>
                    <span style="font-weight: 600; min-width: 70px; text-align: right;">${formatRupiah(subtotal)}</span>
                </div>
            </div>
        `;
    }).join('');

    const total = billiardCost + fnbCost;
    container.innerHTML = `
        <div style="margin-bottom: 12px;">${itemsHtml || '<div style="opacity: 0.5; font-size: 0.8rem;">Belum ada FnB dipilih.</div>'}</div>
        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <div class="order-row">
                <span>Sewa Meja (${duration} Jam)</span>
                <span style="font-weight: 600;">${formatRupiah(billiardCost)}</span>
            </div>
            <div class="order-row">
                <span>Pesanan F&B</span>
                <span style="font-weight: 600;">${formatRupiah(fnbCost)}</span>
            </div>
            <div class="order-row" style="font-size: 1.1rem; font-weight: 800; color: #10b981; margin-top: 6px;">
                <span>Total Biaya</span>
                <span>${formatRupiah(total)}</span>
            </div>
        </div>
    `;
}

// Submit Start Order
async function submitStartOrder(e) {
    e.preventDefault();
    const customerName = document.getElementById('orderCustomerName').value.trim();
    const packageId = document.getElementById('orderPackageSelect').value;
    const durationHours = parseFloat(document.getElementById('orderDuration').value) || 1;

    if (!customerName) {
        alert("Nama pelanggan wajib diisi!");
        return;
    }

    const payload = {
        table_id: state.selectedTable.id,
        customer_name: customerName,
        package_id: packageId,
        duration_hours: durationHours,
        items: state.draftItems
    };

    try {
        const res = await fetch('api/tables.php?action=start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            closeStartOrderModal();
            loadDashboardData();
        } else {
            alert(json.message);
        }
    } catch (err) {
        console.error(err);
        alert("Terjadi kesalahan saat memulai sesi.");
    }
}

// Edit Session & Checkout Modal
function openSessionEditModal(tableId) {
    const table = state.tables.find(t => t.id === tableId);
    if (!table || !table.transactions || table.transactions.length === 0) return;

    state.selectedTable = table;
    const tx = table.transactions[0];
    state.selectedActiveTx = tx;

    document.getElementById('sessionModalTitle').textContent = `Kelola ${table.name} - ${tx.customer_name}`;

    // Populate packages
    const pkgSelect = document.getElementById('editPackageSelect');
    pkgSelect.innerHTML = state.packages.map(p => `
        <option value="${p.id}" data-price="${p.price}" ${p.id == tx.package_id ? 'selected' : ''}>${p.name} — ${formatRupiah(p.price)}/jam</option>
    `).join('');

    // Hitung durasi saat ini
    const start = new Date(tx.start_time.replace(' ', 'T'));
    const end = new Date(tx.expected_end_time.replace(' ', 'T'));
    const diffHours = (end - start) / (1000 * 60 * 60);
    document.getElementById('editDuration').value = Math.max(0.5, Math.round(diffHours * 2) / 2);

    // Map items
    state.draftItems = (tx.items || []).map(i => ({
        fnb_item_id: i.fnb_item_id,
        name: i.fnb_name,
        price: Number(i.price),
        quantity: Number(i.quantity)
    }));

    renderFnbCatalog('editFnbCatalog', addEditItem);
    renderEditSummary();
    renderCheckoutSummary();
    switchSessionTab('edit');
    document.getElementById('sessionModal').style.display = 'flex';
}

function closeSessionModal() {
    animateCloseModal('sessionModal');
}

function switchSessionTab(tab) {
    state.sessionTab = tab;
    document.getElementById('tabBtnEdit').className = `modal-tab-btn ${tab === 'edit' ? 'modal-tab-btn--active' : ''}`;
    document.getElementById('tabBtnCheckout').className = `modal-tab-btn ${tab === 'checkout' ? 'modal-tab-btn--active' : ''}`;
    document.getElementById('tabContentEdit').style.display = tab === 'edit' ? 'block' : 'none';
    document.getElementById('tabContentCheckout').style.display = tab === 'checkout' ? 'block' : 'none';
}

function addEditItem(item) {
    const existing = state.draftItems.find(i => i.fnb_item_id === item.id);
    if (existing) {
        existing.quantity++;
    } else {
        state.draftItems.push({
            fnb_item_id: item.id,
            name: item.name,
            price: Number(item.price),
            quantity: 1
        });
    }
    renderEditSummary();
    renderCheckoutSummary();
}

function updateEditQty(index, delta) {
    state.draftItems[index].quantity += delta;
    if (state.draftItems[index].quantity <= 0) {
        state.draftItems.splice(index, 1);
    }
    renderEditSummary();
    renderCheckoutSummary();
}

function renderEditSummary() {
    const container = document.getElementById('editSelectedItems');
    const pkgSelect = document.getElementById('editPackageSelect');
    const durationInput = document.getElementById('editDuration');
    if (!container || !pkgSelect || !durationInput) return;

    const selectedPkgOption = pkgSelect.options[pkgSelect.selectedIndex];
    const pkgPrice = selectedPkgOption ? Number(selectedPkgOption.getAttribute('data-price')) : 0;
    const duration = parseFloat(durationInput.value) || 1;
    const billiardCost = pkgPrice * duration;

    let fnbCost = 0;
    const itemsHtml = state.draftItems.map((item, idx) => {
        const subtotal = item.price * item.quantity;
        fnbCost += subtotal;
        return `
            <div class="order-row">
                <div>
                    <div>${item.name}</div>
                    <div style="font-size: 0.75rem; color: #f59e0b;">${formatRupiah(item.price)}</div>
                </div>
                <div class="qty-counter">
                    <button type="button" class="qty-btn" onclick="updateEditQty(${idx}, -1)">-</button>
                    <span>${item.quantity}</span>
                    <button type="button" class="qty-btn" onclick="updateEditQty(${idx}, 1)">+</button>
                    <span style="font-weight: 600; min-width: 70px; text-align: right;">${formatRupiah(subtotal)}</span>
                </div>
            </div>
        `;
    }).join('');

    const total = billiardCost + fnbCost;
    container.innerHTML = `
        <div style="margin-bottom: 12px;">${itemsHtml || '<div style="opacity: 0.5; font-size: 0.8rem;">Belum ada FnB.</div>'}</div>
        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
            <div class="order-row">
                <span>Sewa Meja (${duration} Jam)</span>
                <span style="font-weight: 600;">${formatRupiah(billiardCost)}</span>
            </div>
            <div class="order-row">
                <span>Pesanan F&B</span>
                <span style="font-weight: 600;">${formatRupiah(fnbCost)}</span>
            </div>
            <div class="order-row" style="font-size: 1.1rem; font-weight: 800; color: #10b981; margin-top: 6px;">
                <span>Total Biaya</span>
                <span>${formatRupiah(total)}</span>
            </div>
        </div>
    `;
}

function renderCheckoutSummary() {
    const container = document.getElementById('checkoutSummaryBox');
    const tx = state.selectedActiveTx;
    if (!container || !tx) return;

    const pkgSelect = document.getElementById('editPackageSelect');
    const durationInput = document.getElementById('editDuration');
    const selectedPkgOption = pkgSelect ? pkgSelect.options[pkgSelect.selectedIndex] : null;
    const pkgPrice = selectedPkgOption ? Number(selectedPkgOption.getAttribute('data-price')) : Number(tx.package_price);
    const duration = durationInput ? parseFloat(durationInput.value) || 1 : 1;
    const billiardCost = pkgPrice * duration;

    let fnbCost = 0;
    state.draftItems.forEach(i => {
        fnbCost += (i.price * i.quantity);
    });
    const totalCost = billiardCost + fnbCost;

    container.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 0.85rem; color: #94a3b8;">Total Tagihan</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #10b981;">${formatRupiah(totalCost)}</div>
        </div>

        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>Paket Biliar (${selectedPkgOption ? selectedPkgOption.text.split('—')[0] : 'Biliar'})</span>
                <span style="font-weight: 600;">${formatRupiah(billiardCost)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>Pesanan F&B (${state.draftItems.length} item)</span>
                <span style="font-weight: 600;">${formatRupiah(fnbCost)}</span>
            </div>
            <div style="border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 8px; margin-top: 8px; display: flex; justify-content: space-between; font-weight: 700;">
                <span>Pelanggan</span>
                <span>${tx.customer_name}</span>
            </div>
        </div>
    `;
}

// Submit Edit Session
async function submitEditSession() {
    const tableId = state.selectedTable.id;
    const txId = state.selectedActiveTx.id;
    const packageId = document.getElementById('editPackageSelect').value;
    const durationHours = parseFloat(document.getElementById('editDuration').value) || 1;

    try {
        const res = await fetch('api/tables.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_id: tableId,
                transaction_id: txId,
                package_id: packageId,
                duration_hours: durationHours,
                items: state.draftItems
            })
        });
        const json = await res.json();
        if (json.success) {
            closeSessionModal();
            loadDashboardData();
        } else {
            alert(json.message);
        }
    } catch (e) {
        console.error(e);
        alert("Gagal memperbarui sesi.");
    }
}

// Execute Checkout
async function executeCheckout() {
    const table = state.selectedTable;
    if (!confirm(`Selesaikan pembayaran dan matikan lampu ${table.name}?`)) return;

    try {
        const res = await fetch('api/tables.php?action=stop', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: table.id })
        });
        const json = await res.json();
        if (json.success) {
            closeSessionModal();
            // Cetak struk otomatis
            const txData = {
                ...state.selectedActiveTx,
                items: state.draftItems,
                end_time: new Date().toISOString()
            };
            printThermalReceipt(txData, table.name);
            loadDashboardData();
        } else {
            alert(json.message);
        }
    } catch (e) {
        console.error(e);
        alert("Gagal melakukan checkout.");
    }
}

// Thermal Receipt 80mm Print Utility
function printThermalReceipt(transaction, tableName = '') {
    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    let itemsHtml = '';
    if (transaction.items && transaction.items.length > 0) {
        transaction.items.forEach(item => {
            const name = item.fnb_name || item.name || 'Item';
            itemsHtml += `
                <tr>
                    <td style="padding: 2px 0;">${name} x${item.quantity}</td>
                    <td style="padding: 2px 0; text-align: right;">${formatRupiah(item.subtotal || (item.price * item.quantity))}</td>
                </tr>
            `;
        });
    }

    const billiardCost = Number(transaction.billiard_cost) || 0;
    const fnbCost = Number(transaction.fnb_cost) || 0;
    const totalCost = Number(transaction.total_cost) || (billiardCost + fnbCost);

    const html = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk #${transaction.id || ''}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 8px; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .double-divider { border-top: 2px solid #000; margin: 6px 0; }
        .store-name { font-size: 18px; font-weight: bold; letter-spacing: 2px; }
        .total-row { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .footer { margin-top: 10px; font-size: 11px; }
        @media print { body { width: 80mm; } @page { size: 80mm auto; margin: 0; } }
    </style>
</head>
<body>
    <div class="center">
        <div class="store-name">POOLSTREAM</div>
        <div style="font-size: 10px; margin-top: 2px;">Billiard & Lounge</div>
    </div>
    <div class="double-divider"></div>

    <table>
        <tr><td>No</td><td class="right">#${(transaction.id || '-').substring(0, 8)}</td></tr>
        <tr><td>Tanggal</td><td class="right">${dateStr} ${timeStr}</td></tr>
        <tr><td>Customer</td><td class="right">${transaction.customer_name || '-'}</td></tr>
        ${tableName ? `<tr><td>Meja</td><td class="right">${tableName}</td></tr>` : ''}
    </table>

    <div class="divider"></div>
    <div class="bold" style="margin-bottom: 4px;">BILIAR</div>
    <table>
        <tr>
            <td>${transaction.package_name || 'Paket Biliar'} x ${calculateDurationText(transaction.start_time, transaction.end_time || transaction.expected_end_time)}</td>
            <td class="right">${formatRupiah(billiardCost)}</td>
        </tr>
    </table>

    ${itemsHtml ? `
        <div class="divider"></div>
        <div class="bold" style="margin-bottom: 4px;">F&B</div>
        <table>${itemsHtml}</table>
    ` : ''}

    <div class="divider"></div>
    <table>
        <tr><td>Subtotal Biliar</td><td class="right">${formatRupiah(billiardCost)}</td></tr>
        <tr><td>Subtotal F&B</td><td class="right">${formatRupiah(fnbCost)}</td></tr>
    </table>
    <div class="double-divider"></div>
    <table>
        <tr class="total-row">
            <td>TOTAL</td>
            <td class="right">${formatRupiah(totalCost)}</td>
        </tr>
    </table>
    <div class="double-divider"></div>

    <div class="center footer">
        <div>Terima kasih!</div>
        <div>Selamat bermain &#127921;</div>
    </div>

    <script>
        window.onload = function() { window.print(); };
    <\/script>
</body>
</html>`;

    const printWin = window.open('', '_blank', 'width=420,height=600');
    if (printWin) {
        printWin.document.write(html);
        printWin.document.close();
    }
}

// Initial setup
document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();

    // Polling refresh setiap 5 detik
    setInterval(loadDashboardData, 5000);

    // Countdown setiap 1 detik
    setInterval(updateCountdowns, 1000);

    // Filter Buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.remove('bb-btn--success');
                b.classList.add('bb-btn--ghost');
            });
            btn.classList.remove('bb-btn--ghost');
            btn.classList.add('bb-btn--success');
            state.filter = btn.getAttribute('data-filter');
            renderTables();
        });
    });

    // Theme toggle
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const savedTheme = localStorage.getItem('poolstream_theme') || 'dark';

    function applyTheme(t) {
        document.documentElement.setAttribute('data-bs-theme', t);
        themeIcon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        localStorage.setItem('poolstream_theme', t);
    }
    applyTheme(savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-bs-theme');
            applyTheme(cur === 'dark' ? 'light' : 'dark');
        });
    }

    // Realtime Header Clock
    function updateHeaderClock() {
        const now = new Date();
        const dStr = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        const tStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const elem = document.getElementById('headerClock');
        if (elem) elem.textContent = `${dStr} | ${tStr}`;
    }
    setInterval(updateHeaderClock, 1000);
    updateHeaderClock();

    // Close on Backdrop Click
    document.querySelectorAll('.bb-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                animateCloseModal(backdrop.id);
            }
        });
    });

    // Close on Escape Key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            ['historyModal', 'orderModal', 'sessionModal'].forEach(id => {
                const m = document.getElementById(id);
                if (m && m.style.display === 'flex') {
                    animateCloseModal(id);
                }
            });
        }
    });
});
