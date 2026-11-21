<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@/shims/inertia.js';
import Logo from '@/Components/Logo.vue';

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
</script>

<template>
    <div class="bb-auth-wrapper position-relative">
        <!-- Theme Toggle -->
        <button @click="toggleTheme" class="bb-theme-toggle position-fixed top-0 end-0 m-3 m-sm-4" style="z-index: 10;">
            <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
        </button>

        <div class="bb-auth-card">
            <!-- Logo -->
            <div class="text-center mb-4">
                <Link href="/" class="text-decoration-none d-inline-flex align-items-center gap-2">
                    <Logo :size="48" />
                    <span class="fw-bolder fs-4 bb-text-primary">PoolStream</span>
                </Link>
            </div>
            <slot />
        </div>
    </div>
</template>
