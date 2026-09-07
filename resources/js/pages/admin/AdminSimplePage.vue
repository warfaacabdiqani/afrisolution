<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';
const route=useRoute(), items=ref([]), loading=ref(true), notice=ref('');
const title=computed(()=>route.meta.title);
const form=reactive({name:'',branch_limit:1,member_limit:5,trial_days:14});
async function load(){if(route.name==='admin.audit')items.value=(await api.get('/v1/platform/audits')).data.data}
onMounted(async()=>{try{await load()}finally{loading.value=false}});
</script>
<template><div><header class="page-header"><div><p class="eyebrow">Platform administration</p><h1>{{title}}</h1><p>Manage {{title.toLowerCase()}} from one secure workspace.</p></div></header><section class="admin-card"><p v-if="loading" class="loading-state">Loading...</p>
<template v-else-if="route.name==='admin.audit'"><div v-for="item in items" :key="item.id" class="border-b py-4"><p class="font-semibold">{{item.action}}</p><p class="text-sm text-slate-500">{{item.subject_type}} #{{item.subject_id}}</p></div></template>
<p v-else class="text-sm text-slate-600">This administration module will be implemented when its backend workflow is available.</p></section></div></template>
