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

// Option C: Simple maximum major amount (whole-number part) threshold.
// If user enters a value with whole part greater than this 16-digit number, we block it.
// NOTE: This threshold itself (9,999,999,999,999,999) exceeds JS Number safe integer precision,
// so calculations beyond ~9,007,199,254,740,991 may display rounding. We accept this per requirement.
const MAX_MAJOR_AMOUNT_STR = '9999999999999999';

const emit = defineEmits(['transfer-success', 'transfer-error']);

function exceedsMaxMajor(wholeStr) {
  const trimmed = (wholeStr || '').replace(/^0+/, '') || '0';
  if (trimmed.length < MAX_MAJOR_AMOUNT_STR.length) return false;
  if (trimmed.length > MAX_MAJOR_AMOUNT_STR.length) return true;
  return trimmed > MAX_MAJOR_AMOUNT_STR; // lexicographic works because equal length digits only
}

function parseToCents(val) {
  const s = String(val).trim();
  if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(s)) return null;
  const [i, f = ''] = s.split('.');
  if (exceedsMaxMajor(i)) return null; // treat as invalid for parse (will trigger error in computePreview)
  const frac = (f + '00').slice(0, 2);
  // Warning: may be imprecise for very large i but acceptable per chosen Option C.
  return parseInt(i, 10) * 100 + parseInt(frac, 10);
}
function centsToDecimalString(cents) {
  const abs = Math.abs(cents);
  const whole = Math.trunc(abs / 100);
  const frac = String(abs % 100).padStart(2, '0');
  return (cents < 0 ? '-' : '') + whole + '.' + frac;
}
function formatMoney(cents) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(
    cents / 100
  );
}

function computePreview() {
  error.value = '';
  const raw = String(amount.value).trim();
  if (raw === '') {
    preview.value = null;
    return;
  }
  if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(raw)) {
    preview.value = null;
    return;
  }
  const [whole] = raw.split('.');
  if (exceedsMaxMajor(whole)) {
    preview.value = null;
    error.value = 'Amount too large to represent safely.';
    return;
  }
  const cents = parseToCents(raw);
  if (cents === null || cents === 0) {
    preview.value = null;
    return;
  }
  const commission = Math.floor((cents * 15 + 500) / 1000); // 1.5% half-up
  const totalDebit = cents + commission;
  preview.value = { amount: cents, commission, totalDebit };
}

watch(amount, computePreview);

const insufficient = computed(() => {
  if (!preview.value) return false;
  if (props.currentBalance == null) return false;
  return preview.value.totalDebit > Math.round(props.currentBalance * 100);
});

const canSubmit = computed(() => {
  if (!receiverId.value) return false;
  if (error.value) return false;
  const cents = parseToCents(amount.value);
  return cents !== null && cents > 0;
});

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
    const cents = parseToCents(amount.value);
    if (cents === null) {
      error.value = 'Invalid amount.';
      submitting.value = false;
      return;
    }
    const res = await axios.post(
      '/api/transactions',
      {
        receiver_id: receiverId.value,
        amount: centsToDecimalString(cents),
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
