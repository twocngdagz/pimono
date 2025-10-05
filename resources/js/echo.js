import { configureEcho } from '@laravel/echo-vue';

configureEcho({
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  // wsHost: import.meta.env.VITE_PUSHER_HOST,
  // wsPort: import.meta.env.VITE_PUSHER_PORT,
  // wssPort: import.meta.env.VITE_PUSHER_PORT,
  // enabledTransports: ["ws", "wss"],
});
