<script setup>
import { Head, Link } from '@/shims/inertia.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const masterMenus = [
    {
        title: 'Kelola Meja',
        icon: 'bi-columns-gap',
        desc: 'Atur nama meja dan channel relay',
        href: route('tables.index'),
        accent: '#10b981',
        accent2: '#059669',
        rgb: '16, 185, 129',
    },
    {
        title: 'Paket & Harga',
        icon: 'bi-tag-fill',
        desc: 'Tarif per jam dan durasi sesi',
        href: route('packages.index'),
        accent: '#6366f1',
        accent2: '#4f46e5',
        rgb: '99, 102, 241',
    },
    {
        title: 'Kelola Makanan',
        icon: 'bi-cup-straw',
        desc: 'Menu F&B, foto, dan harga jual',
        href: route('fnb_items.index'),
        accent: '#f97316',
        accent2: '#ea580c',
        rgb: '249, 115, 22',
    },
    {
        title: 'Kelola User',
        icon: 'bi-people-fill',
        desc: 'Akun kasir dan pengaturan akses',
        href: route('users.index'),
        accent: '#8b5cf6',
        accent2: '#7c3aed',
        rgb: '139, 92, 246',
    },
];

const reportMenus = [
    {
        title: 'Penjualan F&B',
        icon: 'bi-receipt-cutoff',
        desc: 'Rekap penjualan makanan & minuman',
        href: route('reports.fnb-sales'),
        accent: '#f59e0b',
        accent2: '#d97706',
        rgb: '245, 158, 11',
    },
    {
        title: 'Transaksi Meja',
        icon: 'bi-journal-text',
        desc: 'Riwayat transaksi sesi meja',
        href: route('reports.table-transactions'),
        accent: '#10b981',
        accent2: '#059669',
        rgb: '16, 185, 129',
    },
    {
        title: 'Total Pendapatan',
        icon: 'bi-wallet2',
        desc: 'Rekap seluruh pendapatan usaha',
        href: route('reports.revenue'),
        accent: '#6366f1',
        accent2: '#4f46e5',
        rgb: '99, 102, 241',
    },
    {
        title: 'Analityc',
        icon: 'bi-graph-up-arrow',
        desc: 'Analisis performa dan tren usaha',
        href: route('reports.analytics'),
        accent: '#8b5cf6',
        accent2: '#7c3aed',
        rgb: '139, 92, 246',
    },
];

const aiMenu = {
    title: 'Evaluasi AI',
    desc: 'Kirim rekap laporan ke AI lokal, dapatkan temuan & rekomendasi otomatis',
    href: route('ai-evaluations.index'),
    icon: 'bi-stars',
};
</script>

<template>
    <Head title="Panel Admin" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-shield-lock-fill me-2" style="color: #6366f1;"></i>Panel Admin</h1>
        </template>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <p class="text-secondary mb-0 small">
                <i class="bi bi-info-circle me-1"></i>Pilih modul yang ingin dikelola.
            </p>
            <Link :href="route('landing')" class="bb-btn bb-btn--ghost bb-btn--sm">
                <i class="bi bi-arrow-left"></i> Kembali ke Menu Utama
            </Link>
        </div>

        <!-- AI Insight (fitur andalan) -->
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-stars" style="color: #a855f7;"></i>AI Insight
        </h6>
        <Link :href="aiMenu.href" class="bb-ai-banner mb-4">
            <div class="bb-ai-banner__orb"></div>
            <div class="bb-ai-banner__body">
                <span class="bb-ai-banner__badge"><i class="bi bi-stars"></i> Fitur Andalan</span>
                <div class="bb-ai-banner__title">
                    <i class="bi" :class="aiMenu.icon"></i> {{ aiMenu.title }}
                </div>
                <div class="bb-ai-banner__desc">{{ aiMenu.desc }}</div>
                <span class="bb-ai-banner__cta">
                    Buka Evaluasi AI <i class="bi bi-arrow-right"></i>
                </span>
            </div>
        </Link>

        <!-- Master Data -->
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-database-fill" style="color: #10b981;"></i>Master Data
        </h6>
        <div class="row g-3 mb-4">
            <div v-for="menu in masterMenus" :key="menu.title" class="col-12 col-sm-6 col-xl-3">
                <Link
                    :href="menu.href"
                    class="bb-menu-card bb-menu-card--sm"
                    :style="{
                        '--bb-menu-accent': menu.accent,
                        '--bb-menu-accent-2': menu.accent2,
                        '--bb-menu-accent-rgb': menu.rgb,
                    }"
                >
                    <div class="d-flex align-items-center gap-3">
                        <div class="bb-menu-icon">
                            <i class="bi" :class="menu.icon"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div class="bb-menu-title text-truncate">{{ menu.title }}</div>
                            <div class="bb-menu-desc">{{ menu.desc }}</div>
                        </div>
                    </div>
                    <span class="bb-menu-cta mt-3">
                        Buka <i class="bi bi-arrow-right"></i>
                    </span>
                </Link>
            </div>
        </div>

        <!-- Reports -->
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-graph-up-arrow" style="color: #f59e0b;"></i>Laporan
        </h6>
        <div class="row g-3">
            <div v-for="menu in reportMenus" :key="menu.title" class="col-12 col-sm-6 col-xl-3">
                <Link
                    :href="menu.href"
                    class="bb-menu-card bb-menu-card--sm"
                    :style="{
                        '--bb-menu-accent': menu.accent,
                        '--bb-menu-accent-2': menu.accent2,
                        '--bb-menu-accent-rgb': menu.rgb,
                    }"
                >
                    <div class="d-flex align-items-center gap-3">
                        <div class="bb-menu-icon">
                            <i class="bi" :class="menu.icon"></i>
                        </div>
                        <div style="min-width: 0;">
                            <div class="bb-menu-title text-truncate">{{ menu.title }}</div>
                            <div class="bb-menu-desc">{{ menu.desc }}</div>
                        </div>
                    </div>
                    <span class="bb-menu-cta mt-3">
                        Buka <i class="bi bi-arrow-right"></i>
                    </span>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.bb-ai-banner {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 1.1rem;
    padding: 1.25rem 1.4rem;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(135deg, #6d28d9, #4f46e5 52%, #0e7490);
    box-shadow: 0 16px 34px rgba(79, 70, 229, 0.3);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bb-ai-banner:hover {
    transform: translateY(-2px);
    box-shadow: 0 22px 42px rgba(79, 70, 229, 0.38);
    color: #fff;
}

.bb-ai-banner__orb {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    top: -90px;
    right: -40px;
    background: rgba(255, 255, 255, 0.12);
    pointer-events: none;
}

.bb-ai-banner__body {
    position: relative;
    z-index: 1;
}

.bb-ai-banner__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 0.25rem 0.6rem;
    border-radius: 100px;
    background: rgba(255, 255, 255, 0.18);
}

.bb-ai-banner__title {
    font-size: 1.2rem;
    font-weight: 800;
    margin: 0.55rem 0 0.2rem;
}

.bb-ai-banner__desc {
    font-size: 0.82rem;
    opacity: 0.9;
    max-width: 42rem;
}

.bb-ai-banner__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.75rem;
    font-size: 0.8rem;
    font-weight: 800;
    padding: 0.45rem 0.95rem;
    border-radius: 100px;
    background: #fff;
    color: #4f46e5;
}
</style>
