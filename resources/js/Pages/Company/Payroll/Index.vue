<script setup>
import { dateLocale } from '@/lib/i18n';
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
const monthName = (m) => new Date(2000, m - 1, 1).toLocaleString(dateLocale(), { month: 'long' });

const now = new Date();
const runForm = useForm({ year: now.getFullYear(), month: now.getMonth() + 1 });
const createRun = () => runForm.post('/payroll/runs', { preserveScroll: true });

const confirmRules = () => confirm('Mark these values as reviewed? This records that your company checked them with its accountant. It does not verify them.') && router.post('/payroll/rules/confirm', {}, { preserveScroll: true });

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
    <Head :title="$t('Payroll')" />

    <CompanyLayout :title="$t('Payroll')" :subtitle="$t('Egypt monthly payroll')">
        <section class="mb-6">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-700">{{ $t('MONEY') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $t('Payroll') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $t('Estimated monthly pay with social insurance and salary tax for Egypt. Rates are editable. Your company must mark them as reviewed before an estimate can be created.') }}</p>
        </section>

                <p class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><b>{{ $t('Experimental estimator.') }}</b> {{ $t('These figures are not verified payroll and must not be used to pay staff. The Egypt values are unverified starting points, the tax method is simplified, and some levies are not included. Amounts are in EGP.') }}</p>

        <p v-for="(m, k) in $page.props.errors" :key="k" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $t(m) }}</p>
        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $t($page.props.flash.status) }}</p>

        <section v-if="rules" class="mb-6 rounded-2xl border bg-white p-5 shadow-sm" :class="'border-amber-300'">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">{{ $t(rules.name) }}</h2>
                    <p class="mt-1 text-sm" :class="'text-amber-700'">
                        {{ rules.confirmed ? $t('Marked as reviewed by your company on {date}. This is not a verification of the values.', { date: formatDate(rules.confirmed_at) }) : $t('Not reviewed. These are unverified starting values. Check them before creating an estimate.') }}
                    </p>
                </div>
                <div v-if="abilities.manage" class="flex gap-3 text-xs font-semibold">
                    <button class="text-cyan-700" @click="showRules = !showRules">{{ showRules ? $t('Close') : $t('Edit rules') }}</button>
                    <button v-if="!rules.confirmed" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-white" @click="confirmRules">{{ $t('Mark rules as reviewed') }}</button>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
                <div><dt class="text-xs text-slate-500">{{ $t('Employee insurance') }}</dt><dd class="font-semibold">{{ pct(rules.insurance_employee_rate) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Employer insurance') }}</dt><dd class="font-semibold">{{ pct(rules.insurance_employer_rate) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Insurable wage range') }}</dt><dd class="font-semibold">{{ money(rules.insurable_min) }} {{ $t('to') }} {{ money(rules.insurable_max) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ $t('Personal exemption (a year)') }}</dt><dd class="font-semibold">{{ money(rules.personal_exemption_annual) }}</dd></div>
            </dl>

            <form v-if="showRules" class="mt-5 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 md:grid-cols-4" @submit.prevent="saveRules">
                <label class="text-xs font-medium text-slate-500 md:col-span-2">{{ $t('Name') }}<input v-model="ruleForm.name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Effective from') }}<input v-model="ruleForm.effective_from" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Days in a month for unpaid leave') }}<input v-model="ruleForm.month_days_basis" type="number" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Employee insurance rate (0.11 = 11%)') }}<input v-model="ruleForm.insurance_employee_rate" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Employer insurance rate') }}<input v-model="ruleForm.insurance_employer_rate" type="number" step="0.0001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Insurable wage minimum') }}<input v-model="ruleForm.insurable_min" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Insurable wage maximum') }}<input v-model="ruleForm.insurable_max" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Personal exemption (a year)') }}<input v-model="ruleForm.personal_exemption_annual" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Round net income down to') }}<input v-model="ruleForm.rounding_step" type="number" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Extra levy on gross (0.0005 = 0.05%)') }}<input v-model="ruleForm.after_tax_levy_rate" type="number" step="0.000001" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500">{{ $t('Levy name') }}<input v-model="ruleForm.after_tax_levy_label" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                <label class="text-xs font-medium text-slate-500 md:col-span-4">{{ $t('Tax tables (JSON). Each table applies up to "up_to" of yearly net income. Bands are [upper limit, rate]. End each table with a null limit.') }}<textarea v-model="ruleForm.tax_tables_json" rows="8" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
                </label>
                <div class="flex items-center gap-3 md:col-span-4">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="ruleForm.processing">{{ $t('Save rules') }}</button>
                    <p class="text-xs text-slate-500">{{ $t('Saving clears the reviewed mark.') }}</p>
                </div>
                <p v-for="(m, k) in ruleForm.errors" :key="k" class="text-xs text-red-600 md:col-span-4">{{ $t(m) }}</p>
            </form>

            <details class="mt-4 text-sm text-slate-600">
                <summary class="cursor-pointer font-semibold text-slate-700">{{ $t('Sources and open questions') }}</summary>
                <p class="mt-2 whitespace-pre-line text-xs">{{ rules.sources }}</p>
                <p class="mt-3 text-xs font-semibold text-amber-700">{{ $t('Not settled') }}</p>
                <p class="whitespace-pre-line text-xs">{{ rules.open_questions }}</p>
                <p class="mt-3 text-xs text-slate-500">{{ $t('This is a calculation aid. It is not tax or legal advice. Have your accountant check the first run.') }}</p>
            </details>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1">
                <h2 class="text-base font-bold text-slate-900">{{ $t('Estimate runs') }}</h2>
                <form v-if="abilities.manage" class="mt-3 flex flex-wrap items-end gap-2" @submit.prevent="createRun">
                    <label class="text-xs font-medium text-slate-500">{{ $t('Month') }}<select v-model="runForm.month" class="mt-1 rounded-lg border-slate-300 text-sm"><option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option></select>
                    </label>
                    <label class="text-xs font-medium text-slate-500">{{ $t('Year') }}<input v-model="runForm.year" type="number" class="mt-1 w-24 rounded-lg border-slate-300 text-sm" /></label>
                    <button class="whitespace-nowrap rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40" :disabled="!rules?.confirmed || runForm.processing">{{ $t('Create estimate') }}</button>
                </form>
                <p v-if="abilities.manage && !rules?.confirmed" class="mt-2 text-xs text-amber-700">{{ $t('Mark the rules as reviewed first.') }}</p>
                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="r in runs" :key="r.id" class="flex items-center justify-between py-3 text-sm">
                        <Link :href="`/payroll/runs/${r.id}`" class="font-semibold text-cyan-700">{{ monthName(r.month) }} {{ r.year }}</Link>
                        <span class="text-xs text-slate-500">{{ $t('{n} people', { n: r.employees }) }} · EGP {{ money(r.net_total) }}
                            <span class="ml-1 rounded-full px-2 py-0.5 font-semibold" :class="r.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ r.status === 'approved' ? $t('locked') : $t(r.status) }}</span></span>
                    </li>
                    <li v-if="!runs.length" class="py-6 text-center text-sm text-slate-500">{{ $t('No runs yet.') }}</li>
                </ul>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="border-b border-slate-100 p-5">
                    <h2 class="text-base font-bold text-slate-900">{{ $t('Salaries (EGP a month)') }}</h2>
                    <p class="text-xs text-slate-500"><span v-if="withoutSalary" class="font-semibold text-amber-700">{{ $t('{n} without a salary. They are left out of runs.', { n: withoutSalary }) }} </span>{{ $t('A new salary takes effect from its date and older runs are not changed.') }}</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="e in employees" :key="e.id" class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-slate-800">{{ e.name }} <span class="text-xs font-normal text-slate-400">{{ e.employee_number }}</span></p>
                                <p v-if="e.compensation" class="text-xs text-slate-500">{{ $t('Basic') }} {{ money(e.compensation.basic_salary) }} + {{ $t('allowances') }} {{ money(e.compensation.allowances) }} · {{ e.compensation.insured ? $t('insured') : $t('not insured') }} · {{ $t('from') }} {{ formatDate(e.compensation.effective_from) }}</p>
                                <p v-else class="text-xs text-amber-700">{{ $t('No salary recorded') }}</p>
                            </div>
                            <button v-if="abilities.manage" class="text-xs font-semibold text-cyan-700" @click="editComp(e)">{{ editing === e.id ? $t('Close') : e.compensation ? $t('Change salary') : $t('Set salary') }}</button>
                        </div>
                        <form v-if="editing === e.id" class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5" @submit.prevent="saveComp(e)">
                            <label class="text-xs font-medium text-slate-500">{{ $t('From') }}<input v-model="comp.effective_from" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                            <label class="text-xs font-medium text-slate-500">{{ $t('Basic') }}<input v-model="comp.basic_salary" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required /></label>
                            <label class="text-xs font-medium text-slate-500">{{ $t('Allowances') }}<input v-model="comp.allowances" type="number" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="text-xs font-medium text-slate-500">{{ $t('Insurable wage') }}<input v-model="comp.insurable_wage" type="number" step="0.01" :placeholder="$t('= basic')" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="flex items-end gap-2 pb-2 text-xs font-medium text-slate-600"><input v-model="comp.insured" type="checkbox" class="rounded border-slate-300" />{{ $t('Insured') }}</label>
                            <div class="col-span-2 flex gap-2 md:col-span-5"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="comp.processing">{{ $t('Save salary') }}</button></div>
                            <p v-for="(m, k) in comp.errors" :key="k" class="col-span-2 text-xs text-red-600 md:col-span-5">{{ $t(m) }}</p>
                        </form>
                    </li>
                    <li v-if="!employees.length" class="p-8 text-center text-sm text-slate-500">{{ $t('No employees yet.') }}</li>
                </ul>
            </div>
        </section>
    </CompanyLayout>
</template>
