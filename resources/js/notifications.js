import { reactive } from 'vue';

// Simple in-memory notification store (not persisted across reloads)
// Shape: { id, title, message, createdAt, expiresAt }
const state = reactive({
  items: [],
});

let _id = 0;
const DEFAULT_TTL_MS = 6500;

function push({ title, message, ttl = DEFAULT_TTL_MS }) {
  const id = ++_id;
  const now = Date.now();
  state.items.unshift({
    id,
    title,
    message,
    createdAt: now,
    expiresAt: ttl ? now + ttl : null,
  });
  return id;
}

function dismiss(id) {
  const idx = state.items.findIndex((n) => n.id === id);
  if (idx !== -1) state.items.splice(idx, 1);
}

function pruneExpired() {
  const now = Date.now();
  for (let i = state.items.length - 1; i >= 0; i--) {
    const n = state.items[i];
    if (n.expiresAt && n.expiresAt < now) state.items.splice(i, 1);
  }
}

export function useNotifications() {
  return {
    notifications: state.items,
    push,
    dismiss,
    pruneExpired,
  };
}
