<script setup>
import { computed } from 'vue';
import { ticketStatus as status, ticketPriority as priority } from '../../config/supportTickets';
const props = defineProps({ value: String });
const tones = {
    [status.open]: 'bg-blue-50 text-blue-800', [status.inProgress]: 'bg-amber-50 text-amber-800',
    [status.waiting]: 'bg-purple-50 text-purple-800', [status.resolved]: 'bg-emerald-50 text-emerald-800',
    [status.closed]: 'bg-slate-100 text-slate-700', [priority.low]: 'bg-slate-100 text-slate-700',
    [priority.normal]: 'bg-blue-50 text-blue-800', [priority.high]: 'bg-amber-50 text-amber-800',
    [priority.urgent]: 'bg-red-50 text-red-800',
};
const tone = computed(() => tones[props.value] || 'bg-slate-100 text-slate-700');
</script>
<template><span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', tone]">{{ value }}</span></template>
