<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import FormErrors from '../../components/ui/FormErrors.vue';
import { supportService } from '../../services/support';
import TicketConversation from '../../components/support/TicketConversation.vue';
import TicketBadge from '../../components/support/TicketBadge.vue';
import { ticketStatus } from '../../config/supportTickets';

const route = useRoute();
const downloadUrl = id => `/api/v1/clinic/support/tickets/${route.params.id}/attachments/${id}/download`;
const ticket = ref(null);
const messages = ref([]);
const attachments = ref([]);
const error = ref(null);
const reply = ref('');
const sending = ref(false);
const uploading = ref(false);

async function load() {
    try {
        const { data } = await supportService.getTicket(route.params.id);
        ticket.value = data.data.ticket;
        messages.value = data.data.messages || [];
        attachments.value = data.data.attachments || [];
    } catch (err) {
        error.value = err;
    }
}

async function sendReply() {
    if (!reply.value.trim()) return;
    sending.value = true;
    error.value = null;

    try {
        await supportService.replyToTicket(route.params.id, { message: reply.value.trim() });
        reply.value = '';
        await load();
    } catch (err) {
        error.value = err;
    } finally {
        sending.value = false;
    }
}

async function uploadAttachment(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    error.value = null;

    try {
        const payload = new FormData();
        payload.append('attachment', file);
        await supportService.uploadAttachment(route.params.id, payload);
        event.target.value = '';
        await load();
    } catch (err) {
        error.value = err;
    } finally {
        uploading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6 py-4">
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/support">Support Tickets</RouterLink>
                    <span>/</span>
                    {{ ticket?.ticket_number || 'Ticket' }}
                </p>
                <h1>{{ ticket?.ticket_number || 'Ticket' }}</h1>
                <p v-if="ticket">{{ ticket.subject }}</p>
            </div>
        </div>

        <FormErrors :error="error" />

        <section v-if="ticket" class="clinic-panel p-6">
            <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">{{ ticket.ticket_number }}</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-800">{{ ticket.subject }}</h2>
                </div>
                <TicketBadge :value="ticket.status" />
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.5fr,1fr]">
                <div class="space-y-4">
                    <TicketConversation :messages="messages" :attachments="attachments" :download-url="downloadUrl" />
                    <p v-if="ticket.status === ticketStatus.closed" class="rounded-lg bg-slate-100 p-4 text-sm text-slate-600">This ticket is closed. Contact platform support to request reopening.</p>

                    <div v-if="ticket.status !== ticketStatus.closed" class="rounded-xl border border-slate-200 bg-white p-4">
                        <label class="field">
                            <span>Reply</span>
                            <textarea v-model="reply" rows="4" placeholder="Add a reply to the support team"></textarea>
                        </label>
                        <div class="mt-3 flex justify-end gap-3">
                            <button class="btn" type="button" :disabled="sending || !reply.trim()" @click="sendReply">
                                {{ sending ? 'Sending...' : 'Send Reply' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-600">Ticket details</h3>
                        <dl class="space-y-2 text-sm text-slate-600">
                            <div class="flex justify-between gap-3"><dt>Category</dt><dd>{{ ticket.category }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Priority</dt><dd>{{ ticket.priority }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Created</dt><dd>{{ ticket.created_at }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Business</dt><dd>{{ ticket.tenant_name || 'Not recorded' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Branch</dt><dd>{{ ticket.branch_name || 'Not recorded' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Submitted by</dt><dd>{{ ticket.user_name || 'Current user' }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="attachments.length" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-600">Attachments</h3>
                        <div class="space-y-2">
                            <a
                                v-for="attachment in attachments"
                                :key="attachment.id"
                                class="block rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-sky-700 underline"
                                :href="`/api/v1/clinic/support/tickets/${route.params.id}/attachments/${attachment.id}/download`"
                                target="_blank"
                            >
                                {{ attachment.original_name }}
                            </a>
                        </div>
                    </div>

                    <label v-if="ticket.status !== ticketStatus.closed" class="field">
                        <span>Upload attachment</span>
                        <input type="file" :disabled="uploading" @change="uploadAttachment" />
                    </label>
                </div>
            </div>
        </section>
    </div>
</template>
