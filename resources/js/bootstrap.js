import axios from 'axios';
window.axios = axios;

window.axios.defaults.baseURL = window.location.origin;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      import('./auth').then((mod) => {
        if (mod.currentUser.value !== false) {
          mod.currentUser.value = false; // mark logged out
        }
      });
    }
    return Promise.reject(error);
  }
);
