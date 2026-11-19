<script setup>
defineProps({
    show: Boolean,
    title: {
        type: String,
        default: 'Konfirmasi'
    },
    message: {
        type: String,
        default: 'Apakah Anda yakin ingin melanjutkan aksi ini?'
    },
    confirmText: {
        type: String,
        default: 'Ya, Lanjutkan'
    },
    cancelText: {
        type: String,
        default: 'Batal'
    },
    danger: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="show" class="bb-modal-backdrop" @click.self="emit('cancel')">
        <div class="bb-modal" style="max-width: 400px; width: 90%;">
            <div class="bb-modal-body p-4 text-center">
                <div class="mb-4" :class="danger ? 'text-danger' : 'text-warning'">
                    <i v-if="danger" class="bi bi-exclamation-octagon" style="font-size: 3rem;"></i>
                    <i v-else class="bi bi-question-circle" style="font-size: 3rem;"></i>
                </div>
                <h5 class="fw-bold mb-2 bb-text-primary">{{ title }}</h5>
                <p class="text-secondary small mb-4">{{ message }}</p>
                
                <div class="d-flex gap-2">
                    <button type="button" @click="emit('cancel')" class="bb-btn bb-btn--ghost flex-grow-1 py-2">
                        {{ cancelText }}
                    </button>
                    <button type="button" @click="emit('confirm')" class="bb-btn flex-grow-1 py-2" :class="danger ? 'bb-btn--danger' : 'bb-btn--primary'">
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
