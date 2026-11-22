<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, usePage } from '@/shims/inertia.js';
import Toast from '@/Components/Toast.vue';
import Logo from '@/Components/Logo.vue';

const isDark = ref(true);
const currentTime = ref('');
const isSidebarOpen = ref(true);

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const closeSidebarMobile = () => {
    if (window.innerWidth < 1200) {
        isSidebarOpen.value = false;
    }
};

const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const updateClock = () => {
    currentTime.value = new Date().toLocaleTimeString('en-US', { hour12: false });
};

onMounted(() => {
    if (localStorage.theme === 'light') {
        isDark.value = false;
        document.documentElement.setAttribute('data-bs-theme', 'light');
    } else {
        isDark.value = true;
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
    
    if (window.innerWidth < 1200) {
        isSidebarOpen.value = false;
    }

    updateClock();
    setInterval(updateClock, 1000);
});

const userName = computed(() => usePage().props.auth?.user?.name || 'Admin');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
</script>

<template>
    <div class="d-flex" style="height: 100vh; overflow: hidden; position: relative;">
        <Toast />
        
        <!-- Mobile Sidebar Backdrop -->
        <div v-if="isSidebarOpen" class="bb-sidebar-backdrop" @click="closeSidebarMobile"></div>

        <!-- Sidebar -->
        <aside class="bb-sidebar" :class="{ 'bb-sidebar--open': isSidebarOpen }">
            <!-- Logo -->
            <div class="d-flex justify-content-between align-items-center bb-sidebar-logo">
                <Link :href="route('menu')" class="text-decoration-none d-flex align-items-center gap-3 w-100" @click="closeSidebarMobile">
                    <Logo :size="48" />
                    <div class="bb-logo-text">
                        PoolStream
                        <small>Management System</small>
                    </div>
                </Link>
                <button class="d-lg-none btn btn-sm border-0 text-secondary" @click="closeSidebarMobile">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="bb-nav">
                <Link :href="route('menu')" class="bb-nav-link" :class="{ active: route().current('menu') }" @click="closeSidebarMobile">
                    <i class="bi bi-speedometer2" style="color: #06b6d4;"></i>
                    Menu Utama
                </Link>

                <template v-if="$page.props.auth.user.role === 'admin'">
                    <div class="bb-nav-label">Admin</div>

                    <Link :href="route('admin.index')" class="bb-nav-link" :class="{ active: route().current('admin.index') }" @click="closeSidebarMobile">
                        <i class="bi bi-grid-3x3-gap-fill" style="color: #6366f1;"></i>
                        Panel Admin
                    </Link>

                    <div class="bb-nav-label">Laporan</div>
                    <Link :href="route('reports.fnb-sales')" class="bb-nav-link" :class="{ active: route().current('reports.fnb-sales') }" @click="closeSidebarMobile">
                        <i class="bi bi-receipt-cutoff" style="color: #f59e0b;"></i>
                        Penjualan F&B
                    </Link>

                    <Link :href="route('reports.table-transactions')" class="bb-nav-link" :class="{ active: route().current('reports.table-transactions') }" @click="closeSidebarMobile">
                        <i class="bi bi-journal-text" style="color: #10b981;"></i>
                        Transaksi Meja
                    </Link>

                    <Link :href="route('reports.revenue')" class="bb-nav-link" :class="{ active: route().current('reports.revenue') }" @click="closeSidebarMobile">
                        <i class="bi bi-wallet2" style="color: #6366f1;"></i>
                        Total Pendapatan
                    </Link>

                    <Link :href="route('reports.analytics')" class="bb-nav-link" :class="{ active: route().current('reports.analytics') }" @click="closeSidebarMobile">
                        <i class="bi bi-graph-up-arrow" style="color: #8b5cf6;"></i>
                        Analityc
                    </Link>

                    <div class="bb-nav-label">Master Data</div>

                    <Link :href="route('tables.index')" class="bb-nav-link" :class="{ active: route().current('tables.index') }" @click="closeSidebarMobile">
                        <i class="bi bi-columns-gap" style="color: #10b981;"></i>
                        Kelola Meja
                    </Link>

                    <Link :href="route('packages.index')" class="bb-nav-link" :class="{ active: route().current('packages.index') }" @click="closeSidebarMobile">
                        <i class="bi bi-tag-fill" style="color: #f59e0b;"></i>
                        Paket & Harga
                    </Link>

                    <Link :href="route('fnb_items.index')" class="bb-nav-link" :class="{ active: route().current('fnb_items.index') }" @click="closeSidebarMobile">
                        <i class="bi bi-cup-straw" style="color: #f97316;"></i>
                        Kelola Makanan
                    </Link>

                    <Link :href="route('users.index')" class="bb-nav-link" :class="{ active: route().current('users.index') }" @click="closeSidebarMobile">
                        <i class="bi bi-people-fill" style="color: #8b5cf6;"></i>
                        Kelola User
                    </Link>
                </template>
            </nav>

            <!-- User Section -->
            <div class="bb-sidebar-user">
                <div class="dropdown">
                    <button class="bb-user-card dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="--bs-dropdown-toggle-icon-transform: none;">
                        <div v-if="$page.props.auth.user.photo_url" class="bb-user-avatar border border-2 border-primary overflow-hidden p-0">
                            <img :src="$page.props.auth.user.photo_url" class="w-100 h-100 object-fit-cover" alt="User Photo">
                        </div>
                        <div v-else class="bb-user-avatar">{{ userInitial }}</div>
                        <div class="bb-user-info">
                            <div class="bb-user-name">{{ userName }}</div>
                            <div class="bb-user-role text-capitalize">{{ $page.props.auth.user.role }}</div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 0.75rem; min-width: 200px;">
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('profile.edit')">
                                <i class="bi bi-person-circle me-2"></i> Profil
                            </Link>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3 text-danger" :href="route('logout')" method="post" as="button">
                                <i class="bi bi-box-arrow-left me-2"></i> Log Out
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-grow-1 d-flex flex-column overflow-hidden bb-main" :class="{ 'bb-main--shifted': isSidebarOpen }">
            <!-- Header Bar -->
            <header class="bb-header">
                <div class="d-flex align-items-center gap-3">
                    <button class="bb-btn bb-btn--ghost p-2" style="border-radius: 0.5rem;" @click="toggleSidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <slot name="header" />
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="font-monospace fw-bold px-3 py-1 rounded-3 small d-none d-sm-flex" style="background: rgba(16,185,129,0.08); color: #10b981; border: 1px solid rgba(16,185,129,0.15);">
                        <i class="bi bi-clock me-1"></i>{{ currentTime }}
                    </div>
                    <button @click="toggleTheme" class="bb-theme-toggle">
                        <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                    </button>
                    <div class="dropdown">
                        <button class="bb-header-profile dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="--bs-dropdown-toggle-icon-transform: none;">
                            <div v-if="$page.props.auth.user.photo_url" class="bb-header-avatar overflow-hidden">
                                <img :src="$page.props.auth.user.photo_url" class="w-100 h-100 object-fit-cover" alt="User Photo">
                            </div>
                            <div v-else class="bb-header-avatar">{{ userInitial }}</div>
                            <span class="bb-header-profile-name d-none d-md-inline">{{ userName }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 0.75rem; min-width: 200px;">
                            <li>
                                <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('profile.edit')">
                                    <i class="bi bi-person-circle me-2"></i> Profil
                                </Link>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <Link class="dropdown-item rounded-2 py-2 px-3 text-danger" :href="route('logout')" method="post" as="button">
                                    <i class="bi bi-box-arrow-left me-2"></i> Log Out
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="bb-page-content">
                <slot />
            </div>
        </main>
        
    </div>
</template>
