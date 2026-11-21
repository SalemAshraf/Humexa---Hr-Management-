<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    jobs: { type: Array, required: true },
    candidates: { type: Object, required: true },
    funnel: { type: Array, required: true },
    filters: { type: Object, required: true },
    departments: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    abilities: { type: Object, required: true },
});

const label = (v) => String(v).replace(/_/g, ' ');
const stageStyles = {
    applied: 'bg-slate-100 text-slate-600',
    screening: 'bg-sky-50 text-sky-700',
    interview: 'bg-indigo-50 text-indigo-700',
    offer: 'bg-amber-50 text-amber-700',
    hired: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
};
const jobStatusStyles = {
    draft: 'bg-slate-100 text-slate-600',
    open: 'bg-emerald-50 text-emerald-700',
    on_hold: 'bg-amber-50 text-amber-700',
    closed: 'bg-slate-200 text-slate-500',
};

const filterBy = (patch) => router.get('/recruitment', { ...props.filters, ...patch }, { preserveState: true, replace: true });

const editingJob = ref(null);
const blankJob = () => ({ title: '', department_id: '', branch_id: '', employment_type: 'full_time', positions: 1, status: 'open', description: '' });
const jobForm = useForm(blankJob());
const showJobForm = ref(false);
const startJob = (job = null) => {
    editingJob.value = job?.id ?? null;
    jobForm.defaults(job ? {
        title: job.title, department_id: job.department_id ?? '', branch_id: job.branch_id ?? '',
        employment_type: job.employment_type, positions: job.positions, status: job.status, description: job.description ?? '',
    } : blankJob()).reset();
    showJobForm.value = true;
};
const saveJob = () => {
    const opts = { preserveScroll: true, onSuccess: () => { showJobForm.value = false; } };
    editingJob.value ? jobForm.put(`/recruitment/jobs/${editingJob.value}`, opts) : jobForm.post('/recruitment/jobs', opts);
};
const removeJob = (job) => confirm(`Delete "${job.title}"?`) && router.delete(`/recruitment/jobs/${job.id}`, { preserveScroll: true });

const showCandidateForm = ref(false);
const candForm = useForm({ job_opening_id: props.filters.job ?? '', name: '', email: '', phone: '', source: '', rating: '', expected_salary: '', notes: '' });
const saveCandidate = () => candForm.post('/recruitment/candidates', {
    preserveScroll: true,
    onSuccess: () => { candForm.reset('name', 'email', 'phone', 'source', 'rating', 'expected_salary', 'notes'); showCandidateForm.value = false; },
});

const editingCand = ref(null);
const editForm = useForm({ name: '', email: '', phone: '', source: '', rating: '', expected_salary: '', notes: '' });
const startEdit = (c) => {
    editingCand.value = editingCand.value === c.id ? null : c.id;
    editForm.defaults({ name: c.name, email: c.email ?? '', phone: c.phone ?? '', source: c.source ?? '', rating: c.rating ?? '', expected_salary: c.expected_salary ?? '', notes: c.notes ?? '' }).reset();
};
const saveEdit = (c) => editForm.put(`/recruitment/candidates/${c.id}`, { preserveScroll: true, onSuccess: () => { editingCand.value = null; } });

const move = (c, stage) => router.post(`/recruitment/candidates/${c.id}/move`, { stage }, { preserveScroll: true });
const hire = (c) => confirm(`Create a draft employee record for ${c.name}?`) && router.post(`/recruitment/candidates/${c.id}/hire`);
const removeCandidate = (c) => confirm(`Delete ${c.name}?`) && router.delete(`/recruitment/candidates/${c.id}`, { preserveScroll: true });
const openJobs = computed(() => props.jobs.filter((j) => j.status === 'open').length);
const errors = computed(() => Object.values({ ...jobForm.errors, ...candForm.errors }));
</script>

<template>
    <Head title="Recruitment" />

    <CompanyLayout title="Recruitment" subtitle="Openings, candidates and hiring">
        <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">HIRING</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Recruitment</h1>
                <p class="mt-2 text-sm text-slate-500">{{ openJobs }} open {{ openJobs === 1 ? 'opening' : 'openings' }} · {{ candidates.total }} candidates in view</p>
            </div>
            <div v-if="abilities.manage" class="flex gap-2">
                <button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200" @click="startJob()">New opening</button>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!jobs.length" @click="showCandidateForm = !showCandidateForm">Add candidate</button>
            </div>
        </section>

        <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-testid="funnel">
            <button v-for="f in funnel" :key="f.stage" class="rounded-2xl border bg-white p-4 text-left shadow-sm"
                    :class="filters.stage === f.stage ? 'border-cyan-500' : 'border-slate-200'"
                    @click="filterBy({ stage: filters.stage === f.stage ? null : f.stage })">
                <p class="text-xs font-medium capitalize text-slate-500">{{ label(f.stage) }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ f.total }}</p>
            </button>
        </section>

        <p v-for="(msg, i) in errors" :key="i" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ msg }}</p>

        <section v-if="showJobForm" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">{{ editingJob ? 'Edit opening' : 'New opening' }}</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4" @submit.prevent="saveJob">
                <label class="text-xs font-medium text-slate-500 md:col-span-2">Job title
                    <input v-model="jobForm.title" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500">Department
                    <select v-model="jobForm.department_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">None</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500">Branch
                    <select v-model="jobForm.branch_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">None</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500">Type
                    <select v-model="jobForm.employment_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm capitalize">
                        <option v-for="t in options.types" :key="t" :value="t">{{ label(t) }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500">Positions to fill
                    <input v-model="jobForm.positions" type="number" min="1" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">Status
                    <select v-model="jobForm.status" class="mt-1 w-full rounded-lg border-slate-300 text-sm capitalize">
                        <option v-for="s in options.statuses" :key="s" :value="s">{{ label(s) }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500 md:col-span-4">Description
                    <textarea v-model="jobForm.description" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                </label>
                <div class="flex gap-2 md:col-span-4">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="jobForm.processing">Save opening</button>
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-500" @click="showJobForm = false">Cancel</button>
                </div>
            </form>
        </section>

        <section v-if="showCandidateForm && abilities.manage" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Add candidate</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4" @submit.prevent="saveCandidate">
                <label class="text-xs font-medium text-slate-500">Opening
                    <select v-model="candForm.job_opening_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required>
                        <option value="" disabled>Choose</option>
                        <option v-for="j in jobs.filter((x) => x.status !== 'closed')" :key="j.id" :value="j.id">{{ j.title }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-500 md:col-span-2">Full name
                    <input v-model="candForm.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required />
                </label>
                <label class="text-xs font-medium text-slate-500">Source
                    <input v-model="candForm.source" placeholder="Referral, LinkedIn..." class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">Email
                    <input v-model="candForm.email" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">Phone
                    <input v-model="candForm.phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">Rating (1-5)
                    <input v-model="candForm.rating" type="number" min="1" max="5" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">Expected salary
                    <input v-model="candForm.expected_salary" type="number" min="0" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500 md:col-span-4">Notes
                    <textarea v-model="candForm.notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                </label>
                <div class="md:col-span-4"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="candForm.processing">Add candidate</button></div>
            </form>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-testid="jobs">
            <article v-for="j in jobs" :key="j.id" class="rounded-2xl border bg-white p-5 shadow-sm" :class="filters.job === j.id ? 'border-cyan-500' : 'border-slate-200'">
                <div class="flex items-start justify-between gap-2">
                    <button class="text-left" @click="filterBy({ job: filters.job === j.id ? null : j.id })">
                        <p class="font-semibold text-slate-900">{{ j.title }}</p>
                        <p class="text-xs capitalize text-slate-400">{{ label(j.employment_type) }} · {{ j.positions }} {{ j.positions === 1 ? 'position' : 'positions' }}</p>
                    </button>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="jobStatusStyles[j.status]">{{ label(j.status) }}</span>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ j.active_count }} in progress · {{ j.hired_count }} hired · {{ j.candidates_count }} total</p>
                <div v-if="abilities.manage" class="mt-3 flex gap-3 text-xs font-semibold">
                    <button class="text-cyan-700" @click="startJob(j)">Edit</button>
                    <button class="text-red-600" @click="removeJob(j)">Delete</button>
                </div>
            </article>
            <p v-if="!jobs.length" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                No openings yet. Create one, then add candidates and move them through the stages.
            </p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 p-4">
                <h2 class="text-base font-bold text-slate-900">Candidates</h2>
                <button v-if="filters.job || filters.stage" class="text-xs font-semibold text-cyan-700" @click="router.get('/recruitment')">Clear filters</button>
            </div>
            <ul class="divide-y divide-slate-100">
                <li v-for="c in candidates.data" :key="c.id" class="flex flex-col gap-3 p-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800">{{ c.name }} <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="stageStyles[c.stage]">{{ label(c.stage) }}</span></p>
                        <p class="truncate text-xs text-slate-500">{{ c.job?.title }}<span v-if="c.email"> · {{ c.email }}</span><span v-if="c.phone"> · {{ c.phone }}</span><span v-if="c.source"> · {{ c.source }}</span></p>
                        <p v-if="c.rating" class="text-xs text-amber-600">Rating {{ c.rating }}/5</p>
                    </div>
                    <div v-if="abilities.manage" class="flex flex-wrap items-center gap-2">
                        <select v-if="c.stage !== 'hired'" :value="c.stage" class="rounded-lg border-slate-300 py-1 text-xs capitalize" @change="move(c, $event.target.value)">
                            <option v-for="s in options.stages.filter((x) => x !== 'hired')" :key="s" :value="s">{{ label(s) }}</option>
                        </select>
                        <button v-if="c.stage === 'offer' && abilities.hire" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white" @click="hire(c)">Hire</button>
                        <a v-if="c.employee_id" :href="`/employees/${c.employee_id}`" class="text-xs font-semibold text-cyan-700">View employee</a>
                        <button class="text-xs font-semibold text-cyan-700" @click="startEdit(c)">{{ editingCand === c.id ? 'Close' : 'Edit' }}</button>
                        <button v-if="!c.employee_id" class="text-xs font-semibold text-red-600" @click="removeCandidate(c)">Delete</button>
                    </div>
                    <form v-if="editingCand === c.id" class="grid w-full grid-cols-1 gap-3 md:grid-cols-4 lg:basis-full" @submit.prevent="saveEdit(c)">
                        <label class="text-xs font-medium text-slate-500 md:col-span-2">Full name<input v-model="editForm.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                        <label class="text-xs font-medium text-slate-500">Email<input v-model="editForm.email" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label class="text-xs font-medium text-slate-500">Phone<input v-model="editForm.phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label class="text-xs font-medium text-slate-500">Source<input v-model="editForm.source" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label class="text-xs font-medium text-slate-500">Rating (1-5)<input v-model="editForm.rating" type="number" min="1" max="5" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label class="text-xs font-medium text-slate-500">Expected salary<input v-model="editForm.expected_salary" type="number" min="0" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                        <label class="text-xs font-medium text-slate-500 md:col-span-4">Notes<textarea v-model="editForm.notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                        <p v-for="(m, k) in editForm.errors" :key="k" class="text-xs text-red-600 md:col-span-4">{{ m }}</p>
                        <div class="md:col-span-4"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="editForm.processing">Save changes</button></div>
                    </form>
                </li>
                <li v-if="!candidates.data.length" class="p-8 text-center text-sm text-slate-500">No candidates match this view.</li>
            </ul>
        </section>
    </CompanyLayout>
</template>
