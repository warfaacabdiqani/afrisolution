<script setup>
import { computed } from 'vue';
import { clock, minutes } from '../../utils/appointmentDates';
import { positionBookings } from '../../utils/bookingLayout';
const props = defineProps({ columns: Array, rows: Array, statuses: Object, canCreate: Boolean });
defineEmits(['open', 'create']);
const start = computed(() => Math.floor(Math.min(480, ...props.rows.map(r => minutes(r.starts_at.slice(11)))) / 60) * 60);
const end = computed(() => Math.min(1440, Math.ceil(Math.max(1080, ...props.rows.map(r => minutes(r.ends_at.slice(11)))) / 60) * 60));
const slots = computed(() => Array.from({ length: (end.value - start.value) / 30 }, (_, i) => start.value + i * 30));
const positioned = column => positionBookings(props.rows.filter(row => row.starts_at.slice(0, 10) === column.date && (!column.stylist_id || row.stylist_id === column.stylist_id)));
</script>
<template><div class="appointment-timeline-scroll"><div class="appointment-timeline" :style="{ '--days': columns.length, '--timeline-height': `${(end-start)*1.4}px` }">
<div class="appointment-time-heading">Time</div><div v-for="column in columns" :key="column.key" class="appointment-day-heading"><strong>{{ column.label }}</strong><span>{{ column.date }}</span></div>
<div class="appointment-time-axis"><span v-for="time in slots" :key="time" :style="{ top: `${(time-start)*1.4}px` }">{{ clock(time) }}</span></div>
<div v-for="column in columns" :key="column.key+'-body'" class="appointment-day-column">
<button v-for="time in slots" :key="time" class="appointment-time-slot" :style="{ top: `${(time-start)*1.4}px`, height: '42px' }" :disabled="!canCreate" :aria-label="`Book ${column.label} ${column.date} at ${clock(time)}`" @click="$emit('create', { date: column.date, start_time: clock(time), stylist_id: column.stylist_id })"></button>
<button v-for="row in positioned(column)" :key="row.id" class="appointment-calendar-card" :class="`appointment-color-${statuses[row.status]?.[1] || 'gray'}`" :style="{ top: `${(row.start-start)*1.4}px`, height: `${Math.max((row.end-row.start)*1.4-3,24)}px`, left: `calc(${100*row.lane/row.lanes}% + 2px)`, width: `calc(${100/row.lanes}% - 4px)` }" :title="`${row.client.full_name} · ${row.items.map(i=>i.name).join(', ')} · ${row.stylist.name} · ${statuses[row.status]?.[0]}`" @click="$emit('open', row)"><small>{{ row.starts_at.slice(11,16) }}–{{ row.ends_at.slice(11,16) }}</small><strong>{{ row.client.full_name }}</strong><span>{{ row.items.map(i=>i.name).join(', ') }}</span><small>{{ statuses[row.status]?.[0] }}</small></button>
</div></div></div></template>
