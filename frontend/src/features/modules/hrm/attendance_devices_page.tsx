'use client';

import { useCallback, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { copyText, employeeLabel, HrmField, useHrmRows, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  assignAttendancePunch,
  createAttendanceDevice,
  deleteAttendanceDevice,
  deleteDeviceUserMapping,
  getAttendanceDevices,
  getAttendancePunches,
  getDeviceUserMappings,
  importAttendanceCsv,
  rotateAttendanceDeviceKey,
  saveDeviceUserMapping,
  updateAttendanceDevice,
} from '@/lib/api/hrm';

const VENDORS = ['zkteco', 'suprema', 'hikvision', 'virdi', 'anviz', 'generic'] as const;
const PUNCH_STATUSES = ['applied', 'duplicate', 'conflict', 'unmatched', 'invalid'] as const;
const ALL = 'all';

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'applied') return 'default';
  if (status === 'unmatched' || status === 'invalid') return 'destructive';
  if (status === 'conflict') return 'outline';
  return 'secondary';
}

export function AttendanceDevicesPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const feedback = useCrmFeedback();
  const { layoutProps, setSuccess, applyAxiosError } = feedback;
  const devicesLoader = useCallback(() => getAttendanceDevices(), []);
  const devices = useHrmRows(devicesLoader, applyAxiosError);

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.attendanceDevices')} description={t('devices.hint')} {...layoutProps}>
      <Tabs defaultValue="devices" className="space-y-4">
        <TabsList className="flex flex-wrap">
          <TabsTrigger value="devices">{t('devices.tabs.devices')}</TabsTrigger>
          <TabsTrigger value="mappings">{t('devices.tabs.mappings')}</TabsTrigger>
          <TabsTrigger value="punches">{t('devices.tabs.punches')}</TabsTrigger>
          <TabsTrigger value="import">{t('devices.tabs.import')}</TabsTrigger>
        </TabsList>
        <TabsContent value="devices">
          <DevicesTab devices={devices.rows} loading={devices.loading} reload={devices.load} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
        <TabsContent value="mappings">
          <MappingsTab devices={devices.rows} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
        <TabsContent value="punches">
          <PunchesTab devices={devices.rows} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
        <TabsContent value="import">
          <ImportTab devices={devices.rows} onError={applyAxiosError} onSuccess={setSuccess} />
        </TabsContent>
      </Tabs>
    </HrmPageLayout>
  );
}

type TabProps = { devices: HrmRow[]; onError: (err: unknown) => void; onSuccess: (msg: string) => void };

function DevicesTab({ devices, loading, reload, onError, onSuccess }: TabProps & { loading: boolean; reload: () => Promise<void> }) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDateTime } = useLocale();
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [vendor, setVendor] = useState<string>('zkteco');
  const [location, setLocation] = useState('');
  const [secret, setSecret] = useState<{ key: string; url: string; code: string } | null>(null);

  const showSecret = (res: HrmRow) => {
    setSecret({ key: String(res.api_key ?? ''), url: String(res.ingest_url ?? ''), code: String(res.device_code ?? '') });
  };

  const create = async () => {
    try {
      const res = await createAttendanceDevice({ name, device_code: code, vendor, location: location || null });
      showSecret(res);
      setName('');
      setCode('');
      setLocation('');
      onSuccess(tNav('common.saved'));
      void reload();
    } catch (err) {
      onError(err);
    }
  };

  return (
    <div className="space-y-4">
      {secret ? (
        <Alert>
          <AlertDescription className="space-y-2">
            <p className="font-medium">{t('devices.keyOnce')}</p>
            <div className="grid gap-2 md:grid-cols-3">
              <HrmField label={t('devices.headerId')}><Input readOnly dir="ltr" value={secret.code} /></HrmField>
              <HrmField label={t('devices.headerKey')}><Input readOnly dir="ltr" value={secret.key} /></HrmField>
              <HrmField label={t('devices.ingestUrl')}><Input readOnly dir="ltr" value={secret.url} /></HrmField>
            </div>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" onClick={() => void copyText(secret.key).then((ok) => ok && onSuccess(t('devices.copied')))}>{t('devices.copyKey')}</Button>
              <Button size="sm" variant="ghost" onClick={() => setSecret(null)}>{tNav('common.close')}</Button>
            </div>
          </AlertDescription>
        </Alert>
      ) : null}

      <Card>
        <CardHeader><CardTitle className="text-base">{t('devices.addDevice')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <HrmField label={t('devices.name')}><Input value={name} onChange={(e) => setName(e.target.value)} /></HrmField>
          <HrmField label={t('devices.code')} hint={t('devices.codeHint')}><Input dir="ltr" value={code} onChange={(e) => setCode(e.target.value)} /></HrmField>
          <HrmField label={t('devices.vendor')}>
            <Select value={vendor} onValueChange={setVendor}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {VENDORS.map((v) => <SelectItem key={v} value={v}>{t(`devices.vendors.${v}`)}</SelectItem>)}
              </SelectContent>
            </Select>
          </HrmField>
          <HrmField label={t('devices.location')}><Input value={location} onChange={(e) => setLocation(e.target.value)} /></HrmField>
          <div className="md:col-span-2">
            <Button disabled={!name.trim() || !code.trim()} onClick={() => void create()}>{tNav('common.save')}</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle className="text-base">{t('devices.list')}</CardTitle></CardHeader>
        <CardContent>
          {loading ? <PageLoadingState /> : (
            <Table>
              <TableHeader><TableRow>
                <TableHead>{t('devices.name')}</TableHead>
                <TableHead>{t('devices.code')}</TableHead>
                <TableHead>{t('devices.vendor')}</TableHead>
                <TableHead>{t('devices.location')}</TableHead>
                <TableHead>{t('devices.lastSeen')}</TableHead>
                <TableHead>{t('devices.punchCount')}</TableHead>
                <TableHead>{t('active')}</TableHead>
                <TableHead>{tNav('common.actions')}</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {devices.length === 0 ? (
                  <TableRow><TableCell colSpan={8}><PageEmptyState /></TableCell></TableRow>
                ) : devices.map((d) => (
                  <TableRow key={String(d.id)}>
                    <TableCell>{String(d.name ?? '')}</TableCell>
                    <TableCell dir="ltr" className="text-start">{String(d.device_code ?? '')}</TableCell>
                    <TableCell>{t.has(`devices.vendors.${String(d.vendor)}`) ? t(`devices.vendors.${String(d.vendor)}`) : String(d.vendor ?? '')}</TableCell>
                    <TableCell>{String(d.location ?? '—')}</TableCell>
                    <TableCell>{d.last_seen_at ? formatDateTime(String(d.last_seen_at)) : t('devices.never')}</TableCell>
                    <TableCell><HrmDigits value={d.punches_count ?? 0} /></TableCell>
                    <TableCell>
                      <Switch
                        checked={Boolean(d.is_active)}
                        onCheckedChange={(checked) => void updateAttendanceDevice(d.id as number, { is_active: checked }).then(() => reload()).catch(onError)}
                      />
                    </TableCell>
                    <TableCell className="space-x-2 space-x-reverse whitespace-nowrap">
                      <Button size="sm" variant="outline" onClick={() => {
                        if (!window.confirm(t('devices.rotateConfirm'))) return;
                        void rotateAttendanceDeviceKey(d.id as number).then((res) => showSecret(res)).catch(onError);
                      }}>{t('devices.rotateKey')}</Button>
                      <Button size="sm" variant="destructive" onClick={() => {
                        if (!window.confirm(tNav('common.confirmDelete'))) return;
                        void deleteAttendanceDevice(d.id as number).then(() => { onSuccess(tNav('common.deleted')); void reload(); }).catch(onError);
                      }}>{tNav('common.delete')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle className="text-base">{t('devices.howTo')}</CardTitle></CardHeader>
        <CardContent className="space-y-2 text-sm text-muted-foreground">
          <p>{t('devices.howTo1')}</p>
          <p>{t('devices.howTo2')}</p>
          <p>{t('devices.howTo3')}</p>
        </CardContent>
      </Card>
    </div>
  );
}

function DeviceSelect({ devices, value, onChange, allLabel }: { devices: HrmRow[]; value: string; onChange: (v: string) => void; allLabel: string }) {
  return (
    <Select value={value} onValueChange={onChange}>
      <SelectTrigger><SelectValue /></SelectTrigger>
      <SelectContent>
        <SelectItem value={ALL}>{allLabel}</SelectItem>
        {devices.map((d) => <SelectItem key={String(d.id)} value={String(d.id)}>{String(d.name ?? d.device_code)}</SelectItem>)}
      </SelectContent>
    </Select>
  );
}

function MappingsTab({ devices, onError, onSuccess }: TabProps) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const [deviceId, setDeviceId] = useState<string>(ALL);
  const [deviceUserId, setDeviceUserId] = useState('');
  const [employeeId, setEmployeeId] = useState('');
  const loader = useCallback(() => getDeviceUserMappings(), []);
  const list = useHrmRows(loader, onError);

  const save = async () => {
    try {
      await saveDeviceUserMapping({
        device_id: deviceId === ALL ? null : Number(deviceId),
        device_user_id: deviceUserId.trim(),
        employee_id: Number(employeeId),
      });
      setDeviceUserId('');
      onSuccess(tNav('common.saved'));
      void list.load();
    } catch (err) {
      onError(err);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle className="text-base">{t('devices.addMapping')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-3">
          <HrmField label={t('devices.device')}>
            <DeviceSelect devices={devices} value={deviceId} onChange={setDeviceId} allLabel={t('devices.allDevices')} />
          </HrmField>
          <HrmField label={t('devices.deviceUserId')} hint={t('devices.deviceUserIdHint')}>
            <Input dir="ltr" value={deviceUserId} onChange={(e) => setDeviceUserId(e.target.value)} />
          </HrmField>
          <HrmField label={t('employee')}>
            <HrmEmployeeSelect value={employeeId} onChange={setEmployeeId} />
          </HrmField>
          <div className="md:col-span-3">
            <Button disabled={!deviceUserId.trim() || !employeeId} onClick={() => void save()}>{tNav('common.save')}</Button>
          </div>
          <p className="md:col-span-3 text-xs text-muted-foreground">{t('devices.matchOrder')}</p>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          {list.loading ? <PageLoadingState /> : (
            <Table>
              <TableHeader><TableRow>
                <TableHead>{t('devices.device')}</TableHead>
                <TableHead>{t('devices.deviceUserId')}</TableHead>
                <TableHead>{t('employee')}</TableHead>
                <TableHead>{t('staffCode')}</TableHead>
                <TableHead>{tNav('common.actions')}</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {list.rows.length === 0 ? (
                  <TableRow><TableCell colSpan={5}><PageEmptyState /></TableCell></TableRow>
                ) : list.rows.map((m) => (
                  <TableRow key={String(m.id)}>
                    <TableCell>{m.device ? String((m.device as HrmRow).name ?? '') : t('devices.allDevices')}</TableCell>
                    <TableCell><HrmDigits value={m.device_user_id} /></TableCell>
                    <TableCell>{employeeLabel(m.employee)}</TableCell>
                    <TableCell><HrmDigits value={(m.employee as HrmRow | undefined)?.employee_code} /></TableCell>
                    <TableCell>
                      <Button size="sm" variant="destructive" onClick={() => void deleteDeviceUserMapping(m.id as number).then(() => { onSuccess(tNav('common.deleted')); void list.load(); }).catch(onError)}>{tNav('common.delete')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function PunchNote({ note }: { note: unknown }) {
  const t = useTranslations('hrm');
  const code = String(note ?? '');
  if (!code) return <>—</>;
  return <>{t.has(`devices.notes.${code}`) ? t(`devices.notes.${code}`) : code}</>;
}

function PunchesTab({ devices, onError, onSuccess }: TabProps) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDateTime } = useLocale();
  const [status, setStatus] = useState<string>(ALL);
  const [deviceId, setDeviceId] = useState<string>(ALL);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [assign, setAssign] = useState<HrmRow | null>(null);
  const [assignEmployee, setAssignEmployee] = useState('');
  const [remember, setRemember] = useState(true);
  const loader = useCallback(() => getAttendancePunches({
    status: status === ALL ? undefined : status,
    device_id: deviceId === ALL ? undefined : deviceId,
    from: from || undefined,
    to: to || undefined,
  }), [status, deviceId, from, to]);
  const list = useHrmRows(loader, onError);

  const doAssign = async () => {
    if (!assign) return;
    try {
      await assignAttendancePunch(assign.id as number, Number(assignEmployee), remember);
      setAssign(null);
      setAssignEmployee('');
      onSuccess(tNav('common.saved'));
      void list.load();
    } catch (err) {
      onError(err);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="grid gap-3 pt-6 md:grid-cols-4">
          <HrmField label={t('status')}>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>{tNav('common.all')}</SelectItem>
                {PUNCH_STATUSES.map((s) => <SelectItem key={s} value={s}>{t(`devices.statuses.${s}`)}</SelectItem>)}
              </SelectContent>
            </Select>
          </HrmField>
          <HrmField label={t('devices.device')}>
            <DeviceSelect devices={devices} value={deviceId} onChange={setDeviceId} allLabel={tNav('common.all')} />
          </HrmField>
          <HrmField label={t('dateFrom')}><LocaleDatePicker value={from} onChange={setFrom} /></HrmField>
          <HrmField label={t('dateTo')}><LocaleDatePicker value={to} onChange={setTo} /></HrmField>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          {list.loading ? <PageLoadingState /> : (
            <Table>
              <TableHeader><TableRow>
                <TableHead>{t('devices.punchedAt')}</TableHead>
                <TableHead>{t('devices.device')}</TableHead>
                <TableHead>{t('devices.rawCode')}</TableHead>
                <TableHead>{t('employee')}</TableHead>
                <TableHead>{t('devices.direction')}</TableHead>
                <TableHead>{t('devices.source')}</TableHead>
                <TableHead>{t('status')}</TableHead>
                <TableHead>{t('devices.note')}</TableHead>
                <TableHead>{tNav('common.actions')}</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {list.rows.length === 0 ? (
                  <TableRow><TableCell colSpan={9}><PageEmptyState /></TableCell></TableRow>
                ) : list.rows.map((p) => {
                  const st = String(p.status ?? '');
                  return (
                    <TableRow key={String(p.id)}>
                      <TableCell className="whitespace-nowrap">{formatDateTime(String(p.punched_at ?? ''))}</TableCell>
                      <TableCell>{p.device ? String((p.device as HrmRow).name ?? '') : '—'}</TableCell>
                      <TableCell><HrmDigits value={p.raw_code ?? p.national_id} /></TableCell>
                      <TableCell>{p.employee ? employeeLabel(p.employee) : '—'}</TableCell>
                      <TableCell>{t(`devices.directions.${p.direction === 'out' ? 'out' : 'in'}`)}</TableCell>
                      <TableCell>{t(`devices.sources.${p.source === 'csv' ? 'csv' : 'device'}`)}</TableCell>
                      <TableCell><Badge variant={statusVariant(st)}>{t.has(`devices.statuses.${st}`) ? t(`devices.statuses.${st}`) : st}</Badge></TableCell>
                      <TableCell><PunchNote note={p.conflict_note} /></TableCell>
                      <TableCell>
                        {st === 'unmatched' ? (
                          <Button size="sm" variant="outline" onClick={() => setAssign(p)}>{t('devices.assign')}</Button>
                        ) : null}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={assign !== null} onOpenChange={(open) => { if (!open) setAssign(null); }}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('devices.assignTitle')}</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {t('devices.rawCode')}: <HrmDigits value={assign?.raw_code ?? assign?.national_id} />
            </p>
            <HrmEmployeeSelect value={assignEmployee} onChange={setAssignEmployee} />
            <label className="flex items-center gap-2 text-sm">
              <Checkbox checked={remember} onCheckedChange={(v) => setRemember(v === true)} />
              {t('devices.rememberMapping')}
            </label>
          </div>
          <DialogFooter>
            <Button disabled={!assignEmployee} onClick={() => void doAssign()}>{t('devices.assign')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function ImportTab({ devices, onError, onSuccess }: TabProps) {
  const t = useTranslations('hrm');
  const { formatDateTime, formatNumber } = useLocale();
  const [deviceId, setDeviceId] = useState<string>(ALL);
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<HrmRow | null>(null);

  const upload = async () => {
    if (!file) return;
    setBusy(true);
    try {
      const res = await importAttendanceCsv(file, deviceId === ALL ? undefined : deviceId);
      setResult(res);
      onSuccess(t('devices.importDone'));
    } catch (err) {
      onError(err);
    } finally {
      setBusy(false);
    }
  };
  const issues = Array.isArray(result?.issues) ? (result?.issues as HrmRow[]) : [];

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle className="text-base">{t('devices.importTitle')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <HrmField label={t('devices.device')}>
            <DeviceSelect devices={devices} value={deviceId} onChange={setDeviceId} allLabel={t('devices.noDevice')} />
          </HrmField>
          <HrmField label={t('devices.file')} hint={t('devices.importHint')}>
            <Input type="file" accept=".csv,.txt,.dat,.tsv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          </HrmField>
          <div className="md:col-span-2">
            <Button disabled={!file || busy} onClick={() => void upload()}>{t('devices.upload')}</Button>
          </div>
        </CardContent>
      </Card>
      {result ? (
        <Card>
          <CardHeader><CardTitle className="text-base">{t('devices.importResult')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
              {(['parsed', ...PUNCH_STATUSES] as const).map((k) => (
                <div key={k} className="rounded border p-3">
                  <div className="text-xs text-muted-foreground">{k === 'parsed' ? t('devices.parsed') : t(`devices.statuses.${k}`)}</div>
                  <div className="text-lg font-semibold">{formatNumber(Number(result[k] ?? 0))}</div>
                </div>
              ))}
            </div>
            {issues.length > 0 ? (
              <Table>
                <TableHeader><TableRow>
                  <TableHead>{t('devices.line')}</TableHead>
                  <TableHead>{t('devices.rawCode')}</TableHead>
                  <TableHead>{t('devices.punchedAt')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('devices.note')}</TableHead>
                </TableRow></TableHeader>
                <TableBody>
                  {issues.map((i, idx) => (
                    <TableRow key={idx}>
                      <TableCell><HrmDigits value={i.line} /></TableCell>
                      <TableCell><HrmDigits value={i.code} /></TableCell>
                      <TableCell>{i.status === 'invalid' ? '—' : formatDateTime(String(i.punched_at ?? ''))}</TableCell>
                      <TableCell>{t.has(`devices.statuses.${String(i.status)}`) ? t(`devices.statuses.${String(i.status)}`) : String(i.status ?? '')}</TableCell>
                      <TableCell><PunchNote note={i.note} /></TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : null}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
