<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    requests: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    types: { type: Array, required: true },
    employees: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
    abilities: { type: Object, required: true },
    settings: { type: Object, required: true },
    year: { type: Number, required: true },
});

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const weekendForm = useForm({ weekend_days: [...props.settings.weekend_days] });
const saveWeekend = () => weekendForm.put('/leave/settings', { preserveScroll: true });

const statusStyles = {
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
    cancelled: 'bg-slate-100 text-slate-500',
};

const statusTabs = [
    { label: 'All', value: null },
    { label: 'Pending', value: 'pending' },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' },
];

const setStatus = (value) =>
    router.get('/leave', value ? { status: value } : {}, { preserveState: true, replace: true });

const requestForm = useForm({
    employee_id: '',
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
});
const submitRequest = () =>
    requestForm.post('/leave/requests', { preserveScroll: true, onSuccess: () => requestForm.reset() });

const typeForm = useForm({ name: '', annual_days: 21, is_paid: true });
const submitType = () =>
    typeForm.post('/leave/types', { preserveScroll: true, onSuccess: () => typeForm.reset() });

const toggleType = (type) =>
    router.put(`/leave/types/${type.id}`, { ...type, is_active: !type.is_active }, { preserveScroll: true });

const removeType = (type) => confirm(`Delete "${type.name}"? Only types with no requests can be deleted.`) && router.delete(`/leave/types/${type.id}`, { preserveScroll: true });

const editing = ref(null);
const editingName = computed(() => props.requests.data.find((x) => x.id === editing.value)?.employee ?? '');
const editForm = useForm({ leave_type_id: '', start_date: '', end_date: '', reason: '' });
const startEdit = (r) => {
    editing.value = editing.value === r.id ? null : r.id;
    editForm.defaults({ leave_type_id: r.leave_type_id, start_date: r.start_date, end_date: r.end_date, reason: r.reason ?? '' }).reset();
};
const saveEdit = (r) => editForm.put(`/leave/requests/${r.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });

const note = reactive({});
const review = (id, decision) =>
    router.post(`/leave/requests/${id}/review`, { decision, note: note[id] ?? null }, { preserveScroll: true });
const cancel = (id) => router.post(`/leave/requests/${id}/cancel`, {}, { preserveScroll: true });

const loadBalances = () => {
    if (!requestForm.employee_id) return;
    router.get('/leave', { ...(props.filters.status ? { status: props.filters.status } : {}), employee_id: requestForm.employee_id }, {
        preserveState: true, preserveScroll: true, replace: true, only: ['balances'],
    });
};

const showTypes = ref(false);
const scopeLabel = computed(() => (props.summary.scope === 'company' ? 'All employees' : 'Your direct reports'));
</script>

<template>
    <Head title="Leave" />

    <CompanyLayout title="Leave" subtitle="Requests, balances and approvals">
        <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">TIME OFF</p>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Leave management</h1>
                <p class="mt-2 text-sm text-slate-500">{{ scopeLabel }} · {{ year }}</p>
            </div>
            <button v-if="abilities.manage" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900" @click="showTypes = !showTypes">
                {{ showTypes ? 'Hide leave types' : 'Manage leave types' }}
            </button>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-testid="pending-count">
                <p class="text-sm font-medium text-slate-500">Waiting for approval</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ summary.pending }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">On leave today</p>
                <p class="mt-2 text-3xl font-bold text-emerald-700">{{ summary.on_leave_today }}</p>
            </article>
        </section>

        <section v-if="abilities.manage && showTypes" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Company weekend</h2>
            <p class="mt-1 text-xs text-slate-500">
                Days ticked here are not counted as leave days.
                <span v-if="settings.is_default" class="font-semibold text-amber-600">Not confirmed yet: the default (Friday and Saturday) is in use. Check it matches your company policy.</span>
            </p>
            <form class="mt-3 flex flex-wrap items-center gap-3" @submit.prevent="saveWeekend">
                <label v-for="(name, i) in dayNames" :key="i" class="flex items-center gap-1.5 text-sm text-slate-700">
                    <input v-model="weekendForm.weekend_days" :value="i" type="checkbox" class="rounded" /> {{ name }}
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-semibold text-white" :disabled="weekendForm.processing">Save weekend</button>
            </form>
            <p class="mt-2 text-xs text-slate-400">Annual-day amounts are whatever your company sets. They are not checked against any labor law.</p>
            <h2 class="mt-6 text-base font-bold text-slate-900">Leave types</h2>
            <p v-if="$page.props.errors.type" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $page.props.errors.type }}</p>
            <ul class="mt-3 divide-y divide-slate-100">
                <li v-for="type in types" :key="type.id" class="flex items-center justify-between py-2 text-sm">
                    <span :class="type.is_active ? 'text-slate-800' : 'text-slate-400 line-through'">
                        {{ type.name }} · {{ type.annual_days }} days/year · {{ type.is_paid ? 'Paid' : 'Unpaid' }}
                    </span>
                    <span class="flex items-center gap-3"><button class="text-xs font-semibold text-cyan-700" @click="toggleType(type)">{{ type.is_active ? 'Deactivate' : 'Activate' }}</button><button class="text-xs font-semibold text-red-600" @click="removeType(type)">Delete</button></span>
                </li>
                <li v-if="!types.length" class="py-2 text-sm text-slate-500">No leave types yet. Add one below (for example Annual leave).</li>
            </ul>
            <form class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr_auto_auto]" @submit.prevent="submitType">
                <input v-model="typeForm.name" placeholder="Name" class="rounded-lg border-slate-300 text-sm" />
                <input v-model="typeForm.annual_days" type="number" min="0" step="0.5" class="rounded-lg border-slate-300 text-sm" />
                <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="typeForm.is_paid" type="checkbox" class="rounded" /> Paid</label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" :disabled="typeForm.processing">Add type</button>
            </form>
            <p v-if="typeForm.errors.name || typeForm.errors.annual_days" class="mt-2 text-xs text-red-600">{{ typeForm.errors.name || typeForm.errors.annual_days }}</p>
        </section>

        <section v-if="abilities.manage" class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Record a leave request</h2>
            <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5" @submit.prevent="submitRequest">
                <select v-model="requestForm.employee_id" class="rounded-lg border-slate-300 text-sm" @change="loadBalances">
                    <option value="" disabled>Employee</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <select v-model="requestForm.leave_type_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="" disabled>Leave type</option>
                    <option v-for="t in types.filter((x) => x.is_active)" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <label class="text-xs font-medium text-slate-500">Start date
                    <input v-model="requestForm.start_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <label class="text-xs font-medium text-slate-500">End date
                    <input v-model="requestForm.end_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <button class="self-end rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" :disabled="requestForm.processing">Submit</button>
                <input v-model="requestForm.reason" placeholder="Reason (optional)" class="rounded-lg border-slate-300 text-sm md:col-span-5" />
            </form>
            <p v-for="(msg, key) in requestForm.errors" :key="key" class="mt-2 text-xs text-red-600">{{ msg }}</p>
            <div v-if="balances.length" class="mt-4 flex flex-wrap gap-3">
                <div v-for="b in balances" :key="b.leave_type_id" class="rounded-xl bg-slate-50 px-4 py-2 text-xs text-slate-600">
                    <span class="font-semibold text-slate-800">{{ b.name }}</span>:
                    {{ b.remaining }} of {{ b.entitled }} left ({{ b.used }} used, {{ b.pending }} pending)
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                <button
                    v-for="tab in statusTabs" :key="tab.label"
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="(filters.status ?? null) === tab.value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="setStatus(tab.value)"
                >{{ tab.label }}</button>
            </div>
            <form v-if="editing" class="grid grid-cols-1 gap-3 border-b border-slate-100 bg-slate-50 p-4 md:grid-cols-5" @submit.prevent="saveEdit({ id: editing })">
                <p class="text-sm font-semibold text-slate-800 md:col-span-5">Editing the pending request of {{ editingName }}. Saving cancels the old request (kept in history) and files a new one that is checked again.</p>
                <label class="text-xs font-medium text-slate-500">Type<select v-model="editForm.leave_type_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="t in types.filter((x) => x.is_active)" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
                <label class="text-xs font-medium text-slate-500">From<input v-model="editForm.start_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">To<input v-model="editForm.end_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">Reason<input v-model="editForm.reason" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <div class="flex items-end gap-2"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="editForm.processing">Save changes</button><button type="button" class="text-sm font-semibold text-slate-500" @click="editing = null">Close</button></div>
                <p v-for="(m, k) in editForm.errors" :key="k" class="text-xs text-red-600 md:col-span-5">{{ m }}</p>
            </form>
            <ul class="divide-y divide-slate-100 sm:hidden" data-testid="leave-cards">
                <li v-for="r in requests.data" :key="r.id" class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div><p class="font-semibold text-slate-800">{{ r.employee }}</p><p class="text-xs text-slate-400">{{ r.type }} · {{ r.days }} day(s)</p></div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusStyles[r.status]">{{ r.status }}</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ r.start_date }} → {{ r.end_date }}</p>
                    <div v-if="r.can_review" class="flex items-center gap-2">
                        <input v-model="note[r.id]" placeholder="Note" class="min-w-0 flex-1 rounded-lg border-slate-300 py-1 text-xs" />
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white" @click="review(r.id, 'approve')">Approve</button>
                        <button class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-red-600 ring-1 ring-red-200" @click="review(r.id, 'reject')">Reject</button>
                    </div>
                    <div v-if="r.can_cancel" class="mt-2 flex gap-3"><button v-if="r.can_edit" class="text-xs font-semibold text-cyan-700" @click="startEdit(r)">Edit</button><button class="text-xs font-semibold text-slate-500" @click="cancel(r.id)">Cancel request</button></div>
                </li>
                <li v-if="!requests.data.length" class="p-8 text-center text-sm text-slate-500">No leave requests here yet.</li>
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="min-w-full text-sm" data-testid="leave-table">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dates</th>
                            <th class="px-4 py-3">Days</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="r in requests.data" :key="r.id">
                            <td class="px-4 py-3"><p class="font-semibold text-slate-800">{{ r.employee }}</p><p class="text-xs text-slate-400">{{ r.employee_number }}</p></td>
                            <td class="px-4 py-3 text-slate-600">{{ r.type }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ r.start_date }} → {{ r.end_date }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ r.days }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusStyles[r.status]">{{ r.status }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="r.can_review" class="flex items-center justify-end gap-2">
                                    <input v-model="note[r.id]" placeholder="Note" class="w-28 rounded-lg border-slate-300 py-1 text-xs" />
                                    <button class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white" @click="review(r.id, 'approve')">Approve</button>
                                    <button class="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-red-600 ring-1 ring-red-200" @click="review(r.id, 'reject')">Reject</button>
                                </div>
                                <div v-if="r.can_cancel" class="mt-2 text-right"><button v-if="r.can_edit" class="mr-3 text-xs font-semibold text-cyan-700" @click="startEdit(r)">Edit</button><button class="text-xs font-semibold text-slate-500 hover:text-red-600" @click="cancel(r.id)">Cancel</button></div>
                            </td>
                        </tr>
                        <tr v-if="!requests.data.length"><td colspan="6" class="px-4 py-10 text-center text-slate-500">No leave requests here yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </CompanyLayout>
</template>
