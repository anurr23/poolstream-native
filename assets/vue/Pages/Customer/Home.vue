<script setup>
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Head, Link, router, usePage } from '@/shims/inertia.js';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    packages: {
        type: Array,
        default: () => [],
    },
    nextBooking: {
        type: Object,
        default: null,
    },
    totalTables: {
        type: Number,
        default: 0,
    },
    availableNow: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const firstName = computed(() => (user.value?.name || '').trim().split(/\s+/)[0] || 'Sobat');

const formatRupiah = (value) => 'Rp ' + (Number(value) || 0).toLocaleString('id-ID');

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatTime = (value) => {
    if (!value) return '--:--';
    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
};

const bookingEnd = (booking) => booking?.expected_end_time || booking?.end_time;

const durationLabel = (booking) => {
    if (!booking?.start_time || !bookingEnd(booking)) return '-';
    const minutes = Math.round((new Date(bookingEnd(booking)) - new Date(booking.start_time)) / 60000);
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours && rest) return `${hours} jam ${rest} menit`;
    if (hours) return `${hours} jam`;
    return `${rest} menit`;
};

const bookingStatus = (booking) => {
    if (booking?.status === 'active') {
        return { label: 'Sedang berjalan', icon: 'bi-broadcast', class: 'is-active' };
    }
    return { label: 'Dijadwalkan', icon: 'bi-calendar2-check', class: 'is-booked' };
};

const features = [
    { icon: 'bi-calendar2-plus', title: 'Booking Online', desc: 'Pesan meja kapan saja' },
    { icon: 'bi-stopwatch', title: 'Timer Otomatis', desc: 'Hitung mundur sesi' },
    { icon: 'bi-wallet2', title: 'Bayar di Tempat', desc: 'Tanpa transfer dulu' },
];

// ─── Timer sesi berjalan ────────────────────────────────
const now = ref(Date.now());
let tickTimer = null;
let refreshTimer = null;

const isActive = computed(() => nextBooking.value?.status === 'active');

const formatDuration = (ms) => {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = String(Math.floor(total / 3600)).padStart(2, '0');
    const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
};

const elapsedLabel = computed(() => {
    if (!isActive.value || !nextBooking.value?.start_time) return '00:00:00';
    return formatDuration(now.value - new Date(nextBooking.value.start_time).getTime());
});

const remainingMs = computed(() => {
    if (!isActive.value || !bookingEnd(nextBooking.value)) return 0;
    return new Date(bookingEnd(nextBooking.value)).getTime() - now.value;
});

const remainingLabel = computed(() => formatDuration(remainingMs.value));

const isOver = computed(() => isActive.value && remainingMs.value <= 0);

const sessionProgress = computed(() => {
    if (!isActive.value) return 0;
    const start = new Date(nextBooking.value?.start_time).getTime();
    const end = new Date(bookingEnd(nextBooking.value)).getTime();
    if (!start || !end || end <= start) return 0;
    return Math.min(100, Math.max(0, ((now.value - start) / (end - start)) * 100));
});

onMounted(() => {
    tickTimer = setInterval(() => {
        now.value = Date.now();
    }, 1000);

    // Segarkan data booking tiap 30 detik agar timer ikut muncul saat kasir memulai sesi
    refreshTimer = setInterval(() => {
        router.reload({ only: ['nextBooking'], preserveScroll: true, preserveState: true });
    }, 30000);
});

onUnmounted(() => {
    clearInterval(tickTimer);
    clearInterval(refreshTimer);
});
</script>

<template>
    <Head title="Beranda" />

    <CustomerLayout>
        <!-- Greeting -->
        <div class="bb-home-greet">
            <div>
                <div class="bb-home-greet__hi">Halo, {{ firstName }} 👋</div>
                <div class="bb-home-greet__sub">Mau main biliar hari ini?</div>
            </div>
        </div>

        <!-- Hero CTA -->
        <div class="bb-home-hero">
            <div class="bb-home-hero__orb bb-home-hero__orb--1"></div>
            <div class="bb-home-hero__orb bb-home-hero__orb--2"></div>
            <div class="bb-home-hero__content">
                <div class="bb-home-hero__badge">
                    <span class="bb-home-dot"></span> {{ availableNow }} meja kosong sekarang
                </div>
                <h2 class="bb-home-hero__title">Booking Meja Biliar</h2>
                <p class="bb-home-hero__sub">Pilih tanggal, meja, dan paket dalam hitungan detik.</p>
                <Link :href="route('customer.booking.index')" class="bb-home-hero__btn">
                    <i class="bi bi-calendar2-plus me-2"></i> Booking Sekarang
                </Link>
            </div>
        </div>

        <!-- Quick stats -->
        <div class="bb-home-stats">
            <div class="bb-home-stat">
                <div class="bb-home-stat__icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
                    <i class="bi bi-columns-gap"></i>
                </div>
                <div class="bb-home-stat__body">
                    <div class="bb-home-stat__value">{{ availableNow }} / {{ totalTables }}</div>
                    <div class="bb-home-stat__label">Meja kosong</div>
                </div>
            </div>
            <div class="bb-home-stat">
                <div class="bb-home-stat__icon" style="background: rgba(245,158,11,0.14); color: #f59e0b;">
                    <i class="bi bi-tag-fill"></i>
                </div>
                <div class="bb-home-stat__body">
                    <div class="bb-home-stat__value">{{ packages.length }}</div>
                    <div class="bb-home-stat__label">Pilihan paket</div>
                </div>
            </div>
            <div class="bb-home-stat">
                <div class="bb-home-stat__icon" style="background: rgba(6,182,212,0.14); color: #06b6d4;">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="bb-home-stat__body">
                    <div class="bb-home-stat__value">Di Kasir</div>
                    <div class="bb-home-stat__label">Bayar langsung</div>
                </div>
            </div>
        </div>

        <!-- Next booking -->
        <div v-if="nextBooking" class="bb-home-booking">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">Booking Terdekat</h6>
                <Link :href="route('customer.history')" class="small text-decoration-none" style="color: #06b6d4;">
                    Lihat semua
                </Link>
            </div>

            <div class="bb-home-booking__card">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="bb-home-booking__date">{{ formatDate(nextBooking.start_time) }}</div>
                        <div class="bb-home-booking__time">
                            {{ formatTime(nextBooking.start_time) }} – {{ formatTime(bookingEnd(nextBooking)) }}
                        </div>
                    </div>
                    <span class="bb-home-booking__status" :class="bookingStatus(nextBooking).class">
                        <i class="bi" :class="bookingStatus(nextBooking).icon"></i>
                        {{ bookingStatus(nextBooking).label }}
                    </span>
                </div>

                <div v-if="isActive" class="bb-home-timer">
                    <div class="bb-home-timer__cell">
                        <div class="bb-home-timer__label">
                            <i class="bi bi-stopwatch"></i> Sudah berjalan
                        </div>
                        <div class="bb-home-timer__value">{{ elapsedLabel }}</div>
                    </div>
                    <div class="bb-home-timer__cell">
                        <div class="bb-home-timer__label">
                            <i class="bi bi-hourglass-split"></i> Sisa waktu
                        </div>
                        <div class="bb-home-timer__value" :class="{ 'is-over': isOver }">
                            {{ isOver ? 'Waktu habis' : remainingLabel }}
                        </div>
                    </div>
                    <div class="bb-home-timer__bar">
                        <div class="bb-home-timer__fill" :style="{ width: sessionProgress + '%' }"></div>
                    </div>
                </div>

                <div class="bb-home-booking__body">
                    <div class="bb-home-booking__table">{{ nextBooking.table?.name || '-' }}</div>
                    <div class="bb-home-booking__meta">
                        {{ nextBooking.package?.name || 'Biliar' }} · {{ durationLabel(nextBooking) }}
                    </div>
                    <div class="bb-home-booking__total">{{ formatRupiah(nextBooking.total_cost) }}</div>
                </div>
            </div>
        </div>

        <div v-else class="bb-home-booking">
            <h6 class="fw-bold mb-2">Booking Terdekat</h6>
            <div class="bb-home-booking__empty">
                <i class="bi bi-calendar-plus"></i>
                <div>
                    <div class="fw-semibold">Belum ada booking</div>
                    <div class="small text-secondary">Yuk pesan meja pertamamu.</div>
                </div>
                <Link :href="route('customer.booking.index')" class="bb-btn bb-btn--success bb-btn--sm ms-auto">
                    Pesan
                </Link>
            </div>
        </div>

        <!-- Packages -->
        <div class="bb-home-section">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">Paket &amp; Harga</h6>
                <span class="small text-secondary">{{ packages.length }} paket</span>
            </div>

            <div class="bb-home-packages">
                <Link
                    v-for="pkg in packages"
                    :key="pkg.id"
                    :href="route('customer.booking.index')"
                    class="bb-home-package"
                >
                    <div class="bb-home-package__name">{{ pkg.name }}</div>
                    <div class="bb-home-package__price">
                        {{ formatRupiah(pkg.price) }}<small>/jam</small>
                    </div>
                    <div class="bb-home-package__cta">
                        Pesan <i class="bi bi-arrow-right"></i>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Features -->
        <div class="bb-home-section">
            <h6 class="fw-bold mb-2">Kenapa PoolStream?</h6>
            <div class="bb-home-features">
                <div v-for="feature in features" :key="feature.title" class="bb-home-feature">
                    <div class="bb-home-feature__icon">
                        <i class="bi" :class="feature.icon"></i>
                    </div>
                    <div>
                        <div class="bb-home-feature__title">{{ feature.title }}</div>
                        <div class="bb-home-feature__desc">{{ feature.desc }}</div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>

<style scoped>
.bb-home-greet {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.bb-home-greet__hi {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.2;
}

.bb-home-greet__sub {
    color: var(--bs-secondary-color);
    font-size: 0.85rem;
}

.bb-home-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.2rem;
    padding: 1.4rem;
    background: linear-gradient(135deg, #0891b2, #0e7490 55%, #164e63);
    color: #fff;
    margin-bottom: 1rem;
}

.bb-home-hero__orb {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    pointer-events: none;
}

.bb-home-hero__orb--1 {
    width: 160px;
    height: 160px;
    top: -60px;
    right: -40px;
}

.bb-home-hero__orb--2 {
    width: 110px;
    height: 110px;
    bottom: -40px;
    right: 60px;
}

.bb-home-hero__content {
    position: relative;
    z-index: 1;
}

.bb-home-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.28rem 0.65rem;
    border-radius: 100px;
    background: rgba(255, 255, 255, 0.14);
}

.bb-home-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.25);
}

.bb-home-hero__title {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0.7rem 0 0.35rem;
}

.bb-home-hero__sub {
    font-size: 0.85rem;
    opacity: 0.85;
    margin-bottom: 1rem;
}

.bb-home-hero__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    color: #0e7490;
    font-weight: 800;
    padding: 0.7rem 1.25rem;
    border-radius: 100px;
    text-decoration: none;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.18);
}

.bb-home-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}

.bb-home-stat {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 0.5rem;
    border-radius: 0.9rem;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    min-width: 0;
    overflow: hidden;
}

.bb-home-stat__icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.bb-home-stat__body {
    min-width: 0;
    flex: 1;
    overflow: hidden;
}

.bb-home-stat__value {
    font-weight: 800;
    font-size: 0.78rem;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bb-home-stat__label {
    font-size: 0.62rem;
    color: var(--bs-secondary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bb-home-booking {
    margin-bottom: 1.25rem;
}

.bb-home-booking__card {
    padding: 0.9rem;
    border-radius: 1rem;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
}

.bb-home-booking__date {
    font-weight: 800;
    font-size: 0.86rem;
}

.bb-home-booking__time {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}

.bb-home-booking__status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.55rem;
    border-radius: 100px;
    font-size: 0.66rem;
    font-weight: 700;
    white-space: nowrap;
    background: rgba(6, 182, 212, 0.14);
    color: #06b6d4;
}

.bb-home-booking__status.is-active {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
}

.bb-home-timer {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin-top: 0.7rem;
    padding: 0.6rem;
    border-radius: 0.8rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.bb-home-timer__cell {
    min-width: 0;
}

.bb-home-timer__label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--bs-secondary-color);
}

.bb-home-timer__value {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-variant-numeric: tabular-nums;
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.25;
    color: #10b981;
}

.bb-home-timer__value.is-over {
    font-size: 0.92rem;
    color: #f59e0b;
}

.bb-home-timer__bar {
    grid-column: 1 / -1;
    height: 5px;
    border-radius: 100px;
    background: rgba(16, 185, 129, 0.18);
    overflow: hidden;
}

.bb-home-timer__fill {
    height: 100%;
    border-radius: 100px;
    background: linear-gradient(90deg, #10b981, #06b6d4);
    transition: width 0.6s linear;
}

.bb-home-booking__body {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.6rem;
    margin-top: 0.7rem;
    padding-top: 0.7rem;
    border-top: 1px dashed var(--bs-border-color);
}

.bb-home-booking__table {
    font-weight: 800;
    font-size: 1rem;
}

.bb-home-booking__meta {
    font-size: 0.74rem;
    color: var(--bs-secondary-color);
}

.bb-home-booking__total {
    font-weight: 800;
    color: #10b981;
    white-space: nowrap;
}

.bb-home-booking__empty {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.9rem;
    border-radius: 0.9rem;
    background: rgba(6, 182, 212, 0.06);
    border: 1px dashed rgba(6, 182, 212, 0.3);
}

.bb-home-booking__empty > i {
    font-size: 1.5rem;
    color: #06b6d4;
}

.bb-home-section {
    margin-bottom: 1.25rem;
}

.bb-home-packages {
    display: flex;
    gap: 0.6rem;
    overflow-x: auto;
    padding-bottom: 0.35rem;
    scrollbar-width: none;
}

.bb-home-packages::-webkit-scrollbar {
    display: none;
}

.bb-home-package {
    flex: 0 0 150px;
    padding: 0.9rem;
    border-radius: 1rem;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    text-decoration: none;
    color: var(--bs-body-color);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.bb-home-package__name {
    font-weight: 800;
    font-size: 0.85rem;
}

.bb-home-package__price {
    font-weight: 800;
    color: #10b981;
    font-size: 0.85rem;
}

.bb-home-package__price small {
    font-weight: 500;
    color: var(--bs-secondary-color);
}

.bb-home-package__cta {
    margin-top: auto;
    font-size: 0.72rem;
    font-weight: 700;
    color: #06b6d4;
}

.bb-home-features {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.bb-home-feature {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 0.9rem;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
}

.bb-home-feature__icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #06b6d4;
    background: rgba(6, 182, 212, 0.12);
    flex-shrink: 0;
}

.bb-home-feature__title {
    font-weight: 800;
    font-size: 0.82rem;
}

.bb-home-feature__desc {
    font-size: 0.72rem;
    color: var(--bs-secondary-color);
}
</style>
