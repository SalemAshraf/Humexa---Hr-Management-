<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const props = defineProps({
    run: { type: Object, required: true },
    rule_name: { type: String, default: '' },
    payslips: { type: Array, required: true },
    totals: { type: Object, required: true },
    adjustments: { type: Array, required: true },
    employees: { type: Array, required: true },
    abilities: { type: Object, required: true },
});

const money = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const title = `${new Date(2000, props.run.month - 1, 1).toLocaleString('en-US', { month: 'long' })} ${props.run.year}`;
const draft = props.run.status === 'draft';
const empName = (id) => props.employees.find((e) => e.id === id)?.name ?? '';

const adj = useForm({ employee_id: '', kind: 'deduction', label: '', amount: '', taxable: false });
const addAdj = () => adj.post(`/payroll/runs/${props.run.id}/adjustments`, { preserveScroll: true, onSuccess: () => adj.reset('label', 'amount') });
const removeAdj = (a) => router.delete(`/payroll/runs/${props.run.id}/adjustments/${a.id}`, { preserveScroll: true });
const recalc = () => router.post(`/payroll/runs/${props.run.id}/recalculate`, {}, { preserveScroll: true });
const approve = () => confirm(`Lock the ${title} estimate? Nothing in it can change afterwards. It is still an estimate, not verified payroll.`) && router.post(`/payroll/runs/${props.run.id}/approve`);
const remove = () => confirm(`Delete the draft run for ${title}?`) && router.delete(`/payroll/runs/${props.run.id}`);
</script>

<template>
    <Head :title="`Payroll ${title}`" />

    <CompanyLayout title="Payroll" :subtitle="title">
        <Link href="/payroll" class="text-xs font-semibold text-cyan-700">&larr; All runs</Link>
        <section class="mb-6 mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ title }}
                    <span class="ml-2 rounded-full px-2 py-0.5 align-middle text-xs font-semibold" :class="draft ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700'">{{ draft ? 'draft estimate' : 'locked estimate' }}</span></h1>
                <p class="mt-2 text-sm text-slate-500">Rules: {{ rule_name }}. Tax is the yearly tax on this month times 12, divided by 12. It is not the tax authority running-total method.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm font-semibold">
                <a :href="`/payroll/runs/${run.id}/export`" class="rounded-lg bg-white px-3 py-2 text-slate-700 ring-1 ring-slate-200">Download estimate CSV</a>
                <template v-if="abilities.manage && draft">
                    <button class="rounded-lg bg-white px-3 py-2 text-slate-700 ring-1 ring-slate-200" @click="recalc">Recalculate</button>
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-white" @click="approve">Lock estimate</button>
                    <button class="px-3 py-2 text-red-600" @click="remove">Delete draft</button>
                </template>
            </div>
        </section>

                <p v-if="run.year >= 2027" class="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="insurance-2027-note"><b>Rates may be out of date for {{ run.year }}.</b> Press reports citing an unpublished insurance authority memo say the pension share rises by 0.5 point for the employee and 0.5 point for the employer from January 2027, and the insurable wage limits change. This rule set still uses the 2026 values. Check the official notice and update the rules before relying on this estimate.</p>
                <p class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"><b>Experimental estimator.</b> These figures are not verified payroll and must not be used to pay staff. The Egypt values are unverified starting points, the tax method is simplified, and some levies are not included. Amounts are in EGP.</p>

        <p v-for="(m, k) in $page.props.errors" :key="k" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ m }}</p>
        <p v-if="$page.props.flash?.status" class="mb-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $page.props.flash.status }}</p>

        <section class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <article v-for="(label, key) in { gross: 'Gross (EGP)', insurance_employee: 'Employee insurance (EGP)', tax: 'Income tax (EGP)', insurance_employer: 'Employer insurance (EGP)', net: 'Net pay (EGP)' }" :key="key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ label }}</p><p class="mt-1 text-xl font-bold text-slate-900">{{ money(totals[key]) }}</p>
            </article>
        </section>

        <section class="mb-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Employee (EGP)</th><th class="px-3 py-3 text-right">Basic + allow.</th><th class="px-3 py-3 text-right">Unpaid leave</th><th class="px-3 py-3 text-right">Adjust.</th><th class="px-3 py-3 text-right">Gross</th><th class="px-3 py-3 text-right">Insurance</th><th class="px-3 py-3 text-right">Tax</th><th class="px-3 py-3 text-right">Net</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in payslips" :key="p.id">
                        <td class="min-w-40 px-4 py-3 font-semibold text-slate-800">{{ p.employee }}<span class="block text-xs font-normal text-slate-400">{{ p.employee_number }}</span></td>
                        <td class="px-3 py-3 text-right">{{ money(Number(p.basic_salary) + Number(p.allowances)) }}</td>
                        <td class="px-3 py-3 text-right text-slate-500">{{ Number(p.unpaid_leave_days) ? `-${money(p.unpaid_leave_deduction)} (${Number(p.unpaid_leave_days)}d)` : '' }}</td>
                        <td class="px-3 py-3 text-right text-slate-500">{{ Number(p.adjustments_earnings) ? `+${money(p.adjustments_earnings)}` : '' }} {{ Number(p.adjustments_deductions) ? `-${money(p.adjustments_deductions)}` : '' }}</td>
                        <td class="px-3 py-3 text-right">{{ money(p.gross) }}</td>
                        <td class="px-3 py-3 text-right">{{ money(p.insurance_employee) }}</td>
                        <td class="px-3 py-3 text-right">{{ money(p.income_tax) }}</td>
                        <td class="px-3 py-3 text-right font-bold text-slate-900">{{ money(p.net_pay) }}</td>
                    </tr>
                    <tr v-if="!payslips.length"><td colspan="8" class="p-8 text-center text-slate-500">No employees with a salary for this month.</td></tr>
                </tbody>
            </table>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">One-off adjustments</h2>
            <p class="text-xs text-slate-500">Bonuses, advances, penalties. They apply to this month only.</p>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                <li v-for="a in adjustments" :key="a.id" class="flex items-center justify-between py-2">
                    <span>{{ empName(a.employee_id) }} · {{ a.label }} <span class="text-xs text-slate-400">({{ a.kind }}{{ a.kind === 'earning' ? (a.taxable ? ', taxable' : ', not taxable') : '' }})</span></span>
                    <span class="flex items-center gap-3"><b>{{ money(a.amount) }}</b><button v-if="abilities.manage && draft" class="text-xs font-semibold text-red-600" @click="removeAdj(a)">Remove</button></span>
                </li>
                <li v-if="!adjustments.length" class="py-3 text-slate-500">None.</li>
            </ul>
            <form v-if="abilities.manage && draft" class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-6" @submit.prevent="addAdj">
                <select v-model="adj.employee_id" class="rounded-lg border-slate-300 text-sm md:col-span-2" required><option value="" disabled>Employee</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option></select>
                <select v-model="adj.kind" class="rounded-lg border-slate-300 text-sm"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                <input v-model="adj.label" placeholder="Label" class="rounded-lg border-slate-300 text-sm" required />
                <input v-model="adj.amount" type="number" step="0.01" placeholder="Amount" class="rounded-lg border-slate-300 text-sm" required />
                <label v-if="adj.kind === 'earning'" class="flex items-center gap-2 text-xs text-slate-600"><input v-model="adj.taxable" type="checkbox" class="rounded border-slate-300" /> Taxable</label>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white md:col-span-6 md:w-32" :disabled="adj.processing">Add</button>
                <p v-for="(m, k) in adj.errors" :key="k" class="col-span-2 text-xs text-red-600 md:col-span-6">{{ m }}</p>
            </form>
        </section>
    </CompanyLayout>
</template>
