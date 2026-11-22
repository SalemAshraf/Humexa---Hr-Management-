<script setup>
import { computed } from 'vue';

const props = defineProps({
    code: { type: String, default: '' },
    fallback: { type: String, default: '?' },
});

// Stroke icons (24x24 grid), keyed by the short code each nav item already carries.
const icons = {
    OV: ['M3 12l9-9 9 9', 'M5 10v10h14V10', 'M10 20v-6h4v6'],
    ME: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21a8 8 0 0 1 16 0'],
    EM: ['M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'M2.5 20a6.5 6.5 0 0 1 13 0', 'M16 4.5a3.5 3.5 0 0 1 0 6.5', 'M18 14a6 6 0 0 1 3.5 6'],
    OR: ['M12 3v5', 'M5 21v-5h14v5', 'M5 16v-4h14v4', 'M10 3h4v5h-4z', 'M3 21h18'],
    DX: ['M6 3h8l5 5v13H6z', 'M14 3v5h5', 'M12 12v4', 'M12 18.5v.01'],
    AT: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7v5l3 2'],
    LE: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
    PA: ['M3 7h18v12H3z', 'M3 11h18', 'M7 15h3'],
    RE: ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z', 'M21 21l-5-5'],
    PE: ['M4 20V10', 'M10 20V4', 'M16 20v-8', 'M22 20H2'],
    RP: ['M6 3h12v18H6z', 'M9 8h6', 'M9 12h6', 'M9 16h4'],
    NO: ['M6 9a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7', 'M10 20a2 2 0 0 0 4 0'],
    TR: ['M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z', 'M9 12l2 2 4-4'],
    SE: ['M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14 3h-4l-.6 2.7a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2 1.2L10 21h4l.6-2.7a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z'],
    AC: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z', 'M4 21a8 8 0 0 1 16 0'],
    CO: ['M4 21V5l8-2v18', 'M12 9l8 2v10', 'M2 21h20', 'M7 9h2', 'M7 13h2', 'M7 17h2'],
    PL: ['M12 3l9 5-9 5-9-5z', 'M3 13l9 5 9-5', 'M3 17.5l9 5 9-5'],
    BI: ['M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z', 'M9 8h6', 'M9 12h6'],
    US: ['M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'M2.5 20a6.5 6.5 0 0 1 13 0', 'M16 4.5a3.5 3.5 0 0 1 0 6.5', 'M18 14a6 6 0 0 1 3.5 6'],
};

// Platform "Activity Logs" and "Account" both use AC in the layouts; the platform one reads as a log.
const paths = computed(() => icons[props.code] ?? null);
</script>

<template>
    <svg v-if="paths" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="d in paths" :key="d" :d="d" />
    </svg>
    <span v-else>{{ fallback }}</span>
</template>
