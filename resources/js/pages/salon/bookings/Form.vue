<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { salonBookingApi as api } from '../../../services/salonBookings';
import { useClinicContextStore } from '../../../stores/clinicContext';
import FormErrors from '../../../components/ui/FormErrors.vue';
const route=useRoute(), router=useRouter(), context=useClinicContextStore();
const options=ref(null), existing=ref(null), clients=ref([]), search=ref(''), error=ref(null), busy=ref(false), ready=ref(false), slots=ref([]), slotMessage=ref(''), eligible=ref(null), checking=ref(false);
const form=reactive({ branch_id:Number(route.query.branch_id)||context.data.branch.id, client_id:Number(route.query.client_id)||'', stylist_id:Number(route.query.stylist_id)||'', service_ids:[], date:route.query.date||context.data.today, start_time:route.query.start_time||'', source:route.query.walk_in?'walk_in':'reception', notes:'', discount:0, override_conflict:false, override_reason:'' });
const reschedule=computed(()=>Boolean(route.meta.reschedule));
let version=0, searchVersion=0, slotVersion=0, eligibilityVersion=0, timer;
const services=computed(()=>options.value?.services||[]);
const chosen=computed(()=>form.service_ids.map(id=>existing.value?.items.find(i=>i.service_id===id)||services.value.find(s=>s.id===id)).filter(Boolean));
const duration=computed(()=>chosen.value.reduce((sum,s)=>sum+Number(s.duration_minutes),0));
const subtotal=computed(()=>chosen.value.reduce((sum,s)=>sum+Number(s.unit_price??s.price),0));
const taxRate=computed(()=>Number(existing.value?.tax_rate??options.value?.settings.default_service_tax??0));
const total=computed(()=>Math.max(0,subtotal.value-Number(form.discount||0))*(1+taxRate.value/100));
const deposit=computed(()=>chosen.value.reduce((sum,s)=>sum+Number(s.deposit_amount||0),0));
const stylists=computed(()=>(options.value?.stylists||[]).filter(s=>form.service_ids.every(id=>s.service_ids.includes(id)) && (eligible.value===null || eligible.value.includes(s.id) || form.override_conflict)));
const selectedClient=computed(()=>clients.value.find(c=>c.id===Number(form.client_id)));
async function loadClients() {
    const token=++searchVersion;
    try { const response=await api.get('appointments/clients',{branch_id:form.branch_id,search:search.value}); if(token===searchVersion) { clients.value=response.data.data; if(existing.value && !clients.value.some(c=>c.id===existing.value.client_id)) clients.value.unshift({id:existing.value.client_id,name:existing.value.client.full_name,phone:existing.value.client.phone,client_number:existing.value.client.client_number}); } } catch(e){if(token===searchVersion)error.value=e;}
}
async function loadOptions() { const token=++version; const response=await api.get('appointments/options',{branch_id:form.branch_id}); if(token===version)options.value=response.data.data; }
async function available() {
    const token=++eligibilityVersion; eligible.value=null;
    if(!ready.value || !form.client_id || !form.service_ids.length || !form.date || !form.start_time || form.override_conflict) { checking.value=false; return; }
    checking.value=true;
    try { const response=await api.get('appointments/available-stylists',{branch_id:form.branch_id,client_id:form.client_id,service_ids:form.service_ids,date:form.date,start_time:form.start_time,appointment_id:existing.value?.id}); if(token===eligibilityVersion) { eligible.value=response.data.data.map(s=>s.id); if(form.stylist_id && !eligible.value.includes(Number(form.stylist_id)))form.stylist_id=''; } } catch(e){if(token===eligibilityVersion)error.value=e;} finally{if(token===eligibilityVersion)checking.value=false;}
}
async function loadSlots() {
    const token=++slotVersion; slots.value=[]; slotMessage.value='';
    if(!ready.value || !form.client_id || !form.stylist_id || !form.service_ids.length || !form.date)return;
    try { const response=await api.get('appointments/available-slots',{branch_id:form.branch_id,client_id:form.client_id,stylist_id:form.stylist_id,service_ids:form.service_ids,date:form.date,appointment_id:existing.value?.id}); if(token===slotVersion){slots.value=response.data.data.slots;slotMessage.value=response.data.data.message;} } catch(e){if(token===slotVersion)error.value=e;}
}
watch(search,()=>{clearTimeout(timer);timer=setTimeout(loadClients,300);});
watch(()=>[form.client_id,form.service_ids.join(','),form.date,form.start_time,form.override_conflict],available);
watch(()=>[form.client_id,form.service_ids.join(','),form.date,form.stylist_id],loadSlots);
watch(()=>form.branch_id,async()=>{if(!ready.value)return;form.client_id='';form.stylist_id='';form.service_ids=[];eligible.value=null;try{await Promise.all([loadOptions(),loadClients()]);}catch(e){error.value=e;}});
onMounted(async()=>{
    try {
        if(route.params.id) { existing.value=(await api.get('appointments/'+route.params.id)).data.data; const a=existing.value; Object.assign(form,{branch_id:a.branch_id,client_id:a.client_id,stylist_id:a.stylist_id,service_ids:a.items.map(i=>i.service_id),date:a.starts_at.slice(0,10),start_time:a.starts_at.slice(11,16),source:a.source,notes:a.notes||'',discount:Number(a.discount)}); }
        await Promise.all([loadOptions(),loadClients()]);
        if(form.source==='walk_in'&&!existing.value){form.date=options.value.now.slice(0,10);form.start_time=options.value.now.slice(11,16);}
        ready.value=true; await Promise.all([available(),loadSlots()]);
    }catch(e){error.value=e;}
});
onBeforeUnmount(()=>{++version;++searchVersion;++slotVersion;++eligibilityVersion;clearTimeout(timer);});
async function save() {
    busy.value=true;error.value=null;
    try { const payload={...form,service_ids:[...form.service_ids]}; const response=existing.value ? (reschedule.value?await api.post('appointments/'+existing.value.id+'/reschedule',payload):await api.put('appointments/'+existing.value.id,payload)):await api.post('appointments',payload); await router.push({path:'/app/appointments/'+response.data.data.id,query:{date:form.date,branch_id:form.branch_id}}); }catch(e){error.value=e;}finally{busy.value=false;}
}
</script>
<template><div><RouterLink to="/app/appointments">← Appointments</RouterLink><header class="patient-page-header"><div><h1>{{ reschedule?'Reschedule Appointment':existing?'Edit Appointment':form.source==='walk_in'?'Walk-In Appointment':'New Appointment' }}</h1><p>Choose a client, services, stylist and available time.</p></div></header><FormErrors :error="error"/><p v-if="!ready" role="status">Loading booking options…</p>
<form v-else class="clinic-panel p-6 space-y-5" @submit.prevent="save"><div class="form-grid">
<label class="field">Location<select v-model.number="form.branch_id" aria-label="Location" :disabled="!!existing" required><option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option></select></label>
<div><label class="field">Search Clients<input v-model="search" placeholder="Client ID, name, phone or email" :disabled="reschedule"></label><label class="field mt-2">Client<select v-model.number="form.client_id" aria-label="Client" :disabled="reschedule" required><option value="">Select client</option><option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }} · {{ c.client_number }} · {{ c.phone }}</option></select></label><p v-if="selectedClient" class="hint">{{ selectedClient.name }} · {{ selectedClient.phone || selectedClient.email }}</p><RouterLink v-if="context.allowed('clients')&&context.can('clients.create')&&!reschedule" class="text-emerald-700" to="/app/clients/create">+ Add New Client</RouterLink></div>
</div><fieldset :disabled="reschedule"><legend class="font-semibold">Services</legend><div class="grid gap-2 md:grid-cols-2 mt-3"><label v-for="service in services" :key="service.id" class="flex items-start gap-3 rounded-lg border p-3"><input v-model="form.service_ids" type="checkbox" :value="service.id" :aria-label="service.name"><span><strong>{{ service.name }}</strong><small class="block text-slate-500">{{ service.category }} · {{ service.duration_minutes }} min · {{ options.currency }} {{ Number(service.price).toFixed(2) }}</small></span></label></div></fieldset>
<div class="form-grid"><label class="field">Date<input v-model="form.date" type="date" required></label><label class="field">Start Time<input v-model="form.start_time" type="time" required></label><label class="field">Stylist<select v-model.number="form.stylist_id" aria-label="Stylist" required><option value="">Select stylist</option><option v-for="s in stylists" :key="s.id" :value="s.id">{{ s.name }}</option></select><span v-if="checking" class="hint">Checking availability…</span><span v-else-if="!stylists.length" class="hint">No stylist is available for these services and time. Choose another time or check schedules.</span></label><label class="field">Source<select v-model="form.source" aria-label="Source" :disabled="reschedule"><option value="reception">Reception</option><option value="phone">Phone</option><option value="online">Online</option><option v-if="options.settings.allow_walk_in || existing?.source==='walk_in'" value="walk_in">Walk-in</option></select></label></div>
<div v-if="form.stylist_id"><h2 class="font-semibold">Available Times</h2><p v-if="slotMessage" class="hint">{{ slotMessage }}</p><div class="flex flex-wrap gap-2 mt-2"><button v-for="slot in slots" :key="slot" type="button" class="btn-secondary" :class="{'ring-2 ring-emerald-500':form.start_time===slot}" @click="form.start_time=slot">{{ slot }}</button></div></div>
<div class="rounded-lg bg-slate-50 p-4"><p><strong>Duration:</strong> {{ duration }} minutes</p><p><strong>Subtotal:</strong> {{ options.currency }} {{ subtotal.toFixed(2) }}</p><label v-if="context.can('appointments.discount')&&!reschedule" class="field mt-2">Discount (fixed amount)<input v-model.number="form.discount" type="number" min="0" :max="subtotal" step="0.01"></label><p v-if="taxRate">Tax: {{ taxRate }}%</p><p class="font-semibold mt-2">Total: {{ options.currency }} {{ total.toFixed(2) }}</p><p v-if="deposit" class="mt-2">Deposit required: {{ options.currency }} {{ Math.min(deposit,total).toFixed(2) }}. {{ options.settings.deposit_policy || 'Arrange the deposit with reception. Online payments are not connected.' }}</p><p v-if="existing" class="hint">Existing services keep their booked duration and price.</p></div>
<label class="field">Notes<textarea v-model="form.notes" rows="3" maxlength="5000" :disabled="reschedule"></textarea></label><p v-if="options.settings.cancellation_policy" class="hint">Cancellation policy: {{ options.settings.cancellation_policy }}</p>
<div v-if="options.settings.allow_overbooking&&context.can('appointments.override_conflict')"><label><input v-model="form.override_conflict" type="checkbox"> Override stylist conflict</label><label v-if="form.override_conflict" class="field">Override Reason<input v-model="form.override_reason" required maxlength="500"></label></div>
<div class="flex justify-end gap-3"><RouterLink class="btn-secondary" to="/app/appointments">Cancel</RouterLink><button class="btn" :disabled="busy||checking||!form.service_ids.length">{{ busy?'Saving…':reschedule?'Reschedule Appointment':'Save Appointment' }}</button></div></form></div></template>
