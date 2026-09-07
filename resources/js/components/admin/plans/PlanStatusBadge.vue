<script setup>defineProps({status:String})</script>
<template><span :class="['badge',status==='active'?'badge-green':status==='archived'?'badge-rose':'badge-amber']">{{status}}</span></template>
