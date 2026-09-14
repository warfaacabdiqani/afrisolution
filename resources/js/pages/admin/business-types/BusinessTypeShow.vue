<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../../services/api';
import FormErrors from '../../../components/ui/FormErrors.vue';
const route = useRoute(), row = ref(null), error = ref(null);
onMounted(async () => { try { row.value = (await api.get('/v1/platform/business-types/' + route.params.id)).data.data; } catch (e) { error.value = e; } });
</script>
<template><RouterLink to="/app/admin/business-types">Back to Business Types</RouterLink><FormErrors :error="error" /><section v-if="row" class="admin-card mt-5"><h1 class="text-2xl font-bold">{{ row.name }}</h1><dl class="detail-list"><div v-for="key in ['name', 'slug', 'category', 'status', 'tenant_count']" :key="key"><dt class="capitalize">{{ key.replaceAll('_', ' ') }}</dt><dd>{{ row[key] }}</dd></div><div><dt>Navigation Profile</dt><dd>{{ row.profile.navigation_profile_key }}</dd></div><div><dt>Dashboard Profile</dt><dd>{{ row.profile.dashboard_profile_key }}</dd></div></dl><h2 class="mt-6 text-lg font-bold">Terminology</h2><dl class="detail-list"><div v-for="(value, key) in row.profile.labels" :key="key"><dt class="capitalize">{{ key.replaceAll('_', ' ') }}</dt><dd>{{ value }}</dd></div></dl><h2 class="mt-6 text-lg font-bold">Modules</h2><dl class="detail-list"><div v-for="(enabled, key) in row.profile.modules" :key="key"><dt>{{ row.profile.labels[key] || key }}</dt><dd>{{ enabled ? 'Enabled' : 'Disabled' }}</dd></div></dl><p class="mt-5 text-slate-500">Business profile configuration is read-only.</p></section><p v-else-if="!error" role="status">Loading business type?</p></template>
