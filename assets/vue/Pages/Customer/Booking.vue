<script setup>
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Head, useForm } from '@/shims/inertia.js';
import { computed, ref } from 'vue';
import flatPickr from 'vue-flatpickr-component';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';

const props = defineProps({
    packages: {
        type: Array,
        default: () => [],
    },
    today: {
        type: String,
        required: true,
    },
    maxDate: {
        type: String,
        required: true,
    },
});

const pad = (value) => String(value).padStart(2, '0');

const toYMD = (date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const tomorrow = computed(() => {
    const date = new Date(`${props.today}T00:00:00`);
    date.setDate(date.getDate() + 1);
    return toYMD(date);
});

const step = ref(1);
const selectedDate = ref(props.today);
const fpConfig = computed(() => ({
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'l, d F Y',
    altInputClass: 'bb-input w-100 mt-1',
    minDate: props.today,
    maxDate: props.maxDate,
    disableMobile: true,
    locale: Indonesian,
}));

const timeConfig = {
    enableTime: true,
    noCalendar: true,
    dateFormat: 'H:i',
    time_24hr: true,
    minuteIncrement: 30,
    disableMobile: true,
};
const tables = ref([]);
const loadingTables = ref(false);
const selectedTable = ref(null);
const tableBookings = ref([]);
const loadingBookings = ref(false);
const message = ref('');

const durations = [0.5, 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8];

const form = useForm({
    date: props.today,
    start_time: '10:00',
    duration_hours: 1,
    package_id: props.packages[0]?.id ?? '',
    table_id: '',
});

const formatRupiah = (value) => 'Rp ' + (Number(value) || 0).toLocaleString('id-ID');

const dateLabel = computed(() => {
    if (!selectedDate.value) return '';
    return new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const selectedPackage = computed(
    () => props.packages.find((pkg) => pkg.id === form.package_id) || null,
);

const total = computed(
    () => (Number(selectedPackage.value?.price) || 0) * (Number(form.duration_hours) || 0),
);

const currentStart = computed(() => {
    if (!form.start_time) return null;
    return new Date(`${selectedDate.value}T${form.start_time}:00`);
});

const currentEnd = computed(() => {
    if (!currentStart.value) return null;
    return new Date(currentStart.value.getTime() + Number(form.duration_hours) * 3600000);
});

const endTime = computed(() => {
    if (!currentEnd.value || Number.isNaN(currentEnd.value.getTime())) return '-';

    const crossesDay = currentStart.value
        ? currentEnd.value.toDateString() !== currentStart.value.toDateString()
        : false;

    return `${pad(currentEnd.value.getHours())}:${pad(currentEnd.value.getMinutes())}${crossesDay ? ' (+1 hari)' : ''}`;
});

const hasConflict = (start, duration, bookings) => {
    const startMs = start.getTime();
    const endMs = startMs + Number(duration) * 3600000;

    return bookings.find((booking) => {
        if (!['booked', 'active'].includes(booking.status)) return false;
        const bookingStart = new Date(booking.start_time).getTime();
        const bookingEnd = new Date(booking.expected_end_time || booking.end_time).getTime();
        return bookingStart < endMs && bookingEnd > startMs;
    });
};

const conflict = computed(() => {
    if (!currentStart.value || !currentEnd.value) return null;

    if (currentStart.value < new Date()) {
        return { label: 'Waktu mulai sudah lewat. Pilih jam lain.' };
    }

    const found = hasConflict(currentStart.value, form.duration_hours, tableBookings.value);

    if (!found) return null;

    return {
        label: found.is_own
            ? 'Anda sudah punya booking pada jam ini.'
            : 'Meja sudah dipesan pada jam ini. Pilih jam lain.',
    };
});

const canSubmit = computed(
    () =>
        step.value === 3 &&
        !!selectedTable.value &&
        !!form.package_id &&
        !!form.start_time &&
        !conflict.value &&
        !form.processing,
);

const firstErrorMessage = computed(() => {
    const errors = Object.values(form.errors || {});
    return errors.length ? errors[0] : '';
});

const fetchTables = async () => {
    message.value = '';
    loadingTables.value = true;

    try {
        const response = await fetch(`${route('customer.tables')}&date=${selectedDate.value}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) throw new Error('request failed');

        const data = await response.json();
        tables.value = data.tables || [];
        step.value = 2;
    } catch (error) {
        message.value = 'Gagal memuat daftar meja. Coba lagi.';
    } finally {
        loadingTables.value = false;
    }
};

const baseStartDate = () => {
    if (selectedDate.value !== props.today) {
        const date = new Date(`${selectedDate.value}T10:00:00`);
        return date;
    }

    const date = new Date();
    date.setSeconds(0, 0);
    date.setMinutes(date.getMinutes() + 30);
    date.setMinutes(Math.ceil(date.getMinutes() / 30) * 30);
    return date;
};

const nextAvailableStart = (bookings) => {
    const base = baseStartDate();
    const duration = Number(form.duration_hours) || 1;

    for (let index = 0; index < 30; index += 1) {
        const candidate = new Date(base.getTime() + index * 30 * 60000);

        if (candidate.toDateString() !== base.toDateString()) break;
        if (candidate.getHours() >= 24) break;

        if (!hasConflict(candidate, duration, bookings)) {
            return `${pad(candidate.getHours())}:${pad(candidate.getMinutes())}`;
        }
    }

    return `${pad(base.getHours())}:${pad(base.getMinutes())}`;
};

const selectTable = async (table) => {
    selectedTable.value = table;
    tableBookings.value = [];
    form.table_id = table.id;
    form.date = selectedDate.value;
    step.value = 3;
    loadingBookings.value = true;
    message.value = '';

    try {
        const response = await fetch(
            `${route('customer.table-bookings', table.id)}&date=${selectedDate.value}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) throw new Error('request failed');

        const data = await response.json();
        tableBookings.value = data.bookings || [];
        form.start_time = nextAvailableStart(tableBookings.value);
    } catch (error) {
        message.value = 'Gagal memuat jadwal meja. Coba lagi.';
    } finally {
        loadingBookings.value = false;
    }
};

const submit = () => {
    if (!canSubmit.value) return;

    form.date = selectedDate.value;
    form.table_id = selectedTable.value.id;

    form.post(route('customer.booking.store'), {
        preserveScroll: true,
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

const bookingStatus = (booking) => {
    if (booking.is_own) return { label: 'Booking Anda', class: 'is-own' };
    if (booking.status === 'active') return { label: 'Berjalan', class: 'is-active' };
    if (booking.status === 'completed') return { label: 'Selesai', class: 'is-completed' };
    return { label: 'Dipesan', class: 'is-booked' };
};

const tableState = (table) => {
    if (table.is_active) return { label: 'Sedang dipakai', class: 'is-active' };
    if (table.bookings_count > 0) return { label: 'Ada jadwal', class: 'is-booked' };

    return { label: 'Kosong', class: 'is-free' };
};
</script>

<template>
    <Head title="Booking Online" />

    <CustomerLayout>
        <div class="bb-cust-steps">
            <div
                v-for="(label, index) in ['Tanggal', 'Meja', 'Jadwal']"
                :key="label"
                class="bb-cust-step"
                :class="{ 'is-done': step > index + 1, 'is-current': step === index + 1 }"
            >
                <span class="bb-cust-step__num">
                    <i v-if="step > index + 1" class="bi bi-check-lg"></i>
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span class="bb-cust-step__label">{{ label }}</span>
            </div>
        </div>

        <!-- Step 1: pilih tanggal -->
        <section v-if="step === 1">
            <div class="bb-cust-card bb-cust-hero">
                <div class="bb-cust-hero__icon">
                    <i class="bi bi-calendar2-heart"></i>
                </div>
                <div>
                    <h4 class="mb-1">Mau main kapan?</h4>
                    <p class="mb-0">Pilih tanggal dulu, lalu lihat meja yang tersedia.</p>
                </div>
            </div>

            <div class="bb-cust-card mt-3">
                <label class="bb-label">Tanggal bermain</label>
                <flatPickr
                    v-model="selectedDate"
                    :config="fpConfig"
                    class="bb-input w-100 mt-1"
                    placeholder="Pilih tanggal bermain"
                />

                <div class="bb-chip-row mt-2">
                    <button
                        type="button"
                        class="bb-chip"
                        :class="{ 'is-active': selectedDate === today }"
                        @click="selectedDate = today"
                    >
                        Hari Ini
                    </button>
                    <button
                        type="button"
                        class="bb-chip"
                        :class="{ 'is-active': selectedDate === tomorrow }"
                        @click="selectedDate = tomorrow"
                    >
                        Besok
                    </button>
                </div>

                <div class="bb-cust-hint mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Booking bisa dilakukan maksimal 30 hari ke depan.
                </div>

                <button
                    type="button"
                    class="bb-btn bb-btn--success w-100 py-3 mt-4"
                    :disabled="loadingTables || !selectedDate"
                    @click="fetchTables"
                >
                    <span v-if="loadingTables" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="bi bi-columns-gap me-1"></i>
                    Lihat Meja
                </button>

                <div v-if="message" class="small text-danger mt-2">{{ message }}</div>
            </div>
        </section>

        <!-- Step 2: pilih meja -->
        <section v-if="step === 2">
            <div class="bb-cust-topline">
                <button type="button" class="bb-cust-back" @click="step = 1">
                    <i class="bi bi-chevron-left"></i> Tanggal
                </button>
                <span class="small text-secondary text-end">{{ dateLabel }}</span>
            </div>

            <div v-if="loadingTables" class="bb-cust-card text-center py-5 text-secondary">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Memuat meja...
            </div>

            <div v-else-if="tables.length === 0" class="bb-cust-card text-center py-5 text-secondary">
                <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem; opacity: 0.4;"></i>
                Belum ada meja. Hubungi admin.
            </div>

            <div v-else class="bb-table-grid">
                <button
                    v-for="table in tables"
                    :key="table.id"
                    type="button"
                    class="bb-table-card-cust"
                    @click="selectTable(table)"
                >
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="text-start">
                            <div class="bb-table-card-cust__name">{{ table.name }}</div>
                            <div class="bb-table-card-cust__meta">
                                {{ table.bookings_count ? `${table.bookings_count} jadwal` : 'Belum ada booking' }}
                            </div>
                        </div>
                        <span class="bb-table-dot" :class="tableState(table).class"></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="bb-table-state" :class="tableState(table).class">
                            {{ tableState(table).label }}
                        </span>
                        <i class="bi bi-chevron-right small"></i>
                    </div>

                    <div v-if="table.next_booking" class="bb-table-card-cust__next">
                        <i class="bi bi-clock me-1"></i>Berikutnya {{ formatTime(table.next_booking) }}
                    </div>
                </button>
            </div>

            <div v-if="message" class="small text-danger mt-2">{{ message }}</div>
        </section>

        <!-- Step 3: detail meja + form -->
        <section v-if="step === 3">
            <div class="bb-cust-topline">
                <button type="button" class="bb-cust-back" @click="step = 2">
                    <i class="bi bi-chevron-left"></i> Meja lain
                </button>
                <span class="small text-secondary text-end">{{ dateLabel }}</span>
            </div>

            <div v-if="message" class="small text-danger mb-2">{{ message }}</div>

            <div class="bb-cust-card">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="bb-cust-eyebrow">Meja dipilih</div>
                        <h4 class="mb-1">{{ selectedTable?.name }}</h4>
                        <div class="small text-secondary">{{ dateLabel }}</div>
                    </div>
                    <span class="bb-table-state" :class="tableState(selectedTable || {}).class">
                        {{ tableState(selectedTable || {}).label }}
                    </span>
                </div>

                <hr class="my-3" style="opacity: 0.1;" />

                <h6 class="fw-bold mb-2">
                    <i class="bi bi-calendar3 me-2 text-info"></i>Jadwal meja ini
                </h6>

                <div v-if="loadingBookings" class="py-3 text-secondary small">
                    <span class="spinner-border spinner-border-sm me-2"></span>Memuat jadwal...
                </div>

                <div v-else-if="tableBookings.length === 0" class="bb-cust-empty">
                    <i class="bi bi-check2-circle"></i>
                    <div>
                        <div class="fw-semibold">Meja kosong</div>
                        <div class="small text-secondary">Belum ada booking di tanggal ini.</div>
                    </div>
                </div>

                <div v-else class="bb-slot-list">
                    <div v-for="booking in tableBookings" :key="booking.id" class="bb-slot-row">
                        <div>
                            <div class="bb-slot-row__time">
                                {{ formatTime(booking.start_time) }} – {{ formatTime(bookingEnd(booking)) }}
                            </div>
                            <div class="bb-slot-row__meta">
                                {{ booking.package_name || 'Biliar' }}
                            </div>
                        </div>
                        <span class="bb-slot-badge" :class="bookingStatus(booking).class">
                            {{ bookingStatus(booking).label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="bb-cust-card mt-3">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-sliders me-2 text-success"></i>Atur jadwal Anda
                </h6>

                <label class="bb-label">Jam mulai</label>
                <div class="d-flex align-items-center gap-2">
                    <flatPickr
                        v-model="form.start_time"
                        :config="timeConfig"
                        class="bb-input flex-grow-1 mt-1"
                        placeholder="Pilih jam mulai"
                    />
                    <div class="bb-time-end">
                        <span class="small text-secondary d-block">Selesai</span>
                        <strong>{{ endTime }}</strong>
                    </div>
                </div>
                <div v-if="form.errors.start_time" class="small text-danger mt-1">
                    {{ form.errors.start_time }}
                </div>

                <label class="bb-label mt-3">Durasi</label>
                <div class="bb-chip-row mt-1">
                    <button
                        v-for="duration in durations"
                        :key="duration"
                        type="button"
                        class="bb-chip"
                        :class="{ 'is-active': Number(form.duration_hours) === duration }"
                        @click="form.duration_hours = duration"
                    >
                        {{ duration }} jam
                    </button>
                </div>
                <div v-if="form.errors.duration_hours" class="small text-danger mt-1">
                    {{ form.errors.duration_hours }}
                </div>

                <label class="bb-label mt-3">Paket</label>
                <div class="bb-package-grid mt-1">
                    <button
                        v-for="pkg in packages"
                        :key="pkg.id"
                        type="button"
                        class="bb-package-card"
                        :class="{ 'is-active': form.package_id === pkg.id }"
                        @click="form.package_id = pkg.id"
                    >
                        <span class="bb-package-card__name">{{ pkg.name }}</span>
                        <span class="bb-package-card__price">
                            {{ formatRupiah(pkg.price) }}<small>/jam</small>
                        </span>
                    </button>
                </div>
                <div v-if="form.errors.package_id" class="small text-danger mt-1">
                    {{ form.errors.package_id }}
                </div>

                <div v-if="conflict" class="bb-cust-warning mt-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>{{ conflict.label }}</span>
                </div>

                <div v-if="firstErrorMessage && !conflict" class="small text-danger mt-2">
                    {{ firstErrorMessage }}
                </div>

                <div class="bb-cust-total mt-3">
                    <div>
                        <div class="small text-secondary">Total bayar di kasir</div>
                        <strong>{{ formatRupiah(total) }}</strong>
                    </div>
                    <div class="text-end small text-secondary">
                        {{ form.duration_hours }} jam × {{ formatRupiah(selectedPackage?.price) }}
                    </div>
                </div>

                <button
                    type="button"
                    class="bb-btn bb-btn--success w-100 py-3 mt-3"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="bi bi-calendar2-check me-1"></i>
                    Konfirmasi Booking
                </button>
            </div>
        </section>
    </CustomerLayout>
</template>

<style scoped>
.bb-cust-steps {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.9rem;
}

.bb-cust-step {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    color: var(--bs-secondary-color);
    font-size: 0.72rem;
    font-weight: 700;
}

.bb-cust-step__num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    font-size: 0.72rem;
    flex-shrink: 0;
}

.bb-cust-step.is-current {
    color: #06b6d4;
}

.bb-cust-step.is-current .bb-cust-step__num {
    background: rgba(6, 182, 212, 0.14);
    border-color: rgba(6, 182, 212, 0.5);
    color: #06b6d4;
}

.bb-cust-step.is-done {
    color: var(--bs-body-color);
}

.bb-cust-step.is-done .bb-cust-step__num {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.4);
    color: #10b981;
}

.bb-cust-card {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 1rem;
    padding: 1rem;
}

.bb-cust-hero {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.16), rgba(14, 116, 144, 0.04));
    border-color: rgba(6, 182, 212, 0.22);
}

.bb-cust-hero h4 {
    font-size: 1.15rem;
    font-weight: 800;
}

.bb-cust-hero p {
    font-size: 0.82rem;
    color: var(--bs-secondary-color);
}

.bb-cust-hero__icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(6, 182, 212, 0.16);
    color: #06b6d4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.bb-cust-hint {
    font-size: 0.78rem;
    color: var(--bs-secondary-color);
}

.bb-cust-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.bb-cust-back {
    border: 0;
    background: transparent;
    color: var(--bs-body-color);
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.2rem 0;
}

.bb-cust-back i {
    color: #06b6d4;
}

.bb-table-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.bb-table-card-cust {
    text-align: left;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
    border-radius: 0.9rem;
    padding: 0.85rem;
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.bb-table-card-cust:active {
    transform: scale(0.98);
}

.bb-table-card-cust:hover {
    border-color: rgba(6, 182, 212, 0.5);
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.16);
}

.bb-table-card-cust__name {
    font-weight: 800;
    font-size: 0.98rem;
}

.bb-table-card-cust__meta {
    font-size: 0.72rem;
    color: var(--bs-secondary-color);
}

.bb-table-card-cust__next {
    margin-top: 0.45rem;
    font-size: 0.7rem;
    color: #06b6d4;
}

.bb-table-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
}

.bb-table-dot.is-booked {
    background: #f59e0b;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.14);
}

.bb-table-dot.is-active {
    background: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
}

.bb-table-state {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.18rem 0.55rem;
    border-radius: 100px;
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.bb-table-state.is-booked {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
}

.bb-table-state.is-active {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.bb-cust-empty {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.9rem;
    border-radius: 0.8rem;
    background: rgba(16, 185, 129, 0.06);
    border: 1px dashed rgba(16, 185, 129, 0.3);
}

.bb-cust-empty > i {
    color: #10b981;
    font-size: 1.3rem;
}

.bb-slot-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.bb-slot-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.7rem;
    border-radius: 0.75rem;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
}

.bb-slot-row__time {
    font-weight: 800;
    font-size: 0.86rem;
}

.bb-slot-row__meta {
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
}

.bb-slot-badge {
    padding: 0.18rem 0.55rem;
    border-radius: 100px;
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
}

.bb-slot-badge.is-active {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.bb-slot-badge.is-completed {
    background: rgba(108, 117, 125, 0.14);
    color: #94a3b8;
}

.bb-slot-badge.is-own {
    background: rgba(6, 182, 212, 0.14);
    color: #06b6d4;
}

.bb-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.bb-chip {
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-radius: 100px;
    padding: 0.35rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.bb-chip.is-active {
    background: rgba(6, 182, 212, 0.14);
    border-color: rgba(6, 182, 212, 0.5);
    color: #06b6d4;
}

.bb-package-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.bb-package-card {
    text-align: left;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-radius: 0.8rem;
    padding: 0.65rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.bb-package-card.is-active {
    border-color: rgba(16, 185, 129, 0.55);
    background: rgba(16, 185, 129, 0.1);
}

.bb-package-card__name {
    font-weight: 800;
    font-size: 0.82rem;
}

.bb-package-card__price {
    font-size: 0.75rem;
    color: #10b981;
    font-weight: 700;
}

.bb-package-card__price small {
    font-weight: 500;
    color: var(--bs-secondary-color);
}

.bb-time-end {
    min-width: 82px;
    text-align: center;
    padding: 0.35rem 0.5rem;
    border-radius: 0.65rem;
    background: rgba(6, 182, 212, 0.08);
    border: 1px solid rgba(6, 182, 212, 0.18);
}

.bb-cust-warning {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.25);
    color: #f59e0b;
    font-size: 0.78rem;
    font-weight: 600;
}

.bb-cust-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.8rem 0.9rem;
    border-radius: 0.8rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.bb-cust-total strong {
    font-size: 1.25rem;
    color: #10b981;
}

.bb-cust-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #06b6d4;
}

@media (min-width: 576px) {
    .bb-table-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 900px) {
    .bb-table-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .bb-cust-card {
        padding: 1.25rem;
    }
}
</style>
