<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../../services/api';
import { useAuthStore } from '../../../stores/auth';
import FormErrors from '../../../components/ui/FormErrors.vue';
import PlatformUserForm from '../../../components/admin/users/PlatformUserForm.vue';

const route=useRoute(),router=useRouter(),auth=useAuthStore(),roles=ref([]),user=ref(null),error=ref(null),busy=ref(false);
const editing=computed(()=>Boolean(route.params.id));
onMounted(async()=>{
    if(editing.value&&Number(route.params.id)===Number(auth.user?.id)){await router.replace({name:'admin.users'});return}
    try{roles.value=(await api.get('/v1/platform/roles')).data.data;if(editing.value)user.value=(await api.get('/v1/platform/users/'+route.params.id)).data.data}catch(e){error.value=e}
});
async function submit(data){busy.value=true;error.value=null;try{if(editing.value)await api.put('/v1/platform/users/'+route.params.id,data);else await api.post('/v1/platform/users',data);await router.push({name:'admin.users'})}catch(e){error.value=e}finally{busy.value=false}}
</script>
<template><div><header class="page-header"><div><RouterLink class="back-link" :to="{name:'admin.users'}">Back to System Users</RouterLink><h1 class="mt-3">{{editing?'Edit Administrator':'Add Administrator'}}</h1><p>Assign account status and roles that control platform permissions.</p></div></header><FormErrors :error="error"/><p v-if="editing&&!user&&!error" class="loading-state">Loading administrator...</p><PlatformUserForm v-if="!editing||user" :roles="roles" :initial="user" :busy="busy" @submit="submit" @cancel="router.push({name:'admin.users'})"/></div></template>
