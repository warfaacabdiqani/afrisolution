<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../../stores/auth';
import { platformSupportService as service } from '../../../services/platformSupport';
import { ticketStatus, supportDate } from '../../../config/supportTickets';
import FormErrors from '../../../components/ui/FormErrors.vue';
import TicketBadge from '../../../components/support/TicketBadge.vue';
import TicketConversation from '../../../components/support/TicketConversation.vue';
import AttachmentList from '../../../components/support/AttachmentList.vue';
import AdminReplyBox from '../../../components/admin/support/AdminReplyBox.vue';
import TicketManagement from '../../../components/admin/support/TicketManagement.vue';

const route = useRoute(), auth = useAuthStore();
const data = ref(null), options = ref({}), error = ref(null), loading = ref(true), busy = ref(false), success = ref('');
const sending = ref(false);
const ticket = computed(() => data.value?.ticket);
const permissions = computed(() => auth.user?.platform_permissions || []);
const can = name => permissions.value.includes(`support_tickets.${name}`);
const downloadUrl = id => service.downloadUrl(route.params.id, id);
let sequence = 0;
async function load() {
    const request = ++sequence;
    const response = await service.get(route.params.id);
    if (request === sequence) data.value = response.data.data;
}
watch(() => route.params.id, async () => {
    data.value = null; loading.value = true; error.value = null; success.value = '';
    try { await Promise.all([load(), service.options().then(response => { options.value = response.data.data; })]); }
    catch (e) { error.value = e; } finally { loading.value = false; }
}, { immediate: true });
async function reply(payload, reset) {
    busy.value = true; sending.value = true; error.value = null; success.value = '';
    try { await service.reply(route.params.id, payload); reset(); success.value = 'Reply sent to the shared conversation.'; await load(); }
    catch (e) { error.value = e; } finally { busy.value = false; sending.value = false; }
}
async function update(field, value) {
    busy.value = true; error.value = null; success.value = '';
    try { await service.update(route.params.id, field, value); success.value = 'Ticket updated.'; await load(); }
    catch (e) { error.value = e; } finally { busy.value = false; }
}
</script>
<template>
    <div class="space-y-6">
        <RouterLink :to="{ name: 'admin.support' }" class="text-sm font-medium text-teal-800">← Support Tickets</RouterLink>
        <FormErrors :error="error" /><p v-if="success" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ success }}</p>
        <p v-if="loading" role="status" class="text-slate-500">Loading ticket...</p>
        <template v-else-if="ticket">
            <header class="page-header"><div class="min-w-0"><p class="eyebrow">{{ ticket.ticket_number }} · Ticket ID {{ ticket.id }}</p><h1 class="break-words">{{ ticket.subject }}</h1><p>{{ ticket.tenant_name || 'Unavailable business' }}</p></div><div class="flex flex-wrap gap-2"><TicketBadge :value="ticket.status" /><TicketBadge :value="ticket.priority" /></div></header>
            <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                <div class="min-w-0 space-y-6">
                    <section class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 sm:p-6"><h2 class="text-lg font-semibold text-slate-800">Issue details</h2>
                        <div v-for="[key, label] in [['description', 'Description'], ['steps_to_reproduce', 'Steps to Reproduce'], ['expected_result', 'Expected Result'], ['actual_result', 'Actual Result']]" :key="key"><h3 class="mb-2 text-sm font-semibold text-slate-700">{{ label }}</h3><p class="whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">{{ ticket[key] || 'Not recorded' }}</p></div>
                        <div><h3 class="mb-2 text-sm font-semibold text-slate-700">Attachments</h3><AttachmentList :attachments="data.attachments" :download-url="downloadUrl" :can-download="can('view_attachments')" /><p v-if="data.attachments.length && !can('view_attachments')" class="mt-2 text-xs text-slate-500">Attachment download permission is required.</p></div>
                    </section>
                    <TicketConversation :messages="data.messages" :attachments="data.attachments" :download-url="downloadUrl" :can-download="can('view_attachments')" />
                    <p v-if="ticket.status === ticketStatus.closed" class="rounded-xl bg-slate-100 p-4 text-sm text-slate-600">This ticket is closed. An authorized platform administrator must reopen it before replies can be added.</p>
                    <AdminReplyBox v-else-if="can('reply')" :busy="busy" :sending="sending" @send="reply" />
                </div>
                <aside class="min-w-0 space-y-6">
                    <section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="mb-4 text-lg font-semibold text-slate-800">Ticket information</h2><dl class="space-y-4 text-sm">
                        <div v-for="[key, label] in [['tenant_name', 'Business / Tenant'], ['business_type_name', 'Business Type'], ['branch_name', 'Branch'], ['user_name', 'Submitted By'], ['user_email', 'Submitter Email'], ['user_role', 'User Role'], ['category', 'Category'], ['priority', 'Priority'], ['status', 'Status'], ['current_page', 'Current Page / Route']]" :key="key"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</dt><dd class="mt-1 break-words text-slate-800">{{ ticket[key] || 'Not recorded' }}</dd></div>
                        <div><dt class="text-xs font-medium uppercase text-slate-500">Created</dt><dd class="mt-1">{{ supportDate(ticket.created_at) }}</dd></div><div><dt class="text-xs font-medium uppercase text-slate-500">Last Updated</dt><dd class="mt-1">{{ supportDate(ticket.updated_at) }}</dd></div>
                    </dl></section>
                    <TicketManagement :ticket="ticket" :options="options" :permissions="permissions" :busy="busy" @update="update" />
                </aside>
            </div>
        </template>
    </div>
</template>
