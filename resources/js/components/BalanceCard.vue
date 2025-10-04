<template>
  <div class="p-4 rounded-lg bg-white shadow border flex flex-col justify-between">
    <div>
      <h3 class="text-sm font-medium text-gray-500">Current Balance</h3>
      <p class="mt-2 text-3xl font-semibold tracking-tight">{{ formatted }}</p>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ balance: { type: Number, required: true } });
const formatted = computed(() =>
  new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(props.balance)
);
</script>
