<script setup>
import {computed} from 'vue';import {weekDays,calendarRange,addDays} from '../../utils/appointmentDates';
const props=defineProps({date:String,today:String,counts:Array});defineEmits(['day']);
const cells=computed(()=>Array.from({length:42},(_,i)=>addDays(calendarRange(props.date,'month').start,i)));
</script>
<template><div class="appointment-month"><strong v-for="day in weekDays" :key="day" class="appointment-month-heading">{{day.slice(0,3)}}</strong><button v-for="day in cells" :key="day" class="appointment-month-day" :class="{'is-today':day===today,'is-outside':day.slice(0,7)!==date.slice(0,7)}" :aria-label="day" @click="$emit('day',day)"><span>{{Number(day.slice(-2))}}</span><small v-if="counts.find(c=>c.date===day)?.total">{{counts.find(c=>c.date===day).total}} <span class="month-count-label">appointments</span></small></button></div></template>
