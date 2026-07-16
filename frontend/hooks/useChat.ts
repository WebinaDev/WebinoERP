"use client";

import { useEffect, useRef, useCallback } from "react";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

export type ChatBroadcastHandlers = {
  onMessageCreated?: (payload: { message: Record<string, unknown> }) => void;
  onMessageUpdated?: (payload: { message: Record<string, unknown> }) => void;
  onMessageDeleted?: (payload: {
    channel_id: number;
    message_id: number;
    deleted_by: number;
  }) => void;
  onUserTyping?: (payload: { channel_id: number; user_id: number }) => void;
  onChannelReadReceipt?: (payload: {
    channel_id: number;
    user_id: number;
    last_read_message_id: number | null;
  }) => void;
};

export type UseChatOptions = {
  channelId: number;
  /** API origin for REST + `/broadcasting/auth`, e.g. https://api.example.com */
  apiUrl: string;
  /** WebSocket host (Reverb / Pusher-compatible) */
  wsHost: string;
  wsPort?: number;
  wsScheme?: "http" | "https";
  /** REVERB_APP_KEY or Pusher key */
  key: string;
  handlers?: ChatBroadcastHandlers;
};

/**
 * Subscribes to private channel `chat.{channelId}` (Echo: `private-chat.{id}`).
 * Auth uses HttpOnly session cookie (`credentials: include`), not a JS-readable Bearer token.
 */
export function useChat(options: UseChatOptions) {
  const {
    channelId,
    apiUrl,
    wsHost,
    wsPort = 8080,
    wsScheme = "http",
    key,
    handlers = {},
  } = options;

  const handlersRef = useRef(handlers);
  handlersRef.current = handlers;

  const echoRef = useRef<Echo | null>(null);

  const disconnect = useCallback(() => {
    if (echoRef.current) {
      echoRef.current.leave(`private-chat.${channelId}`);
      echoRef.current.disconnect();
      echoRef.current = null;
    }
  }, [channelId]);

  useEffect(() => {
    if (typeof window === "undefined" || !channelId) return;

    window.Pusher = Pusher;

    const authEndpoint = `${apiUrl.replace(/\/$/, "")}/broadcasting/auth`;

    const echo = new Echo({
      broadcaster: "pusher",
      key,
      cluster: "mt1",
      wsHost,
      wsPort: wsScheme === "https" ? 443 : wsPort,
      wssPort: wsPort,
      forceTLS: wsScheme === "https",
      disableStats: true,
      enabledTransports: wsScheme === "https" ? ["wss"] : ["ws"],
      authEndpoint,
      authorizer: (channel: { name: string }) => ({
        authorize: (
          socketId: string,
          callback: (error: Error | null, data: unknown) => void,
        ) => {
          fetch(authEndpoint, {
            method: "POST",
            credentials: "include",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })
            .then(async (res) => {
              if (!res.ok) {
                throw new Error(`broadcasting auth failed (${res.status})`);
              }
              return res.json();
            })
            .then((data) => callback(null, data))
            .catch((err: Error) => callback(err, null));
        },
      }),
    });

    echoRef.current = echo;

    const ch = echo.private(`chat.${channelId}`);

    ch.listen(".MessageCreated", (e: { message: Record<string, unknown> }) =>
      handlersRef.current.onMessageCreated?.(e)
    );
    ch.listen(".MessageUpdated", (e: { message: Record<string, unknown> }) =>
      handlersRef.current.onMessageUpdated?.(e)
    );
    ch.listen(".MessageDeleted", (e: Record<string, number>) =>
      handlersRef.current.onMessageDeleted?.(e as never)
    );
    ch.listen(".UserTyping", (e: { channel_id: number; user_id: number }) =>
      handlersRef.current.onUserTyping?.(e)
    );
    ch.listen(".ChannelReadReceipt", (e: Record<string, unknown>) =>
      handlersRef.current.onChannelReadReceipt?.(e as never)
    );

    return () => {
      echo.leave(`private-chat.${channelId}`);
      echo.disconnect();
      if (echoRef.current === echo) echoRef.current = null;
    };
  }, [channelId, apiUrl, wsHost, wsPort, wsScheme, key]);

  return { disconnect };
}
