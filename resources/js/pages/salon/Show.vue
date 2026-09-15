<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useSalonPage } from '../../composables/useSalonPage';
import { useClinicContextStore } from '../../stores/clinicContext';
import { salonPages,salonPath,salonValue } from '../../config/salonPages';
import BookingHistory from '../../components/salon/BookingHistory.vue';
import ScheduleEditor from '../../components/salon/ScheduleEditor.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
const props=defineProps({kind:String});const route=useRoute(),context=useClinicContextStore(),definition=salonPages[props.kind],path=salonPath(props.kind);
const {data,error,busy,load}=useSalonPage(props.kind);onMounted(()=>load('/'+route.params.id));
function value(field,row){if(field.key==='preferred_stylist_id')return row.preferred_stylist;if(field.key==='service_category_id')return row.category;if(field.key==='branch_ids')return row.branches;if(field.key==='stylist_ids')return row.stylists;if(field.key==='user_id')return 'Linked staff account #'+row.user_id;return row[field.key];}
</script>
<template><RouterLink :to="path">Back to {{ definition.title }}</RouterLink><FormErrors :error="error" /><p v-if="busy" role="status" class="clinic-panel">Loading {{ definition.singular.toLowerCase() }}�</p><template v-else-if="data">
<div class="patient-page-header mt-4"><div><h1>{{ data.data.full_name || data.data.display_name || data.data.name }}</h1><p>{{ data.data.client_number || data.data.staff_number || data.data.code }} � {{ data.data.status }}</p></div><RouterLink v-if="data.data.editable!==false && context.can(definition.update)" class="btn" :to="path+'/'+data.data.id+'/edit'">Edit {{ definition.singular }}</RouterLink></div>
<section class="clinic-panel"><h2 class="text-lg font-semibold">{{ definition.singular }} Information</h2><dl class="detail-list"><template v-for="field in definition.fields" :key="field.key"><h3 v-if="field.group" class="font-semibold mt-5">{{ field.group }}</h3><div><dt>{{ field.label }}</dt><dd class="whitespace-pre-wrap">{{ salonValue(value(field,data.data),field.key,data.data.currency || 'USD') }}</dd></div></template></dl><div v-if="kind==='stylists'" class="mt-5"><h3 class="font-semibold">Assigned Services</h3><p>{{ salonValue(data.data.services,'services') }}</p></div></section>
<BookingHistory v-if="context.allowed('appointments')&&['clients','stylists','services'].includes(kind)" :kind="kind" :id="data.data.id" />
<ScheduleEditor v-if="kind==='stylists'&&context.allowed('appointments')" :stylist-id="data.data.id" :branches="data.data.branches" />
<section class="clinic-panel mt-5"><h2 class="text-lg font-semibold">Activity</h2><ul v-if="data.activity?.length" class="mt-3 space-y-2"><li v-for="(event,index) in data.activity" :key="index">{{ event.action.replaceAll('.',' ') }} � {{ new Date(event.created_at).toLocaleString() }}</li></ul><p v-else>No activity recorded.</p></section>
</template></template>
