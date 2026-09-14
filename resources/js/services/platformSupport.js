import api from './api';

const root = '/v1/platform/support-tickets';
export const platformSupportService = {
    list: params => api.get(root, { params: Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '')) }),
    stats: () => api.get(`${root}/stats`),
    options: () => api.get(`${root}/options`),
    get: id => api.get(`${root}/${id}`),
    reply: (id, payload) => api.post(`${root}/${id}/reply`, payload),
    update: (id, field, value) => api.put(`${root}/${id}/${field}`, { [field]: value }),
    downloadUrl: (id, attachment) => `/api${root}/${id}/attachments/${attachment}/download`,
};
