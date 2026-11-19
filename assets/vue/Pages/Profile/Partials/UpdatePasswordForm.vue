<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@/shims/inertia.js';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const show = ref({
    current: false,
    password: false,
    confirmation: false,
});

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const rules = computed(() => {
    const p = form.password;
    return [
        { text: 'Min. 8 karakter', ok: p.length >= 8 },
        { text: 'Huruf besar & kecil', ok: /[a-z]/.test(p) && /[A-Z]/.test(p) },
        { text: 'Angka', ok: /\d/.test(p) },
        { text: 'Simbol', ok: /[^A-Za-z0-9\s]/.test(p) },
    ];
});

const score = computed(() => rules.value.filter(r => r.ok).length);

const strength = computed(() =>
    [
        { label: 'Lemah', color: 'danger' },
        { label: 'Lemah', color: 'danger' },
        { label: 'Cukup', color: 'warning' },
        { label: 'Kuat', color: 'success' },
        { label: 'Sangat Kuat', color: 'success' },
    ][score.value],
);

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            show.value = { current: false, password: false, confirmation: false };
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <form @submit.prevent="updatePassword">
        <div class="mb-3">
            <label class="bb-label" for="current_password">Password Saat Ini</label>
            <div class="bb-profil-pw mt-1">
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    :type="show.current ? 'text' : 'password'"
                    class="bb-input"
                    autocomplete="current-password"
                />
                <button
                    type="button"
                    class="bb-profil-pw-toggle"
                    :title="show.current ? 'Sembunyikan password' : 'Tampilkan password'"
                    tabindex="-1"
                    @click="show.current = !show.current"
                >
                    <i class="bi" :class="show.current ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            <div v-if="form.errors.current_password" class="small text-danger mt-1">{{ form.errors.current_password }}</div>
        </div>

        <div class="mb-3">
            <label class="bb-label" for="password">Password Baru</label>
            <div class="bb-profil-pw mt-1">
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    :type="show.password ? 'text' : 'password'"
                    class="bb-input"
                    autocomplete="new-password"
                />
                <button
                    type="button"
                    class="bb-profil-pw-toggle"
                    :title="show.password ? 'Sembunyikan password' : 'Tampilkan password'"
                    tabindex="-1"
                    @click="show.password = !show.password"
                >
                    <i class="bi" :class="show.password ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>

            <div v-if="form.password" class="bb-profil-strength mt-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="bb-profil-strength-bars">
                        <span
                            v-for="i in 4"
                            :key="i"
                            class="bb-profil-strength-bar"
                            :class="i <= score ? `is-${strength.color}` : ''"
                        ></span>
                    </div>
                    <small class="bb-profil-strength-label" :class="`text-${strength.color}`">{{ strength.label }}</small>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span v-for="rule in rules" :key="rule.text" class="bb-profil-req" :class="{ 'is-ok': rule.ok }">
                        <i class="bi" :class="rule.ok ? 'bi-check-circle-fill' : 'bi-circle'"></i>{{ rule.text }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="bb-label" for="password_confirmation">Konfirmasi Password</label>
            <div class="bb-profil-pw mt-1">
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="show.confirmation ? 'text' : 'password'"
                    class="bb-input"
                    autocomplete="new-password"
                />
                <button
                    type="button"
                    class="bb-profil-pw-toggle"
                    :title="show.confirmation ? 'Sembunyikan password' : 'Tampilkan password'"
                    tabindex="-1"
                    @click="show.confirmation = !show.confirmation"
                >
                    <i class="bi" :class="show.confirmation ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            <div
                v-if="form.password_confirmation && form.password && form.password !== form.password_confirmation"
                class="small text-warning mt-1"
            >
                <i class="bi bi-exclamation-triangle me-1"></i>Konfirmasi belum sama.
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--success">
                <i v-if="form.processing" class="spinner-border spinner-border-sm me-1"></i>
                <i v-else class="bi bi-check-lg me-1"></i> Simpan
            </button>
            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                <span v-if="form.recentlySuccessful" class="small text-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Tersimpan
                </span>
            </Transition>
        </div>
    </form>
</template>
