import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 30000,
});

// Request Interceptor: Attach Sanctum Bearer Token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('rupsa_token');
        if (token && token !== 'undefined' && token !== 'null') {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Response Interceptor: Envelope Unwrap & Centralized Error Interception
api.interceptors.response.use(
    (response) => {
        // Return full response for binary/blob downloads to preserve headers
        if (response.config && (response.config.responseType === 'blob' || response.config.responseType === 'arraybuffer')) {
            return response;
        }
        // Return unwrapped payload if standard ApiResponse envelope is present
        return response.data;
    },
    async (error) => {
        const status = error.response ? error.response.status : null;

        if (status === 401) {
            localStorage.removeItem('rupsa_token');
            localStorage.removeItem('rupsa_user');
            if (window.location.pathname.startsWith('/admin') && window.location.pathname !== '/admin/login') {
                window.location.href = '/admin/login';
            }
        }

        let errorMessage = error.message || 'An unexpected error occurred';
        let validationErrors = null;

        if (error.response?.data) {
            if (error.response.data instanceof Blob) {
                try {
                    const text = await error.response.data.text();
                    const json = JSON.parse(text);
                    if (json.message) errorMessage = json.message;
                    if (json.errors) validationErrors = json.errors;
                } catch (e) {
                    // Fallback to error message
                }
            } else if (typeof error.response.data === 'object') {
                errorMessage = error.response.data.message || errorMessage;
                validationErrors = error.response.data.errors || null;
            }
        }

        return Promise.reject({
            status,
            message: errorMessage,
            errors: validationErrors,
            raw: error,
        });
    }
);

export default api;
