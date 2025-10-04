<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold">Dashboard</h2>
    <div class="grid gap-6 md:grid-cols-3">
      <BalanceCard :balance="balance" />
      <TransferForm @transfer="handleTransfer" />
      <TransactionsList :transactions="transactions" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import BalanceCard from '../components/BalanceCard.vue';
import TransferForm from '../components/TransferForm.vue';
import TransactionsList from '../components/TransactionsList.vue';

const balance = ref(1250.42);
const transactions = ref([
  { id: 1, type: 'deposit', amount: 500, date: '2025-10-01' },
  { id: 2, type: 'withdrawal', amount: 100, date: '2025-10-02' },
]);

function handleTransfer(payload) {
  // Placeholder logic, normally call API
  const id = Date.now();
  transactions.value.unshift({
    id,
    type: 'transfer',
    amount: payload.amount,
    date: new Date().toISOString().slice(0, 10),
  });
  balance.value -= payload.amount;
}
</script>
