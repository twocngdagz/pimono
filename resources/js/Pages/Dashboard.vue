<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold">Dashboard</h2>
    <div class="grid gap-6 md:grid-cols-3">
      <BalanceCard :balance="numericBalance" />
      <TransferForm @transfer-success="onTransferSuccess" @transfer-error="onTransferError" />
      <TransactionsList :transactions="transactions" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import BalanceCard from '../components/BalanceCard.vue';
import TransferForm from '../components/TransferForm.vue';
import TransactionsList from '../components/TransactionsList.vue';
import { ensureAuthFetched, currentUser } from '../auth';

const userId = ref(null);
const balance = ref('0.00');
const transactions = ref([]);
let channel = null; // Echo channel reference
const processedTxIds = new Set();

const numericBalance = computed(() => parseFloat(balance.value));

async function fetchUser() {
  await ensureAuthFetched();
  if (currentUser.value && currentUser.value.id) {
    userId.value = currentUser.value.id;
  }
}

async function fetchTransactions() {
  try {
    const res = await axios.get('/api/transactions');
    balance.value = res.data.data.balance;
    transactions.value = res.data.data.transactions;
  } catch (e) {
    console.warn('[Dashboard] Failed to fetch transactions', e?.response?.data || e.message);
  }
}

function handleEvent(e, source = 'primary') {
  console.log(`[Dashboard] (${source}) TransferCompleted raw event`, e);
  if (!e?.transaction) return;
  if (processedTxIds.has(e.transaction.id)) {
    console.log('[Dashboard] Duplicate transaction ignored', e.transaction.id);
    return;
  }
  processedTxIds.add(e.transaction.id);
  const uid = Number(userId.value);
  if (e.sender_balance && uid === e.transaction.sender_id) {
    balance.value = e.sender_balance;
  } else if (e.receiver_balance && uid === e.transaction.receiver_id) {
    balance.value = e.receiver_balance;
  }
  const direction = Number(e.transaction.sender_id) === uid ? 'out' : 'in';
  transactions.value.unshift({ ...e.transaction, direction });
}

function subscribe() {
  if (!window.Echo) {
    return setTimeout(subscribe, 300);
  }
  if (!userId.value) {
    return;
  }
  const privateName = `user.${userId.value}`;
  try {
    channel = window.Echo.private(privateName)
      .listen('.TransferCompleted', (e) => handleEvent(e, 'dot.name'))

    const bindInternal = (attempt = 0) => {
      const internal = window.Echo.connector?.pusher?.channel(`private-${privateName}`);
      if (!internal) {
        if (attempt < 20) {
          return setTimeout(() => bindInternal(attempt + 1), 150);
        }
        return;
      }
    };
    bindInternal();
  } catch (err) {
    console.error('[Dashboard] Error subscribing', err);
  }
}

async function bootstrap() {
  await fetchUser();
  await fetchTransactions();
  subscribe();
}

async function onTransferSuccess() {
  await fetchTransactions();
}

function onTransferError(msg) {
  console.warn('Transfer error', msg);
}

onMounted(() => {
  bootstrap();
});

onBeforeUnmount(() => {
  if (channel) {
    channel.stopListening('.TransferCompleted');
  }
});
</script>
