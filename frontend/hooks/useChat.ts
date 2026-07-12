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
  /** Sanctum bearer token */
  token: string;
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
 * Uses pusher-js driver against a Reverb-compatible server.
 */
export function useChat(options: UseChatOptions) {
  const {
    channelId,
    token,
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
    if (typeof window === "undefined" || !token || !channelId) return;

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
      auth: {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      },
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
  }, [channelId, token, apiUrl, wsHost, wsPort, wsScheme, key]);

  return { disconnect };
}
