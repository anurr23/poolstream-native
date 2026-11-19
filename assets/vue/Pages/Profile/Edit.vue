<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@/shims/inertia.js';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import KasirLayout from '@/Layouts/KasirLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';

const user = usePage().props.auth.user;
const isCustomer = computed(() => user.role === 'customer');
const layoutComponent = computed(() => (isCustomer.value ? CustomerLayout : KasirLayout));

const initial = user.name.charAt(0).toUpperCase();

const roleMeta = computed(() => {
    if (user.role === 'admin') {
        return { label: 'Administrator', icon: 'bi-shield-lock-fill', cls: 'bb-profil-chip--admin' };
    }
    if (user.role === 'customer') {
        return { label: 'Customer', icon: 'bi-person-badge-fill', cls: 'bb-profil-chip--customer' };
    }
    return { label: 'Kasir', icon: 'bi-person-fill', cls: 'bb-profil-chip--kasir' };
});

const joinedAt = computed(() => {
    if (!user.created_at) return null;
    return new Date(user.created_at).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const form = useForm({
    name: user.name,
    photo: null,
});

const photoPreview = ref(user.photo_url || null);
const photoInput = ref(null);

const capitalizeName = () => {
    form.name = form.name.replace(/\b\w/g, l => l.toUpperCase());
};

const selectPhoto = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.photo = file;
    photoPreview.value = URL.createObjectURL(file);
};

const cancelPhoto = () => {
    form.photo = null;
    photoPreview.value = user.photo_url || null;
    if (photoInput.value) photoInput.value.value = '';
};

const submit = () => {
    form.transform(() => ({
        name: form.name,
        ...(form.photo ? { photo: form.photo } : {}),
        _method: 'patch',
    })).post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Profil Saya" />

    <component
        :is="layoutComponent"
        :back-href="isCustomer ? route('customer.home') : null"
    >
        <template #header v-if="!isCustomer">
            <h1 class="bb-header-title"><i class="bi bi-person-circle me-2" style="color: #6366f1;"></i>Profil Saya</h1>
        </template>

        <div class="row g-4">
            <!-- Hero -->
            <div class="col-12">
                <div class="bb-card">
                    <div class="bb-profil-banner" aria-hidden="true">
                        <span class="bb-profil-orb bb-profil-orb--1"></span>
                        <span class="bb-profil-orb bb-profil-orb--2"></span>
                        <i class="bi bi-8-circle-fill bb-profil-banner-icon"></i>
                    </div>

                    <div class="bb-card-body bb-profil-body">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <div class="bb-profil-avatar-wrap">
                                <label
                                    class="bb-profil-avatar"
                                    :title="photoPreview ? 'Ganti foto profil' : 'Upload foto profil'"
                                >
                                    <input
                                        type="file"
                                        ref="photoInput"
                                        @change="selectPhoto"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="d-none"
                                    />
                                    <img v-if="photoPreview" :src="photoPreview" :alt="user.name" />
                                    <span v-else class="bb-profil-avatar-initial">{{ initial }}</span>
                                    <span class="bb-profil-avatar-overlay">
                                        <i class="bi bi-camera-fill"></i>
                                        <span>Ganti</span>
                                    </span>
                                </label>

                                <span
                                    class="bb-profil-avatar-status"
                                    :class="user.is_active ? 'is-online' : 'is-offline'"
                                    :title="user.is_active ? 'Aktif' : 'Nonaktif'"
                                ></span>

                                <button
                                    v-if="form.photo"
                                    type="button"
                                    class="bb-profil-avatar-cancel"
                                    title="Batalkan foto baru"
                                    @click="cancelPhoto"
                                >
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <div class="bb-profil-ident">
                                <h4 class="bb-profil-name">{{ user.name }}</h4>
                                <div class="bb-profil-username">@{{ user.username }}</div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <span class="bb-profil-chip" :class="roleMeta.cls">
                                        <i class="bi" :class="roleMeta.icon"></i>{{ roleMeta.label }}
                                    </span>
                                    <span
                                        class="bb-profil-chip"
                                        :class="user.is_active ? 'bb-profil-chip--active' : 'bb-profil-chip--inactive'"
                                    >
                                        <i class="bi" :class="user.is_active ? 'bi-broadcast' : 'bi-slash-circle'"></i>
                                        {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="joinedAt" class="bb-profil-meta ms-lg-auto">
                                <span class="bb-profil-meta-label">Bergabung sejak</span>
                                <span class="bb-profil-meta-value">
                                    <i class="bi bi-calendar3 me-2"></i>{{ joinedAt }}
                                </span>
                            </div>
                        </div>

                        <div v-if="form.errors.photo" class="small text-danger mt-3">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ form.errors.photo }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account info -->
            <div class="col-12 col-lg-7">
                <div class="bb-card h-100">
                    <div class="bb-card-header">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-person-vcard me-2 text-success"></i>Informasi Akun
                        </h6>
                    </div>
                    <div class="bb-card-body">
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="bb-label">Nama Lengkap</label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    @input="capitalizeName"
                                    required
                                    class="bb-input mt-1"
                                    placeholder="Masukkan nama lengkap"
                                />
                                <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="bb-label">Username</label>
                                <div class="bb-profil-readonly mt-1">
                                    <i class="bi bi-at"></i>
                                    <input type="text" :value="user.username" class="bb-input" readonly disabled />
                                </div>
                                <div class="small text-secondary mt-1">Username tidak dapat diubah.</div>
                            </div>

                            <div class="bb-profil-hint mb-4">
                                <i class="bi bi-image me-2"></i>
                                Klik foto profil di atas untuk mengganti foto. JPEG/PNG/WebP, maks 2MB.
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
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="col-12 col-lg-5">
                <div class="bb-card h-100">
                    <div class="bb-card-header">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-shield-lock me-2" style="color: #f59e0b;"></i>Ubah Password
                        </h6>
                    </div>
                    <div class="bb-card-body">
                        <UpdatePasswordForm />
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
