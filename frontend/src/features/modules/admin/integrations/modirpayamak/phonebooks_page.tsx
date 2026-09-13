'use client';

import { useCallback, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Plus, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  edgeAddPhonebookContact,
  edgeDeletePhonebook,
  edgeField,
  edgeListPhonebookContacts,
  edgeListPhonebooks,
  edgeSavePhonebook,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { cn } from '@/lib/utils';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

function phonebookId(row: EdgeRow): number {
  return Number(edgeField(row, 'id', 'phonebook_id')) || 0;
}

export function ModirpayamakPhonebooksPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const loader = useCallback(() => edgeListPhonebooks(), []);
  const { items: phonebooks, loading, reload } = useModirPayamakEdge(loader, {
    enabled: Boolean(configured),
  });

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [contacts, setContacts] = useState<EdgeRow[]>([]);
  const [contactsLoading, setContactsLoading] = useState(false);

  const [bookDialogOpen, setBookDialogOpen] = useState(false);
  const [bookName, setBookName] = useState('');
  const [editBookId, setEditBookId] = useState<number | null>(null);
  const [savingBook, setSavingBook] = useState(false);
  const [deleteBookId, setDeleteBookId] = useState<number | null>(null);
  const [deletingBook, setDeletingBook] = useState(false);

  const [contactPhone, setContactPhone] = useState('');
  const [contactName, setContactName] = useState('');
  const [addingContact, setAddingContact] = useState(false);

  const loadContacts = useCallback(async (id: number) => {
    setContactsLoading(true);
    const res = await edgeListPhonebookContacts(id);
    setContacts(res.items);
    setContactsLoading(false);
  }, []);

  const selectPhonebook = (row: EdgeRow) => {
    const id = phonebookId(row);
    if (!id) return;
    setSelectedId(id);
    void loadContacts(id);
  };

  const saveBook = async () => {
    setSavingBook(true);
    setError(null);
    const res = await edgeSavePhonebook(editBookId, { name: bookName.trim(), title: bookName.trim() });
    setSavingBook(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setBookDialogOpen(false);
      setBookName('');
      setEditBookId(null);
      void reload();
    } else {
      setError(res.message || t('saveError'));
    }
  };

  const confirmDeleteBook = async () => {
    if (!deleteBookId) return;
    setDeletingBook(true);
    const res = await edgeDeletePhonebook(deleteBookId);
    setDeletingBook(false);
    if (res.ok) {
      setSuccess(tCommon('deleted'));
      setDeleteBookId(null);
      if (selectedId === deleteBookId) {
        setSelectedId(null);
        setContacts([]);
      }
      void reload();
    } else {
      setError(res.message || t('saveError'));
    }
  };

  const addContact = async () => {
    if (!selectedId || !contactPhone.trim()) return;
    setAddingContact(true);
    setError(null);
    const res = await edgeAddPhonebookContact(selectedId, {
      number: contactPhone.trim(),
      phone: contactPhone.trim(),
      name: contactName.trim(),
    });
    setAddingContact(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setContactPhone('');
      setContactName('');
      void loadContacts(selectedId);
    } else {
      setError(res.message || t('saveError'));
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpPhonebooks')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpPhonebooks')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {configured ? (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
              <CardTitle className="text-base">{tNav('nav.erp.admin.mpPhonebooks')}</CardTitle>
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setEditBookId(null);
                  setBookName('');
                  setBookDialogOpen(true);
                }}
              >
                <Plus className="me-2 h-4 w-4" />
                {t('addPhonebook')}
              </Button>
            </CardHeader>
            <CardContent className="space-y-2">
              {loading ? (
                <div className="h-32 animate-pulse rounded bg-muted/40" />
              ) : phonebooks.length === 0 ? (
                <PmEmptyState title={t('phonebooksEmpty')} />
              ) : (
                phonebooks.map((row, i) => {
                  const id = phonebookId(row);
                  const name = edgeField(row, 'name', 'title');
                  return (
                    <div
                      key={id || i}
                      className={cn(
                        'flex cursor-pointer items-center justify-between rounded-lg border p-3 transition-colors',
                        selectedId === id ? 'border-primary bg-primary/5' : 'hover:bg-muted/50',
                      )}
                      onClick={() => selectPhonebook(row)}
                    >
                      <span className="font-medium">{name}</span>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={(e) => {
                          e.stopPropagation();
                          setDeleteBookId(id);
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  );
                })
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('contacts')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {!selectedId ? (
                <p className="py-8 text-center text-sm text-muted-foreground">{t('selectPhonebook')}</p>
              ) : (
                <>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label>{t('colRecipient')}</Label>
                      <Input dir="ltr" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label>{t('contactName')}</Label>
                      <Input value={contactName} onChange={(e) => setContactName(e.target.value)} />
                    </div>
                    <Button
                      type="button"
                      className="sm:col-span-2"
                      disabled={addingContact}
                      onClick={() => void addContact()}
                    >
                      {t('addContact')}
                    </Button>
                  </div>
                  {contactsLoading ? (
                    <div className="h-32 animate-pulse rounded bg-muted/40" />
                  ) : contacts.length === 0 ? (
                    <PmEmptyState title={t('contactsEmpty')} />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('contactName')}</TableHead>
                          <TableHead>{t('colRecipient')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {contacts.map((row, i) => (
                          <TableRow key={i}>
                            <TableCell>{edgeField(row, 'name', 'title')}</TableCell>
                            <TableCell dir="ltr" className="font-mono">
                              {edgeField(row, 'number', 'phone', 'mobile')}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </div>
      ) : null}

      <Dialog open={bookDialogOpen} onOpenChange={setBookDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('addPhonebook')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>{t('name')}</Label>
            <Input value={bookName} onChange={(e) => setBookName(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setBookDialogOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button disabled={savingBook} onClick={() => void saveBook()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteBookId != null}
        title={tCommon('delete')}
        description={t('confirmDeletePhonebook')}
        onConfirm={() => void confirmDeleteBook()}
        onCancel={() => setDeleteBookId(null)}
        pending={deletingBook}
      />
    </CrmPageLayout>
  );
}
