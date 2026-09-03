'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { FolderPlus, Loader2, Share2, Trash2, Upload } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type FileRow = Record<string, unknown>;

function isFolder(row: FileRow) {
  return String(row.mime_type ?? '') === 'inode/directory' || String(row.path ?? '').startsWith('folder://');
}

export function FilesPage() {
  const t = useTranslations('docs.files');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<FileRow[]>([]);
  const [folderId, setFolderId] = useState<number | null>(null);
  const [breadcrumbs, setBreadcrumbs] = useState<{ id: number | null; name: string }[]>([
    { id: null, name: t('root') },
  ]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<FileRow | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [folderOpen, setFolderOpen] = useState(false);
  const [folderName, setFolderName] = useState('');
  const [versionsOpen, setVersionsOpen] = useState(false);
  const [versions, setVersions] = useState<Record<string, unknown>[]>([]);
  const [shareToken, setShareToken] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/docs/files', {
        params: { folder_id: folderId ?? undefined, per_page: 100 },
      });
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, folderId]);

  useEffect(() => {
    void load();
  }, [load]);

  const upload = async (file: File) => {
    setUploading(true);
    const fd = new FormData();
    fd.append('file', file);
    fd.append('name', file.name);
    if (folderId) fd.append('folder_id', String(folderId));
    try {
      await apiClient.post('/v1/docs/files', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const createFolder = async () => {
    if (!folderName.trim()) return;
    try {
      await apiClient.post('/v1/docs/files/folders', {
        name: folderName.trim(),
        parent_id: folderId ?? undefined,
      });
      setFolderOpen(false);
      setFolderName('');
      setSuccess(t('folderCreated'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const openFolder = (row: FileRow) => {
    const id = Number(row.id);
    setFolderId(id);
    setBreadcrumbs((b) => [...b, { id, name: String(row.name ?? id) }]);
  };

  const goBreadcrumb = (idx: number) => {
    const crumb = breadcrumbs[idx];
    setFolderId(crumb.id);
    setBreadcrumbs(breadcrumbs.slice(0, idx + 1));
  };

  const share = async (row: FileRow) => {
    try {
      const res = await apiClient.post(`/v1/docs/files/${row.id}/share`);
      const data = unwrapData(res) as { share_token?: string };
      setShareToken(data.share_token ?? null);
      setSuccess(t('shareCreated'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const showVersions = async (row: FileRow) => {
    try {
      const res = await apiClient.get(`/v1/docs/files/${row.id}/versions`);
      const data = unwrapData(res);
      setVersions(Array.isArray(data) ? (data as Record<string, unknown>[]) : []);
      setVersionsOpen(true);
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget?.id) return;
    setDeleting(true);
    try {
      await apiClient.delete(`/v1/docs/files/${deleteTarget.id}`);
      setDeleteTarget(null);
      setSuccess(tNav('common.deleted'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setDeleting(false);
    }
  };

  const downloadUrl = (id: unknown) => {
    const base = process.env.NEXT_PUBLIC_API_URL || '';
    return `${base}/v1/docs/files/${id}/download`;
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.docs.files')}
      actions={
        <div className="flex flex-wrap gap-2">
          <input
            ref={fileRef}
            type="file"
            className="hidden"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void upload(f);
            }}
          />
          <Button variant="outline" onClick={() => setFolderOpen(true)}>
            <FolderPlus className="size-4 me-2" />
            {t('newFolder')}
          </Button>
          <Button disabled={uploading} onClick={() => fileRef.current?.click()}>
            {uploading ? <Loader2 className="size-4 animate-spin me-2" /> : <Upload className="size-4 me-2" />}
            {t('upload')}
          </Button>
        </div>
      }
      {...layoutProps}
    >
      <div className="mb-3 flex flex-wrap gap-2 text-sm">
        {breadcrumbs.map((b, idx) => (
          <button
            key={`${b.id}-${idx}`}
            type="button"
            className="text-primary underline-offset-2 hover:underline"
            onClick={() => goBreadcrumb(idx)}
          >
            {b.name}
            {idx < breadcrumbs.length - 1 ? ' /' : ''}
          </button>
        ))}
      </div>

      <Card>
        <CardContent className="pt-6">
          {loading ? (
            <div className="flex justify-center py-10">
              <Loader2 className="size-6 animate-spin text-muted-foreground" />
            </div>
          ) : rows.length === 0 ? (
            <PmEmptyState title={t('empty')} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('name')}</TableHead>
                  <TableHead>{t('type')}</TableHead>
                  <TableHead>{t('size')}</TableHead>
                  <TableHead>{t('version')}</TableHead>
                  <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>
                      {isFolder(r) ? (
                        <button type="button" className="font-medium text-primary underline" onClick={() => openFolder(r)}>
                          {String(r.name ?? '')}
                        </button>
                      ) : (
                        String(r.name ?? '')
                      )}
                    </TableCell>
                    <TableCell>{isFolder(r) ? t('folder') : String(r.mime_type ?? '')}</TableCell>
                    <TableCell dir="ltr">{isFolder(r) ? '—' : String(r.size ?? '')}</TableCell>
                    <TableCell dir="ltr">{String(r.version ?? 1)}</TableCell>
                    <TableCell className="text-end">
                      <div className="flex flex-wrap justify-end gap-1">
                        {!isFolder(r) ? (
                          <>
                            <Button size="sm" variant="link" asChild>
                              <a href={downloadUrl(r.id)} target="_blank" rel="noreferrer">
                                {t('download')}
                              </a>
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => void showVersions(r)}>
                              {t('versions')}
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => void share(r)}>
                              <Share2 className="size-4" />
                            </Button>
                          </>
                        ) : null}
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-destructive"
                          onClick={() => setDeleteTarget(r)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={folderOpen} onOpenChange={setFolderOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('newFolder')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>{t('name')}</Label>
            <Input value={folderName} onChange={(e) => setFolderName(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFolderOpen(false)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void createFolder()}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={versionsOpen} onOpenChange={setVersionsOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('versions')}</DialogTitle>
          </DialogHeader>
          <ul className="space-y-2 text-sm">
            {versions.map((v, i) => (
              <li key={i} className="rounded border px-3 py-2">
                {t('version')}: {String(v.version ?? i + 1)}
              </li>
            ))}
          </ul>
          <DialogFooter>
            <Button variant="outline" onClick={() => setVersionsOpen(false)}>
              {tNav('common.close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!shareToken} onOpenChange={(o) => !o && setShareToken(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('shareLink')}</DialogTitle>
          </DialogHeader>
          <Input dir="ltr" readOnly value={shareToken ?? ''} />
          <DialogFooter>
            <Button
              onClick={async () => {
                if (shareToken) await navigator.clipboard.writeText(shareToken);
                setSuccess(t('copied'));
              }}
            >
              {t('copy')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteTarget !== null}
        title={tNav('common.delete')}
        description={t('confirmDelete')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
        pending={deleting}
      />
    </CrmPageLayout>
  );
}
