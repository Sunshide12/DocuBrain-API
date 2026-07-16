import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost', // Laravel API endpoint via Nginx
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
  },
  withCredentials: true, // Crucial for Sanctum cookies (HttpOnly)
});

api.interceptors.request.use((config) => {
  if (typeof document !== 'undefined') {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    if (match && match[2]) {
      config.headers['X-XSRF-TOKEN'] = decodeURIComponent(match[2]);
    }
  }
  return config;
});

export default api;
