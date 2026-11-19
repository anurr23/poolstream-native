<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, usePage } from '@/shims/inertia.js';
import MenuIllustration from '@/Components/MenuIllustration.vue';
import Logo from '@/Components/Logo.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isAuth = computed(() => !!user.value);
const isAdmin = computed(() => user.value?.role === 'admin');

const isDark = ref(true);
const currentTime = ref('');
const currentDate = ref('');

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('id-ID', { hour12: false });
    currentDate.value = now.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

let clock;
onMounted(() => {
    if (localStorage.theme === 'light') {
        isDark.value = false;
        document.documentElement.setAttribute('data-bs-theme', 'light');
    } else {
        isDark.value = true;
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }
    updateClock();
    clock = setInterval(updateClock, 1000);
    window.addEventListener('mousemove', onPointerMove, { passive: true });
});

onUnmounted(() => {
    clearInterval(clock);
    window.removeEventListener('mousemove', onPointerMove);
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const userInitial = computed(() => (user.value?.name || '?').charAt(0).toUpperCase());

// Parallax halus: rak bola bergeser mengikuti mouse (ditulis langsung ke style,
// tanpa memicu re-render per gerakan mouse).
const rackEl = ref(null);
const onPointerMove = (e) => {
    const el = rackEl.value;
    if (!el) return;
    const x = e.clientX / window.innerWidth - 0.5;
    const y = e.clientY / window.innerHeight - 0.5;
    el.style.transform = `translate3d(${x * -12}px, ${y * -8}px, 0)`;
};

// Rak 10 bola atas meja. Bola 8 selalu di tengah baris ketiga.
const ballLayout = [[1], [2, 3], [4, 8, 5], [6, 9, 10, 7]];
const BALL_COLORS = {
    1: '#fbbf24', 2: '#3b82f6', 3: '#ef4444', 4: '#a855f6', 5: '#f97316',
    6: '#22c55e', 7: '#9a3412', 8: '#1f2937', 9: '#fbbf24', 10: '#3b82f6',
};
const balls = ballLayout.flatMap((row, ri) =>
    row.map((n, ci) => ({
        n,
        x: 320 + ri * 34,
        y: 200 + Math.round((ci - (row.length - 1) / 2) * 32),
        color: BALL_COLORS[n],
        stripe: n === 9 || n === 10,
    }))
);
const stripedBalls = balls.filter((b) => b.stripe);

// Kartu menuju modul tujuan. Belum login? Route tujuan dilindungi middleware
// 'auth', jadi server mengarahkan ke halaman login (intended url tersimpan).
const menus = computed(() => {
    const items = [
        {
            key: 'customer_booking',
            title: 'Customer',
            illustration: 'customer',
            tag: 'Booking online',
            accent: '#06b6d4',
            accent2: '#0891b2',
            rgb: '6, 182, 212',
            href: route('customer.home'),
            customerOnly: true,
        },
        {
            key: 'admin',
            title: 'Admin',
            illustration: 'admin',
            tag: 'Data, laporan, user',
            accent: '#8b5cf6',
            accent2: '#6d28d9',
            rgb: '139, 92, 246',
            href: route('admin.index'),
            adminOnly: true,
        },
        {
            key: 'kasir_meja',
            title: 'Kasir Meja',
            illustration: 'meja',
            tag: 'Sesi meja & timer',
            accent: '#10b981',
            accent2: '#059669',
            rgb: '16, 185, 129',
            href: route('dashboard'),
        },
        {
            key: 'kasir',
            title: 'Kasir F&B',
            illustration: 'fnb',
            tag: 'Pesanan F&B',
            accent: '#f59e0b',
            accent2: '#d97706',
            rgb: '245, 158, 11',
            href: route('fnb-orders.index'),
        },
    ];

    if (user.value?.role === 'customer') {
        return items.filter((menu) => menu.customerOnly);
    }

    return items.filter((menu) => !menu.adminOnly || !isAuth.value || isAdmin.value);
});
</script>

<template>
    <Head title="PoolStream - Sistem Manajemen Pool" />

    <div class="bb-landing">
        <div class="bb-landing-inner">
            <!-- Top Bar -->
            <div class="bb-landing-top">
                <Link :href="route('landing')" class="bb-landing-brand">
                    <Logo :size="44" class="bb-landing-logo" />
                    <div>
                        <div class="bb-landing-brand-name">PoolStream</div>
                        <div class="bb-landing-brand-sub">Management System</div>
                    </div>
                </Link>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button @click="toggleTheme" class="bb-theme-toggle" title="Ganti tema">
                        <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                    </button>
                    <div v-if="isAuth" class="bb-landing-user">
                        <div class="bb-landing-avatar">{{ userInitial }}</div>
                        <div class="d-none d-sm-block">
                            <div class="bb-landing-user-name">{{ user.name }}</div>
                            <div class="bb-landing-user-role text-capitalize">{{ user.role }}</div>
                        </div>
                    </div>
                    <Link v-else :href="route('login')" class="bb-landing-login">
                        <i class="bi bi-box-arrow-in-right"></i>Masuk
                    </Link>
                </div>
            </div>

            <!-- Hero -->
            <div class="bb-landing-hero">
                <div class="row g-4 g-lg-5 align-items-center">
                    <div class="col-lg-6">
                        <span class="bb-badge bb-landing-chip">
                            <i class="bi bi-grid-1x2-fill"></i>Sistem Manajemen Pool &amp; F&amp;B
                        </span>
                        <h1 class="bb-landing-title">
                            Kelola Pool &amp; <span class="text-gradient">F&amp;B</span> dalam Satu Halaman
                        </h1>
                        <p class="bb-landing-sub">
                            Buka sesi meja, jalankan timer otomatis, catat pesanan F&amp;B, dan tutup transaksi.
                            Seluruh alur kasir meleleh di satu layar.
                        </p>

                        <div class="bb-landing-feats">
                            <span class="bb-feat"><i class="bi bi-hourglass-split"></i>Timer otomatis</span>
                            <span class="bb-feat bb-feat--amber"><i class="bi bi-receipt-cutoff"></i>Struk thermal</span>
                            <span class="bb-feat bb-feat--indigo"><i class="bi bi-graph-up-arrow"></i>Laporan harian</span>
                        </div>

                        <div class="bb-scoreboard">
                            <div class="bb-scoreboard-label">
                                <span class="bb-live-dot"></span>Jam Kasir
                            </div>
                            <div class="bb-scoreboard-time">{{ currentTime }}</div>
                            <div class="bb-scoreboard-date">{{ currentDate }}</div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="bb-rack" ref="rackEl">
                            <svg class="bb-rack-svg" viewBox="0 0 520 400" role="img" aria-label="Ilustrasi meja biliar">
                                <defs>
                                    <linearGradient id="bbFelt" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#118259" />
                                        <stop offset="100%" stop-color="#0a4d33" />
                                    </linearGradient>
                                    <linearGradient id="bbRail" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#313d55" />
                                        <stop offset="100%" stop-color="#1c2537" />
                                    </linearGradient>
                                    <clipPath v-for="b in stripedBalls" :key="b.n" :id="'bb-stripe' + b.n">
                                        <circle :cx="b.x" :cy="b.y" r="15" />
                                    </clipPath>
                                </defs>

                                <!-- rails & felt -->
                                <rect x="24" y="36" width="472" height="328" rx="36" fill="url(#bbRail)" />
                                <rect x="44" y="56" width="432" height="288" rx="22" fill="url(#bbFelt)" />
                                <rect x="44" y="56" width="432" height="288" rx="22" fill="none" stroke="rgba(4, 12, 8, 0.5)" stroke-width="6" />
                                <ellipse cx="260" cy="112" rx="200" ry="52" fill="#ffffff" opacity="0.05" />

                                <!-- pockets -->
                                <circle cx="54" cy="66" r="15" fill="#060a12" />
                                <circle cx="466" cy="66" r="15" fill="#060a12" />
                                <circle cx="54" cy="334" r="15" fill="#060a12" />
                                <circle cx="466" cy="334" r="15" fill="#060a12" />
                                <circle cx="260" cy="56" r="14" fill="#060a12" />
                                <circle cx="260" cy="344" r="14" fill="#060a12" />

                                <!-- cue ball + aim marker -->
                                <circle class="bb-aim" cx="120" cy="200" r="27" fill="none" stroke="#34d399" stroke-width="1.6" stroke-dasharray="5 9" stroke-linecap="round" opacity="0.85" />
                                <g class="bb-ball" :style="{ animationDelay: '0.2s' }">
                                    <circle cx="120" cy="200" r="15" fill="#f8fafc" stroke="rgba(0,0,0,0.3)" stroke-width="1" />
                                    <ellipse cx="115.5" cy="194" rx="4.5" ry="2.8" fill="#ffffff" opacity="0.45" />
                                </g>

                                <!-- rack -->
                                <g v-for="(b, i) in balls" :key="b.n" class="bb-ball" :style="{ animationDelay: (i * 0.4) + 's' }">
                                    <template v-if="b.stripe">
                                        <circle :cx="b.x" :cy="b.y" r="15" fill="#f1f5f9" />
                                        <rect :x="b.x - 15" :y="b.y - 5" width="30" height="10" :fill="b.color" :clip-path="'url(#bb-stripe' + b.n + ')'" />
                                    </template>
                                    <circle v-else :cx="b.x" :cy="b.y" r="15" :fill="b.color" />
                                    <circle :cx="b.x" :cy="b.y" r="15" fill="none" stroke="rgba(0,0,0,0.35)" stroke-width="1" />
                                    <circle :cx="b.x" :cy="b.y" r="6.5" fill="#f8fafc" />
                                    <text :x="b.x" :y="b.y + 2.6" text-anchor="middle" font-size="7.5" font-weight="700" fill="#0f172a">{{ b.n }}</text>
                                    <ellipse :cx="b.x - 4.5" :cy="b.y - 6" rx="4.5" ry="2.8" fill="#ffffff" opacity="0.4" />
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Module Cards -->
            <div class="row g-4">
                <div v-for="(menu, i) in menus" :key="menu.key" class="col-12 col-sm-6 col-lg-3">
                    <Link
                        :href="menu.href"
                        class="bb-mod-card"
                        :style="{
                            '--bb-menu-accent': menu.accent,
                            '--bb-menu-accent-2': menu.accent2,
                            '--bb-menu-accent-rgb': menu.rgb,
                        }"
                    >
                        <span class="bb-mod-num">0{{ i + 1 }}</span>
                        <div class="bb-mod-head">
                            <MenuIllustration :type="menu.illustration" />
                        </div>

                        <h5 class="bb-mod-title mb-1">{{ menu.title }}</h5>
                        <p class="bb-mod-desc mb-3">{{ menu.tag }}</p>

                        <div class="bb-mod-foot">
                            <span class="bb-mod-cta">
                                {{ isAuth ? 'Buka' : 'Masuk' }} <i class="bi bi-arrow-right"></i>
                            </span>
                            <span v-if="!isAuth" class="bb-mod-lock" title="Login diperlukan"><i class="bi bi-lock-fill"></i></span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
