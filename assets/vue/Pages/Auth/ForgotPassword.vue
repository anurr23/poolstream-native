<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@/shims/inertia.js';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    username: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Password" />

        <div class="mb-4 small text-secondary">
            Lupa password? Masukkan username Anda dan administrator akan meresetnya (atau sistem akan mengirimkan instruksi).
        </div>

        <div
            v-if="status"
            class="alert alert-success rounded-3 mb-4 small"
        >
            {{ status }}
        </div>

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
                    autofocus
                    autocomplete="username"
                    placeholder="Masukkan username"
                />

                <div v-if="form.errors.username" class="small text-danger mt-1">{{ form.errors.username }}</div>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-4">
                <Link :href="route('login')" class="small text-decoration-none text-secondary">
                    Kembali ke Login
                </Link>
                
                <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success py-2 px-4">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                    Kirim Permintaan
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
