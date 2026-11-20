<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router, useForm } from '@/shims/inertia.js';
import { onUnmounted, ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    mailLogMode: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    code: '',
});

const cooldown = ref(0);
let timer = null;

const startCooldown = () => {
    cooldown.value = 60;

    if (timer) clearInterval(timer);

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
    form.post(route('customer.verification.verify'), {
        onFinish: () => form.reset('code'),
    });
};

const resend = () => {
    if (cooldown.value > 0) return;

    router.post(route('customer.verification.resend'), {}, {
        preserveScroll: true,
        onSuccess: () => startCooldown(),
    });
};

const onlyDigits = (event) => {
    form.code = event.target.value.replace(/\D/g, '').slice(0, 6);
};

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px; background: rgba(6,182,212,0.12); color: #06b6d4;">
                <i class="bi bi-envelope-check" style="font-size: 1.8rem;"></i>
            </div>
            <h5 class="bb-text-primary fw-bold mb-1">Verifikasi Email</h5>
            <p class="small text-secondary mb-0">
                Masukkan 6 digit kode OTP yang dikirim ke
                <strong>{{ email }}</strong>
            </p>
        </div>

        <div v-if="mailLogMode" class="alert alert-warning rounded-3 small mb-4">
            <i class="bi bi-info-circle me-1"></i>
            Mode lokal: email belum dikonfigurasi. Kode OTP juga tercatat di
            <code>storage/logs/mail.log</code>.
        </div>

        <div v-if="$page.props.flash?.success" class="alert alert-success rounded-3 small mb-4">
            <i class="bi bi-check-circle me-1"></i>{{ $page.props.flash.success }}
        </div>

        <div v-if="$page.props.flash?.error" class="alert alert-danger rounded-3 small mb-4">
            <i class="bi bi-exclamation-circle me-1"></i>{{ $page.props.flash.error }}
        </div>

        <form @submit.prevent="submit">
            <div class="mb-3">
                <label for="code" class="bb-label text-secondary">Kode OTP</label>
                <input
                    id="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    class="bb-input w-100 mt-1 text-center"
                    :class="{ 'border-danger': form.errors.code }"
                    v-model="form.code"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    style="font-size: 1.5rem; letter-spacing: 0.45em; font-weight: 700;"
                    @input="onlyDigits"
                />
                <div v-if="form.errors.code" class="small text-danger mt-1">{{ form.errors.code }}</div>
            </div>

            <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success w-100 py-3">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-check2-circle me-1"></i>
                Verifikasi Email
            </button>

            <div class="d-flex align-items-center justify-content-between mt-4">
                <button
                    type="button"
                    class="bb-btn bb-btn--ghost py-2 px-3"
                    style="font-size: 0.8rem; text-transform: none;"
                    :disabled="cooldown > 0"
                    @click="resend"
                >
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    {{ cooldown > 0 ? `Kirim ulang (${cooldown}s)` : 'Kirim ulang kode' }}
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="small text-secondary text-decoration-none"
                >
                    Keluar
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
