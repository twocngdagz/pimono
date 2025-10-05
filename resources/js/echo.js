import { configureEcho, echo as getEcho } from '@laravel/echo-vue';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

configureEcho({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
});

// Create the Echo instance (echo-vue does not attach to window automatically)
// and expose it so legacy code using window.Echo continues to work.
// This mirrors the classic pattern shown in Laravel docs for plain JS.
window.Echo = getEcho();
