<template>
  <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900">
    <header class="bg-white border-b shadow-sm">
      <nav class="mx-auto max-w-7xl px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">
          <router-link to="/">PiMono</router-link>
        </h1>
        <div class="space-x-4 text-sm">
          <router-link class="hover:underline" to="/">Login</router-link>
          <router-link class="hover:underline" to="/dashboard">Dashboard</router-link>
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
</script>

<style scoped>
</style>
