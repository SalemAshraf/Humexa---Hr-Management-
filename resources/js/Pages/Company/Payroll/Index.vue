<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';
import { formatDate } from '@/lib/date';

const props = defineProps({
    rules: { type: Object, default: null },
    runs: { type: Array, required: true },
    employees: { type: Array, required: true },
    abilities: { type: Object, required: true },
});

const money = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString('en-US', { month: 'long' });

const now = new Date();
const runForm = useForm({ year: now.getFullYear(), month: now.getMonth() + 1 });
const createRun = () => runForm.post('/payroll/runs', { preserveScroll: true });

const confirmRules = () => confirm('Confirm that these rates, limits and tax tables are correct for your company? Check them with your accountant first.') && router.post('/payroll/rules/confirm', {}, { preserveScroll: true });

const showRules = ref(false);
const ruleForm = useForm({
    name: props.rules?.name ?? '', effective_from: props.rules?.effective_from ?? '',
    insurance_employee_rate: props.rules?.insurance_employee_rate ?? 0, insurance_employer_rate: props.rules?.insurance_employer_rate ?? 0,
    insurable_min: props.rules?.insurable_min ?? 0, insurable_max: props.rules?.insurable_max ?? 0,
    personal_exemption_annual: props.rules?.personal_exemption_annual ?? 0, rounding_step: props.rules?.rounding_step ?? 10,
    after_tax_levy_rate: props.rules?.after_tax_levy_rate ?? 0, after_tax_levy_label: props.rules?.after_tax_levy_label ?? '',
    month_days_basis: props.rules?.month_days_basis ?? 30,
    tax_tables_json: JSON.stringify(props.rules?.tax_tables ?? [], null, 1),
});
const saveRules = () => ruleForm.put('/payroll/rules', { preserveScroll: true, onSuccess: () => { showRules.value = false; } });

const editing = ref(null);
const comp = useForm({ effective_from: '', basic_salary: '', allowances: 0, insured: true, insurable_wage: '', note: '' });
const editComp = (e) => {
    editing.value = editing.value === e.id ? null : e.id;
    const c = e.compensation;
    comp.defaults({ effective_from: new Date().toISOString().slice(0, 7) + '-01', basic_salary: c?.basic_salary ?? '', allowances: c?.allowances ?? 0, insured: c?.insured ?? true, insurable_wage: c?.insurable_wage ?? '', note: '' }).reset();
};
const saveComp = (e) => comp.transform((d) => ({ ...d, insurable_wage: d.insurable_wage === '' ? null : d.insurable_wage })).post(`/payroll/employees/${e.id}/compensation`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });

const pct = (v) => `${(v * 100).toFixed(2).replace(/\.?0+$/, '')}%`;
const withoutSalary = computed(() => props.employees.filter((e) => !e.compensation).length);
const flash = computed(() => window.history.state?.props?.flash);
</script>

<template>
    <Head title="Payroll" />

    <CompanyLayout title="Payroll" subtitle="Egypt monthly payroll">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">MONEY</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Payroll</h1>
            <p class="mt-2 text-sm text-slate-500">Monthly runs with social insurance and salary tax for Egypt. Rates are editable and must be confirmed by your company before a run can be created.</p>
        </section>

        <p v-for="(m, k) in $page.props.errors" :key="k" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ m }}</p>
        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $page.props.flash.status }}</p>

        <section v-if="rules" class="mb-6 rounded-2xl border bg-white p-5 shadow-sm" :class="rules.confirmed ? 'border-emerald-200' : 'border-amber-300'">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">{{ rules.name }}</h2>
                    <p class="mt-1 text-sm" :class="rules.confirmed ? 'text-emerald-700' : 'text-amber-700'">
                        {{ rules.confirmed ? `Confirmed ${formatDate(rules.confirmed_at)}` : 'Not confirmed. These are starting values read from public sources. Review them before creating a run.' }}
                    </p>
                </div>
                <div v-if="abilities.manage" class="flex gap-3 text-xs font-semibold">
                    <button class="text-cyan-700" @click="showRules = !showRules">{{ showRules ? 'Close' : 'Edit rules' }}</button>
                    <button v-if="!rules.confirmed" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-white" @click="confirmRules">Confirm rules</button>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Employee insurance</dt><dd class="font-semibold">{{ pct(rules.insurance_employee_rate) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Employer insurance</dt><dd class="font-semibold">{{ pct(rules.insurance_employer_rate) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Insurable wage range</dt><dd class="font-semibold">{{ money(rules.insurable_min) }} to {{ money(rules.insurable_max) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Personal exemption (a year)</dt><dd class="font-semibold">{{ money(rules.personal_exemption_annual) }}</dd></div>
            </dl>

            <form v-if="showRules" class="mt-5 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 md:grid-cols-4" @submit.prevent="saveRules">
                <label class="text-xs font-medium text-slate-500 md:col-span-2">Name<input v-model="ruleForm.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">Effective from<input v-model="ruleForm.effective_from" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">Days in a month for unpaid leave<input v-model="ruleForm.month_days_basis" type="number" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Employee insurance rate (0.11 = 11%)<input v-model="ruleForm.insurance_employee_rate" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Employer insurance rate<input v-model="ruleForm.insurance_employer_rate" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Insurable wage minimum<input v-model="ruleForm.insurable_min" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Insurable wage maximum<input v-model="ruleForm.insurable_max" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Personal exemption (a year)<input v-model="ruleForm.personal_exemption_annual" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Round net income down to<input v-model="ruleForm.rounding_step" type="number" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Extra levy on gross (0.0005 = 0.05%)<input v-model="ruleForm.after_tax_levy_rate" type="number" step="0.000001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">Levy name<input v-model="ruleForm.after_tax_levy_label" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500 md:col-span-4">Tax tables (JSON). Each table applies up to "up_to" of yearly net income. Bands are [upper limit, rate]. End each table with a null limit.
                    <textarea v-model="ruleForm.tax_tables_json" rows="8" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
                </label>
                <div class="flex items-center gap-3 md:col-span-4">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="ruleForm.processing">Save rules</button>
                    <p class="text-xs text-slate-500">Saving clears the confirmation.</p>
                </div>
                <p v-for="(m, k) in ruleForm.errors" :key="k" class="text-xs text-red-600 md:col-span-4">{{ m }}</p>
            </form>

            <details class="mt-4 text-sm text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">Sources and open questions</summary>
                <p class="mt-2 whitespace-pre-line text-xs">{{ rules.sources }}</p>
                <p class="mt-3 text-xs font-semibold text-amber-700">Not settled</p>
                <p class="whitespace-pre-line text-xs">{{ rules.open_questions }}</p>
                <p class="mt-3 text-xs text-slate-500">This is a calculation aid. It is not tax or legal advice. Have your accountant check the first run.</p>
            </details>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1">
                <h2 class="text-base font-bold text-slate-900">Payroll runs</h2>
                <form v-if="abilities.manage" class="mt-3 flex items-end gap-2" @submit.prevent="createRun">
                    <label class="text-xs font-medium text-slate-500">Month
                        <select v-model="runForm.month" class="mt-1 rounded-lg border-slate-300 text-sm"><option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option></select>
                    </label>
                    <label class="text-xs font-medium text-slate-500">Year<input v-model="runForm.year" type="number" class="mt-1 w-24 rounded-lg border-slate-300 text-sm" /></label>
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!rules?.confirmed || runForm.processing">Create run</button>
                </form>
                <p v-if="abilities.manage && !rules?.confirmed" class="mt-2 text-xs text-amber-700">Confirm the rules first.</p>
                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="r in runs" :key="r.id" class="flex items-center justify-between py-3 text-sm">
                        <Link :href="`/payroll/runs/${r.id}`" class="font-semibold text-cyan-700">{{ monthName(r.month) }} {{ r.year }}</Link>
                        <span class="text-xs text-slate-500">{{ r.employees }} people · {{ money(r.net_total) }}
                            <span class="ml-1 rounded-full px-2 py-0.5 font-semibold" :class="r.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ r.status }}</span></span>
                    </li>
                    <li v-if="!runs.length" class="py-6 text-center text-sm text-slate-500">No runs yet.</li>
                </ul>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="border-b border-slate-100 p-5">
                    <h2 class="text-base font-bold text-slate-900">Salaries</h2>
                    <p class="text-xs text-slate-500"><span v-if="withoutSalary" class="font-semibold text-amber-700">{{ withoutSalary }} without a salary. They are left out of runs. </span>A new salary takes effect from its date and older runs are not changed.</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="e in employees" :key="e.id" class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-slate-800">{{ e.name }} <span class="text-xs font-normal text-slate-400">{{ e.employee_number }}</span></p>
                                <p v-if="e.compensation" class="text-xs text-slate-500">Basic {{ money(e.compensation.basic_salary) }} + allowances {{ money(e.compensation.allowances) }} · {{ e.compensation.insured ? 'insured' : 'not insured' }} · from {{ formatDate(e.compensation.effective_from) }}</p>
                                <p v-else class="text-xs text-amber-700">No salary recorded</p>
                            </div>
                            <button v-if="abilities.manage" class="text-xs font-semibold text-cyan-700" @click="editComp(e)">{{ editing === e.id ? 'Close' : e.compensation ? 'Change salary' : 'Set salary' }}</button>
                        </div>
                        <form v-if="editing === e.id" class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="saveComp(e)">
                            <label class="text-xs font-medium text-slate-500">From<input v-model="comp.effective_from" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                            <label class="text-xs font-medium text-slate-500">Basic<input v-model="comp.basic_salary" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                            <label class="text-xs font-medium text-slate-500">Allowances<input v-model="comp.allowances" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="text-xs font-medium text-slate-500">Insurable wage<input v-model="comp.insurable_wage" type="number" step="0.01" placeholder="= basic" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="flex items-end gap-2 pb-2 text-xs font-medium text-slate-600"><input v-model="comp.insured" type="checkbox" class="rounded border-slate-300" /> Insured</label>
                            <div class="col-span-2 flex gap-2 md:col-span-5"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="comp.processing">Save salary</button></div>
                            <p v-for="(m, k) in comp.errors" :key="k" class="col-span-2 text-xs text-red-600 md:col-span-5">{{ m }}</p>
                        </form>
                    </li>
                    <li v-if="!employees.length" class="p-8 text-center text-sm text-slate-500">No employees yet.</li>
                </ul>
            </div>
        </section>
    </CompanyLayout>
</template>
