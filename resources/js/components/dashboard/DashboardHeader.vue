<script setup>
defineProps({ business: Object, today: String });
</script>
<template><div class="clinic-dashboard-header"><div><p>Welcome back,</p><h1>{{ business.name }}</h1><span>Here's what's happening at your {{ business.workspace_label }} today.</span></div><div class="clinic-header-actions"><time>{{ new Date(today + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' }) }}</time></div></div></template>
