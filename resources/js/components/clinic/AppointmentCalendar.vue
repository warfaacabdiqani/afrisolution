<script setup>
import { computed, ref } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
const props = defineProps({ today: String, dates: { type: Array, default: () => [] }, enabled: Boolean });
const initial = new Date(`${props.today}T12:00:00`);
const month = ref(new Date(initial.getFullYear(), initial.getMonth(), 1));
const title = computed(() => month.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const cells = computed(() => [...Array(month.value.getDay()).fill(null), ...Array.from({ length: new Date(month.value.getFullYear(), month.value.getMonth() + 1, 0).getDate() }, (_, i) => `${month.value.getFullYear()}-${String(month.value.getMonth() + 1).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`)]);
function move(delta) { month.value = new Date(month.value.getFullYear(), month.value.getMonth() + delta, 1); }
</script>
<template>
    <section class="clinic-panel"><div class="clinic-card-heading"><h2>Appointment Calendar</h2><RouterLink v-if="enabled" to="/app/appointments">View all</RouterLink></div>
        <div class="clinic-calendar-title"><button aria-label="Previous month" @click="move(-1)"><AppIcon name="chevronLeft" :size="18" /></button><strong>{{ title }}</strong><button aria-label="Next month" @click="move(1)"><AppIcon name="chevronRight" :size="18" /></button></div>
        <div class="clinic-calendar"><small v-for="day in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="day">{{ day }}</small><template v-for="(date,index) in cells" :key="index"><span v-if="!date"></span><component :is="enabled ? 'RouterLink' : 'span'" v-else :to="enabled ? `/app/appointments?date=${date}` : undefined" :class="{ today: date === today, 'has-visits': dates.includes(date) }" :aria-label="date" :aria-current="date === today ? 'date' : undefined">{{ Number(date.slice(-2)) }}</component></template></div>
    </section>
</template>
