import api from './api';

export const billingApi = {
    list: (page, config, customer = {}) => api.get('/v1/billing/invoices', { ...config, params: { page, ...customer } }),
    show: (id, config) => api.get(`/v1/billing/invoices/${id}`, config),
    invoicePrint: (id, config) => api.get(`/v1/billing/invoices/${id}/print`, config),
    receipt: (id, config) => api.get(`/v1/billing/payments/${id}/receipt`, config),
    report: (params, config) => api.get('/v1/billing/reports/summary', { ...config, params }),
    payment: (id, data, config) => api.post(`/v1/billing/invoices/${id}/payments`, data, config),
};
