<script setup>
import { computed, ref, watch } from 'vue';
import { ticketStatus } from '../../../config/supportTickets';
const props = defineProps({ ticket: Object, options: Object, permissions: Array, busy: Boolean });
const emit = defineEmits(['update']);
const status = ref(''), priority = ref(''), category = ref(''), confirming = ref(false);
const can = name => props.permissions.includes(`support_tickets.${name}`);
const statuses = computed(() => (props.options.statuses || []).filter(value => value !== ticketStatus.closed || can('close')));
watch(() => props.ticket, ticket => { status.value = ticket.status; priority.value = ticket.priority; category.value = ticket.category; confirming.value = false; }, { immediate: true });
function updateStatus() {
    if ([ticketStatus.resolved, ticketStatus.closed].includes(status.value)) { confirming.value = true; return; }
    emit('update', 'status', status.value);
}
</script>
<template>
    <section v-if="can('update') || can('manage_priority')" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold text-slate-800">Manage ticket</h2>
        <form v-if="can('update') && (ticket.status !== ticketStatus.closed || can('close'))" class="space-y-2" @submit.prevent="updateStatus">
            <label class="field"><span>Status</span><select v-model="status" :disabled="busy" @change="confirming = false"><option v-for="value in statuses" :key="value">{{ value }}</option></select></label>
            <button class="btn-secondary" :disabled="busy || status === ticket.status">Update Status</button>
            <div v-if="confirming" class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-3" role="alert">
                <p class="text-sm">Mark this ticket as {{ status }}?<span v-if="status === ticketStatus.closed"> Replies will be disabled until platform support reopens it.</span></p>
                <div class="flex flex-wrap gap-2"><button class="btn" type="button" :disabled="busy" @click="emit('update', 'status', status); confirming = false">Confirm {{ status }}</button><button class="btn-secondary" type="button" :disabled="busy" @click="confirming = false">Cancel</button></div>
            </div>
        </form>
        <form v-if="can('manage_priority')" class="space-y-2" @submit.prevent="emit('update', 'priority', priority)"><label class="field"><span>Priority</span><select v-model="priority" :disabled="busy"><option v-for="value in options.priorities || []" :key="value">{{ value }}</option></select></label><button class="btn-secondary" :disabled="busy || priority === ticket.priority">Update Priority</button></form>
        <form v-if="can('update')" class="space-y-2" @submit.prevent="emit('update', 'category', category)"><label class="field"><span>Category</span><select v-model="category" :disabled="busy"><option v-for="value in options.categories || []" :key="value">{{ value }}</option></select></label><button class="btn-secondary" :disabled="busy || category === ticket.category">Update Category</button></form>
    </section>
</template>
