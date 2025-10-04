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
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <button
        type="submit"
        class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition"
      >
        Sign In
      </button>
    </form>
    <p class="text-xs text-gray-500 mt-4">(Authentication wiring TBD)</p>
  </div>
</template>

<script setup>
import { reactive } from 'vue';

const form = reactive({ email: '', password: '' });

function submit() {
  // Placeholder: would call API for Sanctum auth later
  alert(`Logging in as ${form.email}`);
}
</script>
