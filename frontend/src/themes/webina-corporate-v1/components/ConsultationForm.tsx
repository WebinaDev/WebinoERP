'use client';

import { useTranslations } from 'next-intl';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';

const API_BASE = process.env.NEXT_PUBLIC_API_URL?.trim() || '/api';

type FormProps = {
  source: string;
  title?: string;
  submitLabel?: string;
};

export function ConsultationForm({ source, title, submitLabel }: FormProps) {
  const t = useTranslations();
  const resolvedTitle = title ?? t('auto.themes_webina_corporate_v1_ConsultationForm.s_9ae4aff1');
  const resolvedSubmit =
    submitLabel ?? t('auto.themes_webina_corporate_v1_ConsultationForm.s_fb836859');
  const [pending, setPending] = useState(false);

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setPending(true);
    const fd = new FormData(e.currentTarget);
    try {
      const res = await fetch(`${API_BASE}/v1/public/consultations`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: fd.get('name'),
          email: fd.get('email'),
          phone: fd.get('phone'),
          company: fd.get('company'),
          message: fd.get('message'),
          subject: resolvedTitle,
          source,
        }),
      });
      if (!res.ok) throw new Error(t('auto.themes_webina_corporate_v1_ConsultationForm.s_38719c26'));
      toast.success(t('auto.themes_webina_corporate_v1_ConsultationForm.s_edd88a15'));
      e.currentTarget.reset();
    } catch {
      toast.error(t('auto.themes_webina_corporate_v1_ConsultationForm.s_b1505eb0'));
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="mx-auto max-w-lg space-y-4 rounded-xl border p-6">
      <h2 className="text-xl font-semibold">{resolvedTitle}</h2>
      <div className="space-y-2">
        <Label htmlFor="name">{t('auto.themes_webina_corporate_v1_ConsultationForm.s_45dd06ba')}</Label>
        <Input id="name" name="name" required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="email">{t('auto.themes_webina_corporate_v1_ConsultationForm.s_f1ad423d')}</Label>
        <Input id="email" name="email" type="email" required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="phone">{t('auto.themes_webina_corporate_v1_ConsultationForm.s_ddeae4dd')}</Label>
        <Input id="phone" name="phone" />
      </div>
      <div className="space-y-2">
        <Label htmlFor="company">{t('auto.themes_webina_corporate_v1_ConsultationForm.s_bb7fa7e8')}</Label>
        <Input id="company" name="company" />
      </div>
      <div className="space-y-2">
        <Label htmlFor="message">{t('auto.themes_webina_corporate_v1_ConsultationForm.s_8cd47c67')}</Label>
        <Textarea id="message" name="message" rows={4} />
      </div>
      <Button type="submit" disabled={pending} className="w-full bg-[#0066FF] hover:bg-[#0052cc]">
        {pending ? t('auto.themes_webina_corporate_v1_ConsultationForm.s_775273e4') : resolvedSubmit}
      </Button>
    </form>
  );
}

export function ProposalForm() {
  const t = useTranslations();
  return (
    <ConsultationForm
      source="proposal"
      title={t('auto.themes_webina_corporate_v1_ConsultationForm.s_7e02e335')}
      submitLabel={t('auto.themes_webina_corporate_v1_ConsultationForm.s_7e02e335')}
    />
  );
}

export function ContactForm() {
  const t = useTranslations();
  return (
    <ConsultationForm
      source="contact"
      title={t('auto.themes_webina_corporate_v1_ConsultationForm.s_a4a1bacc')}
      submitLabel={t('auto.themes_webina_corporate_v1_ConsultationForm.s_a2c91b74')}
    />
  );
}
