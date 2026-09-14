<script setup>
import { ref,onMounted } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { useBusinessContext } from '../../composables/useBusinessContext';
import { useSalonPage } from '../../composables/useSalonPage';
import { salonPages,salonPath,salonValue } from '../../config/salonPages';
import { salonService } from '../../services/salon';
import FormErrors from '../../components/ui/FormErrors.vue';
const props=defineProps({kind:String});const definition=salonPages[props.kind],path=salonPath(props.kind),context=useClinicContextStore();
const {label}=useBusinessContext();const {data,error,busy,load}=useSalonPage(props.kind);
const search=ref(''),status=ref(''),page=ref(1),archiveTarget=ref(null),saving=ref(false),currency=ref('USD');
function refresh(next=1) { page.value=next;load('',{search:search.value,status:status.value,page:next}); }
async function archive() { saving.value=true;try { await salonService.request(props.kind,'/'+archiveTarget.value.id+'/archive','post');archiveTarget.value=null;refresh(page.value); }catch(e){error.value=e;}finally{saving.value=false;} }
onMounted(async()=>{refresh();try{currency.value=(await salonService.request(props.kind,'/options')).data.currency;}catch(e){error.value=e;}});
</script>
<template><div class="patient-page-header"><div><h1>{{ kind==='clients'?label('customers'):definition.title }}</h1><p>{{ definition.description }}</p></div><div class="flex gap-3"><RouterLink v-if="kind==='services' && context.can('service_categories.manage')" class="btn-secondary" to="/app/services/categories">Categories</RouterLink><RouterLink v-if="context.can(definition.create)" class="btn" :to="path+'/create'">+ Add {{ definition.singular }}</RouterLink></div></div>
<FormErrors :error="error" />
<div v-if="kind==='clients'" class="clinic-kpis"><section v-for="[key,title] in [['total','Total Clients'],['month','New This Month'],['active','Active Clients'],['inactive','Inactive Clients']]" :key="key" class="clinic-panel clinic-kpi"><div><h2>{{ title }}</h2><strong>{{ data?.stats?.[key] ?? '—' }}</strong><p>Current location</p></div></section></div>
<form class="clinic-panel flex flex-wrap items-center gap-4 mb-4" @submit.prevent="refresh()"><label class="field">Search<input v-model="search" type="search" :placeholder="'Search '+definition.title.toLowerCase()"></label><label class="field">Status<select v-model="status" @change="refresh()"><option value="">All statuses</option><option>active</option><option>inactive</option><option v-if="definition.archive">archived</option></select></label><button class="btn-secondary">Search</button></form>
<section class="clinic-panel"><p v-if="busy" role="status">Loading {{ definition.title.toLowerCase() }}…</p><div v-else-if="data?.data.length" class="table-wrap"><table><thead><tr><th v-for="[key,title] in definition.columns" :key="key">{{ title }}</th><th>Actions</th></tr></thead><tbody><tr v-for="row in data.data" :key="row.id"><td v-for="[key] in definition.columns" :key="key">{{ salonValue(row[key],key,currency) }}</td><td><div class="flex gap-3"><RouterLink :to="path+'/'+row.id">View</RouterLink><RouterLink v-if="row.editable!==false && context.can(definition.update)" :to="path+'/'+row.id+'/edit'">Edit</RouterLink><button v-if="definition.archive && row.editable && context.can(definition.archive)" @click="archiveTarget=row">Archive</button></div></td></tr></tbody></table></div><p v-else class="clinic-empty">No {{ definition.title.toLowerCase() }} match your search.</p>
<div v-if="data?.meta" class="patient-pagination"><span>{{ data.meta.total }} records</span><button class="btn-secondary" :disabled="busy || page===1" @click="refresh(page-1)">Previous</button><span>{{ page }} / {{ data.meta.last_page }}</span><button class="btn-secondary" :disabled="busy || page>=data.meta.last_page" @click="refresh(page+1)">Next</button></div></section>
<section v-if="archiveTarget" class="clinic-panel mt-4" role="alertdialog" aria-label="Archive record"><h2>Archive this {{ definition.singular.toLowerCase() }}?</h2><p>The record will be retained for history.</p><div class="flex gap-3 mt-4"><button class="btn" :disabled="saving" @click="archive">Confirm Archive</button><button class="btn-secondary" :disabled="saving" @click="archiveTarget=null">Cancel</button></div></section>
</template>
