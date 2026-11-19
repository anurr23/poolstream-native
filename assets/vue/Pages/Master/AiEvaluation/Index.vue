<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@/shims/inertia.js';
import { computed, ref } from 'vue';
import flatPickr from 'vue-flatpickr-component';

const props = defineProps({
    presets: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    defaultSections: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const fpConfig = computed(() => ({
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'd M Y',
    maxDate: props.today || undefined,
}));

const form = useForm({
    preset: 'month',
    start_date: '',
    end_date: '',
    sections: [...props.defaultSections],
    include_sample: false,
});

const customRange = ref(false);
const showAllSections = ref(false);

const selectPreset = (key) => {
    form.preset = key;
    customRange.value = key === 'custom';
};

const toggleSection = (key) => {
    if (form.sections.includes(key)) {
        form.sections = form.sections.filter((item) => item !== key);
    } else {
        form.sections = [...form.sections, key];
    }
};

const visibleSections = computed(() =>
    showAllSections.value ? props.sections : props.sections.slice(0, 4)
);

const submit = () => {
    form.post(route('ai-evaluations.store'), { preserveScroll: true });
};

const statusMeta = (status) => {
    const map = {
        completed: { label: 'Selesai', class: 'is-done', icon: 'bi-check-circle-fill' },
        running: { label: 'Diproses', class: 'is-running', icon: 'bi-hourglass-split' },
        failed: { label: 'Gagal', class: 'is-failed', icon: 'bi-exclamation-triangle-fill' },
    };
    return map[status] || { label: status, class: '', icon: 'bi-dot' };
};

const formatDateShort = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Evaluasi AI" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title">
                <i class="bi bi-stars me-2" style="color: #a855f7;"></i>Evaluasi AI
            </h1>
        </template>

        <!-- Highlight hero: fitur andalan -->
        <div class="bb-ai-hero">
            <div class="bb-ai-hero__orb bb-ai-hero__orb--1"></div>
            <div class="bb-ai-hero__orb bb-ai-hero__orb--2"></div>
            <div class="bb-ai-hero__content">
                <span class="bb-ai-hero__badge"><i class="bi bi-stars"></i> Fitur Andalan</span>
                <h2 class="bb-ai-hero__title">Analisa semua laporan dengan AI</h2>
                <p class="bb-ai-hero__sub">
                    Pilih periode, centang data yang ingin dievaluasi, lalu biarkan AI menyusun temuan dan rekomendasi.
                </p>
                <div class="bb-ai-hero__meta">
                    <span><i class="bi bi-shield-lock"></i> Analisa internal, data agregat</span>
                    <span><i class="bi bi-graph-up-arrow"></i> Temuan, rekomendasi, dan kesimpulan</span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="bb-card h-100">
                    <div class="bb-card-header">
                        <h6 class="fw-bold mb-0"><i class="bi bi-sliders me-2"></i>Atur Evaluasi</h6>
                    </div>
                    <div class="bb-card-body">
                        <label class="bb-label">Rentang waktu</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button
                                v-for="preset in presets"
                                :key="preset.key"
                                type="button"
                                class="bb-btn"
                                :class="form.preset === preset.key ? 'bb-btn--primary' : 'bb-btn--ghost'"
                                style="font-size: 0.78rem; text-transform: none; border-radius: 100px; padding: 6px 14px;"
                                @click="selectPreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>

                        <div v-if="customRange" class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="bb-label">Dari tanggal</label>
                                <flat-pickr v-model="form.start_date" :config="fpConfig" class="form-control" placeholder="Pilih tanggal" />
                                <div v-if="form.errors.start_date" class="text-danger small mt-1">{{ form.errors.start_date }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="bb-label">Sampai tanggal</label>
                                <flat-pickr v-model="form.end_date" :config="fpConfig" class="form-control" placeholder="Pilih tanggal" />
                                <div v-if="form.errors.end_date" class="text-danger small mt-1">{{ form.errors.end_date }}</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="bb-label mb-0">Data yang dievaluasi</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.75rem;" @click="showAllSections = !showAllSections">
                                {{ showAllSections ? 'Sembunyikan seksi lanjutan' : 'Tampilkan seksi lanjutan' }}
                            </button>
                        </div>
                        <div class="bb-ai-sections">
                            <label
                                v-for="section in visibleSections"
                                :key="section.key"
                                class="bb-ai-section"
                                :class="{ 'is-active': form.sections.includes(section.key) }"
                            >
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="form.sections.includes(section.key)"
                                    @change="toggleSection(section.key)"
                                />
                                <span>{{ section.label }}</span>
                            </label>
                        </div>
                        <div v-if="form.errors.sections" class="text-danger small mt-1">{{ form.errors.sections }}</div>

                        <div class="form-check form-switch mt-3">
                            <input id="include_sample" v-model="form.include_sample" class="form-check-input" type="checkbox" />
                            <label class="form-check-label small" for="include_sample">
                                Sertakan sampel 50 transaksi terakhir (nama pelanggan disamarkan)
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                            <button class="bb-btn bb-btn--primary" :disabled="!form.sections.length" @click="submit">
                                <i class="bi bi-stars me-2"></i>Buat Evaluasi
                            </button>
                            <span class="small text-secondary">
                                <i class="bi bi-info-circle me-1"></i>Analisa berjalan di latar belakang, halaman tetap bisa dipakai.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="bb-card h-100">
                    <div class="bb-card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Riwayat Evaluasi</h6>
                        <span class="small text-secondary">{{ history.length }}</span>
                    </div>
                    <div class="bb-card-body">
                        <div v-if="!history.length" class="bb-ai-empty">
                            <i class="bi bi-stars"></i>
                            <div>
                                <div class="fw-semibold">Belum ada evaluasi</div>
                                <div class="small text-secondary">Buat evaluasi pertamamu di sebelah kiri.</div>
                            </div>
                        </div>

                        <div v-else class="bb-ai-history">
                            <Link
                                v-for="item in history"
                                :key="item.id"
                                :href="route('ai-evaluations.show', item.id)"
                                class="bb-ai-history__item"
                            >
                                <div style="min-width: 0;">
                                    <div class="bb-ai-history__period">
                                        {{ formatDateShort(item.start_date) }} â€” {{ formatDateShort(item.end_date) }}
                                    </div>
                                    <div class="bb-ai-history__meta">
                                        dibuat {{ formatDateShort(item.created_at) }}
                                    </div>
                                </div>
                                <span class="bb-ai-status" :class="statusMeta(item.status).class">
                                    <i class="bi" :class="statusMeta(item.status).icon"></i>
                                    {{ statusMeta(item.status).label }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.bb-ai-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.2rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    color: #fff;
    background: linear-gradient(135deg, #6d28d9, #4f46e5 55%, #0e7490);
    box-shadow: 0 18px 40px rgba(79, 70, 229, 0.28);
}

.bb-ai-hero__orb {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    pointer-events: none;
}

.bb-ai-hero__orb--1 {
    width: 210px;
    height: 210px;
    top: -80px;
    right: -50px;
}

.bb-ai-hero__orb--2 {
    width: 130px;
    height: 130px;
    bottom: -60px;
    right: 90px;
}

.bb-ai-hero__content {
    position: relative;
    z-index: 1;
}

.bb-ai-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.3rem 0.7rem;
    border-radius: 100px;
    background: rgba(255, 255, 255, 0.18);
}

.bb-ai-hero__title {
    font-size: 1.45rem;
    font-weight: 800;
    margin: 0.7rem 0 0.35rem;
}

.bb-ai-hero__sub {
    font-size: 0.86rem;
    opacity: 0.9;
    max-width: 44rem;
    margin-bottom: 0.8rem;
}

.bb-ai-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 1rem;
    font-size: 0.74rem;
    opacity: 0.9;
}

.bb-ai-sections {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 0.5rem;
}

.bb-ai-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.7rem;
    border-radius: 0.7rem;
    border: 1px solid var(--bs-border-color-translucent);
    background: var(--bs-tertiary-bg);
    font-size: 0.8rem;
    cursor: pointer;
}

.bb-ai-section.is-active {
    border-color: rgba(139, 92, 246, 0.55);
    background: rgba(139, 92, 246, 0.1);
}

.bb-ai-section input {
    flex-shrink: 0;
}

.bb-ai-empty {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem;
    border-radius: 0.9rem;
    border: 1px dashed rgba(139, 92, 246, 0.4);
    background: rgba(139, 92, 246, 0.07);
}

.bb-ai-empty > i {
    font-size: 1.4rem;
    color: #8b5cf6;
}

.bb-ai-history {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    max-height: 26rem;
    overflow-y: auto;
}

.bb-ai-history__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
    border: 1px solid var(--bs-border-color-translucent);
    background: var(--bs-tertiary-bg);
    text-decoration: none;
    color: var(--bs-body-color);
}

.bb-ai-history__item:hover {
    border-color: rgba(139, 92, 246, 0.5);
}

.bb-ai-history__period {
    font-weight: 700;
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bb-ai-history__meta {
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
}

.bb-ai-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 100px;
    white-space: nowrap;
    background: rgba(148, 163, 184, 0.16);
    color: #94a3b8;
}

.bb-ai-status.is-done {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
}

.bb-ai-status.is-running {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
}

.bb-ai-status.is-failed {
    background: rgba(239, 68, 68, 0.14);
    color: #ef4444;
}
</style>
