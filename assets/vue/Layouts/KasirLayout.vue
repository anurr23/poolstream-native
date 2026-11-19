<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@/shims/inertia.js';
import Toast from '@/Components/Toast.vue';

const isDark = ref(true);

const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

onMounted(() => {
    if (localStorage.theme === 'light') {
        isDark.value = false;
        document.documentElement.setAttribute('data-bs-theme', 'light');
    } else {
        isDark.value = true;
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
});

const userName = computed(() => usePage().props.auth?.user?.name || 'Kasir');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
</script>

<template>
    <div class="bb-kasir">
        <Toast />

        <!-- Top Bar -->
        <header class="bb-header">
            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-grow-1 min-w-0">
                <Link
                    :href="route('landing')"
                    class="bb-btn bb-btn--ghost p-2 flex-shrink-0"
                    style="border-radius: 0.5rem;"
                    title="Kembali ke Menu Utama"
                >
                    <i class="bi bi-arrow-left fs-5"></i>
                </Link>
                <slot name="header" />
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-shrink-0">
                <button @click="toggleTheme" class="bb-theme-toggle" title="Ganti tema">
                    <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                </button>
                <div class="dropdown">
                    <button class="bb-header-profile dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="--bs-dropdown-toggle-icon-transform: none;">
                        <div class="bb-header-avatar">{{ userInitial }}</div>
                        <span class="bb-header-profile-name d-none d-md-inline">{{ userName }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 0.75rem; min-width: 200px;">
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('profile.edit')">
                                <i class="bi bi-person-circle me-2"></i> Profil
                            </Link>
                        </li>
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('landing')">
                                <i class="bi bi-grid me-2"></i> Menu Utama
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

        <!-- Content -->
        <main class="bb-kasir-main">
            <slot />
        </main>
    </div>
</template>
