import type { Metadata } from 'next';
import { Suspense } from 'react';
import { AccountView } from '@/features/shop/AccountView';

export const dynamic = 'force-dynamic';
export const metadata: Metadata = { title: 'حساب کاربری', robots: { index: false, follow: false } };

export default function Page() {
  return (
    <Suspense fallback={<div className="min-h-[50vh]" />}>
      <AccountView />
    </Suspense>
  );
}
