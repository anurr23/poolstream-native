<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import logoUrl from '@/Assets/logo.svg';
import { Head, Link, router, useForm } from '@/shims/inertia.js';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    evaluation: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
});

const result = computed(() => props.evaluation.result || {});
const meta = computed(() => props.evaluation.meta || {});
const isDone = computed(() => props.evaluation.status === 'completed');
const isRunning = computed(() => props.evaluation.status === 'running');

const sectionLabel = (key) => props.sections.find((item) => item.key === key)?.label || key;

const impactMeta = (impact) => {
    const map = {
        tinggi: { class: 'is-high' },
        sedang: { class: 'is-medium' },
        rendah: { class: 'is-low' },
    };
    return map[impact] || map.rendah;
};

const formatDateLong = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const formatDateShort = (value) => {
    if (!value) return '';
    const date = new Date(value);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${date.getFullYear()}${month}${day}`;
};

const pdfFilename = computed(
    () => `Evaluasi-AI-PoolStream_${formatDateShort(props.evaluation.start_date)}-${formatDateShort(props.evaluation.end_date)}.pdf`
);

const trendIcon = (trend) => {
    if (trend === 'naik') return 'bi-arrow-up-right';
    if (trend === 'turun') return 'bi-arrow-down-right';
    return 'bi-arrow-right';
};

// --- Animasi: bagian jawaban muncul bertahap dengan efek mengetik pada ringkasan ---
const blocks = computed(() => {
    const list = [];
    if (result.value.ringkasan) list.push('ringkasan');
    if (result.value.metrik_kunci?.length) list.push('metrik');
    if (result.value.analisis?.length) list.push('analisis');
    if (result.value.temuan?.length) list.push('temuan');
    if (result.value.rekomendasi?.length) list.push('rekomendasi');
    if (result.value.risiko?.length) list.push('risiko');
    if (result.value.kesimpulan) list.push('kesimpulan');
    return list;
});

const revealed = ref(0);
const typedText = ref('');
let revealTimer = null;
let typeTimer = null;

const clearTimers = () => {
    if (revealTimer) {
        clearInterval(revealTimer);
        revealTimer = null;
    }
    if (typeTimer) {
        clearInterval(typeTimer);
        typeTimer = null;
    }
};

const typeRingkasan = () => {
    const full = String(result.value.ringkasan || '');
    typedText.value = '';

    if (!full) return;

    let index = 0;
    typeTimer = setInterval(() => {
        index = Math.min(full.length, index + 4);
        typedText.value = full.slice(0, index);

        if (index >= full.length) {
            clearInterval(typeTimer);
            typeTimer = null;
        }
    }, 16);
};

const startReveal = () => {
    clearTimers();
    revealed.value = 0;
    typedText.value = '';

    if (!isDone.value || blocks.value.length === 0) return;

    revealed.value = 1;

    if (blocks.value[0] === 'ringkasan') {
        typeRingkasan();
    }

    revealTimer = setInterval(() => {
        if (revealed.value >= blocks.value.length) {
            clearInterval(revealTimer);
            revealTimer = null;
            return;
        }

        revealed.value += 1;
    }, 700);
};

const isShown = (key) => {
    const index = blocks.value.indexOf(key);
    return index > -1 && index < revealed.value;
};

const typing = computed(() => isDone.value && revealed.value > 0 && revealed.value < blocks.value.length);

// --- Polling ringan selama AI memproses ---
let pollTimer = null;

const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const refreshEvaluation = async () => {
    try {
        const response = await fetch(`${route('api.ai-evaluation.show')}&id=${props.evaluation.id}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const json = await response.json();
        if (json?.evaluation) Object.assign(props.evaluation, json.evaluation);
    } catch (error) {
        // Polling berikutnya akan mencoba lagi.
    }
};

const startPolling = () => {
    stopPolling();

    if (!isRunning.value) return;

    pollTimer = setInterval(refreshEvaluation, 3000);
};

onMounted(() => {
    startReveal();
    startPolling();
});

onUnmounted(() => {
    clearTimers();
    stopPolling();
});

watch(
    () => props.evaluation.status,
    (status) => {
        if (status !== 'running') {
            stopPolling();
        }

        if (status === 'completed') {
            startReveal();
        }
    }
);

// --- Aksi ---
const retryForm = useForm({
    preset: props.evaluation.preset || 'month',
    start_date: props.evaluation.start_date,
    end_date: props.evaluation.end_date,
    sections: props.evaluation.sections || [],
    include_sample: !!meta.value.sertakan_sampel,
});

const rerun = () => retryForm.post(route('ai-evaluations.store'), { preserveScroll: true });

const downloading = ref(false);

// Unduh lewat fetch + blob supaya tidak memicu beforeunload (block UI global bisa nyangkut).
const downloadPdf = async () => {
    if (downloading.value) return;

    downloading.value = true;

    try {
        const response = await fetch(route('ai-evaluations.pdf', props.evaluation.id), {
            credentials: 'same-origin',
            headers: { Accept: 'application/pdf' },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = pdfFilename.value;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    } catch (error) {
        alert(`Gagal mengunduh PDF: ${error.message}`);
    } finally {
        downloading.value = false;
    }
};

const remove = () => {
    if (!confirm('Hapus evaluasi ini?')) return;
    router.delete(route('ai-evaluations.destroy', props.evaluation.id));
};
</script>

<template>
    <Head title="Hasil Evaluasi AI" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title">
                <i class="bi bi-stars me-2" style="color: #a855f7;"></i>Evaluasi AI
            </h1>
        </template>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex flex-wrap gap-2">
                <span class="bb-ai-chip">{{ evaluation.preset_label }}</span>
                <span class="bb-ai-chip">
                    {{ formatDateLong(evaluation.start_date) }} &ndash; {{ formatDateLong(evaluation.end_date) }}
                </span>
                <span class="bb-ai-chip"><i class="bi bi-shield-lock"></i> Analisa internal</span>
            </div>
            <div class="d-flex gap-2">
                <button class="bb-btn bb-btn--ghost bb-btn--sm" :disabled="retryForm.processing" @click="rerun">
                    <i class="bi bi-arrow-repeat"></i> {{ retryForm.processing ? 'Memproses...' : 'Jalankan ulang' }}
                </button>
                <button class="bb-btn bb-btn--ghost bb-btn--sm" @click="remove">
                    <i class="bi bi-trash"></i> Hapus
                </button>
                <Link :href="route('ai-evaluations.index')" class="bb-btn bb-btn--ghost bb-btn--sm">
                    <i class="bi bi-plus-lg"></i> Baru
                </Link>
            </div>
        </div>

        <div v-if="isRunning" class="bb-ai-running mb-3">
            <span class="bb-ai-running__spinner"></span>
            <div>
                <div class="fw-bold" style="font-size: 0.9rem;">AI sedang menganalisa...</div>
                <div class="small text-secondary">
                    Data periode sudah dikirim ke sistem AI. Halaman ini akan memperbarui sendiri saat selesai.
                </div>
            </div>
        </div>

        <div v-if="evaluation.status === 'failed'" class="bb-ai-failed mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <div class="fw-bold mb-1">Evaluasi gagal diproses</div>
                <div class="small text-secondary">{{ evaluation.error || 'Terjadi kesalahan yang tidak diketahui.' }}</div>
                <div class="small text-secondary mt-2">
                    Periksa apakah layanan AI lokal sedang aktif, lalu jalankan ulang evaluasi.
                </div>
            </div>
        </div>

        <!-- Jawaban AI full width -->
        <article v-if="isDone" class="bb-ai-answer">
            <div class="bb-ai-answer__top">
                <span class="bb-ai-answer__avatar"><img :src="logoUrl" alt="PoolStream AI"></span>
                <div>
                    <div class="bb-ai-answer__who">PoolStream AI</div>
                    <div class="bb-ai-answer__when">
                        {{ evaluation.created_at }}
                        <template v-if="evaluation.created_by"> &middot; diminta oleh {{ evaluation.created_by }}</template>
                        &middot; {{ evaluation.sections?.length || 0 }} bagian data
                    </div>
                </div>
            </div>

            <template v-if="isShown('ringkasan')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #7c3aed; background: rgba(124, 58, 237, 0.12);"><i class="bi bi-clipboard-data"></i></span>
                    Ringkasan
                </h2>
                <div class="bb-ai-lead">
                    <p class="bb-ai-p bb-ai-p--lead">
                        {{ typedText }}<span v-if="typedText.length < (result.ringkasan || '').length" class="bb-ai-caret"></span>
                    </p>
                </div>
            </template>

            <template v-if="isShown('metrik')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #0891b2; background: rgba(8, 145, 178, 0.12);"><i class="bi bi-speedometer2"></i></span>
                    Metrik kunci
                </h2>
                <div class="bb-ai-metrics">
                    <div v-for="(metric, index) in result.metrik_kunci" :key="index" class="bb-ai-metric">
                        <div class="bb-ai-metric__label">{{ metric.label }}</div>
                        <div class="bb-ai-metric__value">
                            {{ metric.nilai }}
                            <span class="bb-ai-trend" :class="'is-' + (metric.tren || 'stabil')">
                                <i class="bi" :class="trendIcon(metric.tren)"></i> {{ metric.tren }}
                            </span>
                        </div>
                        <div v-if="metric.catatan" class="bb-ai-metric__note">{{ metric.catatan }}</div>
                    </div>
                </div>
            </template>

            <template v-if="isShown('analisis')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #4f46e5; background: rgba(79, 70, 229, 0.12);"><i class="bi bi-graph-up-arrow"></i></span>
                    Analisis mendalam
                </h2>
                <div class="bb-ai-analysis">
                    <div v-for="(item, index) in result.analisis" :key="index" class="bb-ai-analysis__item">
                        <div class="bb-ai-analysis__aspect">{{ item.aspek }}</div>
                        <p class="bb-ai-p">{{ item.paragraf }}</p>
                    </div>
                </div>
            </template>

            <template v-if="isShown('temuan')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #dc2626; background: rgba(220, 38, 38, 0.1);"><i class="bi bi-search"></i></span>
                    Temuan
                </h2>
                <div class="bb-ai-findings">
                    <div
                        v-for="(finding, index) in result.temuan"
                        :key="index"
                        class="bb-ai-finding"
                        :class="impactMeta(finding.dampak).class"
                    >
                        <div class="bb-ai-finding__head">
                            <span class="bb-ai-finding__title">{{ finding.judul }}</span>
                            <span class="bb-ai-impact" :class="impactMeta(finding.dampak).class">{{ finding.dampak }}</span>
                        </div>
                        <p class="bb-ai-p">{{ finding.detail }}</p>
                        <p v-if="finding.implikasi" class="bb-ai-implication">
                            <i class="bi bi-arrow-return-right me-1"></i>{{ finding.implikasi }}
                        </p>
                        <div v-if="finding.bukti" class="bb-ai-evidence">
                            <i class="bi bi-bar-chart-line me-1"></i>{{ finding.bukti }}
                        </div>
                    </div>
                </div>
            </template>

            <template v-if="isShown('rekomendasi')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #059669; background: rgba(5, 150, 105, 0.12);"><i class="bi bi-lightbulb"></i></span>
                    Rekomendasi
                </h2>
                <ol class="bb-ai-recos">
                    <li v-for="(reco, index) in result.rekomendasi" :key="index" class="bb-ai-reco">
                        <span class="bb-ai-reco__num">{{ index + 1 }}</span>
                        <div class="bb-ai-reco__body">
                            <div class="bb-ai-reco__action">{{ reco.aksi }}</div>
                            <div class="bb-ai-reco__reason">{{ reco.alasan }}</div>
                            <ul v-if="reco.langkah?.length" class="bb-ai-reco__steps">
                                <li v-for="(step, stepIndex) in reco.langkah" :key="stepIndex">{{ step }}</li>
                            </ul>
                            <div v-if="reco.dampak_diharapkan" class="bb-ai-reco__impact">
                                <i class="bi bi-bullseye me-1"></i>{{ reco.dampak_diharapkan }}
                            </div>
                        </div>
                    </li>
                </ol>
            </template>

            <template v-if="isShown('risiko')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #d97706; background: rgba(217, 119, 6, 0.12);"><i class="bi bi-shield-exclamation"></i></span>
                    Risiko
                </h2>
                <ul class="bb-ai-risks">
                    <li v-for="(risk, index) in result.risiko" :key="index" class="bb-ai-risk">
                        <div class="bb-ai-risk__title">{{ risk.risiko }}</div>
                        <div class="bb-ai-risk__note">{{ risk.indikasi }}</div>
                        <div v-if="risk.mitigasi" class="bb-ai-risk__mitigation">
                            <i class="bi bi-shield-check me-1"></i>{{ risk.mitigasi }}
                        </div>
                    </li>
                </ul>
            </template>

            <template v-if="isShown('kesimpulan')">
                <h2 class="bb-ai-h">
                    <span class="bb-ai-h__icon" style="color: #0e7490; background: rgba(14, 116, 144, 0.12);"><i class="bi bi-flag"></i></span>
                    Kesimpulan
                </h2>
                <div class="bb-ai-conclusion">{{ result.kesimpulan }}</div>
            </template>

            <div v-if="typing" class="bb-ai-typing">
                <span class="bb-ai-dots"><i></i><i></i><i></i></span>
            </div>

            <!-- Lampiran PDF di akhir jawaban -->
            <button
                v-if="!typing"
                type="button"
                class="bb-ai-attach"
                :disabled="downloading"
                @click="downloadPdf"
            >
                <span class="bb-ai-attach__icon">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </span>
                <span class="bb-ai-attach__body">
                    <span class="bb-ai-attach__name">{{ pdfFilename }}</span>
                    <span class="bb-ai-attach__desc">
                        Laporan lengkap &middot; {{ evaluation.sections?.length || 0 }} bagian data &middot; PDF
                    </span>
                </span>
                <span class="bb-ai-attach__action">
                    <span v-if="downloading" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <i v-else class="bi bi-download"></i>
                </span>
            </button>
        </article>
    </AuthenticatedLayout>
</template>

<style scoped>
.bb-ai-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.3rem 0.65rem;
    border-radius: 100px;
    border: 1px solid var(--bs-border-color-translucent);
    background: var(--bs-tertiary-bg);
}

.bb-ai-running {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.9rem 1rem;
    border-radius: 0.75rem;
    border: 1px dashed rgba(139, 92, 246, 0.45);
    background: rgba(139, 92, 246, 0.07);
}

.bb-ai-running__spinner {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 3px solid rgba(139, 92, 246, 0.25);
    border-top-color: #8b5cf6;
    animation: bb-ai-spin 0.8s linear infinite;
}

@keyframes bb-ai-spin {
    to {
        transform: rotate(360deg);
    }
}

.bb-ai-failed {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(245, 158, 11, 0.35);
    background: rgba(245, 158, 11, 0.07);
}

.bb-ai-failed > i {
    color: #f59e0b;
    font-size: 1.1rem;
    margin-top: 2px;
}

/* --- Jawaban AI --- */
.bb-ai-answer {
    position: relative;
    max-width: 1180px;
    margin: 0 auto;
    padding: 1.4rem 1.75rem 1.6rem;
    border-radius: 1rem;
    background:
        radial-gradient(120% 70% at 0% 0%, rgba(139, 92, 246, 0.07), transparent 55%),
        radial-gradient(90% 60% at 100% 0%, rgba(6, 182, 212, 0.06), transparent 60%),
        var(--bs-body-bg);
    border: 1px solid var(--bs-border-color-translucent);
    font-size: 0.95rem;
    line-height: 1.75;
    color: var(--bs-body-color);
}

.bb-ai-answer__top {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-bottom: 0.9rem;
    margin-bottom: 0.9rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}

.bb-ai-answer__avatar {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ede9fe, #cffafe);
    border: 1px solid rgba(139, 92, 246, 0.35);
}

.bb-ai-answer__avatar img {
    width: 24px;
    height: 24px;
}

.bb-ai-answer__who {
    font-weight: 800;
    font-size: 0.9rem;
    letter-spacing: -0.01em;
}

.bb-ai-answer__when {
    font-size: 0.72rem;
    color: var(--bs-secondary-color);
}

/* --- Headings --- */
.bb-ai-h {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.02rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    margin: 1.7rem 0 0.7rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}

.bb-ai-h__icon {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
}

.bb-ai-p {
    margin: 0 0 0.45rem;
}

.bb-ai-lead {
    padding-left: 0.9rem;
    border-left: 3px solid rgba(139, 92, 246, 0.55);
}

.bb-ai-p--lead {
    font-size: 1rem;
    line-height: 1.8;
}

.bb-ai-caret {
    display: inline-block;
    width: 2px;
    height: 0.95em;
    margin-left: 2px;
    background: currentColor;
    vertical-align: text-bottom;
    animation: bb-caret 1s steps(2) infinite;
}

@keyframes bb-caret {
    50% {
        opacity: 0;
    }
}

/* --- Metrik --- */
.bb-ai-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 0.6rem;
}

.bb-ai-metric {
    padding: 0.6rem 0.75rem;
    border-radius: 0.7rem;
    border: 1px solid var(--bs-border-color-translucent);
    background: linear-gradient(150deg, rgba(139, 92, 246, 0.06), rgba(6, 182, 212, 0.03));
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.bb-ai-metric:hover {
    transform: translateY(-1px);
    border-color: rgba(139, 92, 246, 0.4);
}

.bb-ai-metric__label {
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--bs-secondary-color);
}

.bb-ai-metric__value {
    font-size: 1.05rem;
    font-weight: 800;
    margin: 0.1rem 0;
}

.bb-ai-metric__note {
    font-size: 0.78rem;
    color: var(--bs-secondary-color);
    line-height: 1.55;
}

.bb-ai-trend {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: capitalize;
    padding: 0.1rem 0.45rem;
    border-radius: 100px;
    margin-left: 0.3rem;
    vertical-align: middle;
}

.bb-ai-trend.is-naik {
    color: #047857;
    background: rgba(16, 185, 129, 0.15);
}

.bb-ai-trend.is-turun {
    color: #b91c1c;
    background: rgba(239, 68, 68, 0.13);
}

.bb-ai-trend.is-stabil {
    color: #475569;
    background: rgba(148, 163, 184, 0.18);
}

/* --- Analisis --- */
.bb-ai-analysis {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.bb-ai-analysis__aspect {
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6d28d9;
    margin-bottom: 0.15rem;
}

/* --- Temuan --- */
.bb-ai-findings {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.bb-ai-finding {
    padding: 0.55rem 0.85rem;
    border-left: 3px solid var(--bs-border-color);
    border-radius: 0 0.5rem 0.5rem 0;
    transition: background 0.15s ease;
}

.bb-ai-finding.is-high {
    border-left-color: #ef4444;
}

.bb-ai-finding.is-medium {
    border-left-color: #f59e0b;
}

.bb-ai-finding.is-low {
    border-left-color: #10b981;
}

.bb-ai-finding:hover {
    background: rgba(148, 163, 184, 0.07);
}

.bb-ai-finding__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.bb-ai-finding__title {
    font-weight: 700;
}

.bb-ai-impact {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.12rem 0.45rem;
    border-radius: 100px;
    white-space: nowrap;
}

.bb-ai-impact.is-high {
    color: #b91c1c;
    background: rgba(239, 68, 68, 0.12);
}

.bb-ai-impact.is-medium {
    color: #b45309;
    background: rgba(245, 158, 11, 0.14);
}

.bb-ai-impact.is-low {
    color: #047857;
    background: rgba(16, 185, 129, 0.14);
}

.bb-ai-implication {
    margin: 0 0 0.3rem;
    font-size: 0.88rem;
    color: var(--bs-body-color);
    opacity: 0.9;
}

.bb-ai-evidence {
    font-size: 0.76rem;
    color: var(--bs-secondary-color);
}

/* --- Rekomendasi --- */
.bb-ai-recos {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    counter-reset: none;
}

.bb-ai-reco {
    display: flex;
    gap: 0.7rem;
    align-items: flex-start;
}

.bb-ai-reco__num {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, #10b981, #0891b2);
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
}

.bb-ai-reco__body {
    min-width: 0;
}

.bb-ai-reco__action {
    font-weight: 700;
}

.bb-ai-reco__reason {
    font-size: 0.88rem;
    color: var(--bs-secondary-color);
}

.bb-ai-reco__steps {
    margin: 0.35rem 0 0;
    padding-left: 1.1rem;
    font-size: 0.86rem;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.bb-ai-reco__impact {
    margin-top: 0.3rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #047857;
}

/* --- Risiko --- */
.bb-ai-risks {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.bb-ai-risk {
    padding: 0.55rem 0.8rem;
    border-radius: 0.5rem;
    background: rgba(245, 158, 11, 0.07);
    border-left: 3px solid rgba(245, 158, 11, 0.6);
}

.bb-ai-risk__title {
    font-weight: 700;
    color: #92400e;
}

.bb-ai-risk__note {
    font-size: 0.86rem;
    color: var(--bs-secondary-color);
}

.bb-ai-risk__mitigation {
    margin-top: 0.2rem;
    font-size: 0.84rem;
    color: #047857;
}

/* --- Kesimpulan --- */
.bb-ai-conclusion {
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    background: linear-gradient(120deg, rgba(139, 92, 246, 0.12), rgba(6, 182, 212, 0.08));
    font-size: 0.94rem;
    line-height: 1.75;
}

/* --- Mengetik --- */
.bb-ai-typing {
    margin-top: 1rem;
}

.bb-ai-dots {
    display: inline-flex;
    gap: 3px;
    align-items: center;
    height: 1rem;
}

.bb-ai-dots i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #a78bfa;
    animation: bb-dot 1.2s infinite ease-in-out;
}

.bb-ai-dots i:nth-child(2) {
    animation-delay: 0.15s;
}

.bb-ai-dots i:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes bb-dot {
    0%,
    80%,
    100% {
        opacity: 0.25;
        transform: translateY(0);
    }
    40% {
        opacity: 1;
        transform: translateY(-3px);
    }
}

/* --- Lampiran PDF --- */
.bb-ai-attach {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding: 0.7rem 0.9rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    background: var(--bs-body-bg);
    text-decoration: none;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
    max-width: 460px;
    transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
}

.bb-ai-attach:disabled {
    cursor: progress;
    opacity: 0.75;
}

.bb-ai-attach:hover {
    border-color: rgba(220, 38, 38, 0.5);
    background: rgba(220, 38, 38, 0.04);
    color: inherit;
    transform: translateY(-1px);
}

.bb-ai-attach__icon {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #dc2626;
    background: rgba(220, 38, 38, 0.1);
}

.bb-ai-attach__body {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.bb-ai-attach__name {
    font-size: 0.84rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bb-ai-attach__desc {
    font-size: 0.72rem;
    color: var(--bs-secondary-color);
}

.bb-ai-attach__action {
    color: var(--bs-secondary-color);
    font-size: 1rem;
}

</style>
