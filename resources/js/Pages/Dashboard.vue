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
import { ensureAuthFetched } from '../auth';
import { currentUser } from '../auth';

const userId = ref(null);
const balance = ref('0.00');
const transactions = ref([]);
let channel = null;

const numericBalance = computed(() => parseFloat(balance.value));

async function fetchUser() {
  await ensureAuthFetched();
  if (currentUser.value && currentUser.value.id) {
    userId.value = currentUser.value.id;
  }
}

async function fetchTransactions() {
  const res = await axios.get('/api/transactions');
  balance.value = res.data.data.balance;
  transactions.value = res.data.data.transactions;
}

function subscribe() {
  if (!window.Echo || !userId.value) return;
  channel = window.Echo.private(`user.${userId.value}`).listen('.TransferCompleted', (e) => {
    // e.transaction + sender_balance / receiver_balance
    if (!userId.value) return;
    if (e.sender_balance && parseFloat(userId.value) === e.transaction.sender_id) {
      balance.value = e.sender_balance;
    } else if (e.receiver_balance && parseFloat(userId.value) === e.transaction.receiver_id) {
      balance.value = e.receiver_balance;
    }
    const direction = e.transaction.sender_id === userId.value ? 'out' : 'in';
    transactions.value.unshift({ ...e.transaction, direction });
  });
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
