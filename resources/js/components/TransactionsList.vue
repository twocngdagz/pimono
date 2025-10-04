<template>
  <div class="p-4 rounded-lg bg-white shadow border col-span-2">
    <h3 class="text-sm font-medium text-gray-600 mb-3">Recent Transactions</h3>
    <ul v-if="transactions.length" class="divide-y text-sm">
      <li v-for="t in transactions" :key="t.id" class="py-2 flex items-center justify-between">
        <span class="capitalize">{{ t.type }}</span>
        <span
          :class="
            t.type === 'withdrawal' || t.type === 'transfer' ? 'text-red-600' : 'text-emerald-600'
          "
        >
          {{ sign(t) }}{{ format(t.amount) }}
        </span>
        <span class="text-gray-500 text-xs">{{ t.date }}</span>
      </li>
    </ul>
    <p v-else class="text-xs text-gray-500">No transactions yet.</p>
  </div>
</template>
<script setup>
defineProps({ transactions: { type: Array, required: true } });
function sign(t) {
  return t.type === 'withdrawal' || t.type === 'transfer' ? '-' : '+';
}
function format(v) {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(v);
}
</script>
