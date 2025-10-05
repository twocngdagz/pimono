<template>
  <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900">
    <header class="bg-white border-b shadow-sm">
      <nav class="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">
          <router-link to="/">PiMono</router-link>
        </h1>
        <div class="flex items-center gap-4 text-sm">
          <template v-if="isAuthenticated">
            <router-link class="hover:underline" to="/dashboard">Dashboard</router-link>
            <span v-if="currentUser && currentUser.name" class="text-gray-500"
              >Hi, {{ currentUser.name }}</span
            >
            <button
              type="button"
              class="text-red-600 hover:underline"
              data-test="logout-btn"
              @click="handleLogout"
            >
              Logout
            </button>
          </template>
          <template v-else>
            <router-link class="hover:underline" to="/">Login</router-link>
          </template>
        </div>
      </nav>
    </header>
    <main class="flex-1 mx-auto w-full max-w-7xl p-4">
      <router-view />
    </main>
    <footer class="text-center text-xs text-gray-500 py-6">Pimono Wallet 2025</footer>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { isAuthenticated, logout, currentUser } from './auth';

const router = useRouter();

async function handleLogout() {
  await logout();
  // After logout, redirect to login route root
  router.push({ name: 'login' });
}
</script>

<style scoped></style>
