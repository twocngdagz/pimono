<template>
  <div class="p-4 rounded-lg bg-white shadow border col-span-2">
    <h3 class="text-sm font-medium text-gray-600 mb-3">Recent Transactions</h3>
    <div
      ref="scrollWrap"
      class="relative max-h-96 overflow-auto pr-1"
      data-test="transactions-scroll"
    >
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
      <div
        v-if="loadingMore"
        class="py-2 text-center text-xs text-gray-500 sticky bottom-0 bg-gradient-to-t from-white via-white/90"
      >
        Loading…
      </div>
      <div v-else-if="hasMore" class="h-4" aria-hidden="true" data-test="scroll-sentinel"></div>
      <div v-else class="py-2 text-center text-[10px] text-gray-400 select-none">
        End of history
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  transactions: { type: Array, required: true },
  hasMore: { type: Boolean, default: false },
  loadingMore: { type: Boolean, default: false },
});
const emit = defineEmits(['load-more']);

const scrollWrap = ref(null);
let ticking = false;
let lastEmit = 0;
const THRESHOLD_PX = 40;
const EMIT_COOLDOWN_MS = 400; // guard against burst emissions

function maybeEmitLoadMore() {
  const now = Date.now();
  if (now - lastEmit < EMIT_COOLDOWN_MS) return;
  lastEmit = now;
  emit('load-more');
}

function onScroll() {
  if (ticking) return;
  ticking = true;
  requestAnimationFrame(() => {
    ticking = false;
    if (!props.hasMore || props.loadingMore) return;
    const el = scrollWrap.value;
    if (!el) return;
    const nearBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - THRESHOLD_PX;
    if (nearBottom) {
      maybeEmitLoadMore();
    }
  });
}

onMounted(() => {
  if (scrollWrap.value) {
    scrollWrap.value.addEventListener('scroll', onScroll, { passive: true });
  }
});

onBeforeUnmount(() => {
  if (scrollWrap.value) {
    scrollWrap.value.removeEventListener('scroll', onScroll);
  }
});

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
