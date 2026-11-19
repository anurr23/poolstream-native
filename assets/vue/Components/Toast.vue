<script setup>
import { computed, watch, ref, onMounted } from 'vue';
import { usePage } from '@/shims/inertia.js';

const page = usePage();
const flashSuccess = computed(() => page.props.flash.success);
const flashError = computed(() => page.props.flash.error);

const show = ref(false);
const message = ref('');
const type = ref('success');
let timeout = null;

const displayToast = (msg, msgType) => {
    if (!msg) return;
    
    message.value = msg;
    type.value = msgType;
    show.value = true;
    
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => {
        show.value = false;
    }, 4000);
};

watch(flashSuccess, (newVal) => displayToast(newVal, 'success'));
watch(flashError, (newVal) => displayToast(newVal, 'error'));

onMounted(() => {
    if (flashSuccess.value) displayToast(flashSuccess.value, 'success');
    else if (flashError.value) displayToast(flashError.value, 'error');
});

const closeToast = () => {
    show.value = false;
    if (timeout) clearTimeout(timeout);
};
</script>

<template>
    <div class="bb-toast-container">
        <Transition name="toast">
            <div v-if="show" class="bb-toast" :class="`bb-toast--${type}`">
                <div class="bb-toast-icon">
                    <i v-if="type === 'success'" class="bi bi-check-circle-fill"></i>
                    <i v-else-if="type === 'error'" class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div class="bb-toast-content">
                    {{ message }}
                </div>
                <button type="button" class="bb-toast-close" @click="closeToast">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.bb-toast-container {
    position: fixed;
    top: 2rem;
    right: 2rem;
    z-index: 1060;
    pointer-events: none;
}

.bb-toast {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0,0,0,0.05);
    pointer-events: auto;
    min-width: 280px;
    max-width: 400px;
}

[data-bs-theme="dark"] .bb-toast {
    background: rgba(30, 41, 59, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.bb-toast--success .bb-toast-icon {
    color: var(--bb-accent);
    font-size: 1.25rem;
}

.bb-toast--error .bb-toast-icon {
    color: var(--bs-danger);
    font-size: 1.25rem;
}

.bb-toast-content {
    flex-grow: 1;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--bs-body-color);
}

.bb-toast-close {
    background: none;
    border: none;
    color: var(--bs-secondary-color);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: color 0.2s ease;
}

.bb-toast-close:hover {
    color: var(--bs-body-color);
}

/* Transitions */
.toast-enter-active,
.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
