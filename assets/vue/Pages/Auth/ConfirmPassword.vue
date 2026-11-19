<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@/shims/inertia.js';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('api.password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Password" />

        <div class="mb-4 small text-secondary">
            Ini adalah area aman aplikasi. Silakan konfirmasi password Anda sebelum melanjutkan.
        </div>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label for="password" class="bb-label text-secondary">Password</label>
                <input
                    id="password"
                    type="password"
                    class="bb-input w-100 mt-1"
                    :class="{'border-danger': form.errors.password}"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success py-3 w-100">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                    Konfirmasi Password
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
