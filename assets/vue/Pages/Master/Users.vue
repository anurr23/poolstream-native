<script setup>
import { ref, computed, toRef } from 'vue';
import { Head, useForm, router } from '@/shims/inertia.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useDatatable } from '@/Composables/useDatatable';
import BbSelect from '@/Components/BbSelect.vue';

const props = defineProps({
    users: Array,
});

const usersRef = toRef(props, 'users');

const {
    searchQuery,
    currentPage,
    totalPages,
    paginatedData,
    nextPage,
    prevPage,
} = useDatatable(usersRef, ['name', 'username', 'email', 'role']);

const isEditing = ref(false);
const editId = ref(null);
const showModal = ref(false);

const roleOptions = [
    { value: 'kasir', label: 'Kasir' },
    { value: 'admin', label: 'Admin' },
    { value: 'customer', label: 'Customer' }
];

const form = useForm({
    _method: 'post',
    name: '',
    username: '',
    email: '',
    role: 'kasir',
    is_active: true,
    password: '',
    photo: null,
});

const photoPreview = ref(null);
const photoInput = ref(null);

const selectPhoto = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.photo = file;
    photoPreview.value = URL.createObjectURL(file);
};

const removePhoto = () => {
    form.photo = null;
    photoPreview.value = null;
    if (photoInput.value) photoInput.value.value = '';
};

const clearPasswordError = () => {
    if (form.errors.password) delete form.errors.password;
};

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    photoPreview.value = null;
    showModal.value = true;
};

const capitalizeName = () => {
    form.name = form.name.replace(/\b\w/g, l => l.toUpperCase());
};

const editUser = (user) => {
    isEditing.value = true;
    editId.value = user.id;
    form._method = 'put';
    form.name = user.name;
    form.username = user.username;
    form.email = user.email || '';
    form.role = user.role;
    form.is_active = user.is_active;
    form.password = '';
    form.photo = null;
    photoPreview.value = user.photo_url || null;
    showModal.value = true;
};

const submit = () => {
    if (form.password && !isEditing.value && form.password.length < 4) {
        form.errors.password = 'Password minimal 4 karakter.';
        return;
    }
    if (form.password && isEditing.value && form.password.length < 4) {
        form.errors.password = 'Password minimal 4 karakter.';
        return;
    }

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', form.name);
    formData.append('username', form.username);
    formData.append('email', form.email || '');
    formData.append('role', form.role);
    formData.append('is_active', form.is_active ? '1' : '0');
    if (form.password) formData.append('password', form.password);
    if (form.photo instanceof File) formData.append('photo', form.photo);

    if (isEditing.value) {
        router.post(route('users.update', editId.value), formData, {
            onSuccess: () => closeModal(),
            forceFormData: true
        });
    } else {
        router.post(route('users.store'), formData, {
            onSuccess: () => closeModal(),
            forceFormData: true
        });
    }
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => {
        isEditing.value = false;
        editId.value = null;
        form.reset();
        form.clearErrors();
        photoPreview.value = null;
        if (photoInput.value) photoInput.value.value = '';
    }, 300);
};

const confirmDelete = ref({
    show: false,
    id: null
});

const openDeleteConfirm = (id) => {
    confirmDelete.value = { show: true, id };
};

const executeDelete = () => {
    if (!confirmDelete.value.id) return;
    
    router.delete(route('users.destroy', confirmDelete.value.id), {
        onSuccess: () => {
            confirmDelete.value.show = false;
        }
    });
};

const toggleActive = (user) => {
    router.put(route('users.update', user.id), {
        name: user.name,
        username: user.username,
        role: user.role,
        is_active: !user.is_active,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Kelola User" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-people-fill me-2" style="color: #6366f1;"></i>Kelola User</h1>
        </template>

        <div class="row g-4">
            <!-- User List -->
            <div class="col-12">
                <div class="bb-card">
                    <div class="bb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Daftar User</h6>
                            <span class="bb-badge" style="background: rgba(99,102,241,0.1); color: #6366f1;">{{ users.length }} User</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center" style="min-width: 300px;">
                            <input type="text" v-model="searchQuery" placeholder="Cari user..." class="bb-input form-control-sm py-2 flex-grow-1" />
                            <button @click="openAddModal" class="bb-btn bb-btn--primary py-2 px-3 text-nowrap">
                                <i class="bi bi-plus-lg me-1"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="bb-card-body p-0">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">No</th>
                                    <th>Foto</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user, index) in paginatedData" :key="user.id">
                                    <td class="ps-4 text-secondary">
                                        {{ (currentPage - 1) * 10 + index + 1 }}
                                    </td>
                                    <td>
                                        <img v-if="user.photo_url" :src="user.photo_url" :alt="user.name" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover;" />
                                        <div v-else style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.85rem;">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                    </td>
                                    <td class="fw-bold">{{ user.name }}</td>
                                    <td>
                                        <div>{{ user.username }}</div>
                                        <div class="small text-secondary">{{ user.email || '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="bb-badge text-capitalize" 
                                            :style="user.role === 'admin' ? 'background: rgba(139,92,246,0.1); color: #8b5cf6;' : (user.role === 'customer' ? 'background: rgba(6,182,212,0.1); color: #06b6d4;' : 'background: rgba(56,189,248,0.1); color: #38bdf8;')">
                                            <i class="bi" :class="user.role === 'admin' ? 'bi-shield-lock' : (user.role === 'customer' ? 'bi-person-badge' : 'bi-person')"></i> {{ user.role }}
                                        </span>
                                    </td>
                                    <td>
                                        <button 
                                            @click="$page.props.auth.user.id !== user.id ? toggleActive(user) : null"
                                            class="bb-badge border-0"
                                            :class="user.is_active ? 'cursor-pointer' : 'cursor-pointer'"
                                            :style="user.is_active ? 'background: rgba(16,185,129,0.1); color: #10b981; cursor: pointer;' : 'background: rgba(239,68,68,0.1); color: #ef4444; cursor: pointer;'"
                                            :disabled="$page.props.auth.user.id === user.id"
                                            :title="$page.props.auth.user.id === user.id ? 'Tidak bisa menonaktifkan akun sendiri' : 'Klik untuk toggle status'"
                                        >
                                            <i class="bi" :class="user.is_active ? 'bi-lightbulb-fill' : 'bi-lightbulb'"></i> {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button @click="editUser(user)" class="bb-btn bb-btn--ghost py-1 px-3 me-2" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button v-if="$page.props.auth.user.id !== user.id" @click="openDeleteConfirm(user.id)" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="paginatedData.length === 0">
                                    <td colspan="7" class="text-center py-5" style="opacity: 0.4;">
                                        <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                        Pencarian tidak ditemukan
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="bb-card-header d-flex justify-content-between align-items-center" style="border-top: 1px solid rgba(255,255,255,0.06); border-bottom: none;">
                        <span class="small text-secondary">Halaman {{ currentPage }} dari {{ totalPages }}</span>
                        <div class="d-flex gap-1">
                            <button @click="prevPage" :disabled="currentPage === 1" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                <i class="bi bi-chevron-left"></i> Prev
                            </button>
                            <button @click="nextPage" :disabled="currentPage === totalPages" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="bb-modal-backdrop" @click.self="closeModal">
            <div class="bb-modal">
                <div class="bb-modal-header">
                    <h5 v-if="!isEditing" class="m-0 bb-text-primary"><i class="bi bi-person-plus-fill me-2 text-success"></i>Tambah User</h5>
                    <h5 v-else class="m-0 bb-text-primary"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit User</h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="bb-modal-body">
                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Nama Lengkap</label>
                            <input type="text" v-model="form.name" @input="capitalizeName" required  class="bb-input w-100 mt-1" />
                            <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Foto Profil</label>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <div v-if="photoPreview" class="position-relative">
                                    <img :src="photoPreview" style="width: 64px; height: 64px; border-radius: 12px; object-fit: cover;" />
                                    <button type="button" @click="removePhoto" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border-0" style="font-size: 0.6rem; cursor: pointer; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div v-else style="width: 64px; height: 64px; border-radius: 12px; background: rgba(99,102,241,0.08); display: flex; align-items: center; justify-content: center; border: 2px dashed rgba(99,102,241,0.2);">
                                    <i class="bi bi-camera" style="font-size: 1.3rem; color: #6366f1; opacity: 0.4;"></i>
                                </div>
                                <div>
                                    <label class="bb-btn bb-btn--ghost py-2 px-3 mb-0" style="font-size: 0.8rem; text-transform: none; cursor: pointer;">
                                        <i class="bi bi-upload me-1"></i> Pilih Foto
                                        <input type="file" ref="photoInput" @change="selectPhoto" accept="image/jpeg,image/png,image/webp" class="d-none" />
                                    </label>
                                    <div class="small text-secondary mt-1">JPEG/PNG/WebP, maks 2MB</div>
                                </div>
                            </div>
                            <div v-if="form.errors.photo" class="small text-danger mt-1">{{ form.errors.photo }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Username</label>
                            <input type="text" v-model="form.username" required  class="bb-input w-100 mt-1" :class="{'border-danger': form.errors.username}" />
                            <div v-if="form.errors.username" class="small text-danger mt-1">{{ form.errors.username }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Email <span class="text-secondary fw-normal">(opsional)</span></label>
                            <input type="email" v-model="form.email" class="bb-input w-100 mt-1" :class="{'border-danger': form.errors.email}" placeholder="nama@email.com" />
                            <div v-if="form.errors.email" class="small text-danger mt-1">{{ form.errors.email }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Role</label>
                            <BbSelect 
                                v-model="form.role" 
                                :options="roleOptions" 
                                :error="!!form.errors.role" 
                                class="mt-1" 
                            />
                            <div v-if="form.errors.role" class="small text-danger mt-1">{{ form.errors.role }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Status</label>
                            <div class="d-flex gap-2 mt-1">
                                <button type="button" @click="form.is_active = true"
                                    class="bb-badge border-0 px-3 py-2"
                                    :style="form.is_active ? 'background: rgba(16,185,129,0.15); color: #10b981; border: 2px solid #10b981 !important;' : 'background: rgba(148,163,184,0.1); color: #94a3b8;'">
                                    <i class="bi" :class="form.is_active ? 'bi-lightbulb-fill' : 'bi-lightbulb'"></i> Aktif
                                </button>
                                <button type="button" @click="form.is_active = false"
                                    class="bb-badge border-0 px-3 py-2"
                                    :style="!form.is_active ? 'background: rgba(239,68,68,0.15); color: #ef4444; border: 2px solid #ef4444 !important;' : 'background: rgba(148,163,184,0.1); color: #94a3b8;'">
                                    <i class="bi" :class="!form.is_active ? 'bi-lightbulb-fill' : 'bi-lightbulb'"></i> Nonaktif
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="bb-label text-secondary">Password <span v-if="isEditing" class="text-secondary fw-normal">(Kosongkan jika tidak diubah)</span></label>
                            <input type="password" v-model="form.password" :required="!isEditing" @input="clearPasswordError" class="bb-input w-100 mt-1" />
                            <div v-if="form.errors.password" class="small text-danger mt-1">{{ form.errors.password }}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" @click="closeModal" class="bb-btn bb-btn--ghost flex-grow-1 py-3">
                                Batal
                            </button>
                            <button type="submit" :disabled="form.processing" class="bb-btn bb-btn--primary flex-grow-1 py-3">
                                <i v-if="!isEditing" class="bi bi-plus-lg"></i>
                                <i v-else class="bi bi-check-lg"></i>
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmModal 
            :show="confirmDelete.show"
            title="Hapus User"
            message="Apakah Anda yakin ingin menghapus user ini? Aksi ini tidak dapat dibatalkan."
            confirmText="Ya, Hapus"
            @confirm="executeDelete"
            @cancel="confirmDelete.show = false"
        />

    </AuthenticatedLayout>
</template>
