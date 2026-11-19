<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@/shims/inertia.js';
import { ref, computed } from 'vue';
import { useDatatable } from '@/Composables/useDatatable';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BbSelect from '@/Components/BbSelect.vue';

const props = defineProps({
    items: Array
});

const activeCategory = ref('Semua');
const showModal = ref(false);

const filteredItems = computed(() => {
    if (activeCategory.value === 'Semua') return props.items;
    return props.items.filter(item => item.category === activeCategory.value);
});

const { 
    searchQuery, currentPage, totalPages, paginatedData, nextPage, prevPage, goToPage 
} = useDatatable(filteredItems, ['name', 'category']);

const isEditing = ref(false);
const editId = ref(null);
const imagePreview = ref(null);
const fileInput = ref(null);

const categories = [
    { label: 'Makanan', value: 'Makanan' },
    { label: 'Minuman', value: 'Minuman' },
    { label: 'Snack', value: 'Snack' },
    { label: 'Lainnya', value: 'Lainnya' }
];

const form = useForm({
    _method: 'post',
    name: '',
    category: '',
    price: '',
    image: null
});

const formattedPrice = ref('');

const handlePriceInput = (e) => {
    let val = e.target.value.replace(/\D/g, '');
    form.price = val;
    formattedPrice.value = val ? new Intl.NumberFormat('id-ID').format(val) : '';
};

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    imagePreview.value = URL.createObjectURL(file);

    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
        const img = new Image();
        img.src = e.target.result;
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 800;
            const MAX_HEIGHT = 800;
            let width = img.width;
            let height = img.height;

            if (width > height) {
                if (width > MAX_WIDTH) {
                    height *= MAX_WIDTH / width;
                    width = MAX_WIDTH;
                }
            } else {
                if (height > MAX_HEIGHT) {
                    width *= MAX_HEIGHT / height;
                    height = MAX_HEIGHT;
                }
            }

            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob((blob) => {
                const compressedFile = new File([blob], file.name, {
                    type: file.type,
                    lastModified: Date.now()
                });
                form.image = compressedFile;
            }, file.type, 0.85);
        };
    };
};

const submit = () => {
    if (isEditing.value) {
        form._method = 'put';
        form.post(route('fnb_items.update', editId.value), {
            onSuccess: () => cancelEdit(),
            forceFormData: true
        });
    } else {
        form._method = 'post';
        form.post(route('fnb_items.store'), {
            onSuccess: () => cancelEdit(),
            forceFormData: true
        });
    }
};

const openAddModal = () => {
    isEditing.value = false;
    form.reset();
    formattedPrice.value = '';
    imagePreview.value = null;
    showModal.value = true;
};

const editItem = (item) => {
    isEditing.value = true;
    editId.value = item.id;
    form.name = item.name;
    form.category = item.category || '';
    form.price = item.price;
    formattedPrice.value = item.price ? new Intl.NumberFormat('id-ID').format(item.price) : '';
    form.image = null;
    imagePreview.value = item.image_url || null;
    if (fileInput.value) fileInput.value.value = '';
    showModal.value = true;
};

const cancelEdit = () => {
    isEditing.value = false;
    editId.value = null;
    imagePreview.value = null;
    if (fileInput.value) fileInput.value.value = '';
    form.reset();
    formattedPrice.value = '';
    showModal.value = false;
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
    
    router.delete(route('fnb_items.destroy', confirmDelete.value.id), {
        onSuccess: () => {
            if(isEditing.value && editId.value === confirmDelete.value.id) {
                cancelEdit();
            }
            confirmDelete.value.show = false;
        }
    });
};
</script>

<template>
    <Head title="Kelola Makanan & Minuman" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="bb-header-title"><i class="bi bi-cup-straw me-2" style="color: #f59e0b;"></i>Kelola Makanan & Minuman</h1>
        </template>

        <!-- Category Tabs -->
        <div class="mb-4" style="overflow-x: auto; white-space: nowrap; padding-bottom: 5px;">
            <ul class="nav nav-pills flex-nowrap" style="gap: 8px;">
                <li class="nav-item">
                    <a class="nav-link bb-tab-link" :class="{ 'active': activeCategory === 'Semua' }" href="#" @click.prevent="activeCategory = 'Semua'">
                        <i class="bi bi-grid-fill me-1"></i> Semua
                    </a>
                </li>
                <li class="nav-item" v-for="cat in categories" :key="cat.value">
                    <a class="nav-link bb-tab-link" :class="{ 'active': activeCategory === cat.value }" href="#" @click.prevent="activeCategory = cat.value">
                        {{ cat.label }}
                    </a>
                </li>
            </ul>
        </div>

        <div class="row g-4">
            <!-- Item List -->
            <div class="col-12">
                <div class="bb-card">
                    <div class="bb-card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Daftar Menu {{ activeCategory !== 'Semua' ? activeCategory : 'F&B' }}</h6>
                            <span class="bb-badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">{{ filteredItems.length }} Menu</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <div style="min-width: 250px;">
                                <input type="text" v-model="searchQuery" placeholder="Cari nama..." class="bb-input py-2" />
                            </div>
                            <button @click="openAddModal" class="bb-btn bb-btn--primary py-2 px-4 text-nowrap">
                                <i class="bi bi-plus-lg me-2"></i> Tambah Menu
                            </button>
                        </div>
                    </div>
                    <div class="bb-card-body p-0">
                        <div class="table-responsive">
                            <table class="bb-table">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Menu</th>
                                        <th>Kategori</th>
                                        <th>Harga</th>
                                        <th class="text-end pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in paginatedData" :key="item.id">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="bb-avatar" style="width: 48px; height: 48px; border-radius: 12px; overflow: hidden; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center;">
                                                    <img v-if="item.image_url" :src="item.image_url" alt="img" style="width: 100%; height: 100%; object-fit: cover;" />
                                                    <i v-else class="bi bi-image text-secondary" style="font-size: 1.5rem;"></i>
                                                </div>
                                                <span class="fw-bold">{{ item.name }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="bb-badge" style="background: rgba(16,185,129,0.1); color: #10b981;">
                                                {{ item.category || '-' }}
                                            </span>
                                        </td>
                                        <td class="font-monospace">Rp {{ Number(item.price).toLocaleString('id-ID') }}</td>
                                        <td class="text-end pe-4">
                                            <button @click="editItem(item)" class="bb-btn bb-btn--ghost py-1 px-3 me-2" style="font-size: 0.8rem; text-transform: none;">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button @click="openDeleteConfirm(item.id)" class="bb-btn bb-btn--ghost py-1 px-3" style="font-size: 0.8rem; text-transform: none;">
                                                <i class="bi bi-trash3 text-danger"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="paginatedData.length === 0">
                                        <td colspan="4" class="text-center py-5" style="opacity: 0.4;">
                                            <i class="bi bi-inbox d-block mb-2" style="font-size: 2rem;"></i>
                                            Pencarian tidak ditemukan
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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

        <!-- Add/Edit Modal Overlay -->
        <div v-if="showModal" class="bb-modal-backdrop" @click.self="cancelEdit">
            <div class="bb-modal">
                <div class="bb-modal-header">
                    <h5 v-if="!isEditing" class="m-0 bb-text-primary"><i class="bi bi-plus-circle me-2 text-success"></i>Tambah Menu</h5>
                    <h5 v-else class="m-0 bb-text-primary"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Menu</h5>
                    <button type="button" class="btn-close" @click="cancelEdit"></button>
                </div>
                <div class="bb-modal-body">
                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="bb-label">Nama Menu</label>
                            <input type="text" v-model="form.name" required placeholder="Contoh: Kopi Hitam" class="bb-input" :class="{'border-danger': form.errors.name}" />
                            <div v-if="form.errors.name" class="small text-danger mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="bb-label">Kategori</label>
                            <BbSelect 
                                v-model="form.category" 
                                :options="categories" 
                                placeholder="Pilih Kategori"
                                :error="!!form.errors.category"
                            />
                            <div v-if="form.errors.category" class="small text-danger mt-1">{{ form.errors.category }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="bb-label">Harga (Rp)</label>
                            <input type="text" :value="formattedPrice" @input="handlePriceInput" required placeholder="Contoh: 15.000" class="bb-input" :class="{'border-danger': form.errors.price}" />
                            <div v-if="form.errors.price" class="small text-danger mt-1">{{ form.errors.price }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="bb-label">Gambar Menu</label>
                            <div class="bb-image-upload" style="border: 2px dashed rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer;" @click="$refs.fileInput.click()">
                                <input type="file" ref="fileInput" @change="handleImageUpload" accept="image/jpeg,image/png,image/webp" style="display: none;" />
                                <div v-if="!imagePreview" class="text-secondary">
                                    <i class="bi bi-cloud-arrow-up d-block mb-2" style="font-size: 2rem;"></i>
                                    <span>Klik untuk upload gambar<br><small>(JPG, PNG, WEBP)</small></span>
                                </div>
                                <div v-else class="position-relative">
                                    <img :src="imagePreview" alt="Preview" style="max-height: 150px; border-radius: 8px; max-width: 100%; object-fit: cover;" />
                                </div>
                            </div>
                            <div v-if="form.errors.image" class="small text-danger mt-1">{{ form.errors.image }}</div>
                        </div>
                        <div class="d-flex gap-2 pt-2">
                            <button v-if="isEditing" type="button" @click="cancelEdit" class="bb-btn bb-btn--ghost flex-grow-1 py-3">
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
            title="Hapus Menu F&B"
            message="Apakah Anda yakin ingin menghapus menu F&B ini? Aksi ini tidak dapat dibatalkan."
            confirmText="Ya, Hapus"
            @confirm="executeDelete"
            @cancel="confirmDelete.show = false"
        />

    </AuthenticatedLayout>
</template>

<style scoped>
.bb-tab-link {
    border-radius: 100px;
    padding: 8px 20px;
    border: 1px solid var(--bs-border-color);
    background-color: var(--bs-tertiary-bg);
    color: var(--bs-secondary-color);
    transition: all 0.3s ease;
    font-weight: 500;
}
.bb-tab-link:hover {
    background-color: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}
.bb-tab-link.active {
    background-color: var(--bb-accent) !important;
    color: #ffffff !important;
    border-color: var(--bb-accent) !important;
}
</style>
