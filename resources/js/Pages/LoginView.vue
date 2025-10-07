<template>
  <div class="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow">
    <h2 class="text-2xl font-semibold mb-4">Login</h2>
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          required
          autocomplete="email"
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          required
          autocomplete="current-password"
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <p v-if="error" class="text-xs text-red-600" data-test="login-error">{{ error }}</p>
      <button
        type="submit"
        class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition disabled:opacity-60"
        :disabled="submitting"
      >
        <span v-if="!submitting">Sign In</span>
        <span v-else>Signing In...</span>
      </button>
    </form>
    <p class="text-xs text-gray-500 mt-4 space-y-1">
      <span class="block font-semibold text-gray-600">Sample Accounts (seeded)</span>
      <span class="block">1 · Alice &mdash; alice@example.com / <code>password1</code></span>
      <span class="block">2 · Bob &mdash; bob@example.com / <code>password2</code></span>
      <span class="block">3 · Charlie &mdash; charlie@example.com / <code>password3</code></span>
      <span class="block">4 · Whale &mdash; whale@example.com / <code>password</code></span>
      <span class="block">5 · Demo User 1 &mdash; demo1@example.com / <code>password</code></span>
      <span class="block"
        >6&ndash;25 · Demo User N &mdash; demoN@example.com / <code>password</code></span
      >
      <span class="block">26 · Load User 1 &mdash; load1@example.com / <code>password</code></span>
      <span class="block"
        >27&ndash;275 · Load User N &mdash; loadN@example.com / <code>password</code></span
      >
      <span class="block italic">IDs assume a fresh seed without prior data.</span>
    </p>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { currentUser } from '../auth';

const form = reactive({ email: '', password: '' });
const submitting = ref(false);
const error = ref('');
const router = useRouter();

async function submit() {
  error.value = '';
  submitting.value = true;
  try {
    // Initialize CSRF cookie per Sanctum SPA docs
    await axios.get('/sanctum/csrf-cookie');
    const res = await axios.post('/login', {
      email: form.email,
      password: form.password,
    });
    currentUser.value = res.data.user; // update global auth state
    await router.push({ name: 'dashboard' });
  } catch (e) {
    if (e.response?.status === 422) {
      error.value = e.response?.data?.errors?.email?.[0] || 'Invalid credentials.';
    } else {
      error.value = 'Login failed. Please try again.';
    }
  } finally {
    submitting.value = false;
  }
}
</script>
