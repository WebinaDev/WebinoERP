'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Bell, Megaphone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  archiveSiteAnnouncement,
  deleteSiteAnnouncement,
  fetchSiteAnnouncementOptions,
  fetchSiteAnnouncements,
  previewSiteAnnouncement,
  publishSiteAnnouncement,
  retrySiteAnnouncement,
  saveSiteAnnouncement,
  type SiteAnnouncement,
  type SiteAnnouncementAudienceMode,
  type SiteAnnouncementInboxAudience,
  type SiteAnnouncementInput,
  type SiteAnnouncementOptions,
} from '@/lib/api/site-builder';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

const MODES: SiteAnnouncementAudienceMode[] = ['all', 'sites', 'category', 'type', 'tag'];
const INBOX: SiteAnnouncementInboxAudience[] = ['admins', 'staff', 'all'];
const LEVELS = ['info', 'warning', 'critical'] as const;

type FormState = {
  id?: number;
  status?: string;
  title_fa: string;
  title_en: string;
  body_fa: string;
  body_en: string;
  level: string;
  inbox_audience: SiteAnnouncementInboxAudience;
  audience_mode: SiteAnnouncementAudienceMode;
  site_ids: number[];
  category_ids: number[];
  type_ids: number[];
  tags: string[];
  expires_at: string;
};

const emptyForm = (): FormState => ({
  title_fa: '',
  title_en: '',
  body_fa: '',
  body_en: '',
  level: 'info',
  inbox_audience: 'admins',
  audience_mode: 'all',
  site_ids: [],
  category_ids: [],
  type_ids: [],
  tags: [],
  expires_at: '',
});

function toLocalInput(iso?: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toggleId(list: number[], id: number): number[] {
  return list.includes(id) ? list.filter((item) => item !== id) : [...list, id];
}

export function SiteAnnouncementsPage() {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const fa = locale === 'fa';
  const [rows, setRows] = useState<SiteAnnouncement[]>([]);
  const [options, setOptions] = useState<SiteAnnouncementOptions | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [previewCount, setPreviewCount] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [list, opts] = await Promise.all([fetchSiteAnnouncements(), fetchSiteAnnouncementOptions()]);
      setRows(list);
      setOptions(opts);
    } catch (e) {
      setError(getAxiosMessage(e) || t('noticesLoadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const payload = useMemo<SiteAnnouncementInput>(() => {
    const body: SiteAnnouncementInput = {
      title_fa: form.title_fa.trim(),
      title_en: form.title_en.trim() || null,
      body_fa: form.body_fa.trim(),
      body_en: form.body_en.trim() || null,
      level: form.level,
      inbox_audience: form.inbox_audience,
      audience_mode: form.audience_mode,
      expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null,
    };
    if (form.audience_mode === 'sites') body.site_ids = form.site_ids;
    if (form.audience_mode === 'category') body.category_ids = form.category_ids;
    if (form.audience_mode === 'type') body.type_ids = form.type_ids;
    if (form.audience_mode === 'tag') body.tags = form.tags;
    return body;
  }, [form]);

  useEffect(() => {
    const ready =
      form.audience_mode === 'all' ||
      (form.audience_mode === 'sites' && form.site_ids.length > 0) ||
      (form.audience_mode === 'category' && form.category_ids.length > 0) ||
      (form.audience_mode === 'type' && form.type_ids.length > 0) ||
      (form.audience_mode === 'tag' && form.tags.length > 0);
    if (!ready) {
      setPreviewCount(null);
      return;
    }
    let cancelled = false;
    const timer = window.setTimeout(() => {
      void previewSiteAnnouncement(payload)
        .then((res) => {
          if (!cancelled) setPreviewCount(res.count);
        })
        .catch(() => {
          if (!cancelled) setPreviewCount(null);
        });
    }, 300);
    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [payload, form.audience_mode, form.site_ids.length, form.category_ids.length, form.type_ids.length, form.tags.length]);

  function labelName(row: { name_fa: string; name_en: string }) {
    return fa ? row.name_fa : row.name_en;
  }

  async function run(action: () => Promise<unknown>) {
    setPending(true);
    setError(null);
    try {
      await action();
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || t('noticesSaveError'));
    } finally {
      setPending(false);
    }
  }

  function edit(row: SiteAnnouncement) {
    setForm({
      id: row.id,
      status: row.status,
      title_fa: row.title_fa,
      title_en: row.title_en ?? '',
      body_fa: row.body_fa,
      body_en: row.body_en ?? '',
      level: row.level || 'info',
      inbox_audience: row.inbox_audience === 'staff' || row.inbox_audience === 'all' ? row.inbox_audience : 'admins',
      audience_mode: row.audience?.mode ?? 'all',
      site_ids: row.audience?.site_ids ?? [],
      category_ids: row.audience?.category_ids ?? [],
      type_ids: row.audience?.type_ids ?? [],
      tags: row.audience?.tags ?? [],
      expires_at: toLocalInput(row.expires_at),
    });
  }

  const modeLabel = (mode: string) => {
    if (mode === 'sites') return t('audienceSites');
    if (mode === 'category') return t('audienceCategory');
    if (mode === 'type') return t('audienceType');
    if (mode === 'tag') return t('audienceTag');
    return t('audienceAll');
  };

  const inboxLabel = (audience: string) => {
    if (audience === 'staff') return t('inboxStaff');
    if (audience === 'all') return t('inboxEveryone');
    return t('inboxAdmins');
  };

  const statusLabel = (status: string) => {
    if (status === 'published') return t('noticeStatusPublished');
    if (status === 'archived') return t('noticeStatusArchived');
    return t('noticeStatusDraft');
  };

  return (
    <PlatformPageLayout
      title={t('noticesTitle')}
      subtitle={t('noticesSubtitle')}
      error={error}
      actions={
        <>
          <Button type="button" size="sm" variant="outline" onClick={() => setForm(emptyForm())}>
            {t('noticeNew')}
          </Button>
          <RefreshButton onClick={() => void load()} label={t('refresh')} />
        </>
      }
    >
      <Card data-testid="site-admin-announcements">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Megaphone className="size-4" />
            {form.id ? t('noticeEdit') : t('noticeNew')}
          </CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="grid gap-2">
              <Label htmlFor="notice-title-fa">{t('fieldTitleFa')}</Label>
              <Input
                id="notice-title-fa"
                value={form.title_fa}
                onChange={(e) => setForm({ ...form, title_fa: e.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="notice-title-en">{t('fieldTitleEn')}</Label>
              <Input
                id="notice-title-en"
                dir="ltr"
                value={form.title_en}
                onChange={(e) => setForm({ ...form, title_en: e.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="notice-body-fa">{t('fieldBodyFa')}</Label>
              <Textarea
                id="notice-body-fa"
                value={form.body_fa}
                onChange={(e) => setForm({ ...form, body_fa: e.target.value })}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="notice-body-en">{t('fieldBodyEn')}</Label>
              <Textarea
                id="notice-body-en"
                dir="ltr"
                value={form.body_en}
                onChange={(e) => setForm({ ...form, body_en: e.target.value })}
              />
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div className="grid gap-2">
              <Label>{t('fieldLevel')}</Label>
              <Select value={form.level} onValueChange={(level) => setForm({ ...form, level })}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {LEVELS.map((level) => (
                    <SelectItem key={level} value={level}>
                      {t(level === 'info' ? 'levelInfo' : level === 'warning' ? 'levelWarning' : 'levelCritical')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2">
              <Label>{t('fieldInboxAudience')}</Label>
              <Select
                value={form.inbox_audience}
                onValueChange={(inbox_audience) =>
                  setForm({ ...form, inbox_audience: inbox_audience as SiteAnnouncementInboxAudience })
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {INBOX.map((audience) => (
                    <SelectItem key={audience} value={audience}>
                      {inboxLabel(audience)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2">
              <Label>{t('fieldAudience')}</Label>
              <Select
                value={form.audience_mode}
                onValueChange={(audience_mode) =>
                  setForm({ ...form, audience_mode: audience_mode as SiteAnnouncementAudienceMode })
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {MODES.map((mode) => (
                    <SelectItem key={mode} value={mode}>
                      {modeLabel(mode)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="notice-expires">{t('fieldExpires')}</Label>
              <Input
                id="notice-expires"
                type="datetime-local"
                dir="ltr"
                value={form.expires_at}
                onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
              />
            </div>
          </div>

          {form.audience_mode !== 'all' ? (
            <div className="grid max-h-56 gap-2 overflow-auto rounded-md border p-3">
              {form.audience_mode === 'sites'
                ? (options?.sites ?? []).map((site) => (
                    <label key={site.id} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={form.site_ids.includes(site.id)}
                        onCheckedChange={() => setForm({ ...form, site_ids: toggleId(form.site_ids, site.id) })}
                      />
                      <span>
                        {site.domain || site.slug}{' '}
                        <span className="text-muted-foreground">({site.status})</span>
                      </span>
                    </label>
                  ))
                : null}
              {form.audience_mode === 'category'
                ? (options?.categories ?? []).map((row) => (
                    <label key={row.id} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={form.category_ids.includes(row.id)}
                        onCheckedChange={() =>
                          setForm({ ...form, category_ids: toggleId(form.category_ids, row.id) })
                        }
                      />
                      <span>{labelName(row)}</span>
                    </label>
                  ))
                : null}
              {form.audience_mode === 'type'
                ? (options?.types ?? []).map((row) => (
                    <label key={row.id} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={form.type_ids.includes(row.id)}
                        onCheckedChange={() => setForm({ ...form, type_ids: toggleId(form.type_ids, row.id) })}
                      />
                      <span>{labelName(row)}</span>
                    </label>
                  ))
                : null}
              {form.audience_mode === 'tag'
                ? (options?.tags ?? []).map((tag) => (
                    <label key={tag} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={form.tags.includes(tag)}
                        onCheckedChange={() =>
                          setForm({
                            ...form,
                            tags: form.tags.includes(tag) ? form.tags.filter((item) => item !== tag) : [...form.tags, tag],
                          })
                        }
                      />
                      <span>{tag}</span>
                    </label>
                  ))
                : null}
              {form.audience_mode === 'tag' && (options?.tags.length ?? 0) === 0 ? (
                <p className="text-muted-foreground text-sm">{t('noticeNoTags')}</p>
              ) : null}
            </div>
          ) : null}

          <p className="text-muted-foreground text-sm">
            {t('noticePreview', { count: previewCount ?? 0 })}
          </p>
          <p className="text-muted-foreground text-sm">{t('noticeDeliveryHint')}</p>

          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              disabled={pending || !form.title_fa.trim() || !form.body_fa.trim() || form.status === 'archived'}
              onClick={() =>
                void run(async () => {
                  await saveSiteAnnouncement({ ...payload, id: form.id });
                  setForm(emptyForm());
                })
              }
            >
              {t('saveDraft')}
            </Button>
            <Button
              type="button"
              disabled={pending || !form.title_fa.trim() || !form.body_fa.trim()}
              onClick={() =>
                void run(async () => {
                  if (form.id && form.status === 'published') {
                    await saveSiteAnnouncement({ ...payload, id: form.id });
                  } else if (form.id) {
                    await saveSiteAnnouncement({ ...payload, id: form.id });
                    await publishSiteAnnouncement(form.id);
                  } else {
                    await saveSiteAnnouncement({ ...payload, publish: true });
                  }
                  setForm(emptyForm());
                })
              }
            >
              {form.status === 'published' || form.status === 'archived' ? t('publishAgain') : t('publish')}
            </Button>
          </div>
        </CardContent>
      </Card>

      {rows.length === 0 ? (
        <p className="text-muted-foreground text-sm">{t('noticesEmpty')}</p>
      ) : (
        <div className="grid gap-3">
          {rows.map((row) => (
            <Card key={row.id}>
              <CardContent className="flex flex-wrap items-start justify-between gap-3 pt-6">
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <Bell className="text-muted-foreground size-4" />
                    <span className="font-medium">{fa ? row.title_fa : row.title_en || row.title_fa}</span>
                    <Badge variant="outline">{statusLabel(row.status)}</Badge>
                    <Badge variant="secondary">{modeLabel(row.audience?.mode)}</Badge>
                    <Badge variant="outline">{inboxLabel(row.inbox_audience || 'admins')}</Badge>
                  </div>
                  <p className="text-muted-foreground line-clamp-2 text-sm">{fa ? row.body_fa : row.body_en || row.body_fa}</p>
                  <p className="text-muted-foreground text-xs">
                    {t('noticeCounts', {
                      delivered: row.delivery.delivered,
                      pending: row.delivery.pending,
                      failed: row.delivery.failed,
                    })}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" size="sm" variant="outline" onClick={() => edit(row)}>
                    {t('noticeEdit')}
                  </Button>
                  {row.status === 'published' ? (
                    <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => void run(() => retrySiteAnnouncement(row.id))}>
                      {t('retryDelivery')}
                    </Button>
                  ) : null}
                  {row.status === 'draft' ? (
                    <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => void run(() => deleteSiteAnnouncement(row.id))}>
                      {t('deleteDraft')}
                    </Button>
                  ) : (
                    <Button type="button" size="sm" variant="outline" disabled={pending} onClick={() => void run(() => archiveSiteAnnouncement(row.id))}>
                      {t('archiveNotice')}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </PlatformPageLayout>
  );
}
