import {ref} from 'vue';import api from '../services/api';
export function useClinicAdmin(){const clinic=ref(null),subscription=ref(null),members=ref([]),branches=ref([]),activity=ref([]),plans=ref([]),loading=ref(true),error=ref(null),notice=ref('');
async function load(id){loading.value=true;error.value=null;try{const root='/v1/platform/tenants/'+id;const [c,s,m,b,a,p]=await Promise.all([api.get(root),api.get(root+'/subscription'),api.get(root+'/members'),api.get(root+'/branches'),api.get(root+'/audits'),api.get('/v1/platform/plans')]);clinic.value=c.data.data;subscription.value=s.data.data;members.value=m.data.data;branches.value=b.data.data;activity.value=a.data.data;plans.value=p.data.data}catch(e){error.value=e}finally{loading.value=false}}
async function run(action,message){error.value=null;notice.value='';try{await action();notice.value=message}catch(e){error.value=e;throw e}}
return{clinic,subscription,members,branches,activity,plans,loading,error,notice,load,run}}
