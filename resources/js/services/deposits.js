import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';

const routes = {
    clinic: id => `/v1/clinic/appointments/${id}/deposit/payments`,
    salon: id => `/v1/salon/appointments/${id}/deposit/payments`,
    dental: id => `/v1/dental/plans/${id}/deposit/payments`,
};

export function collectDeposit(type, id, data) {
    if (!routes[type]) throw new Error('Unsupported deposit source.');
    return api.post(routes[type](id), data, { headers: useClinicContextStore().headers() });
}
