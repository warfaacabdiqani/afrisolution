<script setup>
import { computed, ref } from 'vue';
import { appointmentService } from '../../services/appointments';
import { useClinicContextStore } from '../../stores/clinicContext';
import AppIcon from '../ui/AppIcon.vue';
const props = defineProps({ today: String, dates: { type: Array, default: () => [] }, enabled: Boolean });
const initial = new Date(`${props.today}T12:00:00`);
const month = ref(new Date(initial.getFullYear(), initial.getMonth(), 1));
const title = computed(() => month.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const cells = computed(() => [...Array((month.value.getDay()+1)%7).fill(null), ...Array.from({ length: new Date(month.value.getFullYear(), month.value.getMonth() + 1, 0).getDate() }, (_, i) => `${month.value.getFullYear()}-${String(month.value.getMonth() + 1).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`)]);
const activeDates=ref(props.dates),error=ref('');let generation=0;
async function move(delta) {
    month.value = new Date(month.value.getFullYear(), month.value.getMonth() + delta, 1);
    const version=++generation;activeDates.value=[];error.value='';if(!props.enabled)return;
    const start=`${month.value.getFullYear()}-${String(month.value.getMonth()+1).padStart(2,'0')}-01`;
    const end=`${start.slice(0,7)}-${new Date(month.value.getFullYear(),month.value.getMonth()+1,0).getDate()}`;
    try{const {data}=await appointmentService.calendar({start,end,branch_id:useClinicContextStore().data.branch.id});if(version===generation)activeDates.value=data.counts.map(c=>c.date);}catch(e){if(version===generation)error.value='Unable to load appointment counts.';}
}
</script>
<template>
    <section class="clinic-panel"><div class="clinic-card-heading"><h2>Appointment Calendar</h2><RouterLink v-if="enabled" to="/app/appointments">View all</RouterLink></div>
        <div class="clinic-calendar-title"><button aria-label="Previous month" @click="move(-1)"><AppIcon name="chevronLeft" :size="18" /></button><strong>{{ title }}</strong><button aria-label="Next month" @click="move(1)"><AppIcon name="chevronRight" :size="18" /></button></div>
        <p v-if="error" class="hint" role="alert">{{error}}</p><div class="clinic-calendar"><small v-for="day in ['Sa','Su','Mo','Tu','We','Th','Fr']" :key="day">{{ day }}</small><template v-for="(date,index) in cells" :key="index"><span v-if="!date"></span><component :is="enabled ? 'RouterLink' : 'span'" v-else :to="enabled ? `/app/appointments?view=day&date=${date}` : undefined" :class="{ today: date === today, 'has-visits': activeDates.includes(date) }" :aria-label="date" :aria-current="date === today ? 'date' : undefined">{{ Number(date.slice(-2)) }}</component></template></div>
    </section>
</template>
