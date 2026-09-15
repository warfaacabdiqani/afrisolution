<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { salonBookingApi as api } from '../../services/salonBookings';
import { useClinicContextStore } from '../../stores/clinicContext';
import FormErrors from '../ui/FormErrors.vue';
const props=defineProps({stylistId:Number,branches:Array});const context=useClinicContextStore();
const branch=ref(context.data.branch.id),days=ref([]),off=ref([]),error=ref(null),notice=ref(''),busy=ref(false),loading=ref(true),start=ref(''),end=ref(''),kind=ref('leave'),reason=ref('');
const names=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
let generation=0;
const locations=computed(()=>props.branches||context.data.branches);
const writable=computed(()=>props.stylistId?context.can('salon_staff.manage'):(context.can('clinic_settings.branches.manage')||context.can('clinic_settings.update')));
const path=computed(()=>props.stylistId?`stylists/${props.stylistId}/schedule`:'location-hours');
async function load(){
    const token=++generation,location=branch.value;loading.value=true;error.value=null;notice.value='';
    try {
        const r=await api.get(path.value,{branch_id:location});if(token!==generation)return;
        days.value=names.map((_,i)=>{const old=r.data.data.find(d=>Number(d.day_of_week)===i+1);return {day_of_week:i+1,is_available:Boolean(old?.is_available),start_time:old?.start_time.slice(0,5)||'08:00',end_time:old?.end_time.slice(0,5)||'18:00',break_start:old?.break_start?.slice(0,5)||'',break_end:old?.break_end?.slice(0,5)||''};});
        if(props.stylistId){const response=await api.get(`stylists/${props.stylistId}/time-off`,{branch_id:location});if(token===generation)off.value=response.data.data;}
    }catch(e){if(token===generation)error.value=e;}finally{if(token===generation)loading.value=false;}
}
async function save(){busy.value=true;error.value=null;notice.value='';try{await api.put(path.value,{branch_id:branch.value,days:days.value});notice.value='Working hours saved.';}catch(e){error.value=e;}finally{busy.value=false;}}
async function addOff(){busy.value=true;error.value=null;try{await api.post(`stylists/${props.stylistId}/time-off`,{branch_id:branch.value,starts_at:start.value.replace('T',' '),ends_at:end.value.replace('T',' '),kind:kind.value,reason:reason.value});start.value='';end.value='';reason.value='';await load();}catch(e){error.value=e;}finally{busy.value=false;}}
async function cancel(id){busy.value=true;try{await api.post(`stylists/${props.stylistId}/time-off/${id}/cancel`,{branch_id:branch.value});await load();}catch(e){error.value=e;}finally{busy.value=false;}}
onMounted(load);watch(branch,load);
onBeforeUnmount(()=>{++generation;});
</script>
<template><section class="clinic-panel p-5 mt-5"><h2 class="font-semibold text-lg">{{ stylistId?'Stylist Schedule':'Location Business Hours' }}</h2><p class="hint mt-2">{{ stylistId?'Configure working days within location business hours. Appointments also respect location breaks.':'Days without configured open hours cannot accept appointments.' }}</p><label class="field mt-4">Schedule Location<select v-model.number="branch" aria-label="Schedule Location"><option v-for="b in locations" :key="b.id" :value="b.id">{{ b.name }}</option></select></label><FormErrors :error="error"/><p v-if="notice" role="status" class="text-emerald-700 my-3">{{ notice }}</p><form @submit.prevent="save"><fieldset :disabled="!writable||busy||loading"><div class="overflow-x-auto mt-4"><table class="w-full text-sm"><thead><tr><th>Day</th><th>Open</th><th>Start</th><th>End</th><th>Break Start</th><th>Break End</th></tr></thead><tbody><tr v-for="d in days" :key="d.day_of_week"><td>{{ names[d.day_of_week-1] }}</td><td><input v-model="d.is_available" type="checkbox" :aria-label="names[d.day_of_week-1]+' Open'"></td><td><input v-model="d.start_time" type="time" :aria-label="names[d.day_of_week-1]+' Start'" required></td><td><input v-model="d.end_time" type="time" :aria-label="names[d.day_of_week-1]+' End'" required></td><td><input v-model="d.break_start" type="time" :aria-label="names[d.day_of_week-1]+' Break Start'"></td><td><input v-model="d.break_end" type="time" :aria-label="names[d.day_of_week-1]+' Break End'"></td></tr></tbody></table></div><button v-if="writable" class="btn mt-4">{{ busy?'Saving…':'Save Working Hours' }}</button></fieldset></form>
<template v-if="stylistId"><h3 class="font-semibold mt-6">Time Off</h3><p class="hint">Time off applies to this stylist at every location.</p><ul class="my-4 space-y-2"><li v-for="leave in off" :key="leave.id">{{ leave.starts_at.slice(0,16) }}–{{ leave.ends_at.slice(0,16) }} · {{ leave.kind.replaceAll('_',' ') }} · {{ leave.status }} <button v-if="leave.status==='active'&&writable" class="text-rose-700" :disabled="busy" @click="cancel(leave.id)">Cancel Time Off</button></li></ul><form v-if="writable" @submit.prevent="addOff"><div class="form-grid"><label class="field">Time Off Start<input v-model="start" type="datetime-local" required></label><label class="field">Time Off End<input v-model="end" type="datetime-local" required></label><label class="field">Type<select v-model="kind" aria-label="Time Off Type"><option value="leave">Leave</option><option value="day_off">Day Off</option><option value="unavailable">Unavailable</option></select></label><label class="field">Reason<input v-model="reason" maxlength="1000"></label></div><button class="btn-secondary mt-3" :disabled="busy">Add Time Off</button></form></template></section></template>
<style scoped>th,td{text-align:left;padding:.6rem;border-bottom:1px solid #e2e8f0}input[type=time]{min-width:110px}</style>
