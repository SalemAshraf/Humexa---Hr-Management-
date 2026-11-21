
<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import LanguageSwitch from '@/Components/LanguageSwitch.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
    subtitle: {
        type: String,
        default: '',
    },
    theme: {
        type: String,
        default: 'company',
        validator: (value) => ['platform', 'company'].includes(value),
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    direction: {
        type: String,
        default: 'ltr',
    },
    logoutUrl: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();

const isPlatform = computed(() => props.theme === 'platform');

const user = computed(() => page.props.auth?.user ?? null);
const attention = computed(() => Number(page.props.attention_count ?? 0));

const onKey = (event) => {
    if (!isPlatform.value && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        router.visit('/search');
    }
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-[72px] items-center justify-between gap-4 border-b px-4 sm:px-6 lg:px-8"
        :class="isPlatform
            ? 'border-slate-800 bg-[#111C2E] text-white'
            : 'border-slate-200 bg-white text-slate-800'"
        :dir="direction"
    >
        <!-- Left section -->
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-colors"
                :class="isPlatform
                    ? 'text-slate-300 hover:bg-slate-800'
                    : 'text-slate-600 hover:bg-slate-100'"
                :aria-label="$t('Toggle sidebar')"
                @click="emit('toggle-sidebar')"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="min-w-0">
                <h2 class="truncate text-base font-semibold sm:text-lg">
                    {{ title }}
                </h2>

                <p
                    v-if="subtitle"
                    class="hidden truncate text-xs sm:block"
                    :class="isPlatform ? 'text-slate-400' : 'text-slate-500'"
                >
                    {{ subtitle }}
                </p>
            </div>
        </div>

        <!-- Right section -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-4">
            <LanguageSwitch :dark="isPlatform" />

            <!-- Search -->
            <component
                :is="isPlatform ? 'button' : Link"
                v-bind="isPlatform ? { type: 'button' } : { href: '/search' }"
                class="hidden h-10 items-center gap-2 rounded-xl border px-3 text-sm transition-colors md:flex"
                :class="isPlatform
                    ? 'border-slate-700 text-slate-400 hover:bg-slate-800'
                    : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
                :aria-label="$t('Search')"
            >
                <svg
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-4-4" />
                </svg>

                <span>{{ $t('Search') }}</span>

                <kbd
                    class="rounded border px-1.5 py-0.5 text-xs"
                    :class="isPlatform ? 'border-slate-700' : 'border-slate-200'"
                >{{ $t('Ctrl K') }}</kbd>
            </component>

            <!-- Notifications -->
            <component
                :is="isPlatform ? 'button' : Link"
                v-bind="isPlatform ? { type: 'button' } : { href: '/notifications' }"
                class="relative flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                :class="isPlatform
                    ? 'text-slate-300 hover:bg-slate-800'
                    : 'text-slate-600 hover:bg-slate-100'"
                :aria-label="$t('Notifications')"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <path
                        d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                    />
                    <path d="M10 21h4" />
                </svg>

                <span
                    v-if="isPlatform"
                    class="absolute end-2 top-2 h-2 w-2 rounded-full bg-cyan-500 ring-2 ring-[#111C2E]"
                />
                <span
                    v-else-if="attention > 0"
                    class="absolute -end-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-cyan-600 px-1 text-[11px] font-bold text-white ring-2 ring-white"
                    data-testid="attention-count"
                >{{ attention > 99 ? '99+' : attention }}</span>
            </component>

            <!-- User -->
            <div
                class="flex items-center gap-2 border-s ps-3 sm:gap-3"
                :class="isPlatform ? 'border-slate-700' : 'border-slate-200'"
            >
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-cyan-600 text-sm font-semibold text-white"
                >
                    {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                </div>

                <div class="hidden min-w-0 sm:block">
                    <p class="max-w-32 truncate text-sm font-semibold">
                        {{ user?.name || 'User' }}
                    </p>

                    <p
                        class="max-w-32 truncate text-xs"
                        :class="isPlatform ? 'text-slate-400' : 'text-slate-500'"
                    >
                        {{ user?.email || '' }}
                    </p>
                </div>
            </div>

            <!-- Logout -->
            <Link
                :href="logoutUrl"
                method="post"
                as="button"
                type="button"
                class="flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                :class="isPlatform
                    ? 'text-slate-300 hover:bg-slate-800 hover:text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <svg
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <path d="M10 17l5-5-5-5M15 12H3" />
                    <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
                </svg>
                <span class="hidden sm:inline">{{ $t('Logout') }}</span>
            </Link>
        </div>
    </header>
</template>

