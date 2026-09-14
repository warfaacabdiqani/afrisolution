<script setup>
import TicketBadge from '../../support/TicketBadge.vue';
import { supportDate } from '../../../config/supportTickets';
defineProps({ tickets: Array, loading: Boolean, filtered: Boolean });
</script>
<template>
    <div :aria-busy="loading" class="rounded-2xl border border-slate-200 bg-white">
        <p v-if="loading" class="p-6 text-sm text-slate-500" role="status">Loading support tickets...</p>
        <template v-else-if="tickets.length">
            <div class="hidden overflow-x-auto md:block"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th v-for="label in ['Ticket', 'Subject', 'Business / Tenant', 'Business Type', 'Submitted By', 'Category', 'Priority', 'Status', 'Created', 'Updated', 'Actions']" :key="label" class="whitespace-nowrap px-4 py-4">{{ label }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="t in tickets" :key="t.id" class="align-top text-slate-600 hover:bg-slate-50/60">
                    <td class="whitespace-nowrap px-4 py-4 font-semibold text-teal-800">{{ t.ticket_number }}</td><td class="min-w-56 max-w-sm break-words px-4 py-4 font-medium text-slate-800">{{ t.subject }}</td>
                    <td class="px-4 py-4">{{ t.tenant_name || 'Unavailable business' }}</td><td class="px-4 py-4">{{ t.business_type_name || 'Not recorded' }}</td>
                    <td class="px-4 py-4"><p>{{ t.user_name || 'Former user' }}</p><p class="mt-1 text-xs">{{ t.user_email }}</p></td><td class="px-4 py-4">{{ t.category }}</td>
                    <td class="px-4 py-4"><TicketBadge :value="t.priority" /></td><td class="px-4 py-4"><TicketBadge :value="t.status" /></td>
                    <td class="min-w-36 px-4 py-4 text-xs">{{ supportDate(t.created_at) }}</td><td class="min-w-36 px-4 py-4 text-xs">{{ supportDate(t.updated_at) }}</td>
                    <td class="px-4 py-4"><RouterLink :to="{ name: 'admin.support.show', params: { id: t.id } }" class="btn-secondary inline-flex whitespace-nowrap">View / Manage</RouterLink></td>
                </tr></tbody></table></div>
            <div class="divide-y divide-slate-200 md:hidden"><article v-for="t in tickets" :key="t.id" class="space-y-3 p-4">
                <div class="flex flex-wrap justify-between gap-2"><span class="text-sm font-semibold text-teal-800">{{ t.ticket_number }}</span><TicketBadge :value="t.status" /></div>
                <h2 class="break-words font-semibold text-slate-800">{{ t.subject }}</h2>
                <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2 text-sm text-slate-600"><dt>Business</dt><dd>{{ t.tenant_name }}</dd><dt>Type</dt><dd>{{ t.business_type_name || 'Not recorded' }}</dd><dt>Submitted by</dt><dd class="break-all">{{ t.user_name }}<br>{{ t.user_email }}</dd><dt>Category</dt><dd>{{ t.category }}</dd><dt>Priority</dt><dd><TicketBadge :value="t.priority" /></dd><dt>Created</dt><dd>{{ supportDate(t.created_at) }}</dd><dt>Updated</dt><dd>{{ supportDate(t.updated_at) }}</dd></dl>
                <RouterLink :to="{ name: 'admin.support.show', params: { id: t.id } }" class="btn-secondary inline-flex">View / Manage</RouterLink>
            </article></div>
        </template>
        <div v-else class="p-10 text-center text-slate-500">{{ filtered ? 'No tickets match your filters.' : 'No support tickets have been submitted.' }}</div>
    </div>
</template>
