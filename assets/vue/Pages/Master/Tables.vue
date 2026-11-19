<script setup>
import { ref, computed, toRef } from 'vue';
import { Head, useForm, router } from '@/shims/inertia.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useDatatable } from '@/Composables/useDatatable';

const props = defineProps({
    tables: Array,
});

const tablesRef = toRef(props, 'tables');

const {
    searchQuery,
    currentPage,
    totalPages,
    paginatedData,
    nextPage,
    prevPage,
} = useDatatable(tablesRef, ['name', 'relay_channel']);

const isEditing = ref(false);
const editId = ref(null);
const showModal = ref(false);

const form = useForm({
    name: '',
    relay_channel: ''
});

const openAddModal = () => {
    isEditing.value = false;
    editId.value = null;
    form.reset();
    showModal.value = true;
};

const editTable = (table) => {
    isEditing.value = true;
    editId.value = table.id;
    form.name = table.name;
    form.relay_channel = table.relay_channel;
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('tables.update', editId.value), {
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('tables.store'), {
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
    
    router.delete(route('tables.destroy', confirmDelete.value.id), {
        onSuccess: () => {
            confirmDelete.value.show = false;
        }
    });
};
</script>

<template>
    <Head title="Kelola Meja" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-columns-gap me-2" style="color: #6366f1;"></i>Kelola Meja</h1>
        </template>

        <div class="row g-4">
            <!-- Table List -->
            <div class="col-12">
                <div class="bb-card">
                    <div class="bb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Daftar Meja</h6>
                            <span class="bb-badge" style="background: rgba(99,102,241,0.1); color: #6366f1;">{{ tables.length }} Meja</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center" style="min-width: 300px;">
                            <input type="text" v-model="searchQuery" placeholder="Cari meja..." class="bb-input form-control-sm py-2 flex-grow-1" />
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
                                    <th>Nama Meja</th>
                                    <th>Relay</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(table, index) in paginatedData" :key="table.id">
                                    <td class="ps-4 text-secondary">
                                        {{ (currentPage - 1) * 10 + index + 1 }}
                                    </td>
                                    <td class="fw-bold">{{ table.name }}</td>
                                    <td>
                                        <span class="bb-badge" style="background: rgba(6,182,212,0.1); color: #06b6d4;">
                                            <i class="bi bi-cpu"></i> CH {{ table.relay_channel }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="bb-status-badge" :class="table.status === 'active' ? 'bb-status-badge--active' : 'bb-status-badge--ready'">
                                            {{ table.status === 'active' ? 'Active' : 'Idle' }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button @click="editTable(table)" class="bb-btn bb-btn--ghost py-1 px-3 me-2" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button @click="openDeleteConfirm(table.id)" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                            <i class="bi bi-trash3 text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="paginatedData.length === 0">
                                    <td colspan="5" class="text-center py-5" style="opacity: 0.4;">
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
                    <h5 v-if="!isEditing" class="m-0 bb-text-primary"><i class="bi bi-plus-circle me-2 text-success"></i>Tambah Meja</h5>
                    <h5 v-else class="m-0 bb-text-primary"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Meja</h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="bb-modal-body">
                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="bb-label text-secondary">Nama Meja</label>
                            <input type="text" v-model="form.name" required placeholder="Contoh: Meja 5 VIP" class="bb-input w-100 mt-1" />
                            <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="bb-label text-secondary">Relay Channel (Hardware)</label>
                            <input type="number" v-model="form.relay_channel" required min="1" max="16" placeholder="Channel 1-16" class="bb-input w-100 mt-1" :class="{'border-danger': form.errors.relay_channel}" />
                            <div v-if="form.errors.relay_channel" class="small text-danger mt-1">{{ form.errors.relay_channel }}</div>
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
            title="Hapus Meja"
            message="Apakah Anda yakin ingin menghapus meja ini? Data sesi aktif yang terkait dengan meja ini akan terpengaruh dan aksi tidak dapat dibatalkan."
            confirmText="Ya, Hapus"
            @confirm="executeDelete"
            @cancel="confirmDelete.show = false"
        />

    </AuthenticatedLayout>
</template>
