'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost/api';

type FormProps = {
  source: string;
  title?: string;
  submitLabel?: string;
};

export function ConsultationForm({ source, title = 'درخواست مشاوره', submitLabel = 'ارسال درخواست' }: FormProps) {
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
          subject: title,
          source,
        }),
      });
      if (!res.ok) throw new Error('خطا در ارسال');
      toast.success('درخواست شما ثبت شد. به زودی با شما تماس می‌گیریم.');
      e.currentTarget.reset();
    } catch {
      toast.error('ارسال ناموفق بود. لطفاً دوباره تلاش کنید.');
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="mx-auto max-w-lg space-y-4 rounded-xl border p-6">
      <h2 className="text-xl font-semibold">{title}</h2>
      <div className="space-y-2">
        <Label htmlFor="name">نام</Label>
        <Input id="name" name="name" required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="email">ایمیل</Label>
        <Input id="email" name="email" type="email" required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="phone">تلفن</Label>
        <Input id="phone" name="phone" />
      </div>
      <div className="space-y-2">
        <Label htmlFor="company">شرکت</Label>
        <Input id="company" name="company" />
      </div>
      <div className="space-y-2">
        <Label htmlFor="message">پیام</Label>
        <Textarea id="message" name="message" rows={4} />
      </div>
      <Button type="submit" disabled={pending} className="w-full bg-[#0066FF] hover:bg-[#0052cc]">
        {pending ? 'در حال ارسال…' : submitLabel}
      </Button>
    </form>
  );
}

export function ProposalForm() {
  return <ConsultationForm source="proposal" title="درخواست پروپوزال" submitLabel="درخواست پروپوزال" />;
}

export function ContactForm() {
  return <ConsultationForm source="contact" title="تماس با ما" submitLabel="ارسال پیام" />;
}
