<template>
  <div class="fixed top-4 right-4 z-50 flex flex-col gap-3 w-80" role="status" aria-live="polite">
    <div
      v-for="n in notifications"
      :key="n.id"
      class="rounded shadow bg-white border px-4 py-3 text-sm flex gap-3 items-start animate-fade-in"
    >
      <div class="flex-1">
        <p class="font-medium" v-text="n.title"></p>
        <p class="text-xs text-gray-600 mt-0.5" v-text="n.message" />
        <p class="text-[10px] text-gray-400 mt-1" v-text="formatTime(n.createdAt)" />
      </div>
      <button
        class="text-gray-400 hover:text-gray-700 transition"
        aria-label="Dismiss notification"
        @click="dismiss(n.id)"
      >
        ✕
      </button>
    </div>
  </div>
</template>
<script setup>
import { onMounted } from 'vue';
import { useNotifications } from '../notifications';

const { notifications, dismiss, pruneExpired } = useNotifications();

function formatTime(ts) {
  try {
    return new Date(ts).toLocaleTimeString();
  } catch {
    return '';
  }
}

onMounted(() => {
  // Periodic pruning of auto-dismissed notifications
  setInterval(pruneExpired, 5000);
});
</script>
<style scoped>
@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in {
  animation: fade-in 120ms ease-out;
}
</style>
