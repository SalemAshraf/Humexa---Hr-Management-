<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

defineProps({ dark: { type: Boolean, default: false } });

const page = usePage();
const current = computed(() => page.props.locale ?? 'en');
const next = computed(() => (current.value === 'ar' ? 'en' : 'ar'));
const label = computed(() => (current.value === 'ar' ? 'English' : 'العربية'));

// A full reload applies the new language and text direction to the whole page.
const switchTo = () => router.post('/locale', { locale: next.value }, { preserveScroll: true, onSuccess: () => window.location.reload() });
</script>

<template>
    <button
        type="button"
        data-testid="language-switch"
        class="flex h-10 items-center rounded-xl border px-3 text-sm font-semibold transition-colors"
        :class="dark ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-slate-200 text-slate-700 hover:bg-slate-100'"
        :aria-label="current === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'"
        @click="switchTo"
    >
        {{ label }}
    </button>
</template>
