<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-slate-100 px-6 py-10 text-slate-700">
        <div class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-5xl items-center justify-center">
            <div class="flex items-center gap-5 sm:gap-7">
                <div class="text-[4rem] font-light leading-none tracking-tight text-slate-600 sm:text-[5.5rem] md:text-[6.5rem]">
                    404
                </div>

                <div class="h-16 w-px bg-slate-300 sm:h-20 md:h-24"></div>

                <div class="space-y-6">
                    <h1 class="text-2xl font-medium uppercase tracking-[0.18em] text-slate-600 sm:text-3xl md:text-4xl">
                        Not Found
                    </h1>

                    <p class="max-w-md text-base text-slate-500 sm:text-lg">
                        The page you were looking for does not exist or may have moved.
                    </p>

                    <RouterLink
                        :to="{ name: 'home' }"
                        class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2"
                    >
                        Back to home
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>
