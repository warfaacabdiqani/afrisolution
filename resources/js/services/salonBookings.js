import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
export const salonBookingApi = {
    get(path = '', params = {}) { return api.get('/v1/salon/' + path, { params, headers: useClinicContextStore().headers() }); },
    post(path, data = {}) { return api.post('/v1/salon/' + path, data, { headers: useClinicContextStore().headers() }); },
    put(path, data) { return api.put('/v1/salon/' + path, data, { headers: useClinicContextStore().headers() }); },
};
