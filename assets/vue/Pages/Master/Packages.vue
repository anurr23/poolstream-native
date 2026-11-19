<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@/shims/inertia.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useDatatable } from '@/Composables/useDatatable';

const props = defineProps({
    packages: Array
});

const { 
    searchQuery, currentPage, totalPages, paginatedData, nextPage, prevPage 
} = useDatatable(computed(() => props.packages), ['name']);

const isEditing = ref(false);
const editId = ref(null);
const showModal = ref(false);

const form = useForm({
    name: '',
    price: ''
});

const formattedPrice = ref('');

const handlePriceInput = (e) => {
    let val = e.target.value.replace(/\D/g, '');
    form.price = val;
    formattedPrice.value = val ? new Intl.NumberFormat('id-ID').format(val) : '';
};

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    formattedPrice.value = '';
    showModal.value = true;
};

const editPackage = (pkg) => {
    isEditing.value = true;
    editId.value = pkg.id;
    form.name = pkg.name;
    form.price = pkg.price;
    formattedPrice.value = pkg.price ? new Intl.NumberFormat('id-ID').format(pkg.price) : '';
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('packages.update', editId.value), {
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('packages.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => {
        isEditing.value = false;
        editId.value = null;
        form.reset();
        formattedPrice.value = '';
        form.clearErrors();
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
    
    router.delete(route('packages.destroy', confirmDelete.value.id), {
        onSuccess: () => {
            confirmDelete.value.show = false;
        }
    });
};
</script>

<template>
    <Head title="Paket & Harga" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-tag-fill me-2" style="color: #f59e0b;"></i>Paket & Harga</h1>
        </template>

        <div class="row g-4">
            <!-- Package List -->
            <div class="col-12">
                <div class="bb-card">
                    <div class="bb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Daftar Paket</h6>
                            <span class="bb-badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">{{ packages.length }} Paket</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center" style="min-width: 300px;">
                            <input type="text" v-model="searchQuery" placeholder="Cari paket..." class="bb-input form-control-sm py-2 flex-grow-1" />
                            <button @click="openAddModal" class="bb-btn bb-btn--primary py-2 px-3 text-nowrap">
                                <i class="bi bi-plus-lg me-1"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="bb-card-body p-0">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th class="ps-4">Nama Paket</th>
                                    <th>Harga / Jam</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="pkg in paginatedData" :key="pkg.id">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bb-stat-icon" style="width: 36px; height: 36px; border-radius: 8px; font-size: 0.9rem; background: rgba(245,158,11,0.1); color: #f59e0b;">
                                                <i class="bi bi-tag"></i>
                                            </div>
                                            <span class="fw-bold">{{ pkg.name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-gradient">Rp {{ pkg.price.toLocaleString('id-ID') }}</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button @click="editPackage(pkg)" class="bb-btn bb-btn--ghost py-1 px-3 me-2" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button @click="openDeleteConfirm(pkg.id)" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="paginatedData.length === 0">
                                    <td colspan="3" class="text-center py-5" style="opacity: 0.4;">
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
                    <h5 v-if="!isEditing" class="m-0 bb-text-primary"><i class="bi bi-plus-circle me-2 text-success"></i>Tambah Paket</h5>
                    <h5 v-else class="m-0 bb-text-primary"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Paket</h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="bb-modal-body">
                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Nama Paket</label>
                            <input type="text" v-model="form.name" required placeholder="Contoh: VIP Malam" class="bb-input w-100 mt-1" />
                            <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="bb-label text-secondary">Harga per Jam (Rp)</label>
                            <input type="text" :value="formattedPrice" @input="handlePriceInput" required placeholder="50.000" class="bb-input w-100 mt-1" />
                            <div v-if="form.errors.price" class="small text-danger mt-1">{{ form.errors.price }}</div>
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
            title="Hapus Paket"
            message="Apakah Anda yakin ingin menghapus paket biliar ini? Aksi ini tidak dapat dibatalkan."
            confirmText="Ya, Hapus"
            @confirm="executeDelete"
            @cancel="confirmDelete.show = false"
        />

    </AuthenticatedLayout>
</template>
