<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import GoogleIcon from '@/Components/GoogleIcon.vue';
import { Head, Link, router, useForm } from '@/shims/inertia.js';

const props = defineProps({
    draft: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.draft?.name || '',
    email: props.draft?.email || '',
    whatsapp: props.draft?.whatsapp || '',
    password: '',
    password_confirmation: '',
});

const onWhatsappInput = () => {
    form.whatsapp = form.whatsapp.replace(/\D/g, '').slice(0, 15);
};

const submit = () => {
    form.post(route('api.register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const isGoogleLoading = ref(false);
const googleError = ref('');

const openGooglePopup = () => {
    googleError.value = '';
    isGoogleLoading.value = true;

    const width = 500;
    const height = 650;
    const left = window.screenX + Math.max(0, (window.outerWidth - width) / 2);
    const top = window.screenY + Math.max(0, (window.outerHeight - height) / 2);

    let redirectRoute = route('auth.google.redirect');
    if (!window.location.pathname.includes('/public/') && !window.location.pathname.endsWith('/public')) {
        redirectRoute = redirectRoute.replace(/\/public(\/|$)/, '$1');
    }

    const popup = window.open(
        redirectRoute,
        'google_oauth_popup',
        `width=${width},height=${height},left=${left},top=${top},status=no,toolbar=no,menubar=no`
    );

    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
        window.location.href = redirectRoute;
        return;
    }

    const timer = setInterval(() => {
        try {
            if (!popup || popup.closed) {
                clearInterval(timer);
                isGoogleLoading.value = false;
            }
        } catch {
            // Ignored: Cross-Origin-Opener-Policy
        }
    }, 700);
};

const handleAuthMessage = (event) => {
    if (event.origin !== window.location.origin) return;

    if (event.data?.type === 'google-auth-success') {
        isGoogleLoading.value = true;
        let targetUrl = event.data.url || route('customer.home');
        if (!window.location.pathname.includes('/public/') && !window.location.pathname.endsWith('/public')) {
            targetUrl = targetUrl.replace(/\/public(\/|$)/, '$1');
        }
        router.visit(targetUrl);
    } else if (event.data?.type === 'google-auth-error') {
        isGoogleLoading.value = false;
        googleError.value = event.data?.message || 'Gagal masuk dengan Google.';
    }
};

onMounted(() => {
    window.addEventListener('message', handleAuthMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', handleAuthMessage);
});
</script>

<template>
    <GuestLayout>
        <Head title="Daftar Akun" />

        <h5 class="bb-text-primary fw-bold mb-1">Daftar Akun Customer</h5>
        <p class="small mb-4 text-secondary">
            Buat akun untuk booking meja online. Kode OTP akan dikirim ke email Anda.
        </p>

        <div v-if="$page.props.errors?.email || googleError" class="alert alert-danger rounded-3 mb-4 small">
            <i class="bi bi-exclamation-circle me-1"></i>{{ $page.props.errors?.email || googleError }}
        </div>

        <button
            type="button"
            @click="openGooglePopup"
            :disabled="isGoogleLoading"
            class="bb-btn bb-btn--ghost w-100 py-3 mb-3 d-flex align-items-center justify-content-center"
        >
            <span v-if="isGoogleLoading" class="spinner-border spinner-border-sm me-2"></span>
            <GoogleIcon v-else class="me-2" />
            Daftar dengan Google
        </button>

        <div class="d-flex align-items-center my-3">
            <hr class="flex-grow-1 border-secondary opacity-25 my-0">
            <span class="px-3 small text-secondary">atau daftar dengan email</span>
            <hr class="flex-grow-1 border-secondary opacity-25 my-0">
        </div>

        <form @submit.prevent="submit">
            <div class="mb-3">
                <label for="name" class="bb-label text-secondary">Nama Lengkap</label>
                <input
                    id="name"
                    type="text"
                    class="bb-input w-100 mt-1"
                    :class="{ 'border-danger': form.errors.name }"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nama Anda"
                />
                <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
            </div>

            <div class="mb-3">
                <label for="email" class="bb-label text-secondary">Email</label>
                <input
                    id="email"
                    type="email"
                    class="bb-input w-100 mt-1"
                    :class="{ 'border-danger': form.errors.email }"
                    v-model="form.email"
                    required
                    autocomplete="email"
                    placeholder="nama@email.com"
                />
                <div v-if="form.errors.email" class="small text-danger mt-1">{{ form.errors.email }}</div>
            </div>

            <div class="mb-3">
                <label for="whatsapp" class="bb-label text-secondary">No. WhatsApp</label>
                <input
                    id="whatsapp"
                    type="tel"
                    inputmode="numeric"
                    maxlength="15"
                    pattern="08[0-9]{8,13}"
                    title="Awali dengan 08 dan masukkan 10-15 digit angka"
                    class="bb-input w-100 mt-1"
                    :class="{ 'border-danger': form.errors.whatsapp }"
                    v-model="form.whatsapp"
                    @input="onWhatsappInput"
                    required
                    autocomplete="tel-national"
                    placeholder="081234567890"
                />
                <div v-if="form.errors.whatsapp" class="small text-danger mt-1">{{ form.errors.whatsapp }}</div>
            </div>

            <div class="mb-3">
                <label for="password" class="bb-label text-secondary">Password</label>
                <input
                    id="password"
                    type="password"
                    class="bb-input w-100 mt-1"
                    :class="{ 'border-danger': form.errors.password }"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                />
                <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="bb-label text-secondary">Konfirmasi Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    class="bb-input w-100 mt-1"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi password"
                />
            </div>

            <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success w-100 py-3">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-person-plus me-1"></i>
                Daftar
            </button>

            <div class="small text-secondary text-center mt-4">
                <i class="bi bi-info-circle me-1"></i>
                Akun kasir/admin dibuat oleh admin.
            </div>

            <div class="text-center small mt-3 text-secondary">
                Sudah punya akun?
                <Link :href="route('login')" class="fw-bold text-decoration-none" style="color: #10b981;">
                    Masuk
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
