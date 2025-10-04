<template>
  <div class="p-4 rounded-lg bg-white shadow border">
    <h3 class="text-sm font-medium text-gray-600 mb-3">Quick Transfer</h3>
    <form class="space-y-3" @submit.prevent="submit">
      <div>
        <label class="block text-xs font-medium mb-1" for="amount">Amount</label>
        <input
          id="amount"
          v-model.number="amount"
          type="number"
          min="1"
          step="0.01"
          required
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <button
        type="submit"
        class="w-full bg-emerald-600 text-white py-2 rounded text-sm hover:bg-emerald-700 transition disabled:opacity-50"
        :disabled="!amount"
      >
        Transfer
      </button>
    </form>
  </div>
</template>
<script setup>
import { ref } from 'vue';
const amount = ref(null);
const emit = defineEmits(['transfer']);
function submit() {
  emit('transfer', { amount: amount.value });
  amount.value = null;
}
</script>
