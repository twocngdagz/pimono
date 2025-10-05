import { ref, computed } from 'vue';
import axios from 'axios';

export const currentUser = ref(null);
export const authLoading = ref(false);

export async function ensureAuthFetched() {
  if (currentUser.value !== null || authLoading.value) {
    return currentUser.value;
  }
  authLoading.value = true;
  try {
    const { data } = await axios.get('/api/user');
    currentUser.value = data;
  } catch (e) {
    if (e.response?.status === 401) {
      currentUser.value = false;
    } else {
      currentUser.value = false;
    }
  } finally {
    authLoading.value = false;
  }
  return currentUser.value;
}

export async function logout() {
  try {
    await axios.post('/logout');
  } catch (e) {
    console.log(e.response?.data?.message || 'Logout failed');
  } finally {
    currentUser.value = false;
  }
}

export const isAuthenticated = computed(() => {
  return !!(currentUser.value && currentUser.value.id);
});
