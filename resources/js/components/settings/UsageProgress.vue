<script setup>
import { computed } from 'vue';
const props = defineProps({ label: String, used: Number, limit: [Number, String] });
const percent = computed(() => props.limit == null ? 0 : Math.min(100, props.limit > 0 ? props.used / Number(props.limit) * 100 : 100));
</script>
<template><div class="cs-usage"><div><strong>{{ label }}</strong><span>{{ used }} / {{ limit ?? 'Unlimited' }}</span></div><progress v-if="limit != null" :value="percent" max="100" :aria-label="`${label} usage`" :class="{ 'cs-limit': percent >= 90 }"></progress><small v-if="limit != null && used >= Number(limit)" class="text-amber-700">{{ label }} limit reached.</small></div></template>
