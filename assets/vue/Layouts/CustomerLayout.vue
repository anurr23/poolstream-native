<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@/shims/inertia.js';
import Toast from '@/Components/Toast.vue';
import Logo from '@/Components/Logo.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const userInitial = computed(() => (user.value?.name || '?').charAt(0).toUpperCase());

defineProps({
    backHref: {
        type: String,
        default: null,
    },
});

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
    <div class="bb-customer">
        <Toast />

        <header class="bb-customer-header">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="bb-btn bb-btn--ghost p-2"
                    style="border-radius: 0.5rem;"
                    title="Kembali"
                >
                    <i class="bi bi-arrow-left fs-5"></i>
                </Link>

                <Link :href="route('customer.home')" class="bb-customer-brand">
                    <Logo :size="34" />
                    <div>
                        <div class="bb-customer-brand-name">PoolStream</div>
                        <div class="bb-customer-brand-sub">Booking Online</div>
                    </div>
                </Link>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="bb-theme-toggle" title="Ganti tema" @click="toggleTheme">
                    <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                </button>

                <div class="dropdown">
                    <button
                        class="bb-customer-avatar dropdown-toggle"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        style="--bs-dropdown-toggle-icon-transform: none;"
                    >
                        <img
                            v-if="user?.photo_url"
                            :src="user.photo_url"
                            :alt="user.name"
                            class="bb-customer-avatar-img"
                        />
                        <span v-else>{{ userInitial }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 0.75rem; min-width: 210px;">
                        <li class="px-3 py-2">
                            <div class="fw-bold small">{{ user?.name }}</div>
                            <div class="small text-secondary">@{{ user?.username }}</div>
                        </li>
                        <li><hr class="dropdown-divider my-1" /></li>
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('profile.edit')">
                                <i class="bi bi-person-circle me-2"></i> Profil
                            </Link>
                        </li>
                        <li>
                            <Link class="dropdown-item rounded-2 py-2 px-3 text-danger" :href="route('logout')" method="post" as="button">
                                <i class="bi bi-box-arrow-left me-2"></i> Log Out
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="bb-customer-main">
            <slot />
        </main>

        <nav class="bb-customer-nav" aria-label="Navigasi customer">
            <div class="bb-customer-nav__inner">
                <Link
                    :href="route('customer.home')"
                    class="bb-customer-nav__item"
                    :class="{ 'is-active': route().current('customer.home') }"
                >
                    <div class="bb-customer-nav__icon-wrap">
                        <i :class="route().current('customer.home') ? 'bi bi-house-door-fill' : 'bi bi-house-door'"></i>
                    </div>
                    <span class="bb-customer-nav__label">Beranda</span>
                    <span class="bb-customer-nav__pip"></span>
                </Link>
                <Link
                    :href="route('customer.booking.index')"
                    class="bb-customer-nav__item bb-customer-nav__item--booking"
                    :class="{ 'is-active': route().current('customer.booking.index') }"
                >
                    <div class="bb-customer-nav__icon-wrap">
                        <i :class="route().current('customer.booking.index') ? 'bi bi-calendar2-plus-fill' : 'bi bi-calendar2-plus'"></i>
                    </div>
                    <span class="bb-customer-nav__label">Booking</span>
                    <span class="bb-customer-nav__pip"></span>
                </Link>
                <Link
                    :href="route('customer.history')"
                    class="bb-customer-nav__item"
                    :class="{ 'is-active': route().current('customer.history') }"
                >
                    <div class="bb-customer-nav__icon-wrap">
                        <i :class="route().current('customer.history') ? 'bi bi-clock-fill' : 'bi bi-clock-history'"></i>
                    </div>
                    <span class="bb-customer-nav__label">Riwayat</span>
                    <span class="bb-customer-nav__pip"></span>
                </Link>
            </div>
        </nav>
    </div>
</template>

<style scoped>
.bb-customer {
    height: 100vh;
    height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
    background: var(--bs-body-bg);
}

.bb-customer-header {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 1rem;
    background: var(--bs-body-bg);
    border-bottom: 1px solid var(--bs-border-color-translucent);
}

.bb-customer-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
    color: var(--bs-body-color);
    min-width: 0;
}

.bb-customer-brand-name {
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.bb-customer-brand-sub {
    font-size: 0.68rem;
    color: var(--bs-secondary-color);
}

.bb-customer-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    border: 1px solid var(--bs-border-color);
    background: linear-gradient(135deg, #06b6d4, #0e7490);
    color: #fff;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0;
}

.bb-customer-avatar::after {
    display: none;
}

.bb-customer-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.bb-customer-main {
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    padding: 1rem 1rem calc(7.5rem + env(safe-area-inset-bottom));
}

.bb-customer-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    padding: 0 0.85rem calc(0.65rem + env(safe-area-inset-bottom)) 0.85rem;
    pointer-events: none;
}

.bb-customer-nav__inner {
    pointer-events: auto;
    display: flex;
    align-items: center;
    justify-content: space-around;
    width: 100%;
    max-width: 440px;
    margin: 0 auto;
    padding: 0.45rem 0.55rem;
    border-radius: 1.4rem;
    background: #0f172a;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.65), 0 4px 12px rgba(0, 0, 0, 0.35);
}

[data-bs-theme="light"] .bb-customer-nav__inner {
    background: #ffffff;
    border-color: rgba(0, 0, 0, 0.12);
    box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.18), 0 4px 12px rgba(0, 0, 0, 0.08);
}

.bb-customer-nav__item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.3rem 0.25rem 0.2rem;
    color: var(--bs-secondary-color);
    text-decoration: none;
    position: relative;
    border-radius: 1rem;
    transition: transform 0.15s ease, color 0.2s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.bb-customer-nav__item:active {
    transform: scale(0.92);
}

.bb-customer-nav__icon-wrap {
    width: 48px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 100px;
    font-size: 1.25rem;
    line-height: 1;
    transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bb-customer-nav__label {
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: 0.01em;
    transition: color 0.2s ease, font-weight 0.2s ease, transform 0.2s ease;
}

.bb-customer-nav__pip {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #06b6d4;
    box-shadow: 0 0 8px #06b6d4;
    margin-top: 1px;
    opacity: 0;
    transform: scale(0);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* Active State */
.bb-customer-nav__item.is-active {
    color: #22d3ee;
}

.bb-customer-nav__item.is-active .bb-customer-nav__icon-wrap {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.22), rgba(8, 145, 178, 0.32));
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.45);
    transform: translateY(-2px) scale(1.08);
    box-shadow: 0 6px 16px -2px rgba(6, 182, 212, 0.38);
}

.bb-customer-nav__item.is-active .bb-customer-nav__label {
    color: #22d3ee;
    font-weight: 800;
    transform: translateY(-1px);
}

.bb-customer-nav__item.is-active .bb-customer-nav__pip {
    opacity: 1;
    transform: scale(1);
}

/* Middle Booking Special Accent */
.bb-customer-nav__item--booking .bb-customer-nav__icon-wrap {
    background: rgba(6, 182, 212, 0.08);
}

.bb-customer-nav__item--booking.is-active .bb-customer-nav__icon-wrap {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 8px 20px -2px rgba(6, 182, 212, 0.5);
}

/* Light Theme overrides for Active */
[data-bs-theme="light"] .bb-customer-nav__item.is-active {
    color: #0891b2;
}

[data-bs-theme="light"] .bb-customer-nav__item.is-active .bb-customer-nav__icon-wrap {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.16), rgba(8, 145, 178, 0.24));
    color: #0891b2;
    border-color: rgba(6, 182, 212, 0.4);
    box-shadow: 0 6px 16px -2px rgba(6, 182, 212, 0.25);
}

[data-bs-theme="light"] .bb-customer-nav__item--booking.is-active .bb-customer-nav__icon-wrap {
    background: linear-gradient(135deg, #0891b2, #0e7490);
    color: #ffffff;
}

[data-bs-theme="light"] .bb-customer-nav__item.is-active .bb-customer-nav__label {
    color: #0891b2;
}

[data-bs-theme="light"] .bb-customer-nav__pip {
    background: #0891b2;
    box-shadow: 0 0 8px #0891b2;
}

@media (min-width: 900px) {
    .bb-customer-header {
        padding: 0.8rem 1.5rem;
    }

    .bb-customer-main {
        padding-top: 1.5rem;
        padding-bottom: 7rem;
    }

    .bb-customer-nav {
        left: 50%;
        right: auto;
        bottom: 1.25rem;
        width: 380px;
        transform: translateX(-50%);
        padding: 0;
    }

    .bb-customer-nav__inner {
        max-width: 100%;
    }
}
</style>
