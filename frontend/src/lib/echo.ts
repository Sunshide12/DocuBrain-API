import Echo from "laravel-echo";
import Pusher from "pusher-js";

// Make Pusher available globally for Laravel Echo
if (typeof window !== "undefined") {
  (window as any).Pusher = Pusher;
}

export const echo =
  typeof window !== "undefined"
    ? new Echo({
        broadcaster: "reverb",
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY || "docubrain_key", // You should set this in .env.local
        wsHost: "localhost", // Should match NEXT_PUBLIC_REVERB_HOST
        wsPort: 8080,
        wssPort: 8080,
        forceTLS: false,
        enabledTransports: ["ws", "wss"],
      })
    : null;
