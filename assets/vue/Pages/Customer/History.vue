<script setup>
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router } from '@/shims/inertia.js';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    bookings: {
        type: Array,
        default: () => [],
    },
});

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

const formatDateShort = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
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

const bookingEnd = (booking) => booking.expected_end_time || booking.end_time;

const durationLabel = (booking) => {
    if (!booking.start_time || !bookingEnd(booking)) return '-';
    const minutes = Math.round(
        (new Date(bookingEnd(booking)) - new Date(booking.start_time)) / 60000,
    );
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours && rest) return `${hours} jam ${rest} menit`;
    if (hours) return `${hours} jam`;
    return `${rest} menit`;
};

const statusMeta = (status) => {
    const map = {
        booked: { label: 'Dibooking', class: 'is-booked', icon: 'bi-calendar2-check' },
        active: { label: 'Berjalan', class: 'is-active', icon: 'bi-broadcast' },
        completed: { label: 'Selesai', class: 'is-completed', icon: 'bi-check2-circle' },
        cancelled: { label: 'Dibatalkan', class: 'is-cancelled', icon: 'bi-x-circle' },
    };

    return map[status] || map.cancelled;
};

const upcoming = computed(() =>
    props.bookings
        .filter((booking) => ['booked', 'active'].includes(booking.status))
        .sort((a, b) => new Date(a.start_time) - new Date(b.start_time)),
);

const past = computed(() =>
    props.bookings.filter((booking) => !['booked', 'active'].includes(booking.status)),
);

const nowTick = ref(Date.now());
let clockTimer = null;

onMounted(() => {
    clockTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
});

const isStartReached = (booking) =>
    booking.status === 'booked' && new Date(booking.start_time).getTime() <= nowTick.value;

const canCancel = (booking) =>
    booking.status === 'booked' && new Date(booking.start_time).getTime() > nowTick.value;

const openCancelConfirm = (booking) => {
    if (!canCancel(booking)) return;
    cancelTarget.value = booking;
};

const cancelTarget = ref(null);

const executeCancel = () => {
    if (!cancelTarget.value || !canCancel(cancelTarget.value)) {
        cancelTarget.value = null;
        return;
    }

    router.delete(route('customer.booking.cancel', cancelTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelTarget.value = null;
        },
        onError: () => {
            cancelTarget.value = null;
        },
    });
};
</script>

<template>
    <Head title="Riwayat Booking" />

    <CustomerLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="bb-h-eyebrow">Booking Saya</div>
                <h4 class="mb-0">Riwayat</h4>
            </div>
            <Link :href="route('customer.booking.index')" class="bb-btn bb-btn--success bb-btn--sm">
                <i class="bi bi-plus-lg me-1"></i> Booking
            </Link>
        </div>

        <!-- Akan datang -->
        <section class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-calendar2-week me-2 text-info"></i>Akan Datang
                </h6>
                <span class="small text-secondary">{{ upcoming.length }} booking</span>
            </div>

            <div v-if="upcoming.length === 0" class="bb-h-empty">
                <i class="bi bi-calendar-plus"></i>
                <div>
                    <div class="fw-semibold">Belum ada booking aktif</div>
                    <div class="small text-secondary">Yuk pesan meja untuk bermain.</div>
                </div>
                <Link :href="route('customer.booking.index')" class="bb-btn bb-btn--success bb-btn--sm ms-auto">
                    Pesan
                </Link>
            </div>

            <div v-else class="d-flex flex-column gap-2">
                <article v-for="booking in upcoming" :key="booking.id" class="bb-h-card">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="bb-h-date">{{ formatDate(booking.start_time) }}</div>
                            <div class="bb-h-time">
                                {{ formatTime(booking.start_time) }} – {{ formatTime(bookingEnd(booking)) }}
                            </div>
                        </div>
                        <span class="bb-h-status" :class="statusMeta(booking.status).class">
                            <i class="bi" :class="statusMeta(booking.status).icon"></i>
                            {{ statusMeta(booking.status).label }}
                        </span>
                    </div>

                    <div class="bb-h-body">
                        <div>
                            <div class="bb-h-table">{{ booking.table?.name || '-' }}</div>
                            <div class="bb-h-meta">
                                {{ booking.package?.name || 'Biliar' }} · {{ durationLabel(booking) }}
                            </div>
                        </div>
                        <div class="bb-h-total">{{ formatRupiah(booking.total_cost) }}</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-secondary">
                            <i class="bi bi-wallet2 me-1"></i>Bayar di kasir
                        </span>
                        <button
                            v-if="canCancel(booking)"
                            type="button"
                            class="bb-h-cancel"
                            @click="openCancelConfirm(booking)"
                        >
                            <i class="bi bi-x-circle me-1"></i>Batalkan
                        </button>
                        <span
                            v-else-if="isStartReached(booking)"
                            class="small text-warning fw-semibold"
                            style="font-size: 0.72rem;"
                        >
                            <i class="bi bi-lock-fill me-1"></i>Waktu bermain sudah masuk
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <!-- Riwayat selesai -->
        <section>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-clock-history me-2 text-secondary"></i>Riwayat Selesai
                </h6>
                <span class="small text-secondary">{{ past.length }} booking</span>
            </div>

            <div v-if="past.length === 0" class="bb-h-empty bb-h-empty--muted">
                <i class="bi bi-inbox"></i>
                <div>
                    <div class="fw-semibold">Belum ada riwayat</div>
                    <div class="small text-secondary">Booking yang sudah selesai akan muncul di sini.</div>
                </div>
            </div>

            <div v-else class="d-flex flex-column gap-2">
                <article v-for="booking in past" :key="booking.id" class="bb-h-card bb-h-card--muted">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="bb-h-date">{{ formatDateShort(booking.start_time) }}</div>
                            <div class="bb-h-time">
                                {{ formatTime(booking.start_time) }} – {{ formatTime(bookingEnd(booking)) }}
                            </div>
                        </div>
                        <span class="bb-h-status" :class="statusMeta(booking.status).class">
                            <i class="bi" :class="statusMeta(booking.status).icon"></i>
                            {{ statusMeta(booking.status).label }}
                        </span>
                    </div>

                    <div class="bb-h-body">
                        <div>
                            <div class="bb-h-table">{{ booking.table?.name || '-' }}</div>
                            <div class="bb-h-meta">
                                {{ booking.package?.name || 'Biliar' }} · {{ durationLabel(booking) }}
                            </div>
                        </div>
                        <div class="bb-h-total">{{ formatRupiah(booking.total_cost) }}</div>
                    </div>
                </article>
            </div>
        </section>

        <ConfirmModal
            :show="!!cancelTarget"
            title="Batalkan Booking"
            :message="`Batalkan booking meja ${cancelTarget?.table?.name || ''} pada ${cancelTarget ? formatDate(cancelTarget.start_time) : ''}?`"
            confirm-text="Ya, Batalkan"
            @confirm="executeCancel"
            @cancel="cancelTarget = null"
        />
    </CustomerLayout>
</template>

<style scoped>
.bb-h-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #06b6d4;
}

.bb-h-empty {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.9rem;
    border-radius: 0.9rem;
    background: rgba(6, 182, 212, 0.06);
    border: 1px dashed rgba(6, 182, 212, 0.3);
}

.bb-h-empty > i {
    font-size: 1.5rem;
    color: #06b6d4;
}

.bb-h-empty--muted {
    background: var(--bs-tertiary-bg);
    border-color: var(--bs-border-color);
}

.bb-h-empty--muted > i {
    color: var(--bs-secondary-color);
}

.bb-h-card {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 0.9rem;
}

.bb-h-card--muted {
    opacity: 0.85;
}

.bb-h-date {
    font-weight: 800;
    font-size: 0.86rem;
}

.bb-h-time {
    font-size: 0.76rem;
    color: var(--bs-secondary-color);
}

.bb-h-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.55rem;
    border-radius: 100px;
    font-size: 0.66rem;
    font-weight: 700;
    white-space: nowrap;
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
}

.bb-h-status.is-active {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
}

.bb-h-status.is-completed {
    background: rgba(108, 117, 125, 0.14);
    color: #94a3b8;
}

.bb-h-status.is-cancelled {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.bb-h-body {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.7rem;
    padding-top: 0.7rem;
    border-top: 1px dashed var(--bs-border-color);
}

.bb-h-table {
    font-weight: 800;
    font-size: 1rem;
}

.bb-h-meta {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}

.bb-h-total {
    font-weight: 800;
    color: #10b981;
    white-space: nowrap;
}

.bb-h-cancel {
    border: 1px solid rgba(239, 68, 68, 0.3);
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border-radius: 100px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.28rem 0.7rem;
}
</style>
