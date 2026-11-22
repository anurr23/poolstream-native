<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@/shims/inertia.js';
import { animate, stagger } from 'animejs';
import Logo from '@/Components/Logo.vue';
import MenuIllustration from '@/Components/MenuIllustration.vue';
import HeroBilliard3D from '@/Components/HeroBilliard3D.vue';

const props = defineProps({
    role: { type: String, default: 'kasir' },
    displayName: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => (user.value?.role || props.role) === 'admin');

const firstName = computed(() => {
    const name = (user.value?.name || props.displayName || 'Kasir').trim();
    return name.split(/\s+/)[0] || name;
});
const userInitial = computed(() => (user.value?.name || '?').charAt(0).toUpperCase());

// ─── Tema ────────────────────────────────────────────────
const isDark = ref(true);
const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

// ─── Jam ─────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
const clock = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }),
);
const dateLabel = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
);

// ─── Menu per role ───────────────────────────────────────
const allMenus = [
    {
        key: 'customer',
        title: 'Customer',
        illustration: 'customer',
        tag: 'Booking online & riwayat main',
        meta: 'Tampilan pelanggan',
        href: route('customer.home'),
        accent: '#06b6d4',
        accent2: '#0891b2',
        rgb: '6, 182, 212',
        adminOnly: true,
    },
    {
        key: 'admin',
        title: 'Admin',
        illustration: 'admin',
        tag: 'Master data, laporan & evaluasi AI',
        meta: 'Panel pengelola',
        href: route('admin.index'),
        accent: '#8b5cf6',
        accent2: '#6d28d9',
        rgb: '139, 92, 246',
        adminOnly: true,
    },
    {
        key: 'kasir_meja',
        title: 'Kasir Meja',
        illustration: 'meja',
        tag: 'Sesi meja, booking & timer',
        meta: 'Operasional meja',
        href: route('dashboard'),
        accent: '#10b981',
        accent2: '#059669',
        rgb: '16, 185, 129',
        adminOnly: false,
    },
    {
        key: 'kasir_fnb',
        title: 'Kasir F&B',
        illustration: 'fnb',
        tag: 'Pesanan makanan & minuman',
        meta: 'Operasional F&B',
        href: route('fnb-orders.index'),
        accent: '#f59e0b',
        accent2: '#d97706',
        rgb: '245, 158, 11',
        adminOnly: false,
    },
];

const menus = computed(() => allMenus.filter((menu) => !menu.adminOnly || isAdmin.value));

// ─── Animasi ────────────────────────────────────────────
const rootRef = ref(null);
const scrollProgress = ref(0);
const onScroll = () => {
    if (!rootRef.value) return;
    const el = rootRef.value;
    const maxScroll = Math.max(1, el.scrollHeight - el.clientHeight);
    scrollProgress.value = Math.min(1, Math.max(0, el.scrollTop / maxScroll));
};
const reduceMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

onMounted(() => {
    isDark.value = localStorage.theme !== 'light';
    if (!isDark.value) {
        document.documentElement.setAttribute('data-bs-theme', 'light');
    }
    rootRef.value?.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);

    if (reduceMotion()) {
        rootRef.value?.querySelectorAll('[data-hub-top], [data-hub-card]').forEach((el) => {
            el.style.opacity = '1';
        });
        return;
    }

    const root = rootRef.value;
    if (!root) return;

    try {
        animate(root.querySelectorAll('[data-hub-top]'), {
            opacity: [0, 1],
            translateY: [16, 0],
            duration: 700,
            delay: stagger(90),
            ease: 'outExpo',
        });

        animate(root.querySelectorAll('[data-hub-card]'), {
            opacity: [0, 1],
            translateY: [34, 0],
            scale: [0.96, 1],
            duration: 800,
            delay: stagger(110, { start: 220 }),
            ease: 'outExpo',
        });

        animate(root.querySelectorAll('.bbh-orb'), {
            translateY: [-12, 12],
            translateX: [-6, 6],
            duration: 6000,
            loop: true,
            alternate: true,
            delay: stagger(600),
            ease: 'inOutSine',
        });
    } catch (error) {
        console.warn('Animasi menu dinonaktifkan:', error);
        root.querySelectorAll('[data-hub-top], [data-hub-card]').forEach((el) => {
            el.style.opacity = '1';
        });
    }
});

onBeforeUnmount(() => {
    clearInterval(clockTimer);
    rootRef.value?.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Head title="Menu Utama" />

    <div ref="rootRef" class="bbh">
        <div class="bbh-bg" aria-hidden="true">
            <span class="bbh-orb bbh-orb--emerald"></span>
            <span class="bbh-orb bbh-orb--indigo"></span>
            <span class="bbh-gridlines"></span>
        </div>

        <!-- Top bar -->
        <header class="bbh-nav">
            <div class="bbh-shell bbh-nav__inner">
                <Link :href="route('menu')" class="bbh-brand">
                    <Logo :size="40" />
                    <span class="bbh-brand__text">
                        <strong>PoolStream</strong>
                        <small>{{ isAdmin ? 'Admin Workspace' : 'Kasir Workspace' }}</small>
                    </span>
                </Link>

                <div class="bbh-nav__actions">
                    <span class="bbh-clock d-none d-sm-inline-flex">
                        <i class="bi bi-clock"></i>{{ clock }}
                    </span>

                    <button
                        type="button"
                        class="bbh-icon-btn"
                        :title="isDark ? 'Mode terang' : 'Mode gelap'"
                        @click="toggleTheme"
                    >
                        <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                    </button>

                    <div class="dropdown">
                        <button
                            class="bbh-profile dropdown-toggle"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="--bs-dropdown-toggle-icon-transform: none;"
                        >
                            <span v-if="user?.photo_url" class="bbh-avatar bbh-avatar--img">
                                <img :src="user.photo_url" :alt="user.name" />
                            </span>
                            <span v-else class="bbh-avatar">{{ userInitial }}</span>
                            <span class="bbh-profile__name d-none d-md-inline">{{ user?.name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="border-radius: 0.75rem; min-width: 210px;">
                            <li class="px-3 py-2">
                                <div class="fw-bold small">{{ user?.name }}</div>
                                <div class="small text-secondary text-capitalize">{{ user?.role }}</div>
                            </li>
                            <li><hr class="dropdown-divider my-1" /></li>
                            <li>
                                <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('profile.edit')">
                                    <i class="bi bi-person-circle me-2"></i> Profil
                                </Link>
                            </li>
                            <li>
                                <Link class="dropdown-item rounded-2 py-2 px-3" :href="route('landing')">
                                    <i class="bi bi-globe2 me-2"></i> Landing Page
                                </Link>
                            </li>
                            <li><hr class="dropdown-divider my-1" /></li>
                            <li>
                                <Link class="dropdown-item rounded-2 py-2 px-3 text-danger" :href="route('logout')" method="post" as="button">
                                    <i class="bi bi-box-arrow-left me-2"></i> Log Out
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="bbh-shell bbh-main">
            <!-- Greeting + 3D Table -->
            <div class="bbh-greet">
                <div class="bbh-greet__copy">
                    <span class="bbh-chip" data-hub-top>
                        <i :class="isAdmin ? 'bi bi-shield-lock-fill' : 'bi bi-person-badge-fill'"></i>
                        {{ isAdmin ? 'Administrator' : 'Kasir' }}
                    </span>
                    <h1 class="bbh-title" data-hub-top>
                        Halo, {{ firstName }}<span class="bbh-title__dot">.</span>
                    </h1>
                    <p class="bbh-sub" data-hub-top>
                        {{ isAdmin
                            ? 'Pilih modul yang ingin kamu kelola hari ini.'
                            : 'Pilih menu operasional untuk mulai bekerja.' }}
                    </p>
                    <div class="bbh-date" data-hub-top>
                        <span class="bbh-date__day"><i class="bi bi-calendar3 me-1"></i>{{ dateLabel }}</span>
                        <span class="bbh-date__sep">&middot;</span>
                        <span class="bbh-date__time">{{ clock }}</span>
                    </div>
                </div>

                <div class="bbh-greet__stage" data-hub-top>
                    <HeroBilliard3D :is-dark="isDark" :scroll-progress="scrollProgress" :show-lamp="true">
                        <template #fallback>
                            <div class="bbh-stage-fallback">
                                <MenuIllustration type="meja" />
                            </div>
                        </template>
                    </HeroBilliard3D>
                </div>
            </div>

            <!-- Menu cards -->
            <div class="bbh-grid" :class="{ 'bbh-grid--compact': !isAdmin }">
                <Link
                    v-for="(menu, index) in menus"
                    :key="menu.key"
                    :href="menu.href"
                    class="bbh-card"
                    data-hub-card
                    :style="{
                        '--bbh-accent': menu.accent,
                        '--bbh-accent-2': menu.accent2,
                        '--bbh-accent-rgb': menu.rgb,
                    }"
                >
                    <span class="bbh-card__num">0{{ index + 1 }}</span>
                    <div class="bbh-card__art">
                        <MenuIllustration :type="menu.illustration" />
                    </div>

                    <div class="bbh-card__body">
                        <span class="bbh-card__meta">{{ menu.meta }}</span>
                        <h2 class="bbh-card__title">{{ menu.title }}</h2>
                        <p class="bbh-card__desc">{{ menu.tag }}</p>
                    </div>

                    <span class="bbh-card__cta">
                        Buka <i class="bi bi-arrow-right"></i>
                    </span>
                    <span class="bbh-card__sweep" aria-hidden="true"></span>
                </Link>
            </div>

            <p class="bbh-footnote" data-hub-top>
                <i class="bi bi-info-circle"></i>
                {{ isAdmin
                    ? 'Akses penuh: master data, laporan, evaluasi AI, dan operasional kasir.'
                    : 'Akses kasir mencakup sesi meja dan pesanan F&B.' }}
            </p>
        </main>
    </div>
</template>

<style scoped>
.bbh {
    --bbh-emerald: #34d399;
    --bbh-cyan: #22d3ee;
    --bbh-ink: #f8fafc;
    --bbh-muted: #94a3b8;
    --bbh-card: rgba(17, 26, 44, 0.72);
    --bbh-line: rgba(148, 163, 184, 0.16);

    position: relative;
    isolation: isolate;
    height: 100vh;
    height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;
    background: #070d18;
    color: var(--bbh-ink);
}

[data-bs-theme='light'] .bbh {
    --bbh-ink: #0f172a;
    --bbh-muted: #64748b;
    --bbh-card: rgba(255, 255, 255, 0.9);
    --bbh-line: rgba(15, 23, 42, 0.08);

    background: #f3f7fb;
}

.bbh-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
}
.bbh-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.45;
}
.bbh-orb--emerald {
    width: 44vw;
    height: 44vw;
    min-width: 300px;
    min-height: 300px;
    top: -18vw;
    left: -6vw;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.5), transparent 65%);
}
.bbh-orb--indigo {
    width: 40vw;
    height: 40vw;
    min-width: 280px;
    min-height: 280px;
    bottom: -14vw;
    right: -8vw;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.45), transparent 65%);
}
[data-bs-theme='light'] .bbh-orb {
    opacity: 0.28;
}
.bbh-gridlines {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: radial-gradient(70% 55% at 50% 0%, #000 0%, transparent 100%);
    -webkit-mask-image: radial-gradient(70% 55% at 50% 0%, #000 0%, transparent 100%);
}
[data-bs-theme='light'] .bbh-gridlines {
    background-image: radial-gradient(rgba(15, 23, 42, 0.08) 1px, transparent 1px);
}

.bbh-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding-left: clamp(1.1rem, 4vw, 2.5rem);
    padding-right: clamp(1.1rem, 4vw, 2.5rem);
}

/* Navbar */
.bbh-nav {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(7, 13, 24, 0.7);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid var(--bbh-line);
}
[data-bs-theme='light'] .bbh-nav {
    background: rgba(255, 255, 255, 0.82);
}
.bbh-nav__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-height: 70px;
}
.bbh-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    text-decoration: none;
    color: inherit;
}
.bbh-brand__text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}
.bbh-brand__text strong {
    font-size: 1.02rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.bbh-brand__text small {
    font-size: 0.62rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--bbh-muted);
}
.bbh-nav__actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.bbh-clock {
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border-radius: 50rem;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--bbh-emerald);
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.bbh-icon-btn {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    border: 1px solid var(--bbh-line);
    background: rgba(148, 163, 184, 0.08);
    color: var(--bbh-ink);
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
}
.bbh-icon-btn:hover {
    background: rgba(148, 163, 184, 0.18);
    transform: translateY(-1px);
}

.bbh-profile {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.28rem 0.55rem 0.28rem 0.3rem;
    border-radius: 50rem;
    border: 1px solid var(--bbh-line);
    background: rgba(148, 163, 184, 0.08);
    color: var(--bbh-ink);
    font-weight: 600;
    font-size: 0.82rem;
}
.bbh-profile::after {
    display: none;
}
.bbh-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    overflow: hidden;
    flex-shrink: 0;
}
.bbh-avatar--img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.bbh-profile__name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Main */
.bbh-main {
    padding-top: clamp(2rem, 5vw, 3.25rem);
    padding-bottom: clamp(2.5rem, 5vw, 4rem);
}

.bbh-greet {
    display: grid;
    grid-template-columns: minmax(0, 0.82fr) minmax(0, 1.18fr);
    align-items: center;
    gap: clamp(1rem, 3vw, 2.25rem);
    margin-bottom: clamp(1.25rem, 2.8vw, 2rem);
}
.bbh-greet__stage {
    position: relative;
    width: 100%;
    max-width: 650px;
    margin-left: auto;
}
.bbh-greet__stage::before {
    content: '';
    position: absolute;
    inset: 6% 4% 8%;
    background: radial-gradient(55% 55% at 50% 45%, rgba(16, 185, 129, 0.32), transparent 72%);
    filter: blur(42px);
    pointer-events: none;
}
.bbh-stage-fallback {
    display: flex;
    justify-content: center;
    padding: 1rem;
    color: var(--bbh-emerald);
}
.bbh-stage-fallback :deep(.bb-illustration) {
    width: 220px;
    height: auto;
}
.bbh-greet [data-hub-top],
.bbh-date[data-hub-top],
.bbh-card[data-hub-card],
.bbh-footnote[data-hub-top] {
    opacity: 0;
}

.bbh-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.8rem;
    border-radius: 50rem;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--bbh-emerald);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.bbh-title {
    margin: 0.8rem 0 0.35rem;
    font-size: clamp(1.9rem, 4.4vw, 2.9rem);
    font-weight: 900;
    letter-spacing: -0.035em;
    line-height: 1.05;
}
.bbh-title__dot {
    color: var(--bbh-emerald);
}
.bbh-sub {
    margin: 0;
    color: var(--bbh-muted);
    font-size: 0.9rem;
}
.bbh-date {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.42rem 0.85rem;
    border-radius: 50rem;
    background: rgba(148, 163, 184, 0.08);
    border: 1px solid var(--bbh-line);
}
.bbh-date__day {
    font-size: 0.74rem;
    color: var(--bbh-muted);
    text-transform: capitalize;
}
.bbh-date__sep {
    color: var(--bbh-muted);
    opacity: 0.55;
}
.bbh-date__time {
    font-size: 0.82rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--bbh-emerald);
}

/* Cards */
.bbh-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.bbh-grid--compact {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.bbh-card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 1.4rem;
    border-radius: 1.35rem;
    text-decoration: none;
    color: inherit;
    background: var(--bbh-card);
    border: 1px solid var(--bbh-line);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    min-height: 270px;
}
.bbh-grid--compact .bbh-card {
    min-height: 310px;
    padding: clamp(1.4rem, 3vw, 2rem);
}
.bbh-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--bbh-accent), var(--bbh-accent-2));
    opacity: 0;
    transition: opacity 0.3s ease;
}
.bbh-card:hover {
    transform: translateY(-6px);
    border-color: rgba(var(--bbh-accent-rgb), 0.45);
    box-shadow: 0 26px 50px -24px rgba(var(--bbh-accent-rgb), 0.55);
    color: inherit;
}
.bbh-card:hover::before {
    opacity: 1;
}
.bbh-card__num {
    position: absolute;
    top: 1rem;
    right: 1.25rem;
    font-size: 2.4rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.04em;
    color: rgba(var(--bbh-accent-rgb), 0.2);
    user-select: none;
}
.bbh-card__art {
    width: 100%;
    margin-bottom: 1rem;
}
.bbh-card__art :deep(.bb-illustration) {
    width: 100%;
    max-width: 150px;
    height: auto;
    color: var(--bbh-accent);
    filter: drop-shadow(0 8px 18px rgba(var(--bbh-accent-rgb), 0.25));
    transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.bbh-grid--compact .bbh-card__art :deep(.bb-illustration) {
    max-width: 180px;
}
.bbh-card:hover .bbh-card__art :deep(.bb-illustration) {
    transform: translateY(-4px) scale(1.04);
}
.bbh-card__body {
    margin-top: auto;
}
.bbh-card__meta {
    display: block;
    font-size: 0.63rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--bbh-accent);
    margin-bottom: 0.3rem;
}
.bbh-card__title {
    font-size: 1.15rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin: 0 0 0.25rem;
}
.bbh-card__desc {
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.55;
    color: var(--bbh-muted);
}
.bbh-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    align-self: flex-start;
    margin-top: 1.1rem;
    padding: 0.42rem 0.9rem;
    border-radius: 50rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--bbh-accent);
    background: rgba(var(--bbh-accent-rgb), 0.12);
    border: 1px solid rgba(var(--bbh-accent-rgb), 0.25);
    transition: gap 0.25s ease, background 0.25s ease;
}
.bbh-card:hover .bbh-card__cta {
    gap: 0.7rem;
    background: rgba(var(--bbh-accent-rgb), 0.2);
}
.bbh-card__sweep {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(100deg, transparent 32%, rgba(255, 255, 255, 0.07) 50%, transparent 68%);
    transform: translateX(-110%);
    transition: transform 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.bbh-card:hover .bbh-card__sweep {
    transform: translateX(110%);
}

.bbh-footnote {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 1.75rem 0 0;
    font-size: 0.75rem;
    color: var(--bbh-muted);
}

@media (max-width: 1199.98px) {
    .bbh-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .bbh-grid,
    .bbh-grid--compact {
        grid-template-columns: minmax(0, 1fr);
    }
    .bbh-card,
    .bbh-grid--compact .bbh-card {
        min-height: 0;
        padding: 1.25rem;
    }
    .bbh-card__art :deep(.bb-illustration) {
        max-width: 110px;
    }
    .bbh-grid--compact .bbh-card__art :deep(.bb-illustration) {
        max-width: 120px;
    }
    .bbh-greet {
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
    }
    .bbh-greet__stage {
        max-width: 100%;
        margin: 0 auto;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bbh-card,
    .bbh-card__cta,
    .bbh-icon-btn {
        transition: none;
    }
}
</style>
