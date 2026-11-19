<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Pilih salah satu...'
    },
    error: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const selectRef = ref(null);
const triggerRef = ref(null);

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        triggerRef.value?.focus();
    }
};

const selectOption = (option) => {
    emit('update:modelValue', option.value);
    isOpen.value = false;
    triggerRef.value?.blur();
};

const handleClickOutside = (event) => {
    if (selectRef.value && !selectRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="bb-custom-select" ref="selectRef" :class="{ 'is-open': isOpen, 'has-error': error }">
        <div 
            class="bb-select-trigger bb-input" 
            @click="toggleDropdown" 
            :class="{'border-danger': error, 'focused': isOpen}"
            tabindex="0"
            ref="triggerRef"
            @keydown.enter.prevent="toggleDropdown"
            @keydown.esc.prevent="isOpen = false"
        >
            <span class="selected-text" :style="{ opacity: !modelValue ? 0.6 : 1 }">
                {{ (options || []).find(opt => opt.value === modelValue)?.label || placeholder }}
            </span>
            <i class="bi bi-chevron-down transition-transform" :style="{ transform: isOpen ? 'rotate(180deg)' : 'rotate(0)' }"></i>
        </div>

        <transition name="dropdown">
            <div v-if="isOpen" class="bb-select-dropdown">
                <div 
                    class="bb-select-option" 
                    @click="selectOption({ value: '', label: placeholder })"
                    :class="{ 'active': modelValue === '' }"
                >
                    {{ placeholder }}
                </div>
                <div 
                    v-for="option in (options || [])" 
                    :key="option.value" 
                    class="bb-select-option"
                    :class="{ 'active': modelValue === option.value }"
                    @click="selectOption(option)"
                >
                    {{ option.label }}
                </div>
            </div>
        </transition>
    </div>
</template>

<style>
/* Unscoped to guarantee theme selector works correctly on html root */
.bb-custom-select {
    position: relative;
    width: 100%;

    /* Default Light Theme Variables */
    --bbs-bg: rgba(255, 255, 255, 0.95);
    --bbs-border: rgba(0, 0, 0, 0.1);
    --bbs-text: #374151;
    --bbs-hover: rgba(0, 0, 0, 0.05);
    --bbs-scroll: rgba(0, 0, 0, 0.15);
    --bbs-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

[data-bs-theme="dark"] .bb-custom-select {
    /* Dark Theme Variables */
    --bbs-bg: rgba(17, 24, 39, 0.95);
    --bbs-border: rgba(255, 255, 255, 0.1);
    --bbs-text: #e5e7eb;
    --bbs-hover: rgba(255, 255, 255, 0.05);
    --bbs-scroll: rgba(255, 255, 255, 0.1);
    --bbs-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.bb-select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    transition: all 0.3s ease;
}

/* Simulate focus state when dropdown is open */
.bb-select-trigger.focused {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    background: #fff;
}
[data-bs-theme="dark"] .bb-select-trigger.focused {
    background: rgba(255,255,255,0.06);
    border-color: #10b981;
}

.transition-transform {
    transition: transform 0.3s ease;
}

.bb-select-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--bbs-bg);
    backdrop-filter: blur(12px);
    border: 1px solid var(--bbs-border);
    border-radius: 12px;
    padding: 8px;
    z-index: 100;
    box-shadow: var(--bbs-shadow);
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 250px;
    overflow-y: auto;
}

/* Custom Scrollbar for dropdown */
.bb-select-dropdown::-webkit-scrollbar {
    width: 6px;
}
.bb-select-dropdown::-webkit-scrollbar-track {
    background: transparent;
}
.bb-select-dropdown::-webkit-scrollbar-thumb {
    background: var(--bbs-scroll);
    border-radius: 10px;
}

.bb-select-option {
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--bbs-text);
}

.bb-select-option:hover {
    background: var(--bbs-hover);
}

.bb-select-option.active {
    background: rgba(16, 185, 129, 0.15); /* Emerald tint */
    color: #10b981;
    font-weight: 600;
}

/* Transitions */
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
</style>
