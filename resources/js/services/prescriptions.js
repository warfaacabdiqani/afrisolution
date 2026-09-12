import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
const root = '/v1/clinic/prescriptions';
const config = params => ({ headers: useClinicContextStore().headers(), params });
export const prescriptionService = {
    list: params => api.get(root, config(params)),
    stats: params => api.get(`${root}/stats`, config(params)),
    options: branch_id => api.get(`${root}/options`, config({ branch_id })),
    patients: params => api.get(`${root}/patients`, config(params)),
    appointments: params => api.get(`${root}/appointments`, config(params)),
    medications: search => api.get('/v1/clinic/medications/search', config({ search })),
    createMedication: data => api.post('/v1/clinic/medications', data, config()),
    get: id => api.get(`${root}/${id}`, config()),
    save: (id, data) => id ? api.put(`${root}/${id}`, data, config()) : api.post(root, data, config()),
    action: (id, action, data = {}) => api.post(`${root}/${id}/${action}`, data, config()),
    activity: (id, page = 1) => api.get(`${root}/${id}/activity`, config({ page })),
    print: id => api.get(`${root}/${id}/print`, { ...config(), responseType: 'text' }),
};
