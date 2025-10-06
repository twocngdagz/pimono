<template>
  <div class="space-y-6">
    <h2 class="text-2xl font-semibold">Dashboard</h2>
    <div class="grid gap-6 md:grid-cols-3">
      <BalanceCard :balance="numericBalance" />
      <TransferForm @transfer-success="onTransferSuccess" @transfer-error="onTransferError" />
      <TransactionsList
        :transactions="transactions"
        :has-more="hasMore"
        :loading-more="loadingMore"
        @load-more="loadMore"
      />
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
import { useNotifications } from '../notifications';

const { push: pushNotification } = useNotifications();

const userId = ref(null);
const balance = ref('0.00');
const transactions = ref([]);
let channel = null;
const processedTxIds = new Set();

const nextBeforeId = ref(null);
const hasMore = ref(false);
const loadingMore = ref(false);
const pageLimit = 20;

const numericBalance = computed(() => parseFloat(balance.value));

async function fetchUser() {
  await ensureAuthFetched();
  if (currentUser.value?.id) userId.value = currentUser.value.id;
}

async function fetchTransactions(options = { append: false, cursor: null }) {
  const { append, cursor } = options;
  const params = { limit: pageLimit };
  if (cursor) params.before_id = cursor;
  try {
    const res = await axios.get('/api/transactions', { params });
    balance.value = res.data.data.balance;
    const list = res.data.data.transactions || [];
    const meta = res.data.meta || {};
    hasMore.value = !!meta.has_more;
    nextBeforeId.value = meta.next_before_id || null;

    if (append) {
      const existingIds = new Set(transactions.value.map((t) => t.id));
      for (const tx of list) {
        if (!existingIds.has(tx.id)) {
          transactions.value.push(tx);
          processedTxIds.add(tx.id);
        }
      }
    } else {
      transactions.value = list;
      processedTxIds.clear();
      list.forEach((t) => processedTxIds.add(t.id));
    }
  } catch (e) {
    console.warn('[Dashboard] Failed to fetch transactions', e?.response?.data || e.message);
  }
}

async function loadMore() {
  if (!hasMore.value || loadingMore.value || !nextBeforeId.value) return;
  loadingMore.value = true;
  try {
    await fetchTransactions({ append: true, cursor: nextBeforeId.value });
  } finally {
    loadingMore.value = false;
  }
}

function handleEvent(e) {
  if (!e?.transaction) return;
  if (processedTxIds.has(e.transaction.id)) return;
  processedTxIds.add(e.transaction.id);
  const uid = Number(userId.value);
  if (e.sender_balance && uid === e.transaction.sender_id) {
    balance.value = e.sender_balance;
  } else if (e.receiver_balance && uid === e.transaction.receiver_id) {
    balance.value = e.receiver_balance;
  }
  const direction = uid === Number(e.transaction.sender_id) ? 'out' : 'in';
  transactions.value.unshift({ ...e.transaction, direction });
  if (direction === 'in') {
    pushNotification({
      title: 'Incoming Transfer',
      message: `You received ${e.transaction.amount} from user #${e.transaction.sender_id}`,
      ttl: 8000,
    });
  }
  if (transactions.value.length > pageLimit * 5) {
    const removed = transactions.value.splice(pageLimit * 5);
    removed.forEach((r) => processedTxIds.delete(r.id));
  }
}

function subscribe() {
  if (!window.Echo) return setTimeout(subscribe, 300);
  if (!userId.value) return;
  try {
    channel = window.Echo.private(`user.${userId.value}`).listen('.TransferCompleted', handleEvent);
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

onMounted(bootstrap);

onBeforeUnmount(() => {
  if (channel) channel.stopListening('.TransferCompleted');
});
</script>
