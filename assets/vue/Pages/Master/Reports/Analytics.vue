<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@/shims/inertia.js';
import { ref, computed } from 'vue';
import Flatpickr from 'vue-flatpickr-component';
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, PointElement, Filler, CategoryScale, LinearScale } from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, Filler, CategoryScale, LinearScale)

const props = defineProps({
    hourlyStats: Array,
    tableUtilization: Array,
    packageStats: Array,
    dailyStats: Array,
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

// By default if there's no props passed yet (initial load), it shows the current month in the backend. 
// We should match the active preset visually to "Bulan Ini" if it's the initial load month range.
const activePreset = ref(null);

const checkInitialPreset = () => {
    if (!startDate.value || !endDate.value) return;
    const s = new Date(startDate.value + 'T00:00:00');
    const e = new Date(endDate.value + 'T00:00:00');
    const now = new Date();
    
    // Check if it's "Bulan Ini"
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    
    if (s.getTime() === firstDay.getTime() && e.getFullYear() === now.getFullYear() && e.getMonth() === now.getMonth() && e.getDate() === now.getDate()) {
        activePreset.value = 'Bulan Ini';
    }
};
checkInitialPreset();

const presets = [
    { label: 'Hari Ini', days: 0 },
    { label: '7 Hari', days: 7 },
    { label: '30 Hari', days: 30 },
    { label: 'Bulan Ini', days: 'month' },
];

const applyFilter = () => {
    activePreset.value = null;
    router.get(route('reports.analytics'), {
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
        activePreset.value = 'Bulan Ini';
    } else {
        start = new Date();
        start.setDate(start.getDate() - preset.days);
        activePreset.value = preset.label;
    }

    startDate.value = toYMD(start);
    endDate.value = toYMD(end);
    applyFilter();
};

const formatCurrency = (value) => {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(value || 0);
};

const formatDuration = (minutes) => {
    if (!minutes || minutes < 1) return '0m';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    if (hours > 0) return `${hours}j ${mins}m`;
    return `${mins}m`;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatDateShort = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
};

const displayRange = computed(() => {
    if (!startDate.value || !endDate.value) return '';
    return `${formatDate(startDate.value)} — ${formatDate(endDate.value)}`;
});

const peakHour = computed(() => {
    if (!props.hourlyStats?.length) return null;
    return props.hourlyStats.reduce((max, curr) => curr.transactions > max.transactions ? curr : max);
});

const topTable = computed(() => {
    if (!props.tableUtilization?.length) return null;
    return props.tableUtilization.find(t => t.transactions > 0) || null;
});

const topPackage = computed(() => {
    if (!props.packageStats?.length) return null;
    return props.packageStats.find(p => p.transactions > 0) || null;
});

const totalHourlyTx = computed(() => {
    return props.hourlyStats?.reduce((sum, s) => sum + s.transactions, 0) || 1;
});

const maxTableTx = computed(() => {
    return Math.max(...(props.tableUtilization?.map(t => t.transactions) || [1]), 1);
});

// Flip array back for chart so it reads left-to-right (oldest to newest)
const reversedDailyStats = computed(() => {
    return [...props.dailyStats].reverse();
});

const chartData = computed(() => {
    return {
        labels: reversedDailyStats.value.map(d => formatDateShort(d.date)),
        datasets: [
            {
                label: 'Pendapatan',
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderColor: '#8b5cf6',
                borderWidth: 2,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#8b5cf6',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4,
                data: reversedDailyStats.value.map(d => d.revenue),
            }
        ]
    }
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) {
                    return 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k';
                },
                color: 'rgba(255, 255, 255, 0.6)'
            },
            grid: {
                color: 'rgba(255, 255, 255, 0.05)',
                drawBorder: false,
            },
            border: { display: false }
        },
        x: {
            ticks: {
                color: 'rgba(255, 255, 255, 0.6)'
            },
            grid: {
                display: false,
                drawBorder: false,
            },
            border: { display: false }
        }
    },
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleColor: '#fff',
            bodyColor: '#e2e8f0',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            padding: 12,
            boxPadding: 6,
            usePointStyle: true,
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(context.parsed.y);
                    }
                    return label;
                }
            }
        }
    }
};

</script>

<template>
    <Head title="Analitik" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-graph-up-arrow me-2" style="color: #8b5cf6;"></i>Analitik</h1>
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
                        <Flatpickr
                            v-model="startDate"
                            :config="fpConfig"
                            class="form-control"
                            placeholder="Pilih Tanggal"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="bb-label">
                            <i class="bi bi-calendar3 me-1"></i> Sampai Tanggal
                        </label>
                        <Flatpickr
                            v-model="endDate"
                            :config="fpConfig"
                            class="form-control"
                            placeholder="Pilih Tanggal"
                        />
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button @click="applyFilter" class="bb-btn bb-btn--primary flex-grow-1 py-3">
                            <i class="bi bi-funnel me-2"></i>Terapkan
                        </button>
                        <button
                            @click="router.get(route('reports.analytics'), { start_date: '', end_date: '' }, { preserveState: true })"
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
            <div class="col-md-3 col-6">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Total Transaksi</div>
                        <div class="fs-4 fw-bold" style="color: #6366f1;">{{ formatNumber(summary.total_transactions) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Total Pendapatan</div>
                        <div class="fs-4 fw-bold" style="color: #10b981;">{{ formatCurrency(summary.total_revenue) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Rata-rata / Transaksi</div>
                        <div class="fs-4 fw-bold" style="color: #f59e0b;">{{ formatCurrency(summary.avg_transaction_value) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bb-card">
                    <div class="bb-card-body text-center">
                        <div class="text-secondary small mb-1">Rata-rata Durasi</div>
                        <div class="fs-4 fw-bold" style="color: #8b5cf6;">{{ formatDuration(summary.avg_duration) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body d-flex align-items-center gap-3">
                        <div class="analytics-icon" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                            <i class="bi bi-dribbble"></i>
                        </div>
                        <div>
                            <div class="text-secondary small">Pendapatan Billiard</div>
                            <div class="fs-5 fw-bold">{{ formatCurrency(summary.total_billiard) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body d-flex align-items-center gap-3">
                        <div class="analytics-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                            <i class="bi bi-cup-hot-fill"></i>
                        </div>
                        <div>
                            <div class="text-secondary small">Pendapatan F&B</div>
                            <div class="fs-5 fw-bold">{{ formatCurrency(summary.total_fnb) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bb-card">
                    <div class="bb-card-body d-flex align-items-center gap-3">
                        <div class="analytics-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                            <i class="bi bi-columns-gap"></i>
                        </div>
                        <div>
                            <div class="text-secondary small">Meja Terpakai</div>
                            <div class="fs-5 fw-bold">{{ summary.unique_tables_used }} / {{ tableUtilization.length }} Meja</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Revenue Chart (Chart.js) -->
        <div class="bb-card mb-4" v-if="dailyStats && dailyStats.length > 0">
            <div class="bb-card-header">
                <h6 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i>Tren Pendapatan Harian</h6>
            </div>
            <div class="bb-card-body">
                <div style="height: 300px; width: 100%;">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>
        </div>

        <!-- Insight Cards -->
        <div class="row g-3 mb-4" v-if="peakHour || topTable || topPackage">
            <div class="col-md-4" v-if="peakHour">
                <div class="bb-card h-100">
                    <div class="bb-card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="analytics-icon" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                            <div>
                                <div class="text-secondary small">Jam Tersibuk</div>
                                <div class="fs-5 fw-bold">{{ peakHour.hour }}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small" style="opacity: 0.7;">
                            <span>{{ peakHour.transactions }} transaksi</span>
                            <span class="fw-bold">{{ formatCurrency(peakHour.revenue) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" v-if="topTable">
                <div class="bb-card h-100">
                    <div class="bb-card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="analytics-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                            <div>
                                <div class="text-secondary small">Meja Terlaris</div>
                                <div class="fs-5 fw-bold">{{ topTable.table_name }}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small" style="opacity: 0.7;">
                            <span>{{ topTable.total_hours }} jam</span>
                            <span class="fw-bold">{{ formatCurrency(topTable.revenue) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" v-if="topPackage">
                <div class="bb-card h-100">
                    <div class="bb-card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="analytics-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                                <i class="bi bi-tag-fill"></i>
                            </div>
                            <div>
                                <div class="text-secondary small">Paket Terpopuler</div>
                                <div class="fs-5 fw-bold">{{ topPackage.name }}</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small" style="opacity: 0.7;">
                            <span>{{ topPackage.transactions }}x transaksi</span>
                            <span class="fw-bold">{{ formatCurrency(topPackage.revenue) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Activity -->
        <div class="bb-card mb-4">
            <div class="bb-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Aktivitas Per Jam</h6>
                <span class="bb-badge" style="background: rgba(99,102,241,0.1); color: #6366f1;" v-if="hourlyStats.length">
                    {{ hourlyStats.length }} Jam Aktif
                </span>
            </div>
            <div class="bb-card-body p-0">
                <div class="table-responsive" v-if="hourlyStats.length">
                    <table class="bb-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Jam</th>
                                <th class="text-center">Transaksi</th>
                                <th class="text-end">Pendapatan</th>
                                <th class="text-center">Durasi Rata-rata</th>
                                <th class="text-end pe-4" style="min-width: 140px;">Proporsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in hourlyStats" :key="h.hour">
                                <td class="ps-4 fw-bold">{{ h.hour }}</td>
                                <td class="text-center font-monospace">{{ h.transactions }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(h.revenue) }}</td>
                                <td class="text-center">{{ formatDuration(h.avg_duration) }}</td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <span class="small font-monospace" style="opacity: 0.6; min-width: 36px; text-align: right;">{{ ((h.transactions / totalHourlyTx) * 100).toFixed(0) }}%</span>
                                        <div class="analytics-bar-track">
                                            <div class="analytics-bar" :style="{ width: ((h.transactions / totalHourlyTx) * 100) + '%' }"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-5" style="opacity: 0.4;">
                    <i class="bi bi-clock-history d-block mb-2" style="font-size: 2rem;"></i>
                    Tidak ada data aktivitas untuk periode ini
                </div>
            </div>
        </div>

        <!-- Table Utilization & Package Performance -->
        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="bb-card h-100">
                    <div class="bb-card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-columns-gap me-2"></i>Utilisasi Meja</h6>
                        <span class="bb-badge" style="background: rgba(16,185,129,0.1); color: #10b981;">
                            {{ summary.unique_tables_used }} Aktif
                        </span>
                    </div>
                    <div class="bb-card-body p-0">
                        <div class="table-responsive" v-if="tableUtilization.some(t => t.transactions > 0)">
                            <table class="bb-table">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Meja</th>
                                        <th class="text-center">Transaksi</th>
                                        <th class="text-center">Jam</th>
                                        <th class="text-end">Pendapatan</th>
                                        <th class="text-end pe-4" style="min-width: 120px;">Utilisasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in tableUtilization.filter(t => t.transactions > 0)" :key="t.table_number">
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ t.table_name }}</div>
                                            <div class="small" style="opacity: 0.5;">No. {{ t.table_number }}</div>
                                        </td>
                                        <td class="text-center font-monospace">{{ t.transactions }}</td>
                                        <td class="text-center font-monospace">{{ t.total_hours }}</td>
                                        <td class="text-end font-monospace">{{ formatCurrency(t.revenue) }}</td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <span class="small font-monospace" style="opacity: 0.6; min-width: 36px; text-align: right;">{{ ((t.transactions / maxTableTx) * 100).toFixed(0) }}%</span>
                                                <div class="analytics-bar-track">
                                                    <div class="analytics-bar" style="background: #10b981;" :style="{ width: ((t.transactions / maxTableTx) * 100) + '%' }"></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-5" style="opacity: 0.4;">
                            <i class="bi bi-columns-gap d-block mb-2" style="font-size: 2rem;"></i>
                            Tidak ada data utilisasi meja
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="bb-card h-100">
                    <div class="bb-card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-tag-fill me-2"></i>Performa Paket</h6>
                        <span class="bb-badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                            {{ packageStats.filter(p => p.transactions > 0).length }} Paket
                        </span>
                    </div>
                    <div class="bb-card-body p-0">
                        <div class="table-responsive" v-if="packageStats.some(p => p.transactions > 0)">
                            <table class="bb-table">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Paket</th>
                                        <th class="text-center">Transaksi</th>
                                        <th class="text-center">Jam</th>
                                        <th class="text-end pe-4">Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in packageStats.filter(p => p.transactions > 0)" :key="p.name">
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ p.name }}</div>
                                            <div class="small" style="opacity: 0.5;">{{ formatCurrency(p.price_per_hour) }}/jam</div>
                                        </td>
                                        <td class="text-center font-monospace">{{ p.transactions }}</td>
                                        <td class="text-center font-monospace">{{ p.total_hours }}</td>
                                        <td class="text-end pe-4 font-monospace fw-bold">{{ formatCurrency(p.revenue) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-5" style="opacity: 0.4;">
                            <i class="bi bi-tag-fill d-block mb-2" style="font-size: 2rem;"></i>
                            Tidak ada data paket
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Trend -->
        <div class="bb-card">
            <div class="bb-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-calendar-event me-2"></i>Tren Harian</h6>
                <span class="bb-badge" style="background: rgba(139,92,246,0.1); color: #8b5cf6;" v-if="dailyStats.length">
                    {{ dailyStats.length }} Hari
                </span>
            </div>
            <div class="bb-card-body p-0">
                <div class="table-responsive" v-if="dailyStats.length">
                    <table class="bb-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Tanggal</th>
                                <th class="text-center">Transaksi</th>
                                <th class="text-end">Billiard</th>
                                <th class="text-end">F&B</th>
                                <th class="text-end">Total</th>
                                <th class="text-center pe-4">Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in dailyStats" :key="d.date">
                                <td class="ps-4 fw-bold">{{ new Date(d.date).toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short' }) }}</td>
                                <td class="text-center font-monospace">{{ d.transactions }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(d.billiard_revenue) }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(d.fnb_revenue) }}</td>
                                <td class="text-end font-monospace fw-bold">{{ formatCurrency(d.revenue) }}</td>
                                <td class="text-center pe-4">{{ formatDuration(d.avg_duration) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="dailyStats.length > 1">
                            <tr style="font-weight: 600; background: rgba(255,255,255,0.03);">
                                <td class="ps-4">Total</td>
                                <td class="text-center font-monospace">{{ formatNumber(summary.total_transactions) }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(summary.total_billiard) }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(summary.total_fnb) }}</td>
                                <td class="text-end font-monospace">{{ formatCurrency(summary.total_revenue) }}</td>
                                <td class="text-center pe-4">{{ formatDuration(summary.avg_duration) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div v-else class="text-center py-5" style="opacity: 0.4;">
                    <i class="bi bi-calendar-event d-block mb-2" style="font-size: 2rem;"></i>
                    Tidak ada data tren harian untuk periode ini
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.analytics-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.analytics-bar-track {
    width: 60px;
    height: 6px;
    border-radius: 3px;
    background: rgba(255,255,255,0.06);
    overflow: hidden;
}
.analytics-bar {
    height: 100%;
    border-radius: 3px;
    background: #6366f1;
    transition: width 0.3s ease;
}
</style>
