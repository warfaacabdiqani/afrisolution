<script setup>
import { computed } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
const props = defineProps({ widget: Object });
const value = computed(() => {
    if (!props.widget.available || props.widget.value === null) return 'Unavailable';
    if (props.widget.format === 'currency') return new Intl.NumberFormat(undefined, { style: 'currency', currency: props.widget.currency }).format(props.widget.value);
    if (props.widget.format === 'number') return new Intl.NumberFormat().format(props.widget.value);
    return props.widget.value;
});
</script>
<template><section class="clinic-panel clinic-kpi" :data-widget="widget.key"><span class="clinic-kpi-icon" :class="widget.tone"><AppIcon :name="widget.icon" :size="29" /></span><div><h2>{{ widget.label }}</h2><strong>{{ value }}</strong><p>{{ widget.description }}</p><RouterLink v-if="widget.key==='monthly_revenue'&&widget.available" class="text-emerald-700 text-sm font-semibold" to="/app/billing/invoices">View Billing</RouterLink></div></section></template>
