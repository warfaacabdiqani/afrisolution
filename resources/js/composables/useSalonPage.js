import { ref,onBeforeUnmount } from 'vue';
import { salonService } from '../services/salon';
import { useClinicContextStore } from '../stores/clinicContext';
export function useSalonPage(kind) {
    const data=ref(null),error=ref(null),busy=ref(false);let generation=0;
    onBeforeUnmount(()=>generation++);
    async function load(suffix='',params={}) {
        const revision=++generation, context=useClinicContextStore(), scope=JSON.stringify(context.headers());
        busy.value=true;error.value=null;
        try { const response=await salonService.request(kind,suffix,'get',null,params); if(revision===generation && scope===JSON.stringify(context.headers())) data.value=response; }
        catch(e) { if(revision===generation) error.value=e; }
        finally { if(revision===generation) busy.value=false; }
    }
    return {data,error,busy,load};
}
