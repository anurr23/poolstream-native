<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@/shims/inertia.js';
import { ref, computed } from 'vue';
import flatPickr from 'vue-flatpickr-component';

const props = defineProps({
    sales: Array,
    summary: Object,
    startDate: String,
    endDate: String,
});

const fpConfig = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' };
const toYMD = (d) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const startDate = ref(props.startDate || '');
const endDate = ref(props.endDate || '');
const activePreset = ref(null);

const presets = [
    { label: 'Hari Ini', days: 0 },
    { label: '7 Hari', days: 7 },
    { label: '30 Hari', days: 30 },
    { label: 'Bulan Ini', days: 'month' },
];

const applyFilter = () => {
    activePreset.value = null;
    router.get(route('reports.fnb-sales'), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, { preserveState: true });
};

const setPreset = (preset) => {
    activePreset.value = preset.label;
    const end = new Date();
    let start;

    if (preset.days === 0) {
        start = new Date();
    } else if (preset.days === 'month') {
        start = new Date(end.getFullYear(), end.getMonth(), 1);
    } else {
        start = new Date();
        start.setDate(start.getDate() - preset.days);
    }

    startDate.value = toYMD(start);
    endDate.value = toYMD(end);
    applyFilter();
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID').format(val || 0);
};

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const displayRange = computed(() => {
    if (!startDate.value || !endDate.value) return '';
    return `${formatDate(startDate.value)} — ${formatDate(endDate.value)}`;
});
</script>

<template>
    <Head title="Laporan Penjualan F&B" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-cup-hot-fill me-2" style="color: #f59e0b;"></i>Laporan Penjualan F&B</h1>
        </template>

        <!-- Date Filter -->
        <div class="bb-card mb-4">
            <div class="bb-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-calendar-range me-2"></i>Filter Periode</h6>
                <span class="small text-secondary">{{ displayRange }}</span>
            </div>
            <div class="bb-card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button
                        v-for="preset in presets"
                        :key="preset.label"
                        @click="setPreset(preset)"
                        class="bb-btn"
                        :class="activePreset === preset.label ? 'bb-btn--primary' : 'bb-btn--ghost'"
                        style="font-size: 0.8rem; text-transform: none; border-radius: 100px; padding: 6px 18px;"
                    >
                        {{ preset.label }}
                    </button>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="bb-label">
                            <i class="bi bi-calendar3 me-1"></i> Dari Tanggal
                        </label>
                        <flat-pickr
                            v-model="startDate"
                            :config="fpConfig"
                            class="form-control"
                            placeholder="Pilih tanggal"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="bb-label">
                            <i class="bi bi-calendar3 me-1"></i> Sampai Tanggal
                        </label>
                        <flat-pickr
                            v-model="endDate"
                            :config="fpConfig"
                            class="form-control"
                            placeholder="Pilih tanggal"
                        />
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button @click="applyFilter" class="bb-btn bb-btn--primary flex-grow-1 py-3">
                            <i class="bi bi-funnel me-2"></i>Terapkan
                        </button>
                        <button
                            @click="router.get(route('reports.fnb-sales'), { start_date: '', end_date: '' }, { preserveState: true })"
                            class="bb-btn bb-btn--ghost px-3"
                            title="Reset"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Total Item Terjual</div>
                        <div class="fs-4 fw-bold" style="color: #6366f1;">{{ formatCurrency(summary?.total_qty) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Total Menu Terjual</div>
                        <div class="fs-4 fw-bold" style="color: #10b981;">{{ sales.length }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Total Pendapatan</div>
                        <div class="fs-4 fw-bold" style="color: #f59e0b;">Rp {{ formatCurrency(summary?.total_revenue) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="bb-card">
            <div class="bb-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Detail Penjualan F&B</h6>
                <span class="bb-badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">{{ sales.length }} Menu</span>
            </div>
            <div class="bb-card-body p-0">
                <div class="table-responsive">
                    <table class="bb-table">
                        <thead>
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Menu</th>
                                <th>Kategori</th>
                                <th class="text-end">Qty Terjual</th>
                                <th class="text-end pe-4">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in sales" :key="item.fnb_item_id">
                                <td class="ps-4">{{ index + 1 }}</td>
                                <td class="fw-bold">{{ item.fnb_item?.name || '-' }}</td>
                                <td>
                                    <span class="bb-badge" style="background: rgba(16,185,129,0.1); color: #10b981;">
                                        {{ item.fnb_item?.category || '-' }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace">{{ formatCurrency(item.total_qty) }}</td>
                                <td class="text-end pe-4 font-monospace">Rp {{ formatCurrency(item.total_revenue) }}</td>
                            </tr>
                            <tr v-if="sales.length === 0">
                                <td colspan="5" class="text-center py-5" style="opacity: 0.4;">
                                    <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                    Tidak ada data penjualan untuk periode ini
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="sales.length > 0">
                            <tr style="font-weight: 600; background: rgba(255,255,255,0.03);">
                                <td class="ps-4" colspan="3">Total</td>
                                <td class="text-end font-monospace">{{ formatCurrency(summary?.total_qty) }}</td>
                                <td class="text-end pe-4 font-monospace">Rp {{ formatCurrency(summary?.total_revenue) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
