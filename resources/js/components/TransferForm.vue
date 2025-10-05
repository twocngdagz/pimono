<template>
  <div class="p-4 rounded-lg bg-white shadow border">
    <h3 class="text-sm font-medium text-gray-600 mb-3">Quick Transfer</h3>
    <form class="space-y-3" @submit.prevent="submit">
      <div>
        <label class="block text-xs font-medium mb-1" for="receiver">Receiver User ID</label>
        <input
          id="receiver"
          v-model.number="receiverId"
          type="number"
          min="1"
          required
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1" for="amount">Amount</label>
        <input
          id="amount"
          v-model="amount"
          type="number"
          min="0.01"
          step="0.01"
          required
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        />
      </div>
      <p v-if="error" class="text-xs text-red-600" data-test="transfer-error">{{ error }}</p>
      <button
        type="submit"
        class="w-full bg-emerald-600 text-white py-2 rounded text-sm hover:bg-emerald-700 transition disabled:opacity-50"
        :disabled="submitting || !canSubmit"
      >
        <span v-if="!submitting">Transfer</span>
        <span v-else>Sending...</span>
      </button>
    </form>
  </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const receiverId = ref(null);
const amount = ref('');
const submitting = ref(false);
const error = ref('');

const emit = defineEmits(['transfer-success', 'transfer-error']);

const canSubmit = computed(() => receiverId.value && parseFloat(amount.value) > 0);

async function submit() {
  error.value = '';
  if (!canSubmit.value) return;
  submitting.value = true;
  try {
    const res = await axios.post('/api/transactions', {
      receiver_id: receiverId.value,
      amount: Number(amount.value).toFixed(2),
    });
    emit('transfer-success', res.data.data);
    receiverId.value = null;
    amount.value = '';
  } catch (e) {
    const msg = e.response?.data?.message || 'Transfer failed';
    error.value = msg;
    emit('transfer-error', msg);
  } finally {
    submitting.value = false;
  }
}
</script>
