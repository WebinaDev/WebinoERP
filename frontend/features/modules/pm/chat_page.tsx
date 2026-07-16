'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useChat } from '@/hooks/useChat';
import {
  getChatChannels,
  getChatMessages,
  getReverbConfig,
  markChannelRead,
  sendChatMessage,
  sendTyping,
  type ChatChannel,
  type ChatMessage,
} from '@/lib/api/chat';

export function ChatPage() {
  const t = useTranslations('pm.chat');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [channels, setChannels] = useState<ChatChannel[]>([]);
  const [channelId, setChannelId] = useState<number | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [text, setText] = useState('');
  const [typingUserId, setTypingUserId] = useState<number | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const reverb = getReverbConfig();

  const loadChannels = useCallback(async () => {
    try {
      const list = await getChatChannels();
      setChannels(list);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void loadChannels();
  }, [loadChannels]);

  const loadMessages = useCallback(async (id: number) => {
    try {
      const list = await getChatMessages(id);
      setMessages([...list].reverse());
      const last = list[0];
      if (last?.id) void markChannelRead(id, Number(last.id));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (!channelId) return;
    void loadMessages(channelId);
  }, [channelId, loadMessages]);

  useChat({
    channelId: channelId ?? 0,
    apiUrl: reverb.apiUrl,
    wsHost: reverb.wsHost,
    wsPort: reverb.wsPort,
    wsScheme: reverb.wsScheme,
    key: reverb.key,
    handlers: {
      onMessageCreated: (e) => {
        const msg = e.message;
        if (Number(msg.channel_id) !== channelId) return;
        setMessages((prev) => {
          if (prev.some((m) => m.id === msg.id)) return prev;
          return [...prev, msg];
        });
      },
      onUserTyping: (e) => {
        if (e.channel_id !== channelId) return;
        setTypingUserId(e.user_id);
        if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
        typingTimeoutRef.current = setTimeout(() => setTypingUserId(null), 3000);
      },
    },
  });

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSend = async () => {
    if (!channelId || !text.trim()) return;
    const body = text.trim();
    setText('');
    try {
      const msg = await sendChatMessage(channelId, body);
      setMessages((prev) => {
        if (prev.some((m) => m.id === msg.id)) return prev;
        return [...prev, msg];
      });
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const handleTyping = () => {
    if (!channelId) return;
    void sendTyping(channelId);
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.pm.chat')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardContent className="space-y-2 pt-6">
            {channels.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('noRooms')}</p>
            ) : (
              channels.map((ch) => (
                <Button
                  key={String(ch.id)}
                  variant={channelId === Number(ch.id) ? 'default' : 'ghost'}
                  className="w-full justify-start"
                  onClick={() => setChannelId(Number(ch.id))}
                >
                  <span className="truncate">{String(ch.name ?? ch.id)}</span>
                  {ch.unread_count ? (
                    <Badge variant="secondary" className="ms-auto">{String(ch.unread_count)}</Badge>
                  ) : null}
                </Button>
              ))
            )}
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardContent className="space-y-3 pt-6">
            {!channelId ? (
              <p className="py-12 text-center text-sm text-muted-foreground">{t('selectRoom')}</p>
            ) : (
              <>
                <div className="max-h-80 space-y-2 overflow-y-auto">
                  {messages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('noMessages')}</p>
                  ) : (
                    messages.map((m, i) => {
                      const author = m.author as { name?: string } | undefined;
                      return (
                        <div key={String(m.id ?? i)} className="rounded-md border p-2 text-sm">
                          <p className="text-xs font-medium text-muted-foreground">{String(author?.name ?? '')}</p>
                          <p>{String(m.body ?? '')}</p>
                          <p className="text-[10px] text-muted-foreground">{String(m.created_at ?? '')}</p>
                        </div>
                      );
                    })
                  )}
                  <div ref={messagesEndRef} />
                </div>
                {typingUserId ? (
                  <p className="text-xs text-muted-foreground">{t('typing')}</p>
                ) : null}
                <div className="flex gap-2">
                  <Input
                    value={text}
                    onChange={(e) => {
                      setText(e.target.value);
                      handleTyping();
                    }}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        void handleSend();
                      }
                    }}
                    placeholder={t('messagePlaceholder')}
                  />
                  <Button onClick={() => void handleSend()}>{t('send')}</Button>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  );
}
