<script setup>
import DashboardSection from './DashboardSection.vue';
defineProps({ business: Object, section: Object });
</script>
<template><DashboardSection :title="section.title"><template #action><RouterLink v-if="section.can_manage" to="/app/settings">Manage</RouterLink></template><dl class="detail-list"><div><dt>Business Name</dt><dd>{{ business.name }}</dd></div><div><dt>Business Type</dt><dd>{{ business.type }}</dd></div><div><dt>Current Location / Branch</dt><dd>{{ business.branch.name }}</dd></div><div><dt>Subscription Plan</dt><dd>{{ business.plan || 'Unavailable' }}</dd></div><div><dt>Status</dt><dd>{{ business.status }} ? {{ business.subscription_status }} subscription</dd></div><div v-if="business.staff_count !== null"><dt>Staff Members</dt><dd>{{ business.staff_count }}</dd></div></dl></DashboardSection></template>
