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
      <!-- Commission preview -->
      <div
        v-if="preview"
        class="text-[11px] leading-snug bg-emerald-50 border border-emerald-200 rounded p-2 space-y-0.5"
      >
        <div class="flex justify-between">
          <span>Entered Amount</span><span>{{ formatMoney(preview.amount) }}</span>
        </div>
        <div class="flex justify-between">
          <span>Commission (1.5%)</span><span>{{ formatMoney(preview.commission) }}</span>
        </div>
        <div class="flex justify-between font-medium">
          <span>Total Debit</span><span>{{ formatMoney(preview.totalDebit) }}</span>
        </div>
        <p v-if="insufficient" class="text-red-600 mt-1">Insufficient balance after commission.</p>
      </div>
      <p v-if="info" class="text-xs text-amber-600" data-test="transfer-info">{{ info }}</p>
      <p v-if="error" class="text-xs text-red-600" data-test="transfer-error">{{ error }}</p>
      <button
        type="submit"
        class="w-full bg-emerald-600 text-white py-2 rounded text-sm hover:bg-emerald-700 transition disabled:opacity-50"
        :disabled="submitting || !canSubmit || insufficient"
      >
        <span v-if="!submitting">Transfer</span>
        <span v-else>Sending...</span>
      </button>
    </form>
  </div>
</template>
<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

// Accept currentBalance from parent (numeric) to optionally validate preview
const props = defineProps({
  currentBalance: { type: Number, default: null },
});

const receiverId = ref(null);
const amount = ref('');
const submitting = ref(false);
const error = ref('');
const info = ref('');
const preview = ref(null);

const emit = defineEmits(['transfer-success', 'transfer-error']);

function parseToCents(val) {
  const s = String(val).trim();
  if (!/^\d+(?:\.\d{1,2})?$/.test(s)) return null;
  const [i, f = ''] = s.split('.');
  const frac = (f + '00').slice(0, 2);
  return parseInt(i, 10) * 100 + parseInt(frac, 10);
}
function formatMoney(cents) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(
    cents / 100
  );
}

function computePreview() {
  const cents = parseToCents(amount.value);
  if (cents === null || cents === 0) {
    preview.value = null;
    return;
  }
  // commission cents = (cents * 15 + 500) / 1000 (integer division) half-up
  const commission = Math.floor((cents * 15 + 500) / 1000);
  const totalDebit = cents + commission;
  preview.value = { amount: cents, commission, totalDebit };
}

watch(amount, computePreview);

const insufficient = computed(() => {
  if (!preview.value) return false;
  if (props.currentBalance == null) return false;
  return preview.value.totalDebit > Math.round(props.currentBalance * 100);
});

const canSubmit = computed(() => receiverId.value && parseFloat(amount.value) > 0);

function newIdempotencyKey() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (crypto.getRandomValues(new Uint8Array(1))[0] & 0xf) >> 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

async function submit() {
  error.value = '';
  info.value = '';
  if (!canSubmit.value || insufficient.value) return;
  submitting.value = true;
  const key = newIdempotencyKey();
  try {
    const res = await axios.post(
      '/api/transactions',
      {
        receiver_id: receiverId.value,
        amount: Number(amount.value).toFixed(2),
        idempotency_key: key,
      },
      { headers: { 'Idempotency-Key': key } }
    );
    const data = res.data?.data || {};
    info.value = data.idempotent_replay
      ? 'This transfer was already processed (idempotent replay).'
      : 'Transfer completed.';
    emit('transfer-success', data);
    receiverId.value = null;
    amount.value = '';
    preview.value = null;
  } catch (e) {
    const msg = e.response?.data?.error || e.response?.data?.message || 'Transfer failed';
    error.value = msg;
    emit('transfer-error', msg);
  } finally {
    submitting.value = false;
  }
}
</script>
