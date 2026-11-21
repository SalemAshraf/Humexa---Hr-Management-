<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import { formatDate } from '@/lib/date';

const props = defineProps({
    cycles: { type: Array, required: true },
    selected: { type: Object, default: null },
    reviews: { type: Array, required: true },
    candidates: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    abilities: { type: Object, required: true },
    scope: { type: String, required: true },
});

const statusStyles = {
    draft: 'bg-slate-100 text-slate-600',
    submitted: 'bg-amber-50 text-amber-700',
    finalized: 'bg-emerald-50 text-emerald-700',
};

const pickCycle = (id) => router.get('/performance', { cycle: id }, { preserveState: true, replace: true });

const editingCycle = ref(null);
const showCycle = ref(false);
const cycleForm = useForm({ name: '', starts_on: '', ends_on: '', status: 'open' });
const startCycle = (c = null) => {
    editingCycle.value = c?.id ?? null;
    cycleForm.defaults(c ? { name: c.name, starts_on: c.starts_on, ends_on: c.ends_on, status: c.status } : { name: '', starts_on: '', ends_on: '', status: 'open' }).reset();
    showCycle.value = true;
};
const saveCycle = () => {
    const opts = { preserveScroll: true, onSuccess: () => { showCycle.value = false; } };
    editingCycle.value ? cycleForm.put(`/performance/cycles/${editingCycle.value}`, opts) : cycleForm.post('/performance/cycles', opts);
};
const removeCycle = (c) => confirm(`Delete "${c.name}"?`) && router.delete(`/performance/cycles/${c.id}`);

const newEmployee = ref('');
const startReview = () => router.post('/performance/reviews', { review_cycle_id: props.selected.id, employee_id: newEmployee.value }, {
    preserveScroll: true, onSuccess: () => { newEmployee.value = ''; },
});

const open = ref(null);
const drafts = reactive({});
const edit = (r) => { drafts[r.id] = { rating: r.rating ?? '', strengths: r.strengths ?? '', improvements: r.improvements ?? '', goals: r.goals ?? '' }; open.value = open.value === r.id ? null : r.id; };
const save = (r, status) => router.put(`/performance/reviews/${r.id}`, { ...drafts[r.id], rating: drafts[r.id].rating || null, status }, { preserveScroll: true, onSuccess: () => { open.value = null; } });
const finalize = (r) => confirm(`Finalize the review for ${r.employee}? It cannot be edited afterwards.`) && router.post(`/performance/reviews/${r.id}/finalize`, {}, { preserveScroll: true });
const reopen = (r) => confirm(`Send the review for ${r.employee} back to draft so the manager can change it?`) && router.post(`/performance/reviews/${r.id}/reopen`, {}, { preserveScroll: true });
const removeReview = (r) => confirm(`Delete the review for ${r.employee}?`) && router.delete(`/performance/reviews/${r.id}`, { preserveScroll: true });

</script>

<template>
    <Head title="Performance" />

    <CompanyLayout title="Performance" subtitle="Review cycles and ratings">
        <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">PEOPLE</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Performance reviews</h1>
                <p class="mt-2 text-sm text-slate-500">{{ scope === 'company' ? 'All employees' : 'Your direct reports' }}</p>
            </div>
            <button v-if="abilities.manage" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="startCycle()">New cycle</button>
        </section>

        <section v-if="showCycle" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ editingCycle ? 'Edit cycle' : 'New cycle' }}</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="saveCycle">
                <label class="text-xs font-medium text-slate-500 md:col-span-2">Name
                    <input v-model="cycleForm.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500">Starts
                    <input v-model="cycleForm.starts_on" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500">Ends
                    <input v-model="cycleForm.ends_on" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500">Status
                    <select v-model="cycleForm.status" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="open">Open</option><option value="closed">Closed</option></select>
                </label>
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="cycleForm.processing">Save cycle</button>
                    <button type="button" class="px-4 py-2 text-sm font-semibold text-slate-500" @click="showCycle = false">Cancel</button>
                </div>
            </form>
            <p v-for="(m, k) in cycleForm.errors" :key="k" class="mt-2 text-xs text-red-600">{{ m }}</p>
        </section>

        <p v-for="(m, k) in $page.props.errors" :key="k" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ m }}</p>

        <section v-if="cycles.length" class="mb-6 flex flex-wrap items-center gap-2">
            <button v-for="c in cycles" :key="c.id" class="rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="selected?.id === c.id ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'" @click="pickCycle(c.id)">
                {{ c.name }} <span class="opacity-60">· {{ c.status }}</span>
            </button>
        </section>

        <template v-if="selected">
            <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">Reviews</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ summary.total }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">Waiting for HR</p><p class="mt-1 text-2xl font-bold text-amber-600">{{ summary.submitted }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">Finalized</p><p class="mt-1 text-2xl font-bold text-emerald-700">{{ summary.finalized }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">Average rating</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ summary.average ?? '—' }}</p></article>
            </section>

            <section class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                <p>{{ selected.name }} · {{ formatDate(selected.starts_on) }} to {{ formatDate(selected.ends_on) }}</p>
                <div v-if="abilities.manage" class="flex gap-3 text-xs font-semibold">
                    <button class="text-cyan-700" @click="startCycle(selected)">Edit cycle</button>
                    <button class="text-red-600" @click="removeCycle(selected)">Delete cycle</button>
                </div>
            </section>

            <section v-if="candidates.length" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <select v-model="newEmployee" class="min-w-56 rounded-lg border-slate-300 text-sm">
                    <option value="" disabled>Choose an employee to review</option>
                    <option v-for="e in candidates" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!newEmployee" @click="startReview">Start review</button>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <ul class="divide-y divide-slate-100">
                    <li v-for="r in reviews" :key="r.id" class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-800">{{ r.employee }} <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="statusStyles[r.status]">{{ r.status }}</span></p>
                                <p class="text-xs text-slate-400">{{ r.employee_number }}<span v-if="r.rating"> · Rating {{ r.rating }}/5</span></p>
                            </div>
                            <div class="flex items-center gap-3 text-xs font-semibold">
                                <button v-if="r.can_edit" class="text-cyan-700" @click="edit(r)">{{ open === r.id ? 'Close' : 'Write review' }}</button>
                                <button v-if="abilities.manage && r.status === 'submitted' && selected.status === 'open'" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-white" @click="finalize(r)">Finalize</button>
                                <button v-if="abilities.manage && r.status === 'submitted' && selected.status === 'open'" class="text-slate-600" @click="reopen(r)">Send back</button>
                                <button v-if="abilities.manage && r.status === 'draft'" class="text-red-600" @click="removeReview(r)">Delete</button>
                            </div>
                        </div>
                        <dl v-if="r.strengths || r.improvements || r.goals" class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 md:grid-cols-3">
                            <div v-if="r.strengths"><dt class="font-semibold text-slate-500">Strengths</dt><dd>{{ r.strengths }}</dd></div>
                            <div v-if="r.improvements"><dt class="font-semibold text-slate-500">To improve</dt><dd>{{ r.improvements }}</dd></div>
                            <div v-if="r.goals"><dt class="font-semibold text-slate-500">Goals</dt><dd>{{ r.goals }}</dd></div>
                        </dl>
                        <form v-if="open === r.id" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3" @submit.prevent="save(r, 'submitted')">
                            <label class="text-xs font-medium text-slate-500 md:col-span-3">Rating (1 low, 5 high)
                                <select v-model="drafts[r.id].rating" class="mt-1 w-32 rounded-lg border-slate-300 text-sm"><option value="">Not rated</option><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
                            </label>
                            <label class="text-xs font-medium text-slate-500">Strengths<textarea v-model="drafts[r.id].strengths" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                            <label class="text-xs font-medium text-slate-500">To improve<textarea v-model="drafts[r.id].improvements" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                            <label class="text-xs font-medium text-slate-500">Goals for next period<textarea v-model="drafts[r.id].goals" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                            <div class="flex gap-2 md:col-span-3">
                                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Submit review</button>
                                <button type="button" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200" @click="save(r, 'draft')">Save draft</button>
                            </div>
                        </form>
                    </li>
                    <li v-if="!reviews.length" class="p-8 text-center text-sm text-slate-500">No reviews in this cycle yet. Pick an employee above to start one.</li>
                </ul>
            </section>
        </template>
        <p v-else class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
            No review cycle yet. <span v-if="abilities.manage">Create one to start collecting reviews.</span><span v-else>HR will open one.</span>
        </p>
    </CompanyLayout>
</template>
