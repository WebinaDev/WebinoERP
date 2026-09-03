'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale-next';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input'
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  getKpiTemplates,
  getPerformanceCycles,
  getPerformanceReviews,
  getStaff,
  saveKpiTemplate,
  savePerformanceCycle,
  savePerformanceReview,
} from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

export function PerformancePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [tab, setTab] = useState('templates');
  const [templates, setTemplates] = useState<Record<string, unknown>[]>([]);
  const [cycles, setCycles] = useState<Record<string, unknown>[]>([]);
  const [reviews, setReviews] = useState<Record<string, unknown>[]>([]);
  const [employees, setEmployees] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [templateOpen, setTemplateOpen] = useState(false);
  const [cycleOpen, setCycleOpen] = useState(false);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [templateForm, setTemplateForm] = useState({ name: '', description: '' });
  const [cycleForm, setCycleForm] = useState({
    name: '',
    start_date: '',
    end_date: '',
    status: 'draft',
  });
  const [reviewForm, setReviewForm] = useState({
    employee_id: '',
    period: '',
    score: '',
    feedback: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [tmpl, cyc, rev, staff] = await Promise.all([
        getKpiTemplates(),
        getPerformanceCycles(),
        getPerformanceReviews(),
        getStaff(),
      ]);
      setTemplates(normalizeListPayload(tmpl as { data?: unknown }));
      setCycles(normalizeListPayload(cyc as { data?: unknown }));
      setReviews(normalizeListPayload(rev as { data?: unknown }));
      setEmployees(normalizeListPayload(staff as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSaveTemplate = async () => {
    if (!templateForm.name.trim()) return;
    setSubmitting(true);
    try {
      await saveKpiTemplate({
        name: templateForm.name.trim(),
        description: templateForm.description.trim() || null,
        is_active: true,
      });
      setTemplateOpen(false);
      setTemplateForm({ name: '', description: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveCycle = async () => {
    if (!cycleForm.name.trim() || !cycleForm.start_date || !cycleForm.end_date) return;
    setSubmitting(true);
    try {
      await savePerformanceCycle({
        name: cycleForm.name.trim(),
        start_date: cycleForm.start_date,
        end_date: cycleForm.end_date,
        status: cycleForm.status,
      });
      setCycleOpen(false);
      setCycleForm({ name: '', start_date: '', end_date: '', status: 'draft' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveReview = async () => {
    if (!reviewForm.employee_id || !reviewForm.period.trim()) return;
    setSubmitting(true);
    try {
      await savePerformanceReview({
        employee_id: Number(reviewForm.employee_id),
        period: reviewForm.period.trim(),
        score: reviewForm.score !== '' ? Number(reviewForm.score) : null,
        feedback: reviewForm.feedback.trim() || null,
      });
      setReviewOpen(false);
      setReviewForm({ employee_id: '', period: '', score: '', feedback: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const actions =
    tab === 'templates' ? (
      <Button onClick={() => setTemplateOpen(true)}>{t('newKpiTemplate')}</Button>
    ) : tab === 'cycles' ? (
      <Button onClick={() => setCycleOpen(true)}>{t('newCycle')}</Button>
    ) : (
      <Button onClick={() => setReviewOpen(true)}>{t('newReview')}</Button>
    );

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.performance')} actions={actions} {...layoutProps}>
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="templates">{t('performanceTemplates')}</TabsTrigger>
          <TabsTrigger value="cycles">{t('performanceCycles')}</TabsTrigger>
          <TabsTrigger value="reviews">{t('performanceReviews')}</TabsTrigger>
        </TabsList>

        <TabsContent value="templates">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('templateName')}</TableHead>
                    <TableHead>{t('description')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow><TableCell colSpan={3}><PageLoadingState /></TableCell></TableRow>
                  ) : templates.length === 0 ? (
                    <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    templates.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.name ?? '')}</TableCell>
                        <TableCell>{String(r.description ?? '')}</TableCell>
                        <TableCell>{r.is_active === false ? t('inactive') : t('active')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="cycles">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('cycleName')}</TableHead>
                    <TableHead>{t('dateFrom')}</TableHead>
                    <TableHead>{t('dateTo')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {cycles.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    cycles.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.name ?? '')}</TableCell>
                        <TableCell>{formatDate(String(r.start_date ?? '')) || '—'}</TableCell>
                        <TableCell>{formatDate(String(r.end_date ?? '')) || '—'}</TableCell>
                        <TableCell>{String(r.status ?? '')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="reviews">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('employee')}</TableHead>
                    <TableHead>{t('period')}</TableHead>
                    <TableHead>{t('score')}</TableHead>
                    <TableHead>{t('feedback')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {reviews.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    reviews.map((r) => {
                      const emp = r.employee as Record<string, unknown> | undefined;
                      return (
                        <TableRow key={String(r.id)}>
                          <TableCell>
                            {emp
                              ? `${String(emp.first_name ?? '')} ${String(emp.last_name ?? '')}`.trim()
                              : String(r.employee_id ?? '')}
                          </TableCell>
                          <TableCell>{String(r.period ?? '')}</TableCell>
                          <TableCell>{String(r.score ?? '')}</TableCell>
                          <TableCell className="max-w-xs truncate">{String(r.feedback ?? '')}</TableCell>
                        </TableRow>
                      );
                    })
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={templateOpen} onOpenChange={setTemplateOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newKpiTemplate')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('templateName')} value={templateForm.name} onChange={(e) => setTemplateForm((f) => ({ ...f, name: e.target.value }))} />
            <Textarea placeholder={t('description')} value={templateForm.description} onChange={(e) => setTemplateForm((f) => ({ ...f, description: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setTemplateOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveTemplate()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={cycleOpen} onOpenChange={setCycleOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newCycle')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('cycleName')} value={cycleForm.name} onChange={(e) => setCycleForm((f) => ({ ...f, name: e.target.value }))} />
            <LocaleDatePicker value={cycleForm.start_date} onChange={(v) => setCycleForm((f) => ({ ...f, start_date: v }))} />
            <LocaleDatePicker value={cycleForm.end_date} onChange={(v) => setCycleForm((f) => ({ ...f, end_date: v }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCycleOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveCycle()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={reviewOpen} onOpenChange={setReviewOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newReview')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={reviewForm.employee_id || undefined} onValueChange={(v) => setReviewForm((f) => ({ ...f, employee_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('employee')} /></SelectTrigger>
              <SelectContent>
                {employees.map((e) => (
                  <SelectItem key={String(e.id)} value={String(e.id)}>
                    {`${String(e.first_name ?? '')} ${String(e.last_name ?? '')}`.trim()}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input placeholder={t('period')} value={reviewForm.period} onChange={(e) => setReviewForm((f) => ({ ...f, period: e.target.value }))} />
            <Input type="number" min={0} max={100} placeholder={t('score')} value={reviewForm.score} onChange={(e) => setReviewForm((f) => ({ ...f, score: e.target.value }))} />
            <Textarea placeholder={t('feedback')} value={reviewForm.feedback} onChange={(e) => setReviewForm((f) => ({ ...f, feedback: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setReviewOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveReview()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
