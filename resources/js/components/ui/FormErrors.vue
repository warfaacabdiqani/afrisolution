<script setup>
import { computed } from 'vue';
const props = defineProps({ error: { type: Object, default: null } });
const messages = computed(() => {
    const data = props.error?.response?.data;
    return data?.errors ? Object.values(data.errors).flat() : [data?.message || 'Unable to complete the request. Please try again.'];
});
</script>
<template>
    <div v-if="error" role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800">
        <p v-for="message in messages" :key="message">{{ message }}</p>
    </div>
</template>

