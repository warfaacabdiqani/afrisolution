import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';

const root = '/v1/clinic/support';
const config = (extra = {}) => ({
    headers: useClinicContextStore().headers(),
    ...extra,
});

export const supportService = {
    listArticles: () => api.get(`${root}/articles`, config()),
    getArticle: slug => api.get(`${root}/articles/${slug}`, config()),
    searchArticles: query => api.get(`${root}/search`, config({ params: { q: query } })),
    listFaqs: () => api.get(`${root}/faqs`, config()),
    listTickets: () => api.get(`${root}/tickets`, config()),
    getTicket: id => api.get(`${root}/tickets/${id}`, config()),
    createTicket: payload => api.post(`${root}/tickets`, payload, config()),
    replyToTicket: (id, payload) => api.post(`${root}/tickets/${id}/reply`, payload, config()),
    uploadAttachment: (id, payload) => api.post(`${root}/tickets/${id}/attachments`, payload, config()),
    getSystemInfo: () => api.get(`${root}/system-info`, config()),
};
