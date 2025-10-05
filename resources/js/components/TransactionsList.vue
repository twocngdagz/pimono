<template>
  <div class="p-4 rounded-lg bg-white shadow border col-span-2">
    <h3 class="text-sm font-medium text-gray-600 mb-3">Recent Transactions</h3>
    <ul v-if="transactions.length" class="divide-y text-sm">
      <li
        v-for="transaction in transactions"
        :key="transaction.id"
        class="py-2 flex items-center justify-between gap-3"
      >
        <div class="flex-1">
          <span
            class="font-medium"
            :class="transaction.direction === 'out' ? 'text-red-600' : 'text-emerald-600'"
          >
            {{ directionLabel(transaction) }}
          </span>
          <span class="ml-2 text-xs text-gray-500">#{{ transaction.id }}</span>
        </div>
        <span :class="transaction.direction === 'out' ? 'text-red-600' : 'text-emerald-600'">
          {{ signedAmount(transaction) }}
        </span>
        <span class="text-gray-500 text-xs whitespace-nowrap">
          {{ formatDate(transaction.created_at) }}
        </span>
      </li>
    </ul>
    <p v-else class="text-xs text-gray-500">No transactions yet.</p>
  </div>
</template>
<script setup>
defineProps({ transactions: { type: Array, required: true } });
function directionLabel(transaction) {
  return transaction.direction === 'out' ? 'Sent' : 'Received';
}
function signedAmount(transaction) {
  const amt = parseFloat(transaction.amount);
  const sign = transaction.direction === 'out' ? '-' : '+';
  return (
    sign + new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amt)
  );
}
function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString();
}
</script>
