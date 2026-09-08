import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
const root='/v1/clinic/appointments';
const config=params=>({headers:useClinicContextStore().headers(),params});
export const appointmentService={
    list:params=>api.get(root,config(params)),
    calendar:params=>api.get(`${root}/calendar`,config(params)),
    today:params=>api.get(`${root}/today`,config(params)),
    hours:params=>api.get(`${root}/hours`,config(params)),
    options:branch_id=>api.get(`${root}/options`,config({branch_id})),
    patients:params=>api.get(`${root}/patients`,config(params)),
    get:id=>api.get(`${root}/${id}`,config()),
    save:(id,data)=>id?api.put(`${root}/${id}`,data,config()):api.post(root,data,config()),
    reschedule:(id,data)=>api.post(`${root}/${id}/reschedule`,data,config()),
    action:(id,action,data={})=>api.post(`${root}/${id}/${action}`,data,config()),
    slots:(doctor,params)=>api.get(`/v1/clinic/doctors/${doctor}/available-slots`,config(params)),
    type:data=>api.post('/v1/clinic/appointment-types',data,config()),
    activity:(id,page=1)=>api.get(`${root}/${id}/activity`,config({page})),
};
