
<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    mobileOpen: {
        type: Boolean,
        default: false,
    },
    theme: {
        type: String,
        default: 'company',
        validator: (value) => ['platform', 'company'].includes(value),
    },
    direction: {
        type: String,
        default: 'ltr',
        validator: (value) => ['ltr', 'rtl'].includes(value),
    },
    title: {
        type: String,
        default: 'Humexa',
    },
    subtitle: {
        type: String,
        default: 'Human Resources',
    },
});

const emit = defineEmits([
    'toggle-collapse',
    'close-mobile',
]);

const page = usePage();

const isPlatform = computed(() => props.theme === 'platform');

const closedMobilePosition = computed(() =>
    props.direction === 'rtl'
        ? 'translate-x-full'
        : '-translate-x-full',
);

function isActive(item) {
    if (item.active !== undefined) {
        return item.active;
    }

    return page.url === item.href ||
        (item.href !== '/' && page.url.startsWith(`${item.href}/`));
}
</script>

<template>
    <div :dir="direction">
        <!-- Mobile backdrop -->
        <Transition name="fade">
            <div
                v-if="mobileOpen"
                class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
                @click="emit('close-mobile')"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="hx-sidebar fixed inset-y-0 start-0 z-50 flex flex-col border-e transition-[width,transform] duration-200 ease-in-out"
            :class="[
                isPlatform
                    ? 'border-slate-800 bg-[#0F1A2B] text-slate-100'
                    : 'border-slate-200 bg-white text-slate-800',
                collapsed ? 'is-collapsed' : '',
                mobileOpen
                    ? 'translate-x-0'
                    : `${closedMobilePosition} lg:translate-x-0`,
            ]"
            :style="{
                width: collapsed ? '76px' : '260px',
            }"
            :aria-label="`${title} navigation`"
        >
            <!-- Brand -->
            <div
                class="flex h-[72px] shrink-0 items-center border-b px-5"
                :class="isPlatform ? 'border-slate-800' : 'border-slate-200'"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500 text-lg font-bold text-white"
                    aria-hidden="true"
                >
                    H
                </div>

                <div v-if="!collapsed" class="ms-3 min-w-0">
                    <h1 class="truncate text-lg font-bold tracking-wide">
                        {{ title }}
                    </h1>

                    <p
                        class="truncate text-xs"
                        :class="isPlatform ? 'text-slate-400' : 'text-slate-500'"
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <button
                    type="button"
                    class="ms-auto rounded-lg p-2 transition-colors lg:hidden"
                    :class="isPlatform ? 'hover:bg-slate-800' : 'hover:bg-slate-100'"
                    :aria-label="$t('Close sidebar')"
                    @click="emit('close-mobile')"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m18 6-12 12M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav
                class="flex-1 space-y-1 overflow-y-auto px-3 py-5"
                :aria-label="$t('Main navigation')"
            >
                <template v-for="item in items" :key="item.label">
                    <Link
                        v-if="!item.disabled"
                        :href="item.href"
                        :title="collapsed ? item.label : undefined"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        class="group flex min-h-11 items-center rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                        :class="[
                            isActive(item)
                                ? isPlatform
                                    ? 'bg-cyan-500/15 text-cyan-400'
                                    : 'bg-cyan-50 text-cyan-800'
                                : isPlatform
                                    ? 'text-slate-400 hover:bg-slate-800 hover:text-white'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                            collapsed ? 'justify-center' : 'gap-3',
                        ]"
                        @click="emit('close-mobile')"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                            :class="isActive(item)
                                ? isPlatform
                                    ? 'bg-cyan-500/20 text-cyan-300'
                                    : 'bg-cyan-100 text-cyan-800'
                                : isPlatform
                                    ? 'bg-slate-800 text-slate-400 group-hover:text-white'
                                    : 'bg-slate-100 text-slate-500 group-hover:text-slate-800'"
                            aria-hidden="true"
                        >
                            {{ item.shortcut || item.label?.charAt(0) || '?' }}
                        </span>
                        <span v-if="!collapsed" class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>
                        <span
                            v-if="item.badge && !collapsed"
                            class="rounded-full px-2 py-0.5 text-xs"
                            :class="isPlatform ? 'bg-slate-700 text-slate-400' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>

                    <div
                        v-else
                        :title="collapsed ? item.label : undefined"
                        aria-disabled="true"
                        class="flex min-h-11 cursor-not-allowed items-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 opacity-60"
                        :class="collapsed ? 'justify-center' : 'gap-3'"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                            :class="isPlatform ? 'bg-slate-800 text-slate-500' : 'bg-slate-100 text-slate-400'"
                            aria-hidden="true"
                        >
                            {{ item.shortcut || item.label?.charAt(0) || '?' }}
                        </span>
                        <span v-if="!collapsed" class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>
                        <span v-if="item.badge && !collapsed" class="rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-500">
                            {{ item.badge }}
                        </span>
                    </div>
                </template>
            </nav>

            <!-- Collapse button -->
            <div
                class="hidden shrink-0 border-t p-3 lg:block"
                :class="isPlatform ? 'border-slate-800' : 'border-slate-200'"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm transition-colors"
                    :class="[
                        isPlatform
                            ? 'text-slate-400 hover:bg-slate-800 hover:text-white'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                        collapsed ? 'justify-center' : '',
                    ]"
                    :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    :aria-expanded="!collapsed"
                    @click="emit('toggle-collapse')"
                >
                    <svg
                        class="h-5 w-5 shrink-0 transition-transform"
                        :class="[
                            collapsed ? 'rotate-180' : '',
                            direction === 'rtl' ? 'scale-x-[-1]' : '',
                        ]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m15 18-6-6 6-6" />
                    </svg>

                    <span v-if="!collapsed">{{ $t('Collapse sidebar') }}</span>
                </button>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 200ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
