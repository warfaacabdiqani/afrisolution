<script setup>
defineProps({ status: String, labels: Object });
</script>
<template><span class="rx-status" :class="`rx-status-${status}`">{{ labels?.[status] || status?.replaceAll('_', ' ') }}</span></template>
