import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';

export type ChatChannel = Record<string, unknown>;
export type ChatMessage = Record<string, unknown>;

export async function getChatChannels(): Promise<ChatChannel[]> {
  const res = await apiClient.get('/v1/core/chat/channels');
  return normalizeListPayload(unwrapData(res));
}

export async function getChatMessages(channelId: number, page = 1): Promise<ChatMessage[]> {
  const res = await apiClient.get(`/v1/core/chat/channels/${channelId}/messages`, {
    params: { page, per_page: 50 },
  });
  const data = unwrapData(res);
  if (data && typeof data === 'object' && 'data' in (data as object)) {
    return normalizeListPayload((data as { data: unknown }).data);
  }
  return normalizeListPayload(data);
}

export async function sendChatMessage(channelId: number, body: string): Promise<ChatMessage> {
  const res = await apiClient.post('/v1/core/chat/messages', { channel_id: channelId, body });
  return unwrapData(res) as ChatMessage;
}

export async function markChannelRead(channelId: number, lastMessageId?: number): Promise<void> {
  await apiClient.post(`/v1/core/chat/channels/${channelId}/read`, {
    last_read_message_id: lastMessageId ?? null,
  });
}

export async function sendTyping(channelId: number): Promise<void> {
  await apiClient.post(`/v1/core/chat/channels/${channelId}/typing`);
}

export async function deleteChatMessage(messageId: number): Promise<void> {
  await apiClient.delete(`/v1/core/chat/messages/${messageId}`);
}

export function getReverbConfig() {
  return {
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? '',
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost',
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080),
    wsScheme: (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http') as 'http' | 'https',
    apiUrl: process.env.NEXT_PUBLIC_API_URL ?? '',
  };
}
