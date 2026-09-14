<script setup>defineProps({ title: String });</script>
<template><section class="clinic-panel"><div class="clinic-card-heading"><h2>{{ title }}</h2><slot name="action" /></div><slot /></section></template>
