<script setup>
import { ref,reactive,onMounted } from 'vue';
import { useRoute,useRouter,onBeforeRouteLeave } from 'vue-router';
import { salonPages,salonPath } from '../../config/salonPages';
import { salonService } from '../../services/salon';
import { useClinicContextStore } from '../../stores/clinicContext';
import SalonFields from '../../components/salon/SalonFields.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
const props=defineProps({kind:String});const route=useRoute(),router=useRouter(),context=useClinicContextStore(),definition=salonPages[props.kind],path=salonPath(props.kind),editing=Boolean(route.params.id);
const form=reactive(structuredClone(definition.defaults)),options=ref({}),busy=ref(true),saving=ref(false),error=ref(null),original=ref(''),saved=ref(false);
onMounted(async()=>{try{
    options.value=(await salonService.request(props.kind,'/options')).data;
    if('branch_ids' in form)form.branch_ids=[options.value.current_branch_id];
    if(editing){const row=(await salonService.request(props.kind,'/'+route.params.id)).data;if(row.editable===false)throw new Error('This record cannot be edited with your location access or status.');for(const key of Object.keys(form))form[key]=row[key]??definition.defaults[key];}
    original.value=JSON.stringify(form);
}catch(e){error.value=e;}finally{busy.value=false;}});
onBeforeRouteLeave(()=>saved.value || !original.value || JSON.stringify(form)===original.value || window.confirm('Discard unsaved changes?'));
async function save(){if(saving.value)return;saving.value=true;error.value=null;try{
    const payload=Object.fromEntries(Object.entries(form).map(([key,value])=>[key,value===''?null:value]));
    const result=await salonService.request(props.kind,editing?'/'+route.params.id:'',editing?'put':'post',payload);
    saved.value=true;await router.push(path+'/'+result.data.id);
}catch(e){error.value=e;}finally{saving.value=false;}}
</script>
<template><RouterLink :to="path">Back to {{ definition.title }}</RouterLink><div class="patient-page-header mt-4"><div><h1>{{ editing?'Edit':'Add' }} {{ definition.singular }}</h1><p>{{ definition.description }}</p></div></div><FormErrors :error="error" /><p v-if="error && !original" class="clinic-panel">{{ error.message }}</p><p v-if="busy" class="clinic-panel" role="status">Loading form�</p>
<form v-else-if="original" class="clinic-panel" @submit.prevent="save">
<p v-if="kind==='stylists'" class="mb-5 text-slate-500">Link an existing active staff account. Login details remain in Users / Staff.<RouterLink v-if="context.allowed('staff')" to="/app/staff" class="ml-2 text-teal-700">Manage staff</RouterLink></p>
<p v-if="kind==='services' && !options.categories?.length" class="mb-5">Create an active category before adding a service. <RouterLink v-if="context.can('service_categories.manage')" to="/app/services/categories/create">Add Category</RouterLink></p>
<p v-if="kind==='services'" class="mb-5 text-slate-500">Prices use {{ options.currency }}.</p>
<SalonFields :fields="definition.fields" :form="form" :options="options" :editing="editing" />
<div class="flex gap-3 mt-6"><button class="btn" :disabled="saving">{{ saving?'Saving�':'Save '+definition.singular }}</button><RouterLink class="btn-secondary" :to="path">Cancel</RouterLink></div>
</form></template>
