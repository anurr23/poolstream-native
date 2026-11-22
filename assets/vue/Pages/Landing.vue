<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, usePage } from '@/shims/inertia.js';
import { animate, stagger } from 'animejs';
import Logo from '@/Components/Logo.vue';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    totalTables: { type: Number, default: 0 },
    availableNow: { type: Number, default: 0 },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isAuth = computed(() => !!user.value);
const isStaff = computed(() => ['admin', 'kasir'].includes(user.value?.role));

const primaryTarget = computed(() => {
    if (isStaff.value) return route('menu');
    if (isAuth.value) return route('customer.booking.index');
    return route('register');
});
const primaryLabel = computed(() => {
    if (isStaff.value) return 'Menu Utama';
    if (isAuth.value) return 'Booking Sekarang';
    return 'Daftar & Booking';
});
const navLabel = computed(() => (isStaff.value ? 'Menu Utama' : isAuth.value ? 'Beranda' : 'Daftar'));

// ─── Tema ────────────────────────────────────────────────
const isDark = ref(true);
const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.setAttribute('data-bs-theme', isDark.value ? 'dark' : 'light');
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

// ─── Navigasi mobile & scroll state ─────────────────────
const rootRef = ref(null);
const scrolled = ref(false);
const mobileOpen = ref(false);
const toggleMobile = () => {
    mobileOpen.value = !mobileOpen.value;
};
const onScroll = () => {
    if (!rootRef.value) return;
    scrolled.value = rootRef.value.scrollTop > 24;
};

// ─── Isi konten ─────────────────────────────────────────
const formatRupiah = (value) => 'Rp ' + (Number(value) || 0).toLocaleString('id-ID');

const features = [
    {
        icon: 'bi-calendar2-check',
        title: 'Booking Online 24/7',
        desc: 'Cek meja kosong dan kunci jadwal main kapan pun, langsung dari HP.',
        tone: 'emerald',
    },
    {
        icon: 'bi-stopwatch',
        title: 'Timer Otomatis',
        desc: 'Sesi berjalan otomatis, sisa waktu selalu terlihat jelas di layar.',
        tone: 'cyan',
    },
    {
        icon: 'bi-cup-hot',
        title: 'F&B Diantar ke Meja',
        desc: 'Bilang ke kasir, kopi atau camilan diantar ke meja.',
        tone: 'amber',
    },
    {
        icon: 'bi-receipt-cutoff',
        title: 'Struk Digital',
        desc: 'Struk rapi dikirim ke WhatsApp atau email setelah pembayaran.',
        tone: 'indigo',
    },
    {
        icon: 'bi-wallet2',
        title: 'Bayar di Tempat',
        desc: 'Tanpa DP dan tanpa transfer. Selesaikan pembayaran di kasir.',
        tone: 'emerald',
    },
    {
        icon: 'bi-clock-history',
        title: 'Riwayat Main',
        desc: 'Semua booking dan transaksi tersimpan rapi di akun kamu.',
        tone: 'cyan',
    },
];

const steps = [
    {
        icon: 'bi-calendar2-plus',
        title: 'Pilih Meja & Jadwal',
        desc: 'Tentukan tanggal, jam, dan meja favoritmu. Ketersediaan tampil real-time.',
    },
    {
        icon: 'bi-controller',
        title: 'Main Tanpa Ribet',
        desc: 'Timer berjalan otomatis saat sesi dimulai. Pesan F&B lewat kasir, diantar ke meja.',
    },
    {
        icon: 'bi-check2-circle',
        title: 'Bayar & Selesai',
        desc: 'Cukup bayar di kasir. Struk digital langsung dikirim, riwayat tersimpan.',
    },
];

const perks = [
    { icon: 'bi-lightning-charge-fill', text: 'Konfirmasi instan' },
    { icon: 'bi-shield-check', text: 'Tanpa DP' },
    { icon: 'bi-phone', text: 'Ramah mobile' },
];

// ─── Counter angka ──────────────────────────────────────
const counters = ref({ tables: 0, total: 0, packages: 0 });

// ─── Efek mengetik pada judul (bergantian, tidak berhenti) ─
const typePhrases = ['Giliran kamu.', 'Meja favoritmu.', 'Teman mainmu.'];
const typedText = ref('');
let phraseIndex = 0;
let charIndex = 0;
let deleting = false;
let typeTimer = null;

const nextTypingStep = () => {
    const phrase = typePhrases[phraseIndex];

    if (!deleting) {
        charIndex += 1;
        typedText.value = phrase.slice(0, charIndex);

        if (charIndex >= phrase.length) {
            deleting = true;
            typeTimer = setTimeout(nextTypingStep, 1900);
            return;
        }

        typeTimer = setTimeout(nextTypingStep, 68 + Math.random() * 42);
        return;
    }

    charIndex -= 1;
    typedText.value = phrase.slice(0, Math.max(charIndex, 0));

    if (charIndex <= 0) {
        deleting = false;
        phraseIndex = (phraseIndex + 1) % typePhrases.length;
        typeTimer = setTimeout(nextTypingStep, 420);
        return;
    }

    typeTimer = setTimeout(nextTypingStep, 36);
};

const startTyping = () => {
    if (reduceMotion()) {
        typedText.value = typePhrases[0];
        return;
    }

    typeTimer = setTimeout(nextTypingStep, 1250);
};

// ─── Animasi ────────────────────────────────────────────
let observer = null;
const reduceMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const revealAll = () => {
    rootRef.value
        ?.querySelectorAll('[data-hero], [data-reveal]')
        .forEach((el) => {
            el.style.opacity = '1';
        });
};

const startCounters = () => {
    const run = (key, target) => {
        const obj = { value: 0 };
        animate(obj, {
            value: target,
            duration: 1400,
            ease: 'outExpo',
            onUpdate: () => {
                counters.value[key] = Math.round(obj.value);
            },
        });
    };
    run('tables', props.availableNow);
    run('total', props.totalTables);
    run('packages', props.packages.length);
};

const playHero = () => {
    const root = rootRef.value;
    if (!root) return;

    const items = root.querySelectorAll('[data-hero]');
    animate(items, {
        opacity: [0, 1],
        translateY: [26, 0],
        duration: 900,
        delay: stagger(110, { start: 120 }),
        ease: 'outExpo',
    });

    const balls = root.querySelectorAll('.bbp-ball');
    animate(balls, {
        scale: [0, 1],
        opacity: [0, 1],
        duration: 850,
        delay: stagger(70, { start: 420 }),
        ease: 'outBack',
    });

    animate(root.querySelectorAll('.bbp-dust'), {
        opacity: [0, 0.7],
        translateY: [10, -4],
        duration: 1200,
        delay: stagger(90, { start: 700 }),
        ease: 'outQuad',
    });

    animate(root.querySelector('.bbp-cue'), {
        opacity: [0, 1],
        translateX: [70, 0],
        duration: 1000,
        delay: 260,
        ease: 'outExpo',
    });

    animate(root.querySelectorAll('.bbp-float'), {
        opacity: [0, 1],
        translateY: [18, 0],
        scale: [0.92, 1],
        duration: 800,
        delay: stagger(150, { start: 900 }),
        ease: 'outBack',
    });

    // Gerak idle yang lembut agar ilustrasi terasa hidup.
    animate(root.querySelectorAll('.bbp-ball'), {
        translateY: [-2.5, 2.5],
        duration: 2600,
        loop: true,
        alternate: true,
        delay: stagger(160, { start: 1600 }),
        ease: 'inOutSine',
    });

    animate(root.querySelector('.bbp-aim'), {
        rotate: 360,
        duration: 16000,
        loop: true,
        ease: 'linear',
        delay: 1400,
    });

    animate(root.querySelector('.bbp-cue'), {
        translateX: [-5, 5],
        duration: 2200,
        loop: true,
        alternate: true,
        delay: 1500,
        ease: 'inOutQuad',
    });

    animate(root.querySelectorAll('.bbp-float'), {
        translateY: [-5, 5],
        duration: 3600,
        loop: true,
        alternate: true,
        delay: stagger(400, { start: 1800 }),
        ease: 'inOutSine',
    });
};

const observeReveals = () => {
    const root = rootRef.value;
    if (!root) return;

    const groups = root.querySelectorAll('[data-reveal-group]');
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const items = entry.target.querySelectorAll('[data-reveal]');
                animate(items, {
                    opacity: [0, 1],
                    translateY: [28, 0],
                    duration: 750,
                    delay: stagger(85),
                    ease: 'outExpo',
                });
                observer.unobserve(entry.target);
            });
        },
        { root: root, threshold: 0.15 },
    );
    groups.forEach((group) => observer.observe(group));
};

onMounted(() => {
    isDark.value = localStorage.theme !== 'light';
    if (!isDark.value) {
        document.documentElement.setAttribute('data-bs-theme', 'light');
    }

    rootRef.value?.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (reduceMotion()) {
        revealAll();
        startTyping();
        counters.value = {
            tables: props.availableNow,
            total: props.totalTables,
            packages: props.packages.length,
        };
    } else {
        try {
            playHero();
            startCounters();
            observeReveals();
            startTyping();
        } catch (error) {
            console.warn('Animasi landing dinonaktifkan:', error);
            revealAll();
            typedText.value = typePhrases[0];
        }
    }
});

onUnmounted(() => {
    rootRef.value?.removeEventListener('scroll', onScroll);
    observer?.disconnect();
    clearTimeout(typeTimer);
});
</script>

<template>
    <Head title="Billiard & Lounge" />

    <div ref="rootRef" class="bbp-landing">
        <!-- Latar dekoratif -->
        <div class="bbp-bg" aria-hidden="true">
            <span class="bbp-orb bbp-orb--emerald"></span>
            <span class="bbp-orb bbp-orb--indigo"></span>
            <span class="bbp-orb bbp-orb--cyan"></span>
            <span class="bbp-grid"></span>
        </div>

        <!-- Navbar -->
        <header class="bbp-nav" :class="{ 'is-scrolled': scrolled }">
            <div class="bbp-shell bbp-nav__inner">
                <a href="#top" class="bbp-brand" aria-label="PoolStream">
                    <Logo :size="42" />
                    <span class="bbp-brand__text">
                        <strong>PoolStream</strong>
                        <small>Billiard &amp; Lounge</small>
                    </span>
                </a>

                <nav class="bbp-nav__links" aria-label="Navigasi utama">
                    <a href="#fitur">Fitur</a>
                    <a href="#paket">Paket</a>
                    <a href="#cara">Cara Pesan</a>
                </nav>

                <div class="bbp-nav__actions">
                    <button
                        type="button"
                        class="bbp-icon-btn"
                        :title="isDark ? 'Mode terang' : 'Mode gelap'"
                        @click="toggleTheme"
                    >
                        <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                    </button>

                    <Link v-if="!isAuth" :href="route('login')" class="bbp-btn bbp-btn--ghost bbp-nav__login">
                        Masuk
                    </Link>
                    <Link :href="primaryTarget" class="bbp-btn bbp-btn--primary bbp-nav__cta">
                        <span>{{ navLabel }}</span>
                        <i class="bi bi-arrow-right"></i>
                    </Link>

                    <button
                        type="button"
                        class="bbp-icon-btn bbp-burger"
                        :aria-expanded="mobileOpen ? 'true' : 'false'"
                        aria-label="Buka menu"
                        @click="toggleMobile"
                    >
                        <i :class="mobileOpen ? 'bi bi-x-lg' : 'bi bi-list'"></i>
                    </button>
                </div>
            </div>

            <div class="bbp-mobile" :class="{ 'is-open': mobileOpen }">
                <a href="#fitur" @click="mobileOpen = false"><i class="bi bi-stars"></i>Fitur</a>
                <a href="#paket" @click="mobileOpen = false"><i class="bi bi-tag-fill"></i>Paket &amp; Harga</a>
                <a href="#cara" @click="mobileOpen = false"><i class="bi bi-signpost-split-fill"></i>Cara Pesan</a>
                <Link v-if="!isAuth" :href="route('login')" class="bbp-mobile__cta">
                    <i class="bi bi-box-arrow-in-right"></i>Masuk ke Akun
                </Link>
                <Link v-else :href="primaryTarget" class="bbp-mobile__cta">
                    <i class="bi bi-grid-1x2-fill"></i>{{ navLabel }}
                </Link>
                <button type="button" class="bbp-mobile__theme" @click="toggleTheme">
                    <i :class="isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill'"></i>
                    {{ isDark ? 'Mode Terang' : 'Mode Gelap' }}
                </button>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section id="top" class="bbp-hero bbp-shell">
                <div class="bbp-hero__copy">
                    <span class="bbp-pill" data-hero>
                        <span class="bbp-live"></span>
                        {{ availableNow }} meja kosong sekarang
                    </span>

                    <h1 class="bbp-title" data-hero>
                        Meja siap.
                        <span class="bbp-title__line">Bola siap.</span>
                        <span class="bbp-title__accent">
                            <span aria-hidden="true">{{ typedText }}</span><span class="bbp-caret" aria-hidden="true"></span>
                            <span class="visually-hidden">Giliran kamu.</span>
                        </span>
                    </h1>

                    <p class="bbp-sub" data-hero>
                        Booking meja biliar, pantau sisa waktu, dan tambah pesanan F&amp;B lewat
                        kasir. Tanpa antre, tanpa DP — cukup datang dan main.
                    </p>

                    <div class="bbp-hero__cta" data-hero>
                        <Link :href="primaryTarget" class="bbp-btn bbp-btn--primary bbp-btn--lg">
                            <i class="bi bi-calendar2-plus"></i>
                            {{ primaryLabel }}
                        </Link>
                        <a href="#paket" class="bbp-btn bbp-btn--ghost bbp-btn--lg">
                            Lihat Paket
                            <i class="bi bi-arrow-down-right"></i>
                        </a>
                    </div>

                    <ul class="bbp-perks" data-hero>
                        <li v-for="perk in perks" :key="perk.text">
                            <i :class="['bi', perk.icon]"></i>{{ perk.text }}
                        </li>
                    </ul>

                    <div class="bbp-metrics" data-hero>
                        <div class="bbp-metric">
                            <span class="bbp-metric__value">{{ counters.tables }}</span>
                            <span class="bbp-metric__label">Meja kosong</span>
                        </div>
                        <span class="bbp-metric__divider"></span>
                        <div class="bbp-metric">
                            <span class="bbp-metric__value">{{ counters.total }}</span>
                            <span class="bbp-metric__label">Total meja</span>
                        </div>
                        <span class="bbp-metric__divider"></span>
                        <div class="bbp-metric">
                            <span class="bbp-metric__value">{{ counters.packages }}</span>
                            <span class="bbp-metric__label">Pilihan paket</span>
                        </div>
                    </div>
                </div>

                <div class="bbp-hero__art">
                    <div class="bbp-table" data-hero>
                        <svg class="bbp-svg" viewBox="0 0 560 440" role="img" aria-label="Meja biliar dengan bola dan stik">
                            <defs>
                                <linearGradient id="bbpFelt" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#16a06f" />
                                    <stop offset="55%" stop-color="#0f7d57" />
                                    <stop offset="100%" stop-color="#0a5a3e" />
                                </linearGradient>
                                <linearGradient id="bbpRail" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b4a63" />
                                    <stop offset="45%" stop-color="#25304a" />
                                    <stop offset="100%" stop-color="#161e30" />
                                </linearGradient>
                                <linearGradient id="bbpCue" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#f3e3c6" />
                                    <stop offset="70%" stop-color="#d9b98a" />
                                    <stop offset="100%" stop-color="#b98f55" />
                                </linearGradient>
                                <radialGradient id="bbpFeltGlow" cx="50%" cy="18%" r="75%">
                                    <stop offset="0%" stop-color="#ffffff" stop-opacity="0.16" />
                                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                                </radialGradient>
                                <filter id="bbpShadow" x="-20%" y="-20%" width="140%" height="140%">
                                    <feDropShadow dx="0" dy="24" stdDeviation="18" flood-color="#020617" flood-opacity="0.55" />
                                </filter>
                                <filter id="bbpBallShadow" x="-50%" y="-50%" width="200%" height="200%">
                                    <feDropShadow dx="0" dy="3" stdDeviation="2" flood-color="#020617" flood-opacity="0.45" />
                                </filter>
                            </defs>

                            <!-- bayangan meja -->
                            <ellipse cx="280" cy="392" rx="238" ry="26" fill="#020617" opacity="0.5" />

                            <g filter="url(#bbpShadow)">
                                <rect x="22" y="54" width="516" height="318" rx="42" fill="url(#bbpRail)" />
                                <rect x="34" y="66" width="492" height="294" rx="34" fill="#0e1626" opacity="0.55" />
                                <rect x="48" y="80" width="464" height="266" rx="26" fill="url(#bbpFelt)" />
                                <rect x="48" y="80" width="464" height="266" rx="26" fill="url(#bbpFeltGlow)" />
                                <rect x="48" y="80" width="464" height="266" rx="26" fill="none" stroke="#04120c" stroke-opacity="0.55" stroke-width="5" />

                                <!-- pockets -->
                                <circle cx="54" cy="86" r="15" fill="#050b14" />
                                <circle cx="506" cy="86" r="15" fill="#050b14" />
                                <circle cx="54" cy="340" r="15" fill="#050b14" />
                                <circle cx="506" cy="340" r="15" fill="#050b14" />
                                <circle cx="280" cy="80" r="14" fill="#050b14" />
                                <circle cx="280" cy="346" r="14" fill="#050b14" />

                                <!-- dust / chalk -->
                                <circle class="bbp-dust" cx="120" cy="130" r="2" fill="#e2f8ec" />
                                <circle class="bbp-dust" cx="424" cy="170" r="1.7" fill="#e2f8ec" />
                                <circle class="bbp-dust" cx="356" cy="118" r="1.5" fill="#e2f8ec" />
                                <circle class="bbp-dust" cx="196" cy="300" r="1.8" fill="#e2f8ec" />
                                <circle class="bbp-dust" cx="330" cy="288" r="1.4" fill="#e2f8ec" />

                                <!-- aim ring -->
                                <circle class="bbp-aim" cx="176" cy="216" r="31" fill="none" stroke="#a7f3d0" stroke-opacity="0.8" stroke-width="1.5" stroke-dasharray="5 10" stroke-linecap="round" />

                                <!-- cue stick -->
                                <g class="bbp-cue">
                                    <path d="M514 118 L240 208" stroke="url(#bbpCue)" stroke-width="9" stroke-linecap="round" />
                                    <path d="M514 118 L494 125" stroke="#e2e8f0" stroke-width="9" stroke-linecap="round" />
                                    <path d="M240 208 L228 212" stroke="#38bdf8" stroke-width="9" stroke-linecap="round" />
                                    <path d="M514 118 L240 208" stroke="#ffffff" stroke-opacity="0.28" stroke-width="2.4" stroke-linecap="round" />
                                </g>

                                <!-- cue ball -->
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="176" cy="216" r="17" fill="#f8fafc" />
                                    <ellipse cx="170" cy="209" rx="5" ry="3" fill="#ffffff" opacity="0.7" />
                                    <circle cx="176" cy="216" r="17" fill="none" stroke="#0f172a" stroke-opacity="0.18" stroke-width="1.5" />
                                </g>

                                <!-- rack -->
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="344" cy="216" r="16" fill="#fbbf24" />
                                    <circle cx="344" cy="216" r="6.5" fill="#fff7ed" />
                                    <text x="344" y="219" text-anchor="middle" font-size="8" font-weight="800" fill="#78350f">1</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="374" cy="199" r="16" fill="#3b82f6" />
                                    <circle cx="374" cy="199" r="6.5" fill="#fff7ed" />
                                    <text x="374" y="202" text-anchor="middle" font-size="8" font-weight="800" fill="#1e3a8a">2</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="374" cy="233" r="16" fill="#ef4444" />
                                    <circle cx="374" cy="233" r="6.5" fill="#fff7ed" />
                                    <text x="374" y="236" text-anchor="middle" font-size="8" font-weight="800" fill="#7f1d1d">3</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="404" cy="182" r="16" fill="#a855f7" />
                                    <circle cx="404" cy="182" r="6.5" fill="#fff7ed" />
                                    <text x="404" y="185" text-anchor="middle" font-size="8" font-weight="800" fill="#4c1d95">4</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="404" cy="216" r="16" fill="#1f2937" />
                                    <circle cx="404" cy="216" r="6.5" fill="#fff7ed" />
                                    <text x="404" y="219" text-anchor="middle" font-size="8" font-weight="800" fill="#111827">8</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="404" cy="250" r="16" fill="#f97316" />
                                    <circle cx="404" cy="250" r="6.5" fill="#fff7ed" />
                                    <text x="404" y="253" text-anchor="middle" font-size="8" font-weight="800" fill="#7c2d12">5</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="434" cy="165" r="16" fill="#22c55e" />
                                    <circle cx="434" cy="165" r="6.5" fill="#fff7ed" />
                                    <text x="434" y="168" text-anchor="middle" font-size="8" font-weight="800" fill="#14532d">6</text>
                                </g>
                                <g class="bbp-ball" filter="url(#bbpBallShadow)">
                                    <circle cx="434" cy="267" r="16" fill="#9a3412" />
                                    <circle cx="434" cy="267" r="6.5" fill="#fff7ed" />
                                    <text x="434" y="270" text-anchor="middle" font-size="8" font-weight="800" fill="#431407">7</text>
                                </g>
                            </g>
                        </svg>

                        <div class="bbp-float bbp-float--a">
                            <span class="bbp-float__icon bbp-float__icon--emerald">
                                <i class="bi bi-columns-gap"></i>
                            </span>
                            <div>
                                <strong>{{ availableNow }} meja</strong>
                                <span>tersedia sekarang</span>
                            </div>
                            <span class="bbp-live bbp-live--sm"></span>
                        </div>

                        <div class="bbp-float bbp-float--b">
                            <div class="bbp-float__head">
                                <span class="bbp-float__icon bbp-float__icon--amber">
                                    <i class="bi bi-cup-hot-fill"></i>
                                </span>
                                <div>
                                    <strong>Pesanan meja 3</strong>
                                    <span>diantar ke meja</span>
                                </div>
                            </div>
                            <div class="bbp-float__row">
                                <span>Kopi susu × 2</span><em>Rp 36.000</em>
                            </div>
                            <div class="bbp-float__row">
                                <span>French fries</span><em>Rp 25.000</em>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Marquee -->
            <div class="bbp-marquee" aria-hidden="true">
                <div class="bbp-marquee__track">
                    <span v-for="n in 2" :key="n">
                        Booking Online <i class="bi bi-circle-fill"></i>
                        Timer Otomatis <i class="bi bi-circle-fill"></i>
                        Pesanan F&amp;B <i class="bi bi-circle-fill"></i>
                        Struk Digital <i class="bi bi-circle-fill"></i>
                        Bayar di Kasir <i class="bi bi-circle-fill"></i>
                        Riwayat Main <i class="bi bi-circle-fill"></i>
                    </span>
                </div>
            </div>

            <!-- Fitur -->
            <section id="fitur" class="bbp-section bbp-shell" data-reveal-group>
                <div class="bbp-section__head">
                    <span class="bbp-eyebrow" data-reveal>Kenapa PoolStream</span>
                    <h2 class="bbp-h2" data-reveal>Semua yang kamu butuhkan,<br />dalam satu genggaman.</h2>
                    <p class="bbp-section__sub" data-reveal>
                        Dari cek meja kosong sampai struk digital — alur main biliar dibuat
                        sesederhana mungkin.
                    </p>
                </div>

                <div class="bbp-features">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="bbp-feature"
                        :class="'is-' + feature.tone"
                        data-reveal
                    >
                        <span class="bbp-feature__icon"><i :class="['bi', feature.icon]"></i></span>
                        <h3>{{ feature.title }}</h3>
                        <p>{{ feature.desc }}</p>
                        <span class="bbp-feature__glow" aria-hidden="true"></span>
                    </article>
                </div>
            </section>

            <!-- Paket -->
            <section id="paket" class="bbp-section bbp-section--alt" data-reveal-group>
                <div class="bbp-shell">
                    <div class="bbp-section__head">
                        <span class="bbp-eyebrow bbp-eyebrow--amber" data-reveal>Paket &amp; Harga</span>
                        <h2 class="bbp-h2" data-reveal>Tarif jelas, tanpa biaya tersembunyi.</h2>
                        <p class="bbp-section__sub" data-reveal>
                            Bayar sesuai durasi main. Tambahan F&amp;B dihitung transparan saat
                            pembayaran di kasir.
                        </p>
                    </div>

                    <div v-if="packages.length" class="bbp-packages">
                        <article
                            v-for="(pkg, index) in packages.slice(0, 4)"
                            :key="pkg.id"
                            class="bbp-pack"
                            :class="{ 'is-featured': index === 0 }"
                            data-reveal
                        >
                            <span v-if="index === 0" class="bbp-pack__badge">
                                <i class="bi bi-star-fill"></i> Paling populer
                            </span>
                            <span class="bbp-pack__name">{{ pkg.name }}</span>
                            <div class="bbp-pack__price">
                                <span class="bbp-pack__currency">Rp</span>
                                <span class="bbp-pack__amount">{{ Number(pkg.price).toLocaleString('id-ID') }}</span>
                                <span class="bbp-pack__unit">/ jam</span>
                            </div>
                            <ul class="bbp-pack__list">
                                <li><i class="bi bi-check2"></i> Meja biliar standar</li>
                                <li><i class="bi bi-check2"></i> Timer &amp; hitung otomatis</li>
                                <li><i class="bi bi-check2"></i> F&amp;B diantar ke meja</li>
                                <li><i class="bi bi-check2"></i> Struk digital</li>
                            </ul>
                            <Link :href="primaryTarget" class="bbp-btn bbp-pack__cta">
                                Pilih paket <i class="bi bi-arrow-right"></i>
                            </Link>
                        </article>
                    </div>

                    <div v-else class="bbp-empty" data-reveal>
                        <i class="bi bi-tag"></i>
                        <p>Paket belum tersedia. Hubungi kasir untuk informasi harga.</p>
                    </div>
                </div>
            </section>

            <!-- Cara pesan -->
            <section id="cara" class="bbp-section bbp-shell" data-reveal-group>
                <div class="bbp-section__head">
                    <span class="bbp-eyebrow bbp-eyebrow--cyan" data-reveal>Cara Pesan</span>
                    <h2 class="bbp-h2" data-reveal>Tiga langkah, langsung main.</h2>
                    <p class="bbp-section__sub" data-reveal>
                        Tidak perlu install aplikasi. Semua berjalan di browser HP kamu.
                    </p>
                </div>

                <div class="bbp-steps">
                    <article v-for="(step, index) in steps" :key="step.title" class="bbp-step" data-reveal>
                        <span class="bbp-step__num">0{{ index + 1 }}</span>
                        <span class="bbp-step__icon"><i :class="['bi', step.icon]"></i></span>
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.desc }}</p>
                        <span v-if="index < steps.length - 1" class="bbp-step__line" aria-hidden="true"></span>
                    </article>
                </div>
            </section>

            <!-- CTA -->
            <section class="bbp-shell bbp-cta-wrap" data-reveal-group>
                <div class="bbp-cta-band" data-reveal>
                    <span class="bbp-cta-band__orb bbp-cta-band__orb--1" aria-hidden="true"></span>
                    <span class="bbp-cta-band__orb bbp-cta-band__orb--2" aria-hidden="true"></span>
                    <div class="bbp-cta-band__body">
                        <span class="bbp-pill bbp-pill--onDark">
                            <span class="bbp-live"></span> Meja menunggu
                        </span>
                        <h2>Siap jepot bola?</h2>
                        <p>Amankan meja favoritmu sekarang — gratis, tanpa DP.</p>
                    </div>
                    <div class="bbp-cta-band__actions">
                        <Link :href="primaryTarget" class="bbp-btn bbp-btn--white bbp-btn--lg">
                            <i class="bi bi-calendar2-plus"></i>{{ primaryLabel }}
                        </Link>
                        <a v-if="!isAuth" :href="route('login')" class="bbp-btn bbp-btn--onDark bbp-btn--lg">
                            Sudah punya akun?
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bbp-footer">
            <div class="bbp-shell bbp-footer__inner">
                <div class="bbp-brand">
                    <Logo :size="36" />
                    <span class="bbp-brand__text">
                        <strong>PoolStream</strong>
                        <small>Billiard &amp; Lounge</small>
                    </span>
                </div>

                <nav class="bbp-footer__links" aria-label="Tautan footer">
                    <a href="#fitur">Fitur</a>
                    <a href="#paket">Paket</a>
                    <a href="#cara">Cara Pesan</a>
                    <Link v-if="!isAuth" :href="route('login')">Masuk</Link>
                    <Link v-else :href="primaryTarget">{{ navLabel }}</Link>
                </nav>

                <p class="bbp-footer__copy">
                    © {{ new Date().getFullYear() }} PoolStream · Billiard &amp; Lounge
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* ─────────────────────────────────────────────────────────
   Landing page — billiard hall at night
   ───────────────────────────────────────────────────────── */
.bbp-landing {
    --bbp-emerald: #34d399;
    --bbp-emerald-deep: #10b981;
    --bbp-cyan: #22d3ee;
    --bbp-indigo: #818cf8;
    --bbp-amber: #fbbf24;
    --bbp-ink: #f8fafc;
    --bbp-muted: #94a3b8;
    --bbp-card: rgba(17, 26, 44, 0.72);
    --bbp-line: rgba(148, 163, 184, 0.16);

    position: relative;
    isolation: isolate;
    height: 100vh;
    height: 100dvh;
    overflow-x: hidden;
    overflow-y: auto;
    scroll-behavior: smooth;
    scroll-padding-top: 96px;
    background: #070d18;
    color: var(--bbp-ink);
    -webkit-overflow-scrolling: touch;
}

[data-bs-theme='light'] .bbp-landing {
    --bbp-ink: #0f172a;
    --bbp-muted: #64748b;
    --bbp-card: rgba(255, 255, 255, 0.86);
    --bbp-line: rgba(15, 23, 42, 0.08);

    background: #f3f7fb;
}

.bbp-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
}

.bbp-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.5;
}
.bbp-orb--emerald {
    width: 46vw;
    height: 46vw;
    min-width: 320px;
    min-height: 320px;
    top: -16vw;
    left: -8vw;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.55), transparent 65%);
}
.bbp-orb--indigo {
    width: 42vw;
    height: 42vw;
    min-width: 300px;
    min-height: 300px;
    top: -10vw;
    right: -10vw;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.5), transparent 65%);
}
.bbp-orb--cyan {
    width: 40vw;
    height: 40vw;
    min-width: 280px;
    min-height: 280px;
    bottom: 8%;
    left: 32%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.32), transparent 66%);
}
[data-bs-theme='light'] .bbp-orb {
    opacity: 0.32;
}

.bbp-grid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px);
    background-size: 28px 28px;
    mask-image: radial-gradient(75% 55% at 50% 8%, #000 0%, transparent 100%);
    -webkit-mask-image: radial-gradient(75% 55% at 50% 8%, #000 0%, transparent 100%);
}
[data-bs-theme='light'] .bbp-grid {
    background-image: radial-gradient(rgba(15, 23, 42, 0.08) 1px, transparent 1px);
}

.bbp-shell {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding-left: clamp(1.1rem, 4vw, 2.5rem);
    padding-right: clamp(1.1rem, 4vw, 2.5rem);
}

/* ─── Navbar ───────────────────────────────────────────── */
.bbp-nav {
    position: sticky;
    top: 0;
    z-index: 50;
    transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    border-bottom: 1px solid transparent;
}
.bbp-nav.is-scrolled {
    background: rgba(7, 13, 24, 0.96);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom-color: var(--bbp-line);
    box-shadow: 0 16px 36px -28px rgba(2, 6, 23, 0.9);
}
[data-bs-theme='light'] .bbp-nav.is-scrolled {
    background: rgba(255, 255, 255, 0.97);
}

.bbp-nav__inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-height: 74px;
}

.bbp-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
    color: inherit;
    flex-shrink: 0;
}
.bbp-brand__text {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}
.bbp-brand__text strong {
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.bbp-brand__text small {
    font-size: 0.64rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--bbp-muted);
}

.bbp-nav__links {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-left: 1.25rem;
}
.bbp-nav__links a {
    padding: 0.5rem 0.85rem;
    border-radius: 50rem;
    color: var(--bbp-muted);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: color 0.2s ease, background 0.2s ease;
}
.bbp-nav__links a:hover {
    color: var(--bbp-ink);
    background: rgba(148, 163, 184, 0.12);
}

.bbp-nav__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.bbp-icon-btn {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    border: 1px solid var(--bbp-line);
    background: rgba(148, 163, 184, 0.08);
    color: var(--bbp-ink);
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.bbp-icon-btn:hover {
    background: rgba(148, 163, 184, 0.18);
    border-color: rgba(52, 211, 153, 0.4);
    transform: translateY(-1px);
}
.bbp-burger {
    display: none;
}

.bbp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.62rem 1.15rem;
    border-radius: 50rem;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease, color 0.25s ease;
    white-space: nowrap;
}
.bbp-btn:hover {
    transform: translateY(-2px);
}
.bbp-btn--primary {
    color: #04140d;
    background: linear-gradient(135deg, #6ee7b7, var(--bbp-emerald-deep));
    box-shadow: 0 12px 28px -10px rgba(16, 185, 129, 0.65);
}
.bbp-btn--primary:hover {
    box-shadow: 0 18px 36px -12px rgba(16, 185, 129, 0.8);
    color: #04140d;
}
.bbp-btn--ghost {
    color: var(--bbp-ink);
    background: rgba(148, 163, 184, 0.1);
    border-color: var(--bbp-line);
}
.bbp-btn--ghost:hover {
    color: var(--bbp-ink);
    background: rgba(148, 163, 184, 0.18);
    border-color: rgba(52, 211, 153, 0.35);
}
.bbp-btn--lg {
    padding: 0.85rem 1.55rem;
    font-size: 0.92rem;
}
.bbp-btn--white {
    background: #f8fafc;
    color: #0b1322;
}
.bbp-btn--white:hover {
    color: #0b1322;
    box-shadow: 0 16px 30px -12px rgba(2, 6, 23, 0.6);
}
.bbp-btn--onDark {
    color: #f8fafc;
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.22);
}
.bbp-btn--onDark:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.18);
}

.bbp-nav__login {
    padding: 0.55rem 1rem;
}

.bbp-mobile {
    display: none;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0 1.1rem 1rem;
    background: rgba(7, 13, 24, 0.96);
    border-bottom: 1px solid var(--bbp-line);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}
.bbp-mobile.is-open {
    display: flex;
}
[data-bs-theme='light'] .bbp-mobile {
    background: rgba(255, 255, 255, 0.98);
}
.bbp-mobile a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.8rem 0.9rem;
    border-radius: 0.75rem;
    color: var(--bbp-ink);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}
.bbp-mobile a:not(.bbp-mobile__cta):hover {
    background: rgba(148, 163, 184, 0.12);
}
.bbp-mobile a i {
    color: var(--bbp-emerald);
}
.bbp-mobile__cta {
    margin-top: 0.35rem;
    background: linear-gradient(135deg, #6ee7b7, var(--bbp-emerald-deep));
    color: #04140d !important;
    justify-content: center;
}
.bbp-mobile__theme {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.25rem;
    padding: 0.8rem 0.9rem;
    border: 0;
    border-radius: 0.75rem;
    background: transparent;
    color: var(--bbp-muted);
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    text-align: left;
}
.bbp-mobile__theme:hover {
    background: rgba(148, 163, 184, 0.12);
}
.bbp-mobile__theme i {
    color: var(--bbp-amber);
}

/* ─── Hero ─────────────────────────────────────────────── */
.bbp-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.02fr) minmax(0, 0.98fr);
    align-items: center;
    gap: clamp(2rem, 5vw, 4rem);
    padding-top: clamp(2.25rem, 6vw, 4.5rem);
    padding-bottom: clamp(2.5rem, 6vw, 5rem);
}

.bbp-hero__copy [data-hero] {
    opacity: 0;
}
.bbp-hero__art [data-hero] {
    opacity: 0;
}

.bbp-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.42rem 0.9rem;
    border-radius: 50rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6ee7b7;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.28);
}
[data-bs-theme='light'] .bbp-pill {
    color: #047857;
}
.bbp-pill--onDark {
    color: #d1fae5;
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.2);
}

.bbp-live {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--bbp-emerald);
    flex-shrink: 0;
    animation: bbp-pulse 2s ease-out infinite;
}
.bbp-live--sm {
    width: 7px;
    height: 7px;
    margin-left: auto;
}
@keyframes bbp-pulse {
    0% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.55); }
    70% { box-shadow: 0 0 0 9px rgba(52, 211, 153, 0); }
    100% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
}

.bbp-title {
    margin: 1.15rem 0 1rem;
    font-size: clamp(2.35rem, 5.4vw, 4.1rem);
    font-weight: 900;
    line-height: 1.02;
    letter-spacing: -0.035em;
}
.bbp-title__line {
    display: block;
}
.bbp-title__accent {
    display: block;
    min-height: 1.02em;
    white-space: nowrap;
    background: linear-gradient(100deg, #6ee7b7 0%, #22d3ee 55%, #818cf8 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 0 26px rgba(34, 211, 238, 0.22));
}
[data-bs-theme='light'] .bbp-title__accent {
    background: linear-gradient(100deg, #059669 0%, #0891b2 55%, #4f46e5 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: none;
}

.bbp-caret {
    display: inline-block;
    width: 3px;
    height: 0.82em;
    margin-left: 0.06em;
    border-radius: 2px;
    background: var(--bbp-emerald);
    vertical-align: -0.04em;
    animation: bbp-caret 1s steps(1) infinite;
}
[data-bs-theme='light'] .bbp-caret {
    background: #059669;
}
@keyframes bbp-caret {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0; }
}

.bbp-sub {
    max-width: 33rem;
    color: var(--bbp-muted);
    font-size: clamp(0.95rem, 1.5vw, 1.05rem);
    line-height: 1.75;
    margin-bottom: 1.7rem;
}

.bbp-hero__cta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.bbp-perks {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.1rem;
    list-style: none;
    padding: 0;
    margin: 1.4rem 0 0;
}
.bbp-perks li {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--bbp-muted);
}
.bbp-perks i {
    color: var(--bbp-emerald);
    font-size: 0.9rem;
}

.bbp-metrics {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-top: 1.9rem;
    padding-top: 1.4rem;
    border-top: 1px solid var(--bbp-line);
    max-width: 28rem;
}
.bbp-metric {
    display: flex;
    flex-direction: column;
}
.bbp-metric__value {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.bbp-metric__label {
    margin-top: 0.25rem;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--bbp-muted);
}
.bbp-metric__divider {
    width: 1px;
    height: 30px;
    background: var(--bbp-line);
}

/* ─── Hero art ─────────────────────────────────────────── */
.bbp-hero__art {
    position: relative;
    min-width: 0;
}
.bbp-table {
    position: relative;
    width: 100%;
    max-width: 560px;
    margin: 0 auto;
}
.bbp-table::before {
    content: '';
    position: absolute;
    inset: 10% 4% 12%;
    background: radial-gradient(50% 50% at 50% 50%, rgba(16, 185, 129, 0.28), transparent 72%);
    filter: blur(40px);
    pointer-events: none;
}
.bbp-svg {
    position: relative;
    z-index: 1;
    width: 100%;
    height: auto;
    display: block;
    filter: drop-shadow(0 30px 50px rgba(2, 6, 23, 0.35));
}
[data-bs-theme='light'] .bbp-svg {
    filter: drop-shadow(0 26px 44px rgba(15, 23, 42, 0.18));
}

.bbp-ball {
    transform-box: fill-box;
    transform-origin: center;
}
.bbp-aim {
    transform-box: fill-box;
    transform-origin: center;
}
.bbp-cue {
    transform-box: fill-box;
    transform-origin: center;
}
.bbp-dust {
    opacity: 0;
}

.bbp-float {
    position: absolute;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.7rem 0.9rem;
    border-radius: 1rem;
    background: rgba(10, 17, 31, 0.82);
    border: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: 0 20px 40px -18px rgba(2, 6, 23, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    text-align: left;
    opacity: 0;
}
[data-bs-theme='light'] .bbp-float {
    background: rgba(255, 255, 255, 0.92);
    border-color: rgba(15, 23, 42, 0.08);
    box-shadow: 0 20px 40px -22px rgba(15, 23, 42, 0.35);
}
.bbp-float--a {
    top: 3%;
    left: -2%;
}
.bbp-float--b {
    right: -3%;
    bottom: 2%;
    flex-direction: column;
    align-items: stretch;
    gap: 0.4rem;
    min-width: 216px;
}
.bbp-float__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.bbp-float strong {
    display: block;
    font-size: 0.82rem;
    font-weight: 800;
    line-height: 1.2;
}
.bbp-float span {
    font-size: 0.68rem;
    color: var(--bbp-muted);
}
.bbp-float__icon {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.bbp-float__icon--emerald {
    background: rgba(16, 185, 129, 0.15);
    color: var(--bbp-emerald);
}
.bbp-float__icon--amber {
    background: rgba(245, 158, 11, 0.16);
    color: var(--bbp-amber);
}
.bbp-float__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.72rem;
    color: var(--bbp-muted);
    padding-top: 0.35rem;
    border-top: 1px dashed rgba(148, 163, 184, 0.2);
}
.bbp-float__row em {
    font-style: normal;
    font-weight: 700;
    color: var(--bbp-ink);
}

/* ─── Marquee ──────────────────────────────────────────── */
.bbp-marquee {
    position: relative;
    z-index: 1;
    overflow: hidden;
    border-top: 1px solid var(--bbp-line);
    border-bottom: 1px solid var(--bbp-line);
    background: rgba(148, 163, 184, 0.04);
    padding: 0.8rem 0;
    mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent);
}
.bbp-marquee__track {
    display: flex;
    width: max-content;
    animation: bbp-marquee 32s linear infinite;
}
.bbp-marquee__track span {
    display: inline-flex;
    align-items: center;
    gap: 1.5rem;
    padding-right: 1.5rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--bbp-muted);
    white-space: nowrap;
}
.bbp-marquee__track i {
    font-size: 0.32rem;
    color: var(--bbp-emerald);
}
@keyframes bbp-marquee {
    to { transform: translateX(-50%); }
}

/* ─── Sections ─────────────────────────────────────────── */
.bbp-section {
    padding-top: clamp(3.5rem, 8vw, 6.5rem);
    padding-bottom: clamp(3.5rem, 8vw, 6.5rem);
}
.bbp-section--alt {
    background: linear-gradient(180deg, rgba(148, 163, 184, 0.045), rgba(148, 163, 184, 0.015));
    border-top: 1px solid var(--bbp-line);
    border-bottom: 1px solid var(--bbp-line);
}

.bbp-section__head {
    max-width: 44rem;
    margin-bottom: clamp(2rem, 4vw, 3rem);
}
.bbp-eyebrow {
    display: inline-block;
    margin-bottom: 0.7rem;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--bbp-emerald);
}
.bbp-eyebrow--amber { color: var(--bbp-amber); }
.bbp-eyebrow--cyan { color: var(--bbp-cyan); }

.bbp-h2 {
    font-size: clamp(1.65rem, 3.6vw, 2.55rem);
    font-weight: 900;
    line-height: 1.15;
    letter-spacing: -0.03em;
    margin-bottom: 0.9rem;
}
.bbp-section__sub {
    color: var(--bbp-muted);
    font-size: clamp(0.9rem, 1.4vw, 1rem);
    line-height: 1.75;
    max-width: 36rem;
    margin: 0;
}

.bbp-section [data-reveal] {
    opacity: 0;
}
.bbp-cta-wrap [data-reveal] {
    opacity: 0;
}

/* ─── Features ─────────────────────────────────────────── */
.bbp-features {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}
.bbp-feature {
    position: relative;
    overflow: hidden;
    padding: 1.5rem;
    border-radius: 1.25rem;
    background: var(--bbp-card);
    border: 1px solid var(--bbp-line);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.bbp-feature:hover {
    transform: translateY(-4px);
    border-color: rgba(52, 211, 153, 0.35);
    box-shadow: 0 24px 44px -24px rgba(2, 6, 23, 0.8);
}
[data-bs-theme='light'] .bbp-feature:hover {
    box-shadow: 0 24px 44px -26px rgba(15, 23, 42, 0.3);
}
.bbp-feature__icon {
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1.2rem;
    margin-bottom: 1rem;
    background: rgba(16, 185, 129, 0.13);
    color: var(--bbp-emerald);
    border: 1px solid rgba(16, 185, 129, 0.22);
}
.bbp-feature.is-cyan .bbp-feature__icon {
    background: rgba(6, 182, 212, 0.13);
    color: var(--bbp-cyan);
    border-color: rgba(6, 182, 212, 0.24);
}
.bbp-feature.is-amber .bbp-feature__icon {
    background: rgba(245, 158, 11, 0.13);
    color: var(--bbp-amber);
    border-color: rgba(245, 158, 11, 0.24);
}
.bbp-feature.is-indigo .bbp-feature__icon {
    background: rgba(99, 102, 241, 0.14);
    color: var(--bbp-indigo);
    border-color: rgba(99, 102, 241, 0.26);
}
.bbp-feature h3 {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 0.45rem;
}
.bbp-feature p {
    margin: 0;
    font-size: 0.83rem;
    line-height: 1.65;
    color: var(--bbp-muted);
}
.bbp-feature__glow {
    position: absolute;
    right: -40px;
    bottom: -50px;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.16), transparent 68%);
    pointer-events: none;
}

/* ─── Packages ─────────────────────────────────────────── */
.bbp-packages {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    align-items: stretch;
}
.bbp-pack {
    position: relative;
    display: flex;
    flex-direction: column;
    padding: 1.6rem 1.5rem;
    border-radius: 1.35rem;
    background: var(--bbp-card);
    border: 1px solid var(--bbp-line);
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.bbp-pack:hover {
    transform: translateY(-4px);
    border-color: rgba(52, 211, 153, 0.35);
}
.bbp-pack.is-featured {
    border-color: rgba(52, 211, 153, 0.5);
    box-shadow: 0 24px 50px -28px rgba(16, 185, 129, 0.55);
    background:
        linear-gradient(180deg, rgba(16, 185, 129, 0.1), transparent 45%),
        var(--bbp-card);
}
.bbp-pack__badge {
    position: absolute;
    top: -0.7rem;
    left: 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.7rem;
    border-radius: 50rem;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #04140d;
    background: linear-gradient(135deg, #6ee7b7, #10b981);
    box-shadow: 0 8px 18px -8px rgba(16, 185, 129, 0.7);
}
.bbp-pack__name {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--bbp-muted);
}
.bbp-pack__price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin: 0.65rem 0 1.1rem;
}
.bbp-pack__currency {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--bbp-muted);
}
.bbp-pack__amount {
    font-size: clamp(1.65rem, 3vw, 2.1rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    line-height: 1;
}
.bbp-pack__unit {
    font-size: 0.78rem;
    color: var(--bbp-muted);
}
.bbp-pack__list {
    list-style: none;
    padding: 0;
    margin: 0 0 1.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.bbp-pack__list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--bbp-muted);
}
.bbp-pack__list i {
    color: var(--bbp-emerald);
    font-size: 0.9rem;
}
.bbp-pack__cta {
    margin-top: auto;
    width: 100%;
    padding: 0.72rem 1rem;
    border-radius: 0.85rem;
    font-size: 0.82rem;
    color: var(--bbp-ink);
    background: rgba(148, 163, 184, 0.1);
    border: 1px solid var(--bbp-line);
}
.bbp-pack__cta:hover {
    color: #04140d;
    background: linear-gradient(135deg, #6ee7b7, #10b981);
    border-color: transparent;
}
.bbp-pack.is-featured .bbp-pack__cta {
    color: #04140d;
    background: linear-gradient(135deg, #6ee7b7, #10b981);
    border-color: transparent;
    box-shadow: 0 12px 26px -12px rgba(16, 185, 129, 0.7);
}

.bbp-empty {
    padding: 2.5rem 1.5rem;
    text-align: center;
    border-radius: 1.25rem;
    border: 1px dashed var(--bbp-line);
    color: var(--bbp-muted);
}
.bbp-empty i {
    font-size: 2rem;
    opacity: 0.4;
}
.bbp-empty p {
    margin: 0.6rem 0 0;
    font-size: 0.85rem;
}

/* ─── Steps ────────────────────────────────────────────── */
.bbp-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.25rem;
}
.bbp-step {
    position: relative;
    padding: 1.5rem;
    border-radius: 1.25rem;
    background: var(--bbp-card);
    border: 1px solid var(--bbp-line);
}
.bbp-step__num {
    position: absolute;
    top: 1rem;
    right: 1.25rem;
    font-size: 2.4rem;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.04em;
    color: rgba(148, 163, 184, 0.18);
}
.bbp-step__icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    margin-bottom: 0.9rem;
    font-size: 1.15rem;
    color: var(--bbp-cyan);
    background: rgba(6, 182, 212, 0.12);
    border: 1px solid rgba(6, 182, 212, 0.22);
}
.bbp-step h3 {
    font-size: 1rem;
    font-weight: 800;
    margin-bottom: 0.4rem;
}
.bbp-step p {
    margin: 0;
    font-size: 0.83rem;
    line-height: 1.65;
    color: var(--bbp-muted);
}
.bbp-step__line {
    position: absolute;
    top: 50%;
    right: -0.75rem;
    width: 1.25rem;
    height: 2px;
    background: linear-gradient(90deg, rgba(52, 211, 153, 0.5), transparent);
}

/* ─── CTA band ─────────────────────────────────────────── */
.bbp-cta-wrap {
    padding-bottom: clamp(3.5rem, 8vw, 6rem);
}
.bbp-cta-band {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    flex-wrap: wrap;
    padding: clamp(1.75rem, 4vw, 2.75rem);
    border-radius: 1.75rem;
    background: linear-gradient(120deg, #0b5c43 0%, #0e7490 48%, #4338ca 100%);
    box-shadow: 0 30px 60px -30px rgba(14, 116, 144, 0.7);
    color: #f8fafc;
}
.bbp-cta-band__orb {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    pointer-events: none;
}
.bbp-cta-band__orb--1 {
    width: 240px;
    height: 240px;
    top: -110px;
    right: 18%;
}
.bbp-cta-band__orb--2 {
    width: 180px;
    height: 180px;
    bottom: -90px;
    left: 6%;
    background: rgba(255, 255, 255, 0.08);
}
.bbp-cta-band__body {
    position: relative;
    z-index: 1;
}
.bbp-cta-band h2 {
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 900;
    letter-spacing: -0.03em;
    margin: 0.7rem 0 0.35rem;
    color: #ffffff;
}
.bbp-cta-band p {
    margin: 0;
    font-size: 0.9rem;
    color: rgba(248, 250, 252, 0.82);
}
.bbp-cta-band__actions {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}

/* ─── Footer ───────────────────────────────────────────── */
.bbp-footer {
    position: relative;
    z-index: 1;
    border-top: 1px solid var(--bbp-line);
    padding: 1.75rem 0 2rem;
    background: rgba(7, 13, 24, 0.55);
}
[data-bs-theme='light'] .bbp-footer {
    background: rgba(255, 255, 255, 0.6);
}
.bbp-footer__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
}
.bbp-footer__links {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    flex-wrap: wrap;
}
.bbp-footer__links a {
    color: var(--bbp-muted);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    transition: color 0.2s ease;
}
.bbp-footer__links a:hover {
    color: var(--bbp-emerald);
}
.bbp-footer__copy {
    margin: 0;
    font-size: 0.75rem;
    color: var(--bbp-muted);
}

/* ─── Responsive ───────────────────────────────────────── */
@media (max-width: 991.98px) {
    .bbp-nav__links {
        display: none;
    }
    .bbp-burger {
        display: inline-flex;
    }
    .bbp-nav__login {
        display: none;
    }
    .bbp-hero {
        grid-template-columns: minmax(0, 1fr);
        text-align: center;
        gap: 2.5rem;
        padding-top: 2.25rem;
    }
    .bbp-sub {
        margin-left: auto;
        margin-right: auto;
    }
    .bbp-hero__cta {
        justify-content: center;
    }
    .bbp-perks {
        justify-content: center;
    }
    .bbp-metrics {
        justify-content: center;
        margin-left: auto;
        margin-right: auto;
    }
    .bbp-section__head {
        margin-left: auto;
        margin-right: auto;
        text-align: center;
    }
    .bbp-section__sub {
        margin-left: auto;
        margin-right: auto;
    }
    .bbp-hero__art {
        max-width: 580px;
        margin: 0 auto;
        width: 100%;
    }
    .bbp-features {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .bbp-steps {
        grid-template-columns: minmax(0, 1fr);
        max-width: 30rem;
        margin: 0 auto;
    }
    .bbp-step__line {
        display: none;
    }
    .bbp-cta-band {
        text-align: center;
    }
    .bbp-cta-band__body {
        width: 100%;
    }
    .bbp-cta-band__actions {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 575.98px) {
    .bbp-nav__inner {
        min-height: 64px;
    }
    .bbp-brand__text small {
        display: none;
    }
    .bbp-nav__actions > .bbp-icon-btn:not(.bbp-burger) {
        display: none;
    }
    .bbp-nav__cta {
        padding: 0.58rem 0.95rem;
    }
    .bbp-nav__cta i {
        display: none;
    }
    .bbp-hero {
        padding-top: 1.75rem;
    }
    .bbp-title {
        font-size: clamp(2.1rem, 9.4vw, 2.9rem);
    }
    .bbp-metrics {
        gap: 0.9rem;
    }
    .bbp-metric__value {
        font-size: 1.15rem;
    }
    .bbp-metric__label {
        font-size: 0.6rem;
    }
    .bbp-features {
        grid-template-columns: minmax(0, 1fr);
    }
    .bbp-feature {
        padding: 1.25rem;
    }
    .bbp-float {
        padding: 0.55rem 0.7rem;
        border-radius: 0.8rem;
    }
    .bbp-float--a {
        left: 0;
        top: 0;
    }
    .bbp-float--b {
        right: 0;
        bottom: 0;
        min-width: 0;
        max-width: 58%;
    }
    .bbp-float__row {
        display: none;
    }
    .bbp-float strong {
        font-size: 0.72rem;
    }
    .bbp-float span {
        font-size: 0.6rem;
    }
    .bbp-float__icon {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
    .bbp-footer__inner {
        flex-direction: column;
        text-align: center;
    }
    .bbp-footer__links {
        justify-content: center;
    }
    .bbp-btn--lg {
        padding: 0.75rem 1.2rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 379.98px) {
    .bbp-brand__text {
        display: none;
    }
    .bbp-nav__cta {
        padding: 0.55rem 0.8rem;
        font-size: 0.78rem;
    }
    .bbp-title {
        font-size: clamp(1.8rem, 9vw, 2.5rem);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bbp-landing {
        scroll-behavior: auto;
    }
    .bbp-marquee__track,
    .bbp-live,
    .bbp-caret {
        animation: none;
    }
    .bbp-caret {
        display: none;
    }
    .bbp-feature,
    .bbp-pack,
    .bbp-btn {
        transition: none;
    }
}
</style>
