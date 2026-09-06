<script setup>
import { RouterLink, RouterView } from 'vue-router';
import { useUiStore } from './stores/ui';

const ui = useUiStore();
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <a href="#main" class="sr-only focus:not-sr-only focus:block focus:p-4">Skip to content</a>
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
                <RouterLink :to="{ name: 'home' }" class="text-xl font-semibold tracking-tight text-teal-800">
                    Afri Clinic
                </RouterLink>
                <span class="text-sm text-slate-500">Healthcare platform</span>
            </div>
        </header>
        <main id="main" class="mx-auto max-w-5xl space-y-6 px-6 py-10">
            <div v-if="ui.notice" role="status" class="flex items-start justify-between gap-4 rounded-xl bg-teal-50 p-4 text-sm text-teal-900">
                <p>{{ ui.notice }}</p>
                <button type="button" class="shrink-0 rounded px-2 font-medium underline focus-visible:outline-2 focus-visible:outline-offset-4" @click="ui.dismissNotice">
                    Dismiss
                </button>
            </div>
            <RouterView />
        </main>
    </div>
</template>
