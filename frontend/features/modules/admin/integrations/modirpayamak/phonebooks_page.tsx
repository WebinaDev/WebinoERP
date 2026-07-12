'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { getModirPayamakPhonebookContacts, getModirPayamakPhonebooks, saveModirPayamakPhonebook, saveModirPayamakPhonebookContact } from '@/lib/api/modirpayamak';
import { normalizeListPayload } from '@/lib/list-utils';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakPhonebooksPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [books, setBooks] = useState<Record<string, unknown>[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [contacts, setContacts] = useState<Record<string, unknown>[]>([]);
  const [bookName, setBookName] = useState('');
  const [contactPhone, setContactPhone] = useState('');

  const loadBooks = useCallback(async () => {
    try {
      const res = await getModirPayamakPhonebooks();
      setBooks(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (configured) void loadBooks();
  }, [configured, loadBooks]);

  useEffect(() => {
    if (!selectedId) return;
    void getModirPayamakPhonebookContacts(selectedId).then((res) => {
      setContacts(normalizeListPayload(res as { data?: unknown }));
    });
  }, [selectedId]);

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpPhonebooks')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpPhonebooks')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardContent className="space-y-3 pt-6">
              <div className="flex gap-2">
                <Input placeholder="Phonebook name" value={bookName} onChange={(e) => setBookName(e.target.value)} />
                <Button onClick={() => void saveModirPayamakPhonebook({ name: bookName }).then(() => { setSuccess(tNav('common.saved')); void loadBooks(); })}>{tNav('common.add')}</Button>
              </div>
              <Table>
                <TableHeader><TableRow><TableHead>Name</TableHead></TableRow></TableHeader>
                <TableBody>
                  {books.map((b) => (
                    <TableRow key={String(b.id)} className="cursor-pointer" onClick={() => setSelectedId(Number(b.id))}>
                      <TableCell>{String(b.name ?? b.title ?? '')}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="space-y-3 pt-6">
              {selectedId ? (
                <>
                  <div className="flex gap-2">
                    <Input placeholder="Phone" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} />
                    <Button onClick={() => void saveModirPayamakPhonebookContact(selectedId, { number: contactPhone }).then(() => setSelectedId(selectedId))}>{tNav('common.add')}</Button>
                  </div>
                  <Table>
                    <TableHeader><TableRow><TableHead>Number</TableHead></TableRow></TableHeader>
                    <TableBody>
                      {contacts.map((c, i) => (
                        <TableRow key={String(c.id ?? i)}><TableCell>{String(c.number ?? c.mobile ?? '')}</TableCell></TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </>
              ) : <p className="text-sm text-muted-foreground">{tNav('common.noData')}</p>}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
