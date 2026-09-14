<script setup>
import { computed } from 'vue';
const props = defineProps({ modelValue: Object, options: Object });
const emit = defineEmits(['update:modelValue', 'reset']);
function set(key, value) { emit('update:modelValue', { ...props.modelValue, [key]: value, ...(key === 'business_type_id' ? { tenant_id: '' } : {}) }); }
const tenants = computed(() => (props.options.tenants || []).filter(t => !props.modelValue.business_type_id || String(t.business_type_id) === String(props.modelValue.business_type_id)));
</script>
<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" aria-label="Ticket filters">
        <label class="field"><span>Search tickets</span><input type="search" :value="modelValue.search" @input="set('search', $event.target.value)" placeholder="Search ticket, subject, business, user..." maxlength="255"></label>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="field"><span>Business / Tenant</span><select :value="modelValue.tenant_id" @change="set('tenant_id', $event.target.value)"><option value="">All businesses</option><option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
            <label class="field"><span>Business Type</span><select :value="modelValue.business_type_id" @change="set('business_type_id', $event.target.value)"><option value="">All business types</option><option v-for="t in options.business_types || []" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
            <label v-for="[key, label, values] in [['status', 'Status', options.statuses], ['priority', 'Priority', options.priorities], ['category', 'Category', options.categories]]" :key="key" class="field"><span>{{ label }}</span><select :value="modelValue[key]" @change="set(key, $event.target.value)"><option value="">All</option><option v-for="value in values || []" :key="value">{{ value }}</option></select></label>
            <label class="field"><span>Created from (UTC)</span><input type="date" :value="modelValue.date_from" @change="set('date_from', $event.target.value)"></label>
            <label class="field"><span>Created through (UTC)</span><input type="date" :value="modelValue.date_to" :min="modelValue.date_from || undefined" @change="set('date_to', $event.target.value)"></label>
            <div class="flex items-end"><button class="btn-secondary" type="button" @click="emit('reset')">Reset filters</button></div>
        </div>
    </section>
</template>
