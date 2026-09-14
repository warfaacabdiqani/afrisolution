import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
import { salonPages } from '../config/salonPages';
export const salonService = {
    async request(kind,suffix='',method='get',data=null,params={}) {
        const response = await api.request({url:'/v1/salon/'+salonPages[kind].endpoint+suffix,method,data,params,headers:useClinicContextStore().headers()});
        return response.data;
    },
};
