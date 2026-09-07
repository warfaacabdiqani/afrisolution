<script setup>import { useRoute } from 'vue-router'; const route = useRoute();</script>
<template><section class="clinic-panel"><h2 class="text-lg font-bold">{{ route.meta.title }}</h2><p class="mt-4 text-slate-500">No {{ route.meta.title.toLowerCase() }} data is available. This workflow will be introduced in a later release.</p></section></template>
