<script setup>
defineProps({ attachments: { type: Array, default: () => [] }, downloadUrl: { type: Function, required: true }, canDownload: { type: Boolean, default: true } });
</script>
<template>
    <ul v-if="attachments.length" class="space-y-2">
        <li v-for="attachment in attachments" :key="attachment.id" class="break-all text-sm">
            <a v-if="canDownload" :href="downloadUrl(attachment.id)" class="font-medium text-teal-800 underline" target="_blank" rel="noopener">{{ attachment.original_name }}</a>
            <span v-else>{{ attachment.original_name }}</span>
            <span class="ml-2 text-xs text-slate-500">{{ Math.ceil(attachment.size / 1024) }} KB</span>
        </li>
    </ul>
    <p v-else class="text-sm text-slate-500">No attachments.</p>
</template>
