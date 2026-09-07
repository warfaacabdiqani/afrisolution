<script setup>
import { computed } from 'vue';
import { planFeatureGroups } from '../../../config/planFeatures';
import PlanStatusBadge from './PlanStatusBadge.vue';
const props=defineProps({plan:Object});
const enabled=computed(()=>planFeatureGroups.flatMap(([,items])=>items).filter(([key])=>props.plan.features?.[key]).slice(0,6));
const money=computed(()=>new Intl.NumberFormat(undefined,{style:'currency',currency:props.plan.currency||'USD',maximumFractionDigits:2}).format(Number(props.plan.price||0)));
const limit=value=>value===null?'Unlimited':Number(value).toLocaleString();
</script>
<template><article class="plan-card"><div class="flex items-start justify-between gap-3"><div><h2>{{plan.name}}</h2><p class="mt-2 text-3xl font-bold">{{money}} <small v-if="Number(plan.price)" class="text-sm font-normal text-slate-500">/ {{plan.billing_period}}</small></p></div><PlanStatusBadge :status="plan.status"/></div><p class="mt-3 min-h-10 text-sm text-slate-500">{{plan.description||'No description provided.'}}</p><dl class="plan-limit-grid"><div><dt>Branches</dt><dd>{{limit(plan.branch_limit)}}</dd></div><div><dt>Members</dt><dd>{{limit(plan.member_limit)}}</dd></div><div><dt>Doctors</dt><dd>{{limit(plan.doctor_limit)}}</dd></div><div><dt>Patients</dt><dd>{{limit(plan.patient_limit)}}</dd></div><div><dt>Storage</dt><dd>{{plan.storage_limit_gb===null?'Unlimited':plan.storage_limit_gb+' GB'}}</dd></div><div><dt>Trial</dt><dd>{{plan.trial_days}} days</dd></div></dl><div class="mt-5 border-t pt-4"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Enabled features</p><ul class="space-y-1 text-sm"><li v-for="[,label] in enabled" :key="label" class="text-emerald-700">Included &middot; {{label}}</li><li v-if="!enabled.length" class="text-slate-500">No features enabled</li></ul></div><RouterLink class="btn mt-6 block text-center" :to="{name:'admin.plans.show',params:{id:plan.id}}">Manage Plan</RouterLink></article></template>
