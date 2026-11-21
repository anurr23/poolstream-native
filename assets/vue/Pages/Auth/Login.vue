<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import GoogleIcon from '@/Components/GoogleIcon.vue';
import { Head, Link, router, useForm } from '@/shims/inertia.js';

defineProps({
    status: String,
});

const form = useForm({
    username: '',
    password: '',
});

const submit = () => {
    form.post(route('api.login'), {
        onFinish: () => form.reset('password'),
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
        <Head title="Log in" />

        <div v-if="status" class="alert alert-success rounded-3 mb-4 small">
            {{ status }}
        </div>

        <h5 class="bb-text-primary fw-bold mb-1">Selamat Datang</h5>
        <p class="small mb-4 text-secondary">Masuk untuk mengelola meja biliar, F&amp;B, dan data usaha</p>

        <div v-if="$page.props.errors?.email || googleError" class="alert alert-danger rounded-3 mb-4 small">
            <i class="bi bi-exclamation-circle me-1"></i>{{ $page.props.errors?.email || googleError }}
        </div>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label for="username" class="bb-label text-secondary">Username atau Email</label>
                <input
                    id="username"
                    type="text"
                    class="bb-input w-100 mt-1"
                    :class="{'border-danger': form.errors.username}"
                    v-model="form.username"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="admin atau nama@email.com"
                />
                <div v-if="form.errors.username" class="small text-danger mt-1">{{ form.errors.username }}</div>
            </div>

            <div class="mb-4">
                <label for="password" class="bb-label text-secondary">Password</label>
                <input
                    id="password"
                    type="password"
                    class="bb-input"
                    :class="{'border-danger': form.errors.password}"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>
            </div>

            <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success w-100 py-3 mb-3">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="bi bi-box-arrow-in-right"></i>
                Masuk
            </button>

            <button
                type="button"
                @click="openGooglePopup"
                :disabled="isGoogleLoading"
                class="bb-btn bb-btn--ghost w-100 py-3 d-flex align-items-center justify-content-center"
            >
                <span v-if="isGoogleLoading" class="spinner-border spinner-border-sm me-2"></span>
                <GoogleIcon v-else class="me-2" />
                Masuk dengan Google
            </button>

            <div class="text-center small mt-4 text-secondary">
                Belum punya akun?
                <Link
                    :href="route().has('register') ? route('register') : '/register'"
                    class="fw-bold text-decoration-none ms-1"
                    style="color: #10b981; cursor: pointer;"
                >
                    Daftar sekarang
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
