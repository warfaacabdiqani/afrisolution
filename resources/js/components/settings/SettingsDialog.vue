<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
defineProps({ titleId: String });
defineEmits(['cancel']);
const dialog = ref(null);
const previous = document.activeElement;
onMounted(() => dialog.value.showModal());
onUnmounted(() => previous?.isConnected && previous.focus());
</script>
<template><Teleport to="body"><dialog ref="dialog" class="clinic-panel cs-native-dialog" :aria-labelledby="titleId" @cancel.prevent="$emit('cancel')"><slot /></dialog></Teleport></template>
