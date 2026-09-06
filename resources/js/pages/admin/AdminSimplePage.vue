<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';
const route=useRoute(), items=ref([]), loading=ref(true), notice=ref('');
const title=computed(()=>route.meta.title);
const form=reactive({name:'',branch_limit:1,member_limit:5,trial_days:14});
async function load(){if(route.name==='admin.plans')items.value=(await api.get('/v1/platform/plans')).data.data;else if(route.name==='admin.audit')items.value=(await api.get('/v1/platform/audits')).data.data}
async function createPlan(){await api.post('/v1/platform/plans',form);form.name='';notice.value='Plan created.';await load()}
onMounted(async()=>{try{await load()}finally{loading.value=false}});
</script>
<template><div><header class="page-header"><div><p class="eyebrow">Platform administration</p><h1>{{title}}</h1><p>Manage {{title.toLowerCase()}} from one secure workspace.</p></div></header><section class="admin-card"><p v-if="loading" class="loading-state">Loading...</p>
<template v-else-if="route.name==='admin.plans'"><p v-if="notice" role="status" class="mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{notice}}</p><div class="grid gap-4 md:grid-cols-3"><article v-for="item in items" :key="item.id" class="rounded-xl border p-5"><h2 class="font-bold">{{item.name}}</h2><p class="mt-2 text-sm">{{item.branch_limit}} branches - {{item.member_limit}} members - {{item.trial_days}} trial days</p></article></div><form class="form-grid mt-8 max-w-2xl border-t pt-6" @submit.prevent="createPlan"><label class="field">Name<input v-model="form.name" required></label><label class="field">Branch limit<input v-model.number="form.branch_limit" type="number" min="1" required></label><label class="field">Member limit<input v-model.number="form.member_limit" type="number" min="1" required></label><label class="field">Trial days<input v-model.number="form.trial_days" type="number" min="1" required></label><button class="btn">Create plan</button></form></template>
<template v-else-if="route.name==='admin.audit'"><div v-for="item in items" :key="item.id" class="border-b py-4"><p class="font-semibold">{{item.action}}</p><p class="text-sm text-slate-500">{{item.subject_type}} #{{item.subject_id}}</p></div></template>
<p v-else class="text-sm text-slate-600">This administration module will be implemented when its backend workflow is available.</p></section></div></template>
