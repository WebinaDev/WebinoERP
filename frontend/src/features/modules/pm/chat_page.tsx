'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useChat } from '@/hooks/useChat';
import {
  createChatChannel,
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
  const tCommon = useTranslations('common');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [channels, setChannels] = useState<ChatChannel[]>([]);
  const [channelId, setChannelId] = useState<number | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [text, setText] = useState('');
  const [typingUserId, setTypingUserId] = useState<number | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [channelName, setChannelName] = useState('');
  const [channelType, setChannelType] = useState<'public' | 'private'>('public');
  const [creating, setCreating] = useState(false);

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

  const loadMessages = useCallback(
    async (id: number) => {
      try {
        const list = await getChatMessages(id);
        setMessages([...list].reverse());
        const last = list[0];
        if (last?.id) void markChannelRead(id, Number(last.id));
      } catch (err) {
        applyAxiosError(err);
      }
    },
    [applyAxiosError],
  );

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

  const handleCreateChannel = async () => {
    if (!channelName.trim()) return;
    setCreating(true);
    try {
      const ch = await createChatChannel({
        name: channelName.trim(),
        type: channelType,
      });
      setCreateOpen(false);
      setChannelName('');
      setChannelType('public');
      setSuccess(t('channelCreated'));
      await loadChannels();
      if (ch.id != null) setChannelId(Number(ch.id));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setCreating(false);
    }
  };

  const activeChannel = channels.find((ch) => Number(ch.id) === channelId);

  return (
    <CrmPageLayout
      title={tNav('nav.erp.pm.chat')}
      description={t('listDescription')}
      {...layoutProps}
      actions={
        <Button type="button" size="sm" onClick={() => setCreateOpen(true)}>
          {t('createChannel')}
        </Button>
      }
    >
      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{t('channels')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {channels.length === 0 ? (
              <div className="space-y-2">
                <p className="text-sm text-muted-foreground">{t('noRooms')}</p>
                <Button type="button" variant="outline" size="sm" className="w-full" onClick={() => setCreateOpen(true)}>
                  {t('createChannel')}
                </Button>
              </div>
            ) : (
              channels.map((ch) => (
                <Button
                  key={String(ch.id)}
                  variant={channelId === Number(ch.id) ? 'default' : 'ghost'}
                  className="w-full justify-start"
                  onClick={() => setChannelId(Number(ch.id))}
                >
                  <span className="truncate">{String(ch.name ?? ch.id)}</span>
                  {ch.type ? (
                    <Badge variant="outline" className="ms-2 shrink-0 text-[10px]">
                      {String(ch.type)}
                    </Badge>
                  ) : null}
                  {ch.unread_count ? (
                    <Badge variant="secondary" className="ms-auto">
                      {String(ch.unread_count)}
                    </Badge>
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
                <div className="flex items-center justify-between gap-2 border-b pb-2">
                  <p className="text-sm font-medium">{String(activeChannel?.name ?? channelId)}</p>
                  {activeChannel?.type ? (
                    <Badge variant="outline">{String(activeChannel.type)}</Badge>
                  ) : null}
                </div>
                <div className="max-h-80 space-y-2 overflow-y-auto">
                  {messages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('noMessages')}</p>
                  ) : (
                    messages.map((m, i) => {
                      const author = m.author as { name?: string } | undefined;
                      return (
                        <div key={String(m.id ?? i)} className="rounded-md border p-2 text-sm">
                          <p className="text-xs font-medium text-muted-foreground">
                            {String(author?.name ?? '')}
                          </p>
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

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{t('createChannel')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label>{t('channelName')}</Label>
              <Input
                value={channelName}
                onChange={(e) => setChannelName(e.target.value)}
                placeholder={t('channelNamePlaceholder')}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('channelType')}</Label>
              <Select
                value={channelType}
                onValueChange={(v) => setChannelType(v as 'public' | 'private')}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">{t('typePublic')}</SelectItem>
                  <SelectItem value="private">{t('typePrivate')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => void handleCreateChannel()}
              disabled={creating || !channelName.trim()}
            >
              {tCommon('create')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
