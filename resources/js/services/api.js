import axios from 'axios';

// Relative URLs keep requests on the Laravel origin. Sanctum's CSRF
// initialization endpoint can be called with an explicit baseURL of '/'.
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export default api;
