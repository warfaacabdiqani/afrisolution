<script setup>
import { ref } from 'vue';
defineProps({ busy: Boolean, sending: Boolean });
const emit = defineEmits(['send']);
const message = ref(''), attachment = ref(null), input = ref(null);
function send() {
    const payload = new FormData();
    payload.append('message', message.value.trim());
    if (attachment.value) payload.append('attachment', attachment.value);
    emit('send', payload, () => { message.value = ''; attachment.value = null; if (input.value) input.value.value = ''; });
}
</script>
<template>
    <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="send">
        <h2 class="text-lg font-semibold text-slate-800">Reply to Ticket</h2>
        <label class="field"><span>Message *</span><textarea v-model="message" required maxlength="3000" rows="5" :disabled="busy" placeholder="Write a reply to the business user..."></textarea></label>
        <label class="field"><span>Attachment (optional)</span><input ref="input" type="file" accept=".png,.jpg,.jpeg,.pdf" :disabled="busy" @change="attachment = $event.target.files?.[0] || null"><span class="text-xs text-slate-500">PNG, JPG or PDF, up to 2 MB.</span></label>
        <div class="flex justify-end"><button class="btn" :disabled="busy || !message.trim()">{{ sending ? 'Sending...' : 'Send Reply' }}</button></div>
    </form>
</template>
