<script setup>
defineProps({
    type: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <!-- Admin: panel dashboard -->
    <svg v-if="type === 'admin'" viewBox="0 0 160 120" fill="none" class="bb-illustration">
        <rect x="18" y="14" width="124" height="92" rx="12" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.35" stroke-width="2" />
        <circle cx="31" cy="26" r="2.5" fill="currentColor" fill-opacity="0.5" />
        <circle cx="40" cy="26" r="2.5" fill="currentColor" fill-opacity="0.35" />
        <circle cx="49" cy="26" r="2.5" fill="currentColor" fill-opacity="0.25" />
        <rect x="30" y="38" width="24" height="56" rx="6" fill="currentColor" fill-opacity="0.22" />
        <circle cx="42" cy="50" r="5" fill="currentColor" fill-opacity="0.55" />
        <rect x="35" y="62" width="14" height="3" rx="1.5" fill="currentColor" fill-opacity="0.5" />
        <rect x="35" y="70" width="10" height="3" rx="1.5" fill="currentColor" fill-opacity="0.35" />
        <rect x="35" y="78" width="12" height="3" rx="1.5" fill="currentColor" fill-opacity="0.25" />
        <rect x="62" y="38" width="68" height="14" rx="5" fill="currentColor" fill-opacity="0.2" />
        <rect x="62" y="60" width="68" height="34" rx="7" fill="currentColor" fill-opacity="0.1" />
        <rect x="70" y="78" width="8" height="10" rx="2" fill="currentColor" fill-opacity="0.55" />
        <rect x="83" y="70" width="8" height="18" rx="2" fill="currentColor" fill-opacity="0.75" />
        <rect x="96" y="82" width="8" height="6" rx="2" fill="currentColor" fill-opacity="0.45" />
        <rect x="109" y="74" width="8" height="14" rx="2" fill="currentColor" fill-opacity="0.6" />
    </svg>

    <!-- Kasir Meja: meja biliar -->
    <svg v-else-if="type === 'meja'" viewBox="0 0 160 120" fill="none" class="bb-illustration">
        <rect x="10" y="22" width="140" height="76" rx="12" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="2.5" />
        <rect x="19" y="31" width="122" height="58" rx="7" stroke="currentColor" stroke-opacity="0.22" stroke-width="1.5" />
        <circle cx="10" cy="22" r="4.5" fill="currentColor" fill-opacity="0.7" />
        <circle cx="150" cy="22" r="4.5" fill="currentColor" fill-opacity="0.7" />
        <circle cx="10" cy="98" r="4.5" fill="currentColor" fill-opacity="0.7" />
        <circle cx="150" cy="98" r="4.5" fill="currentColor" fill-opacity="0.7" />
        <circle cx="80" cy="22" r="3" fill="currentColor" fill-opacity="0.45" />
        <circle cx="80" cy="98" r="3" fill="currentColor" fill-opacity="0.45" />
        <circle cx="52" cy="66" r="9" fill="#ef4444" />
        <circle cx="76" cy="52" r="9" fill="#f8fafc" fill-opacity="0.9" stroke="currentColor" stroke-opacity="0.3" stroke-width="1.5" />
        <circle cx="99" cy="70" r="9" fill="#f59e0b" />
        <circle cx="86" cy="84" r="8" fill="#0ea5e9" />
        <path d="M132 110 L100 80" stroke="currentColor" stroke-opacity="0.55" stroke-width="5" stroke-linecap="round" />
    </svg>

    <!-- Customer: kalender booking -->
    <svg v-else-if="type === 'customer'" viewBox="0 0 160 120" fill="none" class="bb-illustration">
        <rect x="22" y="20" width="116" height="88" rx="10" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.4" stroke-width="2" />
        <rect x="22" y="20" width="116" height="20" rx="10" fill="currentColor" fill-opacity="0.25" />
        <rect x="22" y="30" width="116" height="10" fill="currentColor" fill-opacity="0.25" />
        <rect x="40" y="14" width="6" height="14" rx="3" fill="currentColor" fill-opacity="0.55" />
        <rect x="114" y="14" width="6" height="14" rx="3" fill="currentColor" fill-opacity="0.55" />
        <circle cx="46" cy="56" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="64" cy="56" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="82" cy="56" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="100" cy="56" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="118" cy="56" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="46" cy="74" r="4" fill="currentColor" fill-opacity="0.35" />
        <rect x="60" y="68" width="24" height="24" rx="6" fill="currentColor" fill-opacity="0.75" />
        <circle cx="72" cy="76" r="4" fill="#ffffff" />
        <path d="M64 88 C64 82, 68 80, 72 80 C76 80, 80 82, 80 88 Z" fill="#ffffff" />
        <circle cx="100" cy="74" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="118" cy="74" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="46" cy="92" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="64" cy="92" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="82" cy="92" r="4" fill="currentColor" fill-opacity="0.35" />
        <circle cx="100" cy="92" r="4" fill="currentColor" fill-opacity="0.35" />
    </svg>

    <!-- Kasir F&B: kopi + struk -->
    <svg v-else viewBox="0 0 160 120" fill="none" class="bb-illustration">
        <path d="M36 26 q5 -7 0 -14 M48 26 q5 -7 0 -14 M60 26 q5 -7 0 -14" stroke="currentColor" stroke-opacity="0.4" stroke-width="3" stroke-linecap="round" />
        <path d="M22 38 H70 L65 84 A9 9 0 0 1 56 92 H36 A9 9 0 0 1 27 84 Z" fill="currentColor" fill-opacity="0.12" stroke="currentColor" stroke-opacity="0.5" stroke-width="2.5" />
        <path d="M26 54 H66 L63 68 H29 Z" fill="currentColor" fill-opacity="0.45" />
        <path d="M70 48 H78 A11 11 0 0 1 78 70 H67" stroke="currentColor" stroke-opacity="0.5" stroke-width="3" stroke-linecap="round" />
        <ellipse cx="46" cy="98" rx="36" ry="4.5" fill="currentColor" fill-opacity="0.18" />
        <rect x="94" y="28" width="44" height="64" rx="7" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-opacity="0.5" stroke-width="2" />
        <rect x="103" y="40" width="26" height="4" rx="2" fill="currentColor" fill-opacity="0.5" />
        <rect x="103" y="52" width="18" height="4" rx="2" fill="currentColor" fill-opacity="0.35" />
        <rect x="103" y="64" width="26" height="4" rx="2" fill="currentColor" fill-opacity="0.35" />
        <rect x="103" y="78" width="14" height="4" rx="2" fill="currentColor" fill-opacity="0.6" />
    </svg>
</template>
