<script setup>
import { computed, ref } from 'vue';
import { planFeatureGroups } from '../../../config/planFeatures';
import PlanStatusBadge from './PlanStatusBadge.vue';
import AppIcon from '../../ui/AppIcon.vue';
const props=defineProps({plan:Object});
defineEmits(['delete','status']);
const menu=ref(false);
const enabled=computed(()=>planFeatureGroups.flatMap(([,items])=>items).filter(([key])=>props.plan.features?.[key]).slice(0,6));
const money=computed(()=>new Intl.NumberFormat(undefined,{style:'currency',currency:props.plan.currency||'USD',maximumFractionDigits:2}).format(Number(props.plan.price||0)));
const limit=value=>value===null?'Unlimited':Number(value).toLocaleString();
const limits=computed(()=>[
    ['branch','Branches',limit(props.plan.branch_limit)],['members','Members',limit(props.plan.member_limit)],
    ['doctor','Doctors',limit(props.plan.doctor_limit)],['patient','Patients',limit(props.plan.patient_limit)],
    ['storage','Storage',props.plan.storage_limit_gb===null?'Unlimited':props.plan.storage_limit_gb+' GB'],['trial','Trial days',props.plan.trial_days+' days'],
]);
</script>
<template><article class="plan-card relative"><div class="flex items-start justify-between gap-3"><div><h2>{{plan.name}}</h2><p class="mt-2 text-3xl font-bold">{{money}} <small v-if="Number(plan.price)" class="text-sm font-normal text-slate-500">/ {{plan.billing_period}}</small></p></div><div class="flex items-center gap-2"><PlanStatusBadge :status="plan.status"/><button class="menu-trigger" aria-label="Plan actions" :aria-expanded="menu" @click="menu=!menu">&#8942;</button></div><div v-if="menu" class="action-menu"><RouterLink :to="{name:'admin.plans.show',params:{id:plan.id}}"><AppIcon name="view"/>View Details</RouterLink><RouterLink :to="{name:'admin.plans.edit',params:{id:plan.id}}"><AppIcon name="edit"/>Edit Plan</RouterLink><button @click="$emit('status',plan);menu=false"><AppIcon name="pause"/>{{plan.status==='active'?'Deactivate Plan':'Activate Plan'}}</button><button class="danger-item" @click="$emit('delete',plan);menu=false"><AppIcon name="trash"/>Delete Plan</button></div></div><p class="mt-3 min-h-10 text-sm text-slate-500">{{plan.description||'No description provided.'}}</p><dl class="plan-limit-grid"><div v-for="[icon,label,value] in limits" :key="label" class="plan-limit"><span class="limit-icon"><AppIcon :name="icon"/></span><span><dt>{{label}}</dt><dd>{{value}}</dd></span></div></dl><div class="mt-5 border-t pt-4"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Included Features</p><ul class="space-y-2 text-sm"><li v-for="[,label] in enabled" :key="label" class="feature-included"><span><AppIcon name="check" :size="13"/></span>{{label}}</li><li v-if="!enabled.length" class="text-slate-500">No features enabled</li></ul></div><div class="mt-auto pt-6"><RouterLink class="btn block text-center" :to="{name:'admin.plans.show',params:{id:plan.id}}">Manage Plan</RouterLink></div></article></template>
