'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { getStaffProfile, saveStaffProfile } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';

type Props = { id: string };

export function StaffDetailPage({ id }: Props) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [sections, setSections] = useState<{ key: string; label: string; fields: { name: string; label: string; type?: string; value?: string }[] }[]>([]);
  const [values, setValues] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<string>('');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getStaffProfile(id);
      const data = res as { sections?: typeof sections; fields?: Record<string, string>; status?: string };
      const secs = data.sections ?? [{ key: 'main', label: t('profileSaved'), fields: Object.entries(data.fields ?? {}).map(([name, value]) => ({ name, label: name, value: String(value) })) }];
      setSections(secs);
      const v: Record<string, string> = {};
      for (const s of secs) for (const f of s.fields) v[f.name] = String(f.value ?? '');
      setValues(v);
      setStatus(String((data as { status?: string }).status ?? ''));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [id, applyAxiosError, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    try {
      await saveStaffProfile(id, { fields: values });
      setSuccess(t('profileSaved'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.hrm.staff')}
      actions={
        <>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'hrm/staff')}>{t('backToList')}</Link>
          </Button>
          <Button onClick={() => void save()}>{tNav('common.save')}</Button>
        </>
      }
      {...layoutProps}
    >
      {status ? (
        <div className="mb-4">
          <Badge variant="secondary">{t('status')}: {status}</Badge>
        </div>
      ) : null}
      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-48 w-full" />
        </div>
      ) : (
        <Tabs defaultValue={sections[0]?.key ?? 'main'}>
          <TabsList>
            {sections.map((s) => <TabsTrigger key={s.key} value={s.key}>{s.label}</TabsTrigger>)}
          </TabsList>
          {sections.map((s) => (
            <TabsContent key={s.key} value={s.key}>
              <Card>
                <CardContent className="grid gap-4 pt-6 md:grid-cols-2">
                  {s.fields.map((f) => (
                    <div key={f.name} className="space-y-1">
                      <label className="text-sm text-muted-foreground">{f.label}</label>
                      <Input value={values[f.name] ?? ''} onChange={(e) => setValues({ ...values, [f.name]: e.target.value })} />
                    </div>
                  ))}
                </CardContent>
              </Card>
            </TabsContent>
          ))}
        </Tabs>
      )}
    </CrmPageLayout>
  );
}
