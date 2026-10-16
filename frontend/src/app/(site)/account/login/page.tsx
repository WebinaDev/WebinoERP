import type { Metadata } from 'next';
import { Suspense } from 'react';
import { LoginView } from '@/features/shop/LoginView';

export const dynamic = 'force-dynamic';
export const metadata: Metadata = { title: 'ورود و ثبت‌نام', robots: { index: false, follow: false } };

export default function Page() {
  return (
    <Suspense fallback={<div className="min-h-[50vh]" />}>
      <LoginView />
    </Suspense>
  );
}
