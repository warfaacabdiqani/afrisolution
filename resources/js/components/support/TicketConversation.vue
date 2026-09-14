<script setup>
import AttachmentList from './AttachmentList.vue';
import { messageSource, supportDate } from '../../config/supportTickets';
defineProps({ messages: { type: Array, default: () => [] }, attachments: { type: Array, default: () => [] }, downloadUrl: { type: Function, required: true }, canDownload: { type: Boolean, default: true } });
</script>
<template>
    <section class="space-y-4" aria-label="Ticket conversation">
        <h2 class="text-lg font-semibold text-slate-800">Conversation</h2>
        <article v-for="message in messages" :key="message.id" :class="['rounded-xl border p-4', message.source === messageSource.platform ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-white']">
            <header class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div><p class="font-semibold text-slate-800">{{ message.user_name || 'Former user' }}</p><p class="text-xs font-medium text-slate-600">{{ message.source === messageSource.platform ? 'Platform Support / Admin' : 'Business User' }}</p></div>
                <time class="text-xs text-slate-500">{{ supportDate(message.created_at) }}</time>
            </header>
            <p class="whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ message.message }}</p>
            <div v-if="attachments.some(a => a.message_id === message.id)" class="mt-3 border-t border-slate-200 pt-3">
                <AttachmentList :attachments="attachments.filter(a => a.message_id === message.id)" :download-url="downloadUrl" :can-download="canDownload" />
            </div>
        </article>
        <p v-if="!messages.length" class="text-sm text-slate-500">No messages yet.</p>
    </section>
</template>
