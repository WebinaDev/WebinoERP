'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input'
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { getStaffProfile, saveStaffProfile } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';
import { PageEmptyState } from '@/features/shared/ui/PageStates';

type Props = { id: string };

type Dependent = { id: string; full_name: string; relation: string };
type Asset = { id: string; asset_type: string; serial_number: string };

const PROFILE_FIELDS = [
  { name: 'national_id', labelKey: 'nationalId' as const },
  { name: 'birth_date', labelKey: 'birthDate' as const, type: 'date' },
  { name: 'gender', labelKey: 'gender' as const },
  { name: 'address', labelKey: 'address' as const },
  { name: 'emergency_contact', labelKey: 'emergencyContact' as const },
  { name: 'emergency_phone', labelKey: 'emergencyPhone' as const },
];

function uid() {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

export function StaffDetailPage({ id }: Props) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [values, setValues] = useState<Record<string, string>>({});
  const [dependents, setDependents] = useState<Dependent[]>([]);
  const [assets, setAssets] = useState<Asset[]>([]);
  const [shiftName, setShiftName] = useState('');
  const [employee, setEmployee] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getStaffProfile(id);
      const data = res as Record<string, unknown>;
      const emp = (data.employee as Record<string, unknown> | undefined) ?? null;
      setEmployee(emp);
      setValues({
        national_id: String(data.national_id ?? ''),
        birth_date: String(data.birth_date ?? '').slice(0, 10),
        gender: String(data.gender ?? ''),
        address: String(data.address ?? ''),
        emergency_contact: String(data.emergency_contact ?? ''),
        emergency_phone: String(data.emergency_phone ?? ''),
      });
      const cf = (data.custom_fields as Record<string, unknown> | undefined) ?? {};
      const deps = Array.isArray(cf.dependents) ? (cf.dependents as Dependent[]) : [];
      const assts = Array.isArray(cf.assets) ? (cf.assets as Asset[]) : [];
      setDependents(deps);
      setAssets(assts);
      setShiftName(String((cf.shift as { name?: string } | undefined)?.name ?? ''));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const persist = async (next?: {
    dependents?: Dependent[];
    assets?: Asset[];
    shiftName?: string;
    values?: Record<string, string>;
  }) => {
    setSaving(true);
    try {
      const v = next?.values ?? values;
      const deps = next?.dependents ?? dependents;
      const assts = next?.assets ?? assets;
      const shift = next?.shiftName ?? shiftName;
      await saveStaffProfile(id, {
        national_id: v.national_id || null,
        birth_date: v.birth_date || null,
        gender: v.gender || null,
        address: v.address || null,
        emergency_contact: v.emergency_contact || null,
        emergency_phone: v.emergency_phone || null,
        custom_fields: {
          dependents: deps,
          assets: assts,
          shift: { name: shift },
        },
      });
      setSuccess(t('profileSaved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSaving(false);
    }
  };

  const displayName = employee
    ? `${String(employee.first_name ?? '')} ${String(employee.last_name ?? '')}`.trim()
    : tNav('nav.erp.hrm.staff');

  return (
    <CrmPageLayout
      title={displayName || t('profile')}
      actions={
        <>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'hrm/staff')}>{t('backToList')}</Link>
          </Button>
          <Button onClick={() => void persist()} disabled={saving || loading}>{tNav('common.save')}</Button>
        </>
      }
      {...layoutProps}
    >
      {employee?.status ? (
        <div className="mb-4">
          <Badge variant="secondary">{t('status')}: {String(employee.status)}</Badge>
        </div>
      ) : null}

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-48 w-full" />
        </div>
      ) : (
        <Tabs defaultValue="profile">
          <TabsList>
            <TabsTrigger value="profile">{t('profile')}</TabsTrigger>
            <TabsTrigger value="dependents">{t('dependents')}</TabsTrigger>
            <TabsTrigger value="assets">{t('assets')}</TabsTrigger>
            <TabsTrigger value="shifts">{t('shifts')}</TabsTrigger>
          </TabsList>

          <TabsContent value="profile">
            <Card>
              <CardContent className="grid gap-4 pt-6 md:grid-cols-2">
                {PROFILE_FIELDS.map((f) => (
                  <div key={f.name} className={f.name === 'address' ? 'space-y-1 md:col-span-2' : 'space-y-1'}>
                    <label className="text-sm text-muted-foreground">{t(f.labelKey)}</label>
                    {f.type === 'date' ? (
                      <LocaleDatePicker
                        value={values[f.name] ?? ''}
                        onChange={(v) => setValues({ ...values, [f.name]: v })}
                      />
                    ) : (
                      <Input
                        type="text"
                        value={values[f.name] ?? ''}
                        onChange={(e) => setValues({ ...values, [f.name]: e.target.value })}
                      />
                    )}
                  </div>
                ))}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="dependents">
            <Card>
              <CardContent className="space-y-3 pt-6">
                {dependents.length === 0 ? <PageEmptyState /> : null}
                {dependents.map((d) => (
                  <div key={d.id} className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <Input
                      className="max-w-[12rem]"
                      value={d.full_name}
                      onChange={(e) => setDependents((rows) => rows.map((x) => (x.id === d.id ? { ...x, full_name: e.target.value } : x)))}
                      placeholder={t('dependentName')}
                    />
                    <Input
                      className="max-w-[10rem]"
                      value={d.relation}
                      onChange={(e) => setDependents((rows) => rows.map((x) => (x.id === d.id ? { ...x, relation: e.target.value } : x)))}
                      placeholder={t('relation')}
                    />
                    <Button
                      size="sm"
                      variant="outline"
                      className="text-destructive"
                      onClick={() => {
                        const next = dependents.filter((x) => x.id !== d.id);
                        setDependents(next);
                        void persist({ dependents: next });
                      }}
                    >
                      {tNav('common.delete')}
                    </Button>
                  </div>
                ))}
                <Button
                  size="sm"
                  onClick={() => {
                    const next = [...dependents, { id: uid(), full_name: '', relation: 'child' }];
                    setDependents(next);
                  }}
                >
                  {tNav('common.add')}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="assets">
            <Card>
              <CardContent className="space-y-3 pt-6">
                {assets.length === 0 ? <PageEmptyState /> : null}
                {assets.map((a) => (
                  <div key={a.id} className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm">
                    <Input
                      className="max-w-[12rem]"
                      value={a.asset_type}
                      onChange={(e) => setAssets((rows) => rows.map((x) => (x.id === a.id ? { ...x, asset_type: e.target.value } : x)))}
                      placeholder={t('assetType')}
                    />
                    <Input
                      className="max-w-[12rem]"
                      dir="ltr"
                      value={a.serial_number}
                      onChange={(e) => setAssets((rows) => rows.map((x) => (x.id === a.id ? { ...x, serial_number: e.target.value } : x)))}
                      placeholder={t('serialNumber')}
                    />
                    <Button
                      size="sm"
                      variant="outline"
                      className="text-destructive"
                      onClick={() => {
                        const next = assets.filter((x) => x.id !== a.id);
                        setAssets(next);
                        void persist({ assets: next });
                      }}
                    >
                      {tNav('common.delete')}
                    </Button>
                  </div>
                ))}
                <Button
                  size="sm"
                  onClick={() => {
                    const next = [...assets, { id: uid(), asset_type: 'laptop', serial_number: '' }];
                    setAssets(next);
                  }}
                >
                  {tNav('common.add')}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="shifts">
            <Card>
              <CardContent className="space-y-3 pt-6">
                <div className="space-y-1 max-w-sm">
                  <label className="text-sm text-muted-foreground">{t('shiftName')}</label>
                  <Input value={shiftName} onChange={(e) => setShiftName(e.target.value)} placeholder={t('shiftName')} />
                </div>
                <Button size="sm" onClick={() => void persist()} disabled={saving}>{tNav('common.save')}</Button>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      )}
    </CrmPageLayout>
  );
}
