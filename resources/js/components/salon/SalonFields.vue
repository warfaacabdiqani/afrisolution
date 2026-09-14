<script setup>
defineProps({fields:Array,form:Object,options:Object,editing:Boolean});
const values=(field,options)=>field.values || options[field.source] || [];
</script>
<template><div class="form-grid">
    <template v-for="field in fields" :key="field.key">
        <h2 v-if="field.group" class="md:col-span-2 text-lg font-semibold mt-4">{{ field.group }}</h2>
        <fieldset v-if="field.type==='multi'" class="md:col-span-2"><legend class="font-semibold mb-2">{{ field.label }}</legend><label v-for="option in values(field,options)" :key="option.id" class="inline-flex items-center gap-2 mr-5 mb-3"><input v-model="form[field.key]" type="checkbox" :value="option.id">{{ option[field.optionLabel] }}</label><p v-if="!values(field,options).length" class="text-sm text-slate-500">No available {{ field.label.toLowerCase() }}. Create the related records first.</p></fieldset>
        <label v-else-if="field.type==='checkbox'" class="flex items-center gap-2"><input v-model="form[field.key]" type="checkbox">{{ field.label }}</label>
        <label v-else class="field">{{ field.label }}{{ field.required?' *':'' }}
            <textarea v-if="field.type==='textarea'" v-model="form[field.key]" rows="3" maxlength="5000"></textarea>
            <select v-else-if="field.type==='select'" v-model="form[field.key]" :required="field.required" :disabled="editing && field.immutable"><option value="">Select {{ field.label.toLowerCase() }}</option><option v-for="option in values(field,options)" :key="option.id || option" :value="typeof option==='object'?option.id:option">{{ typeof option==='object'?option[field.optionLabel]:option }}</option></select>
            <input v-else v-model="form[field.key]" :type="field.type" :required="field.required" :min="field.min" :max="field.max" :maxlength="field.type==='text'?(field.max || 255):undefined" :step="field.step || 1">
        </label>
    </template>
</div></template>
