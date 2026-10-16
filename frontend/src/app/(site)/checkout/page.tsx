import type { Metadata } from 'next';
import { Suspense } from 'react';
import { CheckoutView } from '@/features/shop/CheckoutView';

export const dynamic = 'force-dynamic';
export const metadata: Metadata = { title: 'تسویه حساب', robots: { index: false, follow: false } };

export default function Page() {
  return (
    <Suspense fallback={<div className="min-h-[50vh]" />}>
      <CheckoutView />
    </Suspense>
  );
}
