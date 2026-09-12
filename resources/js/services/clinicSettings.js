import api from './api';
import { useClinicContextStore } from '../stores/clinicContext';
const root = '/v1/clinic/settings';
const config = () => ({ headers: useClinicContextStore().headers() });
export const clinicSettingsService = {
    get: () => api.get(root, config()),
    save: (section, values) => api.put(`${root}/${section}`, values, config()),
    branches: () => api.get(`${root}/branches`, config()),
    saveBranch: (id, values) => id ? api.put(`${root}/branches/${id}`, values, config()) : api.post(`${root}/branches`, values, config()),
    upload: (asset, file) => { const data = new FormData(); data.append('asset', asset); data.append('file', file); return api.post(`${root}/branding`, data, config()); },
    asset: name => api.get(`${root}/branding/${name}`, { ...config(), responseType: 'blob' }),
    preview: kind => api.get(`${root}/preview/${kind}`, { ...config(), responseType: 'text' }),
};
