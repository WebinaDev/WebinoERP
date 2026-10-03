'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale-next';

type Tag = { id: number; name: string; color?: string };
type Segment = { id: number; name: string; entity: string };
type ListRow = { id: number; name: string; campaign_id?: number | null; members_count?: number };
type Field = { id: number; key: string; label: string; entity: string };

export function CrmAudiencePage() {
  const t = useTranslations('suite');
  const { formatNumber } = useLocale();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [tags, setTags] = useState<Tag[]>([]);
  const [segments, setSegments] = useState<Segment[]>([]);
  const [lists, setLists] = useState<ListRow[]>([]);
  const [fields, setFields] = useState<Field[]>([]);
  const [tagName, setTagName] = useState('');
  const [segmentName, setSegmentName] = useState('');
  const [listName, setListName] = useState('');
  const [campaignId, setCampaignId] = useState('');
  const [fieldKey, setFieldKey] = useState('');
  const [fieldLabel, setFieldLabel] = useState('');
  const [preview, setPreview] = useState<string | null>(null);

  const load = useCallback(async () => {
    const [tagRes, segmentRes, listRes, fieldRes] = await Promise.all([
      apiClient.get('/v1/crm/tags'),
      apiClient.get('/v1/crm/segments'),
      apiClient.get('/v1/crm/lists'),
      apiClient.get('/v1/crm/custom-fields'),
    ]);
    setTags(unwrapData<Tag[]>(tagRes) ?? []);
    setSegments(unwrapData<Segment[]>(segmentRes) ?? []);
    setLists(unwrapData<ListRow[]>(listRes) ?? []);
    setFields(unwrapData<Field[]>(fieldRes) ?? []);
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  return (
    <CrmPageLayout title={t('audienceTitle')} description={t('audienceHint')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('tags')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {tags.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : tags.map((tag) => (
              <p key={tag.id} className="text-sm">{tag.name}</p>
            ))}
            <form className="flex gap-2" onSubmit={(event) => {
              event.preventDefault();
              if (!tagName.trim()) return;
              void apiClient.post('/v1/crm/tags', { name: tagName.trim() }).then(() => { setTagName(''); setSuccess(t('add')); return load(); }).catch(applyAxiosError);
            }}>
              <Label className="sr-only" htmlFor="tag-name">{t('tagName')}</Label>
              <Input id="tag-name" value={tagName} onChange={(event) => setTagName(event.target.value)} placeholder={t('tagName')} />
              <Button type="submit" size="sm">{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('segments')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {segments.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : segments.map((segment) => (
              <div key={segment.id} className="flex items-center justify-between gap-2 text-sm">
                <span>{segment.name}</span>
                <Button type="button" size="sm" variant="outline" onClick={() => void apiClient.get(`/v1/crm/segments/${segment.id}/preview`).then((res) => {
                  const count = (unwrapData<{ count: number }>(res))?.count ?? 0;
                  setPreview(`${segment.name}: ${formatNumber(count)}`);
                }).catch(applyAxiosError)}>{t('preview')}</Button>
              </div>
            ))}
            {preview ? <p className="text-sm text-muted-foreground">{preview}</p> : null}
            <form className="flex gap-2" onSubmit={(event) => {
              event.preventDefault();
              if (!segmentName.trim()) return;
              void apiClient.post('/v1/crm/segments', { name: segmentName.trim(), entity: 'account', filters: {} }).then(() => { setSegmentName(''); return load(); }).catch(applyAxiosError);
            }}>
              <Label className="sr-only" htmlFor="segment-name">{t('segmentName')}</Label>
              <Input id="segment-name" value={segmentName} onChange={(event) => setSegmentName(event.target.value)} placeholder={t('segmentName')} />
              <Button type="submit" size="sm">{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('lists')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {lists.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : lists.map((list) => (
              <p key={list.id} className="text-sm">{list.name} · {t('members')} {formatNumber(list.members_count ?? 0)}{list.campaign_id ? ` · ${t('campaignId')} ${formatNumber(list.campaign_id)}` : ''}</p>
            ))}
            <form className="grid gap-2 sm:grid-cols-2" onSubmit={(event) => {
              event.preventDefault();
              if (!listName.trim()) return;
              void apiClient.post('/v1/crm/lists', { name: listName.trim(), campaign_id: campaignId ? Number(campaignId) : null }).then(() => { setListName(''); setCampaignId(''); return load(); }).catch(applyAxiosError);
            }}>
              <Input value={listName} onChange={(event) => setListName(event.target.value)} placeholder={t('listName')} aria-label={t('listName')} />
              <Input value={campaignId} onChange={(event) => setCampaignId(event.target.value)} placeholder={t('campaignId')} inputMode="numeric" aria-label={t('campaignId')} />
              <Button type="submit" size="sm">{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('fields')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {fields.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : fields.map((field) => (
              <p key={field.id} className="text-sm">{field.label} · {field.key}</p>
            ))}
            <form className="grid gap-2 sm:grid-cols-2" onSubmit={(event) => {
              event.preventDefault();
              if (!fieldKey.trim() || !fieldLabel.trim()) return;
              void apiClient.post('/v1/crm/custom-fields', { entity: 'account', key: fieldKey.trim(), label: fieldLabel.trim(), type: 'text' }).then(() => { setFieldKey(''); setFieldLabel(''); return load(); }).catch(applyAxiosError);
            }}>
              <Input value={fieldKey} onChange={(event) => setFieldKey(event.target.value)} placeholder={t('fieldKey')} aria-label={t('fieldKey')} dir="ltr" />
              <Input value={fieldLabel} onChange={(event) => setFieldLabel(event.target.value)} placeholder={t('fieldLabel')} aria-label={t('fieldLabel')} />
              <Button type="submit" size="sm">{t('add')}</Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  );
}
