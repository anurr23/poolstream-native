<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@/shims/inertia.js';

const props = defineProps({
    username: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    username: props.username,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <h5 class="bb-text-primary fw-bold mb-4">Reset Password</h5>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label for="username" class="bb-label text-secondary">Username</label>

                <input
                    id="username"
                    type="text"
                    class="bb-input w-100 mt-1"
                    :class="{'border-danger': form.errors.username}"
                    v-model="form.username"
                    required
                    readonly
                    autocomplete="username"
                />

                <div v-if="form.errors.username" class="small text-danger mt-1">{{ form.errors.username }}</div>
            </div>

            <div class="mb-4">
                <label for="password" class="bb-label text-secondary">Password Baru</label>

                <input
                    id="password"
                    type="password"
                    class="bb-input w-100 mt-1"
                    :class="{'border-danger': form.errors.password}"
                    v-model="form.password"
                    required
                    autofocus
                    autocomplete="new-password"
                />

                <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="bb-label text-secondary">Konfirmasi Password</label>

                <input
                    id="password_confirmation"
                    type="password"
                    class="bb-input w-100 mt-1"
                    :class="{'border-danger': form.errors.password_confirmation}"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <div v-if="form.errors.password_confirmation" class="small text-danger mt-1">{{ form.errors.password_confirmation }}</div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success py-3 w-100">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                    Simpan Password Baru
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
