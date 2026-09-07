<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useClinicContextStore } from '../../stores/clinicContext';
import { usePatientStore } from '../../stores/patients';
import { patientService } from '../../services/patients';
import FormErrors from '../ui/FormErrors.vue';
const props = defineProps({ patient: Object });
const context = useClinicContextStore(), store = usePatientStore(), router = useRouter();
const sections = [
    ['Personal information', [['first_name','First name','text',true],['middle_name','Middle name'],['last_name','Last name','text',true],['gender','Gender','select',true,['male','female','other','unknown']],['date_of_birth','Date of birth','date'],['blood_group','Blood group','select',false,['A+','A-','B+','B-','AB+','AB-','O+','O-']],['marital_status','Marital status','select',false,['single','married','divorced','widowed','other']]]],
    ['Contact information', [['phone','Phone','tel'],['email','Email','email'],['address','Address'],['city','City'],['country','Country']]],
    ['Emergency contact', [['emergency_contact_name','Emergency contact name'],['emergency_contact_relationship','Relationship'],['emergency_contact_phone','Emergency contact phone','tel']]],
];
const form = reactive(Object.fromEntries(sections.flatMap(([, fields]) => fields.map(([key]) => [key, props.patient?.[key] || '']))));
form.status = props.patient?.status || 'active'; form.notes = props.patient?.notes || ''; form.allergy = ''; form.condition = '';
const busy = ref(false), error = ref(null), matches = ref([]);
async function save(confirm = false) {
    error.value = null; busy.value = true; matches.value = [];
    const data = { ...form, confirm_duplicate: confirm };
    if (!context.can('patients.medical_history.update')) { delete data.notes; delete data.allergy; delete data.condition; }
    if (props.patient) { delete data.allergy; delete data.condition; }
    try { const response = await patientService.save(props.patient?.id, data); store.notice = props.patient ? 'Patient updated successfully.' : 'Patient registered successfully.'; await router.push(`/app/patients/${response.data.data.id}`); }
    catch (e) { if (e.response?.data.duplicates) matches.value = e.response.data.duplicates; else error.value = e; }
    finally { busy.value = false; }
}
</script>
<template>
    <form class="patient-form" @submit.prevent="save(false)">
        <FormErrors :error="error" />
        <section v-if="matches.length" class="clinic-panel border-amber-300 bg-amber-50" role="alert"><h2 class="text-lg font-bold">Possible matching patient found.</h2><p class="mt-2 text-slate-600">Review these records before continuing registration.</p><div v-for="match in matches" :key="match.id" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-amber-200 pt-3"><span><strong>{{ match.full_name }}</strong><small class="block">{{ match.patient_number }} · {{ match.phone || 'No phone' }} · {{ match.date_of_birth || 'DOB unknown' }}</small></span><RouterLink class="btn-secondary" :to="`/app/patients/${match.id}`">View Existing Patient</RouterLink></div><button type="button" class="btn mt-5" :disabled="busy" @click="save(true)">Continue Registration</button></section>
        <section v-for="([title, fields], index) in sections" :key="title" class="clinic-panel"><div class="patient-section-heading"><span>{{ index + 1 }}</span><h2>{{ title }}</h2></div><div class="patient-form-grid"><label v-for="[key,label,type,required,options] in fields" :key="key" class="field">{{ label }}{{ required ? ' *' : '' }}<select v-if="type === 'select'" v-model="form[key]" :required="required"><option value="">Select {{ label.toLowerCase() }}</option><option v-for="option in options" :key="option" :value="option">{{ option }}</option></select><input v-else v-model="form[key]" :type="type || 'text'" :required="required" :max="type === 'date' ? context.data.today : undefined" :maxlength="key === 'address' ? 1000 : 255" :aria-invalid="Boolean(error?.response?.data.errors?.[key])"><span v-if="error?.response?.data.errors?.[key]" class="text-xs text-rose-700">{{ error.response.data.errors[key][0] }}</span></label></div></section>
        <section v-if="context.can('patients.medical_history.update') && (!patient || context.can('patients.medical_history.view'))" class="clinic-panel"><div class="patient-section-heading"><span>4</span><h2>Initial medical information</h2></div><div class="patient-form-grid"><template v-if="!patient"><label class="field">Known allergy<input v-model="form.allergy" maxlength="150" placeholder="e.g. Penicillin"></label><label class="field">Known medical condition<input v-model="form.condition" maxlength="150" placeholder="e.g. Asthma"></label></template><label class="field col-span-full">Intake notes<textarea v-model="form.notes" rows="4" maxlength="5000"></textarea></label></div></section>
        <section class="clinic-panel"><label class="field max-w-xs">Patient status<select v-model="form.status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label><p class="mt-3 text-xs text-slate-500">Patient numbers are assigned automatically and remain unchanged.</p></section>
        <div class="sticky-actions"><RouterLink class="btn-secondary" to="/app/patients">Cancel</RouterLink><button class="btn" :disabled="busy">{{ busy ? 'Saving...' : patient ? 'Save Changes' : 'Register Patient' }}</button></div>
    </form>
</template>
