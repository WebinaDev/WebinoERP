'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { AlertTriangle, Download, FileText, Pencil, RefreshCw, Trash2, Upload } from 'lucide-react';
import { toast } from 'sonner';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { getAxiosMessage } from '@/lib/api-helpers';
import { formatDate } from '@/lib/locale/format-date';
import { deleteDocument, downloadDocument, listDocuments, updateDocument, uploadDocument, type UserDocument } from '@/lib/api/users';

const TYPES = ['national_card', 'birth_certificate', 'passport', 'contract', 'education', 'certificate', 'resume', 'insurance', 'military_service', 'bank', 'photo', 'other'];
const STATUSES = ['valid', 'pending', 'verified', 'rejected'];

type FormState = { type: string; title: string; number: string; issued_at: string; expires_at: string; notes: string; status: string; file: File | null };
const EMPTY: FormState = { type: 'national_card', title: '', number: '', issued_at: '', expires_at: '', notes: '', status: 'valid', file: null };

function size(bytes: number, locale: string) {
  const n = bytes >= 1048576 ? bytes / 1048576 : bytes / 1024;
  const unit = bytes >= 1048576 ? 'MB' : 'KB';
  return `${n.toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US', { maximumFractionDigits: 1 })} ${unit}`;
}

/** Private personal documents. `admin` shows review status controls. */
export function UserDocumentsPanel({ scope, admin = false }: { scope: number | string | 'me'; admin?: boolean }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const [rows, setRows] = useState<UserDocument[] | null>(null);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<UserDocument | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY);
  const [busy, setBusy] = useState(false);
  const [toDelete, setToDelete] = useState<UserDocument | null>(null);
  const fileRef = useRef<HTMLInputElement | null>(null);

  const load = useCallback(() => {
    void listDocuments(scope).then(setRows).catch((e) => toast.error(getAxiosMessage(e)));
  }, [scope]);
  useEffect(load, [load]);

  function openNew() {
    setEditing(null);
    setForm(EMPTY);
    setOpen(true);
  }
  function openEdit(d: UserDocument) {
    setEditing(d);
    setForm({
      type: d.type, title: d.title, number: d.number ?? '', issued_at: d.issued_at ?? '', expires_at: d.expires_at ?? '',
      notes: d.notes ?? '', status: d.status, file: null,
    });
    setOpen(true);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('type', form.type);
    if (form.title) fd.append('title', form.title);
    fd.append('number', form.number);
    fd.append('issued_at', form.issued_at);
    fd.append('expires_at', form.expires_at);
    fd.append('notes', form.notes);
    if (admin) fd.append('status', form.status);
    if (form.file) fd.append('file', form.file);
    setBusy(true);
    try {
      if (editing) {
        await updateDocument(scope, editing.id, fd);
        toast.success(t('docs.updated'));
      } else {
        await uploadDocument(scope, fd);
        toast.success(t('docs.uploaded'));
      }
      setOpen(false);
      load();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    if (!toDelete) return;
    try {
      await deleteDocument(scope, toDelete.id);
      toast.success(t('docs.deleted'));
      setToDelete(null);
      load();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">{t('docs.fileHint')}</p>
        <Button type="button" onClick={openNew}><Upload className="size-4" />{t('docs.upload')}</Button>
      </div>

      {rows === null ? <p className="text-sm text-muted-foreground">{t('loading')}</p> : null}
      {rows && rows.length === 0 ? (
        <Card className="border-dashed shadow-none"><CardContent className="flex flex-col items-center gap-2 p-8 text-center text-sm text-muted-foreground"><FileText className="size-8" />{t('docs.empty')}</CardContent></Card>
      ) : null}

      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {(rows ?? []).map((d) => (
          <Card key={d.id} className="shadow-sm">
            <CardContent className="space-y-3 p-4">
              <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><FileText className="size-5" /></span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{d.title}</p>
                  <p className="text-xs text-muted-foreground">{t(`docs.types.${d.type}`)}{d.number ? ` · ${d.number}` : ''}</p>
                </div>
                <Badge variant={d.status === 'rejected' ? 'destructive' : d.status === 'verified' ? 'default' : 'secondary'}>{t(`docs.statuses.${d.status}`)}</Badge>
              </div>
              <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {d.issued_at ? <span>{t('docs.issuedAt')}: {formatDate(d.issued_at, { locale })}</span> : null}
                {d.expires_at ? (
                  <span className={d.is_expired ? 'text-destructive' : d.expires_soon ? 'text-amber-600' : ''}>
                    {t('docs.expiresAt')}: {formatDate(d.expires_at, { locale })}
                  </span>
                ) : null}
                <span>{t('docs.version', { v: d.version })}</span>
                <span dir="ltr">{size(d.size_bytes, locale)}</span>
              </div>
              {d.is_expired || d.expires_soon ? (
                <p className={`flex items-center gap-1 text-xs ${d.is_expired ? 'text-destructive' : 'text-amber-600'}`}>
                  <AlertTriangle className="size-3.5" />{d.is_expired ? t('docs.expired') : t('docs.expiresSoon')}
                </p>
              ) : null}
              {d.notes ? <p className="line-clamp-2 text-xs">{d.notes}</p> : null}
              <div className="flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="outline" onClick={() => void downloadDocument(scope, d).catch((e) => toast.error(getAxiosMessage(e)))}><Download className="size-3.5" />{t('docs.download')}</Button>
                <Button type="button" size="sm" variant="outline" onClick={() => openEdit(d)}><Pencil className="size-3.5" />{t('docs.edit')}</Button>
                <Button type="button" size="sm" variant="outline" className="text-destructive" onClick={() => setToDelete(d)}><Trash2 className="size-3.5" />{t('docs.delete')}</Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader><DialogTitle>{editing ? t('docs.edit') : t('docs.upload')}</DialogTitle></DialogHeader>
          <form id="user-doc-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(e) => void submit(e)}>
            <label className="grid gap-1 text-sm">
              {t('docs.type')}
              <Select value={form.type} onValueChange={(v) => setForm({ ...form, type: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{TYPES.map((x) => <SelectItem key={x} value={x}>{t(`docs.types.${x}`)}</SelectItem>)}</SelectContent>
              </Select>
            </label>
            <label className="grid gap-1 text-sm">{t('docs.title')}<Input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('docs.number')}<Input dir="ltr" value={form.number} onChange={(e) => setForm({ ...form, number: e.target.value })} /></label>
            {admin ? (
              <label className="grid gap-1 text-sm">
                {t('docs.status')}
                <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>{STATUSES.map((x) => <SelectItem key={x} value={x}>{t(`docs.statuses.${x}`)}</SelectItem>)}</SelectContent>
                </Select>
              </label>
            ) : <span className="hidden sm:block" />}
            <div className="grid gap-1 text-sm">{t('docs.issuedAt')}<LocaleDatePicker value={form.issued_at || null} onChange={(v) => setForm({ ...form, issued_at: v })} /></div>
            <div className="grid gap-1 text-sm">{t('docs.expiresAt')}<LocaleDatePicker value={form.expires_at || null} onChange={(v) => setForm({ ...form, expires_at: v })} /></div>
            <label className="grid gap-1 text-sm sm:col-span-2">{t('docs.notes')}<Textarea rows={2} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></label>
            <div className="grid gap-1 text-sm sm:col-span-2">
              {editing ? t('docs.replace') : t('docs.file')}
              <div className="flex items-center gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()}>
                  {editing ? <RefreshCw className="size-3.5" /> : <Upload className="size-3.5" />}{t('docs.file')}
                </Button>
                <span className="truncate text-xs text-muted-foreground" dir="ltr">{form.file?.name ?? editing?.original_name ?? ''}</span>
              </div>
              <input ref={fileRef} type="file" className="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx,.odt,.txt,.zip" onChange={(e) => setForm({ ...form, file: e.target.files?.[0] ?? null })} />
            </div>
          </form>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>{t('cancel')}</Button>
            <Button type="submit" form="user-doc-form" disabled={busy || (!editing && !form.file)}>{editing ? t('docs.save') : t('docs.submit')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={toDelete !== null} onOpenChange={(v) => !v && setToDelete(null)}>
        <AlertDialogContent dir="rtl">
          <AlertDialogHeader><AlertDialogTitle>{t('docs.deleteConfirm')}</AlertDialogTitle></AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={(e) => { e.preventDefault(); void remove(); }}>{t('docs.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
