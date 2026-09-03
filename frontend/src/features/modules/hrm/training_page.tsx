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
  getStaff,
  getTrainingCourses,
  getTrainingEnrollments,
  getTrainingSessions,
  saveTrainingCourse,
  saveTrainingEnrollment,
  saveTrainingSession,
} from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

export function TrainingPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [tab, setTab] = useState('courses');
  const [courses, setCourses] = useState<Record<string, unknown>[]>([]);
  const [sessions, setSessions] = useState<Record<string, unknown>[]>([]);
  const [enrollments, setEnrollments] = useState<Record<string, unknown>[]>([]);
  const [employees, setEmployees] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [courseOpen, setCourseOpen] = useState(false);
  const [sessionOpen, setSessionOpen] = useState(false);
  const [enrollOpen, setEnrollOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [courseForm, setCourseForm] = useState({
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    status: 'draft',
  });
  const [sessionForm, setSessionForm] = useState({
    course_id: '',
    title: '',
    starts_at: '',
    ends_at: '',
    location: '',
  });
  const [enrollForm, setEnrollForm] = useState({
    course_id: '',
    employee_id: '',
    status: 'enrolled',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [c, s, e, staff] = await Promise.all([
        getTrainingCourses(),
        getTrainingSessions(),
        getTrainingEnrollments(),
        getStaff(),
      ]);
      setCourses(normalizeListPayload(c as { data?: unknown }));
      setSessions(normalizeListPayload(s as { data?: unknown }));
      setEnrollments(normalizeListPayload(e as { data?: unknown }));
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

  const courseTitle = (id: unknown): string => {
    const c = courses.find((row) => String(row.id) === String(id));
    return String(c?.title ?? c?.name ?? id ?? '');
  };

  const handleSaveCourse = async () => {
    if (!courseForm.title.trim()) return;
    setSubmitting(true);
    try {
      await saveTrainingCourse({
        title: courseForm.title.trim(),
        description: courseForm.description.trim() || null,
        start_date: courseForm.start_date || null,
        end_date: courseForm.end_date || null,
        status: courseForm.status,
      });
      setCourseOpen(false);
      setCourseForm({ title: '', description: '', start_date: '', end_date: '', status: 'draft' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveSession = async () => {
    if (!sessionForm.course_id || !sessionForm.title.trim()) return;
    setSubmitting(true);
    try {
      await saveTrainingSession({
        course_id: Number(sessionForm.course_id),
        title: sessionForm.title.trim(),
        starts_at: sessionForm.starts_at || null,
        ends_at: sessionForm.ends_at || null,
        location: sessionForm.location.trim() || null,
      });
      setSessionOpen(false);
      setSessionForm({ course_id: '', title: '', starts_at: '', ends_at: '', location: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveEnrollment = async () => {
    if (!enrollForm.course_id || !enrollForm.employee_id) return;
    setSubmitting(true);
    try {
      await saveTrainingEnrollment({
        course_id: Number(enrollForm.course_id),
        employee_id: Number(enrollForm.employee_id),
        status: enrollForm.status,
      });
      setEnrollOpen(false);
      setEnrollForm({ course_id: '', employee_id: '', status: 'enrolled' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const actions =
    tab === 'courses' ? (
      <Button onClick={() => setCourseOpen(true)}>{t('newCourse')}</Button>
    ) : tab === 'sessions' ? (
      <Button onClick={() => setSessionOpen(true)}>{t('newSession')}</Button>
    ) : (
      <Button onClick={() => setEnrollOpen(true)}>{t('newEnrollment')}</Button>
    );

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.training')} actions={actions} {...layoutProps}>
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="courses">{t('trainingCourses')}</TabsTrigger>
          <TabsTrigger value="sessions">{t('trainingSessions')}</TabsTrigger>
          <TabsTrigger value="enrollments">{t('trainingEnrollments')}</TabsTrigger>
        </TabsList>

        <TabsContent value="courses">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('courseTitle')}</TableHead>
                    <TableHead>{t('dateFrom')}</TableHead>
                    <TableHead>{t('dateTo')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow><TableCell colSpan={4}><PageLoadingState /></TableCell></TableRow>
                  ) : courses.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    courses.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.title ?? r.name ?? '')}</TableCell>
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

        <TabsContent value="sessions">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('trainingCourses')}</TableHead>
                    <TableHead>{t('sessionTitle')}</TableHead>
                    <TableHead>{t('scheduledAt')}</TableHead>
                    <TableHead>{t('location')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {sessions.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    sessions.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{courseTitle(r.course_id)}</TableCell>
                        <TableCell>{String(r.title ?? '')}</TableCell>
                        <TableCell>{String(r.starts_at ?? r.scheduled_at ?? '')}</TableCell>
                        <TableCell>{String(r.location ?? '')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="enrollments">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('trainingCourses')}</TableHead>
                    <TableHead>{t('employee')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {enrollments.length === 0 ? (
                    <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    enrollments.map((r) => {
                      const emp = r.employee as Record<string, unknown> | undefined;
                      return (
                        <TableRow key={String(r.id)}>
                          <TableCell>{courseTitle(r.course_id)}</TableCell>
                          <TableCell>
                            {emp
                              ? `${String(emp.first_name ?? '')} ${String(emp.last_name ?? '')}`.trim()
                              : String(r.employee_id ?? '')}
                          </TableCell>
                          <TableCell>{String(r.status ?? '')}</TableCell>
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

      <Dialog open={courseOpen} onOpenChange={setCourseOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newCourse')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('courseTitle')} value={courseForm.title} onChange={(e) => setCourseForm((f) => ({ ...f, title: e.target.value }))} />
            <Textarea placeholder={t('description')} value={courseForm.description} onChange={(e) => setCourseForm((f) => ({ ...f, description: e.target.value }))} />
            <LocaleDatePicker value={courseForm.start_date} onChange={(v) => setCourseForm((f) => ({ ...f, start_date: v }))} />
            <LocaleDatePicker value={courseForm.end_date} onChange={(v) => setCourseForm((f) => ({ ...f, end_date: v }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCourseOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveCourse()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={sessionOpen} onOpenChange={setSessionOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newSession')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={sessionForm.course_id || undefined} onValueChange={(v) => setSessionForm((f) => ({ ...f, course_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('trainingCourses')} /></SelectTrigger>
              <SelectContent>
                {courses.map((c) => (
                  <SelectItem key={String(c.id)} value={String(c.id)}>{String(c.title ?? c.name ?? '')}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input placeholder={t('sessionTitle')} value={sessionForm.title} onChange={(e) => setSessionForm((f) => ({ ...f, title: e.target.value }))} />
            <Input type="datetime-local" value={sessionForm.starts_at} onChange={(e) => setSessionForm((f) => ({ ...f, starts_at: e.target.value }))} />
            <Input type="datetime-local" value={sessionForm.ends_at} onChange={(e) => setSessionForm((f) => ({ ...f, ends_at: e.target.value }))} />
            <Input placeholder={t('location')} value={sessionForm.location} onChange={(e) => setSessionForm((f) => ({ ...f, location: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSessionOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveSession()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={enrollOpen} onOpenChange={setEnrollOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newEnrollment')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={enrollForm.course_id || undefined} onValueChange={(v) => setEnrollForm((f) => ({ ...f, course_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('trainingCourses')} /></SelectTrigger>
              <SelectContent>
                {courses.map((c) => (
                  <SelectItem key={String(c.id)} value={String(c.id)}>{String(c.title ?? c.name ?? '')}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={enrollForm.employee_id || undefined} onValueChange={(v) => setEnrollForm((f) => ({ ...f, employee_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('employee')} /></SelectTrigger>
              <SelectContent>
                {employees.map((e) => (
                  <SelectItem key={String(e.id)} value={String(e.id)}>
                    {`${String(e.first_name ?? '')} ${String(e.last_name ?? '')}`.trim()}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEnrollOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveEnrollment()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
