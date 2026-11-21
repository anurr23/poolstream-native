<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, router, useForm, usePage } from '@/shims/inertia.js';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    resendCooldown: {
        type: Number,
        default: 0,
    },
    expiresMinutes: {
        type: Number,
        default: 5,
    },
});

const page = usePage();
const OTP_LENGTH = 6;
const digits = ref(Array(OTP_LENGTH).fill(''));
const inputRefs = ref([]);

const form = useForm({
    code: '',
});

const cooldown = ref(Math.max(0, Number(props.resendCooldown) || 0));
const resending = ref(false);
const cancelling = ref(false);
const restarting = ref(false);
let timer = null;

const formattedCooldown = computed(() => {
    const secs = Math.max(0, cooldown.value);
    const mm = String(Math.floor(secs / 60)).padStart(2, '0');
    const ss = String(secs % 60).padStart(2, '0');
    return `${mm}:${ss}`;
});

const syncCode = () => {
    form.code = digits.value.join('');
};

const focusBox = (index) => {
    nextTick(() => {
        const el = inputRefs.value[index];
        if (el) {
            el.focus();
            el.select();
        }
    });
};

const resetBoxes = () => {
    digits.value = Array(OTP_LENGTH).fill('');
    syncCode();
    focusBox(0);
};

const startCooldown = (seconds = 60) => {
    cooldown.value = Math.max(0, Number(seconds) || 0);
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    if (cooldown.value <= 0) return;

    timer = setInterval(() => {
        cooldown.value -= 1;
        if (cooldown.value <= 0) {
            clearInterval(timer);
            timer = null;
            cooldown.value = 0;
        }
    }, 1000);
};

const submit = () => {
    syncCode();
    if (form.code.length !== OTP_LENGTH || form.processing) return;
    form.post(route('customer.verification.verify'), {
        onError: () => resetBoxes(),
    });
};

const resend = () => {
    if (cooldown.value > 0 || resending.value) return;
    resending.value = true;
    form.clearErrors();

    router.post(route('customer.verification.resend'), {}, {
        preserveScroll: true,
        onSuccess: (res) => {
            if (page.props.flash) page.props.flash.error = null;
            startCooldown(res?.cooldown ?? 60);
        },
        onError: (errors) => {
            if (errors?.code) {
                form.errors.code = errors.code;
            }
        },
        onFinish: () => {
            resending.value = false;
        },
    });
};

const restartRegistration = () => {
    if (restarting.value || cancelling.value) return;
    restarting.value = true;
    router.post(route('customer.verification.restart'), {}, {
        onFinish: () => {
            restarting.value = false;
        },
    });
};

const cancelVerification = () => {
    if (cancelling.value || restarting.value) return;
    cancelling.value = true;
    router.post(route('customer.verification.cancel'), {}, {
        onFinish: () => {
            cancelling.value = false;
        },
    });
};

const handleInput = (index, event) => {
    const cleaned = event.target.value.replace(/\D/g, '');
    if (!cleaned) {
        digits.value[index] = '';
        syncCode();
        return;
    }

    if (cleaned.length > 1) {
        const chars = cleaned.slice(0, OTP_LENGTH).split('');
        chars.forEach((ch, offset) => {
            if (index + offset < OTP_LENGTH) {
                digits.value[index + offset] = ch;
            }
        });
        syncCode();
        focusBox(Math.min(index + chars.length, OTP_LENGTH - 1));
        return;
    }

    digits.value[index] = cleaned;
    event.target.value = cleaned;
    syncCode();

    if (index < OTP_LENGTH - 1) {
        focusBox(index + 1);
    }
};

const handleKeydown = (index, event) => {
    if (event.key === 'Backspace') {
        if (digits.value[index]) {
            digits.value[index] = '';
            syncCode();
            return;
        }
        if (index > 0) {
            event.preventDefault();
            digits.value[index - 1] = '';
            syncCode();
            focusBox(index - 1);
        }
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusBox(index - 1);
    } else if (event.key === 'ArrowRight' && index < OTP_LENGTH - 1) {
        event.preventDefault();
        focusBox(index + 1);
    }
};

const handlePaste = (event) => {
    const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
    if (!pasted) return;
    event.preventDefault();

    const next = Array(OTP_LENGTH).fill('');
    pasted.split('').forEach((ch, idx) => {
        next[idx] = ch;
    });
    digits.value = next;
    syncCode();
    focusBox(Math.min(pasted.length, OTP_LENGTH - 1));
};

onMounted(() => {
    if (cooldown.value > 0) {
        startCooldown(cooldown.value);
    }
    focusBox(0);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="text-center mb-4">
            <div class="bb-otp-icon mx-auto mb-3">
                <i class="bi bi-envelope-check-fill"></i>
            </div>
            <h5 class="bb-text-primary fw-bold mb-1">Verifikasi Email</h5>
            <p class="small text-secondary mb-2">
                Masukkan 6 digit kode OTP yang telah dikirim ke
            </p>
            <div v-if="email" class="bb-otp-email d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill small fw-semibold">
                <i class="bi bi-at"></i>
                <span>{{ email }}</span>
            </div>
        </div>

        <div v-if="$page.props.flash?.success" class="alert alert-success border-0 rounded-3 small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
            <span>{{ $page.props.flash.success }}</span>
        </div>

        <div v-if="$page.props.flash?.error" class="alert alert-danger border-0 rounded-3 small mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
            <span>{{ $page.props.flash.error }}</span>
        </div>

        <form @submit.prevent="submit">
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="bb-label text-secondary mb-0">Kode OTP</label>
                    <span class="small text-secondary">
                        <i class="bi bi-clock me-1"></i>Berlaku {{ expiresMinutes }} menit
                    </span>
                </div>

                <div class="bb-otp-grid" @paste="handlePaste">
                    <input
                        v-for="(_, idx) in OTP_LENGTH"
                        :key="idx"
                        :ref="(el) => { if (el) inputRefs[idx] = el; }"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        :autocomplete="idx === 0 ? 'one-time-code' : 'off'"
                        :aria-label="`Digit OTP ${idx + 1}`"
                        class="bb-otp-box"
                        :class="{
                            'is-filled': digits[idx] !== '',
                            'is-error': !!form.errors.code,
                        }"
                        :value="digits[idx]"
                        @input="handleInput(idx, $event)"
                        @keydown="handleKeydown(idx, $event)"
                        @focus="$event.target.select()"
                    />
                </div>

                <div v-if="form.errors.code" class="small text-danger text-center mt-2">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ form.errors.code }}
                </div>
            </div>

            <div class="d-flex justify-content-center mb-3">
                <button
                    type="button"
                    class="bb-btn bb-btn--ghost bb-otp-resend w-100 py-2"
                    :disabled="cooldown > 0 || resending"
                    :aria-disabled="cooldown > 0 || resending"
                    @click="resend"
                >
                    <span v-if="resending" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else-if="cooldown > 0" class="bi bi-hourglass-split me-1"></i>
                    <i v-else class="bi bi-arrow-clockwise me-1"></i>
                    <span v-if="cooldown > 0">
                        Kirim ulang kode dalam <strong class="ms-1 bb-otp-countdown">{{ formattedCooldown }}</strong>
                    </span>
                    <span v-else>Kirim ulang kode</span>
                </button>
            </div>

            <button
                type="submit"
                :disabled="form.processing || form.code.length !== OTP_LENGTH"
                class="bb-btn bb-btn--success w-100 py-3"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-check2-circle me-1"></i>
                Verifikasi Email
            </button>

            <div class="d-flex align-items-center justify-content-between gap-2 mt-4 pt-3 border-top border-secondary border-opacity-10">
                <button
                    type="button"
                    class="bb-otp-action"
                    :disabled="restarting || cancelling"
                    @click="restartRegistration"
                >
                    <i class="bi bi-pencil-square me-1"></i>
                    Isi ulang data diri
                </button>

                <button
                    type="button"
                    class="bb-otp-action bb-otp-action--danger"
                    :disabled="cancelling || restarting"
                    @click="cancelVerification"
                >
                    <i class="bi bi-box-arrow-left me-1"></i>
                    Keluar
                </button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
.bb-otp-icon {
    width: 64px;
    height: 64px;
    border-radius: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.16), rgba(16, 185, 129, 0.16));
    border: 1px solid rgba(6, 182, 212, 0.28);
    color: #06b6d4;
    box-shadow: 0 8px 24px rgba(6, 182, 212, 0.14);
}

.bb-otp-email {
    background: rgba(148, 163, 184, 0.12);
    border: 1px solid rgba(148, 163, 184, 0.2);
    color: var(--bs-body-color);
    max-width: 100%;
    word-break: break-all;
}

.bb-otp-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.5rem;
}

.bb-otp-box {
    width: 100%;
    height: 3.35rem;
    border-radius: 0.85rem;
    border: 1.5px solid rgba(148, 163, 184, 0.28);
    background: rgba(15, 23, 42, 0.45);
    color: var(--bs-body-color);
    text-align: center;
    font-size: 1.35rem;
    font-weight: 700;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease, background-color 0.2s ease;
    caret-color: #10b981;
}

[data-bs-theme="light"] .bb-otp-box {
    background: rgba(248, 250, 252, 0.9);
    border-color: rgba(15, 23, 42, 0.14);
}

.bb-otp-box:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
    transform: translateY(-1px);
}

.bb-otp-box.is-filled {
    border-color: rgba(16, 185, 129, 0.65);
}

.bb-otp-box.is-error {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.14);
}

.bb-otp-action {
    appearance: none;
    background: transparent;
    border: 0;
    padding: 0.45rem 0.7rem;
    border-radius: 0.6rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    cursor: pointer;
    transition: color 0.2s ease, background-color 0.2s ease;
}

.bb-otp-action:hover:not(:disabled) {
    color: var(--bs-body-color);
    background: rgba(148, 163, 184, 0.12);
}

.bb-otp-action--danger:hover:not(:disabled) {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
}

.bb-otp-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.bb-otp-resend {
    font-size: 0.85rem;
    text-transform: none;
    letter-spacing: normal;
}

.bb-otp-resend:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
    background: rgba(148, 163, 184, 0.08);
}

.bb-otp-countdown {
    font-variant-numeric: tabular-nums;
    color: #06b6d4;
}

@media (max-width: 400px) {
    .bb-otp-grid {
        gap: 0.35rem;
    }
    .bb-otp-box {
        height: 3rem;
        font-size: 1.15rem;
    }
}
</style>
