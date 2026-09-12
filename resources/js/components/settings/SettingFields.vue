<script setup>
defineProps({ fields: Object, model: Object, timezones: Array, branches: Array, disabled: Boolean });
const label = value => ({ en: 'English', so: 'Somali', sw: 'Swahili', ar: 'Arabic' }[value] || String(value).replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
</script>
<template><div class="cs-fields"><div v-for="(field, key) in fields" :key="key" :class="{ 'cs-wide': field.type === 'textarea' || field.type === 'multiselect' }">
    <label v-if="field.type === 'checkbox'" class="cs-toggle"><input v-model="model[key]" type="checkbox" :disabled="disabled || field.locked"><span>{{ field.label }}</span></label>
    <fieldset v-else-if="field.type === 'multiselect'" :disabled="disabled || field.locked"><legend>{{ field.label }}</legend><label v-for="value in field.options" :key="value" class="cs-toggle"><input v-model="model[key]" type="checkbox" :value="value">{{ label(value) }}</label></fieldset>
    <label v-else class="field">{{ field.label }}
        <textarea v-if="field.type === 'textarea'" v-model="model[key]" rows="3" :disabled="disabled || field.locked"></textarea>
        <select v-else-if="['select','timezone','branch'].includes(field.type)" v-model="model[key]" :aria-label="field.label" :disabled="disabled || field.locked"><template v-if="field.type === 'branch'"><option :value="null">Use current branch</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></template><option v-for="value in field.type === 'timezone' ? timezones : field.options" v-else :key="value" :value="value">{{ label(value) }}</option></select>
        <input v-else v-model="model[key]" :type="field.type" :disabled="disabled || field.locked" :step="field.type === 'number' ? 'any' : undefined">
    </label><p v-if="field.help" class="cs-hint">{{ field.help }}</p>
</div></div></template>
