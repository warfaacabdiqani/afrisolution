import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';

const root = '/v1/clinic/reports';
const config = (params = {}) => ({
    headers: useClinicContextStore().headers(),
    params,
});

export const reportService = {
    overview: params => api.get(`${root}/overview`, config(params)),
    patients: params => api.get(`${root}/patients`, config(params)),
    appointments: params => api.get(`${root}/appointments`, config(params)),
    clinical: params => api.get(`${root}/clinical`, config(params)),
    doctors: params => api.get(`${root}/doctors`, config(params)),
    prescriptions: params => api.get(`${root}/prescriptions`, config(params)),
    financial: params => api.get(`${root}/financial`, config(params)),
    branches: params => api.get(`${root}/branches`, config(params)),
};
