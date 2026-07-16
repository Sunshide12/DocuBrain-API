import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost', // Laravel API endpoint via Nginx
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
  },
  withCredentials: true, // Crucial for Sanctum cookies (HttpOnly)
});

export default api;
