import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';

const root = '/v1/dental';
const config = params => ({ headers: useClinicContextStore().headers(), params });
export const dentalService = {
    options: () => api.get(`${root}/options`, config()),
    procedures: params => api.get(`${root}/procedures`, config(params)),
    saveProcedure: (id, data) => id ? api.put(`${root}/procedures/${id}`, data, config()) : api.post(`${root}/procedures`, data, config()),
    chart: patient => api.get(`${root}/patients/${patient}/chart`, config()),
    finding: (patient, data) => api.post(`${root}/patients/${patient}/findings`, data, config()),
    voidFinding: (patient, id, reason) => api.post(`${root}/patients/${patient}/findings/${id}/void`, { reason }, config()),
    plans: patient => api.get(`${root}/patients/${patient}/plans`, config()),
    savePlan: (patient, id, data) => id ? api.put(`${root}/plans/${id}`, data, config()) : api.post(`${root}/patients/${patient}/plans`, data, config()),
    status: (plan, status, reason) => api.post(`${root}/plans/${plan.id}/status`, { status, version: plan.version, reason }, config()),
    complete: (plan, item, data) => api.post(`${root}/plans/${plan}/items/${item}/complete`, data, config()),
    appointments: plan => api.get(`${root}/plans/${plan}/appointments`, config()),
    invoice: (plan, item) => api.post(`${root}/plans/${plan}/items/${item}/invoice`, {}, config()),
};
