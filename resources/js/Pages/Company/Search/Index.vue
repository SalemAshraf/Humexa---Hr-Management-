<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    term: { type: String, default: '' },
    groups: { type: Array, required: true },
    tooShort: { type: Boolean, default: false },
});

const q = ref(props.term);
const submit = () => router.get('/search', { q: q.value }, { preserveState: true, replace: true });
</script>

<template>
    <Head :title="$t('Search')" />

    <CompanyLayout :title="$t('Search')" :subtitle="$t('Find people, candidates and more')">
        <form class="mb-6" @submit.prevent="submit">
            <input v-model="q" autofocus type="search" :placeholder="$t('Search by name, employee number, email or phone')" class="w-full rounded-xl border-slate-300 text-sm shadow-sm" />
        </form>

        <p v-if="tooShort" class="mb-4 text-sm text-slate-500">{{ $t('Type at least 2 characters.') }}</p>

        <section v-for="g in groups" :key="g.label" class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">{{ g.label }}</h2>
            <ul class="divide-y divide-slate-100">
                <li v-for="(r, i) in g.results" :key="i">
                    <Link :href="r.url" class="flex items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50">
                        <span class="font-semibold text-slate-800">{{ r.title }}</span>
                        <span class="text-xs capitalize text-slate-500">{{ r.detail }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <p v-if="term && !tooShort && !groups.length" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
            {{ $t('Nothing found for "{term}" in the areas your role can open.', { term }) }}
        </p>
    </CompanyLayout>
</template>
