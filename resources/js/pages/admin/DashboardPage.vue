<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import FormErrors from '../../components/ui/FormErrors.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import AuditEventBadge from '../../components/admin/audit/AuditEventBadge.vue';
import { auditLabel } from '../../config/auditEvents';

const auth=useAuthStore();
const data=ref({stats:{},expiring_trials:[],recent_activity:[]});
const loading=ref(true),error=ref(null);
const cards=computed(()=>[
    {label:'Total Clinics',value:data.value.stats.total_clinics||0,tone:'mint',icon:'clinics',note:`${data.value.stats.new_clinics_30_days||0} added in 30 days`},
    {label:'Active Clinics',value:data.value.stats.active_clinics||0,tone:'blue',icon:'check',note:'Currently operating'},
    {label:'Trial Clinics',value:data.value.stats.trial_clinics||0,tone:'amber',icon:'trial',note:'Active trial periods'},
    {label:'Suspended Clinics',value:data.value.stats.suspended_clinics||0,tone:'rose',icon:'pause',note:'Access restricted'},
    {label:'Active Members',value:data.value.stats.total_members||0,tone:'violet',icon:'members',note:'Across all clinics'},
    {label:'Active Subscriptions',value:data.value.stats.active_subscriptions||0,tone:'mint',icon:'subscriptions',note:'Paid subscriptions'},
    {label:'Subscription Plans',value:data.value.stats.total_plans||0,tone:'blue',icon:'plans',note:'Configured plans'},
    {label:'Monthly Revenue',value:currency(data.value.stats.monthly_revenue||0),tone:'amber',icon:'revenue',note:'Recurring revenue estimate'},
]);
const format=value=>new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(new Date(value));
function currency(value){return new Intl.NumberFormat(undefined,{style:'currency',currency:'USD',maximumFractionDigits:0}).format(value)}
onMounted(async()=>{try{data.value=(await api.get('/v1/platform/dashboard')).data.data}catch(e){error.value=e}finally{loading.value=false}});
</script>

<template><div>
    <header class="page-header"><div><p class="eyebrow">Platform administration</p><h1>Welcome back, {{auth.user?.name}}!</h1><p>Here is what is happening across your platform.</p></div><div class="flex items-center gap-2 text-sm font-medium text-slate-600"><AppIcon name="calendar" :size="18"/><time>{{format(new Date())}}</time></div></header>
    <FormErrors :error="error"/><p v-if="loading" class="loading-state">Loading dashboard...</p>
    <template v-else>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in cards" :key="card.label" :class="['stat-card','stat-'+card.tone]">
                <span class="stat-icon"><AppIcon :name="card.icon" :size="24"/></span>
                <div><p class="text-sm text-slate-600">{{card.label}}</p><strong class="mt-1 block text-3xl text-slate-950">{{card.value}}</strong><p class="mt-1 text-xs text-slate-500">{{card.note}}</p></div>
            </article>
        </section>
        <div class="mt-6 grid gap-6 xl:grid-cols-5">
            <section class="admin-card xl:col-span-2"><div class="card-heading"><h2>Recent activity</h2><RouterLink :to="{name:'admin.audit'}">View all</RouterLink></div>
                <div v-for="item in data.recent_activity" :key="item.id" class="flex items-start gap-3 border-b border-slate-100 py-4"><span class="activity-icon"><AppIcon name="activity" :size="18"/></span><div class="min-w-0"><AuditEventBadge :event="item.action"/><p class="mt-2 text-sm text-slate-600">{{auditLabel(item.action)}} by <strong>{{item.actor_name||'System'}}</strong></p></div></div>
                <p v-if="!data.recent_activity.length" class="empty-state">No recent activity.</p>
            </section>
            <section class="admin-card xl:col-span-3"><div class="card-heading"><h2>Trials expiring soon</h2><RouterLink :to="{name:'admin.subscriptions'}">View subscriptions</RouterLink></div><div class="table-wrap"><table><thead><tr><th>Clinic</th><th>Plan</th><th>Ends</th><th>Days left</th><th></th></tr></thead><tbody><tr v-for="trial in data.expiring_trials" :key="trial.id"><td class="font-semibold">{{trial.name}}</td><td>{{trial.plan_name}}</td><td>{{format(trial.trial_ends_at)}}</td><td><span :class="['badge',trial.days_left<=3?'badge-rose':'badge-amber']">{{trial.days_left}} days</span></td><td><RouterLink class="back-link" :to="{name:'admin.clinics.subscription',params:{id:trial.id}}">Manage</RouterLink></td></tr></tbody></table><p v-if="!data.expiring_trials.length" class="empty-state">No trials expire within 14 days.</p></div></section>
        </div>
    </template>
</div></template>
