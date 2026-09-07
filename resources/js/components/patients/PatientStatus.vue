<script setup>defineProps({ status: String });</script>
<template><span class="badge capitalize" :class="status === 'active' ? 'badge-green' : status === 'archived' ? 'badge-rose' : 'bg-slate-100 text-slate-600'">{{ status }}</span></template>
