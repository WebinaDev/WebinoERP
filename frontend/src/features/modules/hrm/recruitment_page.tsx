'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
  getApplicants,
  getInterviews,
  getJobPostings,
  hireApplicant,
  saveApplicant,
  saveInterview,
  saveJobPosting,
} from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

const PIPELINE_STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'] as const;

export function RecruitmentPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [tab, setTab] = useState('postings');
  const [postings, setPostings] = useState<Record<string, unknown>[]>([]);
  const [applicants, setApplicants] = useState<Record<string, unknown>[]>([]);
  const [interviews, setInterviews] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [postingOpen, setPostingOpen] = useState(false);
  const [applicantOpen, setApplicantOpen] = useState(false);
  const [hireOpen, setHireOpen] = useState(false);
  const [interviewOpen, setInterviewOpen] = useState(false);
  const [hireTarget, setHireTarget] = useState<Record<string, unknown> | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [postingForm, setPostingForm] = useState({ title: '', department: '', description: '', status: 'open' });
  const [applicantForm, setApplicantForm] = useState({
    job_posting_id: '',
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    resume_notes: '',
  });
  const [hireForm, setHireForm] = useState({ employee_code: '', department: '', position: '' });
  const [interviewForm, setInterviewForm] = useState({
    applicant_id: '',
    scheduled_at: '',
    interviewer: '',
    notes: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [p, a, i] = await Promise.all([getJobPostings(), getApplicants(), getInterviews()]);
      setPostings(normalizeListPayload(p as { data?: unknown }));
      setApplicants(normalizeListPayload(a as { data?: unknown }));
      setInterviews(normalizeListPayload(i as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const postingTitle = (id: unknown): string =>
    String(postings.find((p) => String(p.id) === String(id))?.title ?? id ?? '');

  const handleSavePosting = async () => {
    if (!postingForm.title.trim()) return;
    setSubmitting(true);
    try {
      await saveJobPosting({
        title: postingForm.title.trim(),
        department: postingForm.department.trim() || null,
        description: postingForm.description.trim() || null,
        status: postingForm.status,
      });
      setPostingOpen(false);
      setPostingForm({ title: '', department: '', description: '', status: 'open' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveApplicant = async () => {
    if (!applicantForm.job_posting_id || !applicantForm.first_name.trim() || !applicantForm.last_name.trim()) return;
    setSubmitting(true);
    try {
      await saveApplicant({
        job_posting_id: Number(applicantForm.job_posting_id),
        first_name: applicantForm.first_name.trim(),
        last_name: applicantForm.last_name.trim(),
        email: applicantForm.email.trim() || null,
        mobile: applicantForm.mobile.trim() || null,
        resume_notes: applicantForm.resume_notes.trim() || null,
      });
      setApplicantOpen(false);
      setApplicantForm({ job_posting_id: '', first_name: '', last_name: '', email: '', mobile: '', resume_notes: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const openHire = (a: Record<string, unknown>) => {
    setHireTarget(a);
    setHireForm({
      employee_code: '',
      department: String((a.job_posting as Record<string, unknown> | undefined)?.department ?? ''),
      position: String((a.job_posting as Record<string, unknown> | undefined)?.title ?? ''),
    });
    setHireOpen(true);
  };

  const handleHire = async () => {
    if (!hireTarget || !hireForm.employee_code.trim()) return;
    setSubmitting(true);
    try {
      await hireApplicant(hireTarget.id as number, {
        employee_code: hireForm.employee_code.trim(),
        department: hireForm.department.trim() || null,
        position: hireForm.position.trim() || null,
      });
      setHireOpen(false);
      setHireTarget(null);
      setSuccess(t('hired'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveInterview = async () => {
    if (!interviewForm.applicant_id || !interviewForm.scheduled_at) return;
    setSubmitting(true);
    try {
      await saveInterview({
        applicant_id: Number(interviewForm.applicant_id),
        scheduled_at: interviewForm.scheduled_at,
        interviewer: interviewForm.interviewer.trim() || null,
        notes: interviewForm.notes.trim() || null,
      });
      setInterviewOpen(false);
      setInterviewForm({ applicant_id: '', scheduled_at: '', interviewer: '', notes: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const actions =
    tab === 'postings' ? (
      <Button onClick={() => setPostingOpen(true)}>{t('newPosting')}</Button>
    ) : tab === 'applicants' ? (
      <Button onClick={() => setApplicantOpen(true)}>{t('newApplicant')}</Button>
    ) : (
      <Button onClick={() => setInterviewOpen(true)}>{t('newInterview')}</Button>
    );

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.recruitment')} actions={actions} {...layoutProps}>
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="postings">{t('recruitmentPostings')}</TabsTrigger>
          <TabsTrigger value="applicants">{t('recruitmentApplicants')}</TabsTrigger>
          <TabsTrigger value="pipeline">{t('pipeline')}</TabsTrigger>
          <TabsTrigger value="interviews">{t('interviews')}</TabsTrigger>
        </TabsList>

        <TabsContent value="postings">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('postingTitle')}</TableHead>
                    <TableHead>{t('department')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow><TableCell colSpan={3}><PageLoadingState /></TableCell></TableRow>
                  ) : postings.length === 0 ? (
                    <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    postings.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.title ?? '')}</TableCell>
                        <TableCell>{String(r.department ?? '')}</TableCell>
                        <TableCell>{String(r.status ?? '')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="applicants">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('firstName')}</TableHead>
                    <TableHead>{t('lastName')}</TableHead>
                    <TableHead>{t('postingTitle')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {applicants.length === 0 ? (
                    <TableRow><TableCell colSpan={5}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    applicants.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.first_name ?? r.name ?? '')}</TableCell>
                        <TableCell>{String(r.last_name ?? '')}</TableCell>
                        <TableCell>{postingTitle(r.job_posting_id)}</TableCell>
                        <TableCell>{String(r.status ?? '')}</TableCell>
                        <TableCell>
                          {String(r.status) !== 'hired' ? (
                            <Button size="sm" variant="outline" onClick={() => openHire(r)}>{t('hire')}</Button>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="pipeline">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('firstName')}</TableHead>
                    <TableHead>{t('postingTitle')}</TableHead>
                    <TableHead>{t('stage')}</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {applicants.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    applicants.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{`${String(r.first_name ?? '')} ${String(r.last_name ?? '')}`.trim()}</TableCell>
                        <TableCell>{postingTitle(r.job_posting_id)}</TableCell>
                        <TableCell>
                          <span className="text-sm">
                            {PIPELINE_STAGES.includes(String(r.status) as (typeof PIPELINE_STAGES)[number])
                              ? t(`stages.${String(r.status) as (typeof PIPELINE_STAGES)[number]}`)
                              : String(r.status ?? '')}
                          </span>
                        </TableCell>
                        <TableCell>
                          {String(r.status) !== 'hired' ? (
                            <Button size="sm" variant="outline" onClick={() => openHire(r)}>{t('hire')}</Button>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="interviews">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('applicant')}</TableHead>
                    <TableHead>{t('scheduledAt')}</TableHead>
                    <TableHead>{t('interviewer')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {interviews.length === 0 ? (
                    <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    interviews.map((r) => {
                      const a = r.applicant as Record<string, unknown> | undefined;
                      return (
                        <TableRow key={String(r.id)}>
                          <TableCell>
                            {a ? `${String(a.first_name ?? '')} ${String(a.last_name ?? '')}`.trim() : String(r.applicant_id ?? '')}
                          </TableCell>
                          <TableCell>{String(r.scheduled_at ?? '')}</TableCell>
                          <TableCell>{String(r.interviewer ?? '')}</TableCell>
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

      <Dialog open={postingOpen} onOpenChange={setPostingOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newPosting')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('postingTitle')} value={postingForm.title} onChange={(e) => setPostingForm((f) => ({ ...f, title: e.target.value }))} />
            <Input placeholder={t('department')} value={postingForm.department} onChange={(e) => setPostingForm((f) => ({ ...f, department: e.target.value }))} />
            <Textarea placeholder={t('description')} value={postingForm.description} onChange={(e) => setPostingForm((f) => ({ ...f, description: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPostingOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSavePosting()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={applicantOpen} onOpenChange={setApplicantOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newApplicant')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={applicantForm.job_posting_id || undefined} onValueChange={(v) => setApplicantForm((f) => ({ ...f, job_posting_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('postingTitle')} /></SelectTrigger>
              <SelectContent>
                {postings.map((p) => (
                  <SelectItem key={String(p.id)} value={String(p.id)}>{String(p.title ?? '')}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className="grid grid-cols-2 gap-2">
              <Input placeholder={t('firstName')} value={applicantForm.first_name} onChange={(e) => setApplicantForm((f) => ({ ...f, first_name: e.target.value }))} />
              <Input placeholder={t('lastName')} value={applicantForm.last_name} onChange={(e) => setApplicantForm((f) => ({ ...f, last_name: e.target.value }))} />
            </div>
            <Input type="email" dir="ltr" placeholder={t('email')} value={applicantForm.email} onChange={(e) => setApplicantForm((f) => ({ ...f, email: e.target.value }))} />
            <Input dir="ltr" placeholder={t('mobile')} value={applicantForm.mobile} onChange={(e) => setApplicantForm((f) => ({ ...f, mobile: e.target.value }))} />
            <Textarea placeholder={t('resumeNotes')} value={applicantForm.resume_notes} onChange={(e) => setApplicantForm((f) => ({ ...f, resume_notes: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setApplicantOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveApplicant()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={hireOpen} onOpenChange={setHireOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('hire')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('staffCode')} value={hireForm.employee_code} onChange={(e) => setHireForm((f) => ({ ...f, employee_code: e.target.value }))} />
            <Input placeholder={t('department')} value={hireForm.department} onChange={(e) => setHireForm((f) => ({ ...f, department: e.target.value }))} />
            <Input placeholder={t('position')} value={hireForm.position} onChange={(e) => setHireForm((f) => ({ ...f, position: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setHireOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleHire()} disabled={submitting}>{t('hire')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={interviewOpen} onOpenChange={setInterviewOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newInterview')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={interviewForm.applicant_id || undefined} onValueChange={(v) => setInterviewForm((f) => ({ ...f, applicant_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('applicant')} /></SelectTrigger>
              <SelectContent>
                {applicants.map((a) => (
                  <SelectItem key={String(a.id)} value={String(a.id)}>
                    {`${String(a.first_name ?? '')} ${String(a.last_name ?? '')}`.trim()}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input type="datetime-local" value={interviewForm.scheduled_at} onChange={(e) => setInterviewForm((f) => ({ ...f, scheduled_at: e.target.value }))} />
            <Input placeholder={t('interviewer')} value={interviewForm.interviewer} onChange={(e) => setInterviewForm((f) => ({ ...f, interviewer: e.target.value }))} />
            <Textarea placeholder={t('notes')} value={interviewForm.notes} onChange={(e) => setInterviewForm((f) => ({ ...f, notes: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setInterviewOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveInterview()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
