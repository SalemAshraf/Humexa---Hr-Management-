
<script setup>
import { Head } from '@inertiajs/vue3';
import CompanyLayout from '@/Layouts/Dashboard/CompanyLayout.vue';

const stats = [
    { label: 'Total Employees', value: null, description: 'Registered employees', icon: 'EM' },
    { label: 'Present Today', value: null, description: 'Attendance overview', icon: 'PR' },
    { label: 'Absent Today', value: null, description: 'Attendance overview', icon: 'AB' },
    { label: 'Pending Leave Requests', value: null, description: 'Awaiting approval', icon: 'LV' },
];

const quickActions = [
    { label: 'Add Employee', description: 'Create an employee profile', shortcut: '＋' },
    { label: 'Review Attendance', description: 'View today’s attendance', shortcut: 'AT' },
    { label: 'Leave Requests', description: 'Review pending requests', shortcut: 'LR' },
    { label: 'View Reports', description: 'Explore company reports', shortcut: 'RP' },
];
</script>

<template>
    <Head title="Company Dashboard" />

    <CompanyLayout
        title="Dashboard"
        subtitle="Company overview and workforce insights"
    >
        <!-- Welcome -->
        <section class="mb-8">
            <p class="mb-2 text-sm font-medium text-cyan-700">
                COMPANY WORKSPACE
            </p>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Welcome to Humexa
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Here's an overview of your organization's activity.
            </p>
        </section>

        <!-- KPI Cards -->
        <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="stat in stats"
                :key="stat.label"
                class="hx-card p-5"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">
                            {{ stat.label }}
                        </p>

                        <p class="mt-3 text-3xl font-bold text-slate-900">
                            {{ stat.value ?? '—' }}
                        </p>

                        <p class="mt-2 text-xs text-slate-400">
                            {{ stat.description }}
                        </p>
                    </div>

                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-50 text-xs font-bold text-cyan-800">
                        {{ stat.icon }}
                    </span>
                </div>
            </article>
        </section>

        <!-- Main Grid -->
        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Attendance -->
            <article class="hx-card p-6 xl:col-span-2">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">
                            Attendance Overview
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Workforce attendance insights
                        </p>
                    </div>

                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs text-slate-500">
                        Today
                    </span>
                </div>

                <div class="flex min-h-56 flex-col items-center justify-center text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-50 text-xl font-bold text-cyan-700">
                        AT
                    </div>

                    <h3 class="font-medium text-slate-800">
                        No attendance data yet
                    </h3>

                    <p class="mt-2 max-w-sm text-sm text-slate-500">
                        Attendance insights will appear here once attendance records are available.
                    </p>
                </div>
            </article>

            <!-- Quick Actions -->
            <article class="hx-card p-6">
                <h2 class="font-semibold text-slate-900">
                    Quick Actions
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Common workspace tasks
                </p>

                <div class="mt-5 space-y-3">
                    <div
                        v-for="action in quickActions"
                        :key="action.label"
                        class="flex items-center gap-3 rounded-xl border border-slate-100 p-3"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600">
                            {{ action.shortcut }}
                        </span>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                {{ action.label }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ action.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <!-- Recent Activity -->
        <section class="mt-6">
            <article class="hx-card p-6">
                <h2 class="font-semibold text-slate-900">
                    Recent Activity
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Latest updates from your organization
                </p>

                <div class="flex min-h-36 flex-col items-center justify-center text-center">
                    <p class="text-sm font-medium text-slate-700">
                        No recent activity
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        New activities will appear here.
                    </p>
                </div>
            </article>
        </section>
    </CompanyLayout>
</template>
