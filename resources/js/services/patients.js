import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
const root = '/v1/clinic/patients';
const options = extra => ({ headers: useClinicContextStore().headers(), ...extra });
export const patientService = {
    list: params => api.get(root, options({ params })),
    get: id => api.get(`${root}/${id}`, options()),
    save: (id, data) => id ? api.put(`${root}/${id}`, data, options()) : api.post(root, data, options()),
    status: (id, action) => api.post(`${root}/${id}/${action}`, {}, options()),
    entries: (id, kind, page = 1) => api.get(`${root}/${id}/${kind}`, options({ params: { page } })),
    saveEntry: (id, kind, entry, data) => entry ? api.put(`${root}/${id}/${kind}/${entry}`, data, options()) : api.post(`${root}/${id}/${kind}`, data, options()),
    upload: (id, data) => api.post(`${root}/${id}/documents`, data, options()),
    archiveDocument: (id, document) => api.post(`${root}/${id}/documents/${document}/archive`, {}, options()),
    async download(id, document) {
        const response = await api.get(`${root}/${id}/documents/${document.id}/download`, options({ responseType: 'blob' }));
        const url = URL.createObjectURL(response.data);
        const anchor = window.document.createElement('a'); anchor.href = url; anchor.download = `patient-document-${document.id}.${document.extension}`; anchor.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
};
