import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';

const root = '/v1/clinic/staff';
const config = params => ({ headers: useClinicContextStore().headers(), params });

export const staffService = {
    list: params => api.get(root, config(params)),
    options: () => api.get(`${root}/options`, config()),
    get: id => api.get(`${root}/${id}`, config()),
    save: (id, data) => id ? api.put(`${root}/${id}`, data, config()) : api.post(root, data, config()),
    toggleStatus: (id, action) => api.post(`${root}/${id}/${action}`, {}, config()),
    resetPassword: id => api.post(`${root}/${id}/reset-password`, {}, config()),
};
