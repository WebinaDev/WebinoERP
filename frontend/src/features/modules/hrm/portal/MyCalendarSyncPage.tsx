'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { copyText, HrmField } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  downloadHrmFile,
  getMyCalendarFeed,
  getMyNotificationChannels,
  regenerateMyCalendarFeed,
  saveMyNotificationChannels,
} from '@/lib/api/hrm';

const LINKS = ['feed_url', 'webcal_url'] as const;

export function MyCalendarSyncPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { locale } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [links, setLinks] = useState<Record<string, string> | null>(null);
  const [bale, setBale] = useState('');
  const [telegram, setTelegram] = useState('');
  const [channels, setChannels] = useState<Record<string, boolean>>({});

  const load = useCallback(async () => {
    try {
      setLinks(await getMyCalendarFeed());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
    void getMyNotificationChannels().then((res) => {
      setBale(String(res?.bale_chat_id ?? ''));
      setTelegram(String(res?.telegram_chat_id ?? ''));
      setChannels((res?.channels ?? {}) as Record<string, boolean>);
    }).catch(() => undefined);
  }, [load]);

  const copy = async (value: string) => {
    if (await copyText(value)) setSuccess(t('calendarSync.copied'));
  };

  const regenerate = async () => {
    if (!window.confirm(t('calendarSync.regenerateConfirm'))) return;
    try {
      setLinks(await regenerateMyCalendarFeed());
      setSuccess(t('calendarSync.regenerated'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const saveChannels = async () => {
    try {
      await saveMyNotificationChannels({ bale_chat_id: bale.trim() || null, telegram_chat_id: telegram.trim() || null });
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myCalendar')} description={t('calendarSync.hint')} {...layoutProps}>
      <div className="space-y-4">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('calendarSync.feedSection')}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {!links ? <PageLoadingState /> : (
              <>
                {LINKS.map((k) => (
                  <HrmField key={k} label={t(`calendarSync.links.${k}`)}>
                    <div className="flex gap-2">
                      <Input readOnly dir="ltr" value={links[k] ?? ''} />
                      <Button variant="outline" onClick={() => void copy(links[k] ?? '')}>{t('calendarSync.copy')}</Button>
                    </div>
                  </HrmField>
                ))}
                <div className="flex flex-wrap gap-2">
                  <Button asChild variant="outline"><a href={links.google_url} target="_blank" rel="noreferrer">{t('calendarSync.addGoogle')}</a></Button>
                  <Button asChild variant="outline"><a href={links.outlook_url} target="_blank" rel="noreferrer">{t('calendarSync.addOutlook')}</a></Button>
                  <Button variant="outline" onClick={() => void downloadHrmFile(`me/calendar.ics?lang=${locale === 'fa' ? 'fa' : 'en'}`, 'my-hr.ics').catch(applyAxiosError)}>{t('calendarSync.download')}</Button>
                  <Button variant="destructive" onClick={() => void regenerate()}>{t('calendarSync.regenerate')}</Button>
                </div>
                <p className="text-xs text-muted-foreground">{t('calendarSync.privacy')}</p>
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('calendarSync.howTo')}</CardTitle></CardHeader>
          <CardContent className="space-y-3 text-sm">
            <div>
              <p className="font-medium">{t('calendarSync.googleTitle')}</p>
              <p className="text-muted-foreground">{t('calendarSync.googleSteps')}</p>
            </div>
            <div>
              <p className="font-medium">{t('calendarSync.outlookTitle')}</p>
              <p className="text-muted-foreground">{t('calendarSync.outlookSteps')}</p>
            </div>
            <div>
              <p className="font-medium">{t('calendarSync.appleTitle')}</p>
              <p className="text-muted-foreground">{t('calendarSync.appleSteps')}</p>
            </div>
            <p className="text-xs text-muted-foreground">{t('calendarSync.refreshNote')}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('calendarSync.notifySection')}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            <HrmField label={t('calendarSync.baleChatId')} hint={channels.bale ? t('calendarSync.channelOn') : t('calendarSync.channelOff')}>
              <Input dir="ltr" value={bale} onChange={(e) => setBale(e.target.value)} />
            </HrmField>
            <HrmField label={t('calendarSync.telegramChatId')} hint={channels.telegram ? t('calendarSync.channelOn') : t('calendarSync.channelOff')}>
              <Input dir="ltr" value={telegram} onChange={(e) => setTelegram(e.target.value)} />
            </HrmField>
            <p className="md:col-span-2 text-xs text-muted-foreground">{t('calendarSync.chatIdHelp')}</p>
            <div className="md:col-span-2"><Button onClick={() => void saveChannels()}>{tNav('common.save')}</Button></div>
          </CardContent>
        </Card>
      </div>
    </HrmPageLayout>
  );
}
