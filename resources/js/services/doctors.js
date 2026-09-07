import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
const root = '/v1/clinic/doctors';
const config = params => ({ headers: useClinicContextStore().headers(), params });
export const doctorService = {
    list: params => api.get(root, config(params)), options: () => api.get(`${root}/options`, config()),
    get: id => api.get(`${root}/${id}`, config()),
    save: (id, data) => id ? api.put(`${root}/${id}`, data, config()) : api.post(root, data, config()),
    status: (id, action) => api.post(`${root}/${id}/${action}`, {}, config()),
    specialty: name => api.post('/v1/clinic/specialties', { name }, config()),
    schedule: (id, branch_id) => api.get(`${root}/${id}/schedule`, config({ branch_id })),
    saveSchedule: (id, data) => api.put(`${root}/${id}/schedule`, data, config()),
    leaves: (id, page = 1) => api.get(`${root}/${id}/leaves`, config({ page })),
    addLeave: (id, data) => api.post(`${root}/${id}/leaves`, data, config()),
    cancelLeave: (id, leave) => api.post(`${root}/${id}/leaves/${leave}/cancel`, {}, config()),
    activity: (id, page = 1) => api.get(`${root}/${id}/activity`, config({ page })),
};
