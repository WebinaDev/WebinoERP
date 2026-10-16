import type { Metadata } from 'next';
import { Suspense } from 'react';
import { CheckoutResult } from '@/features/shop/CheckoutResult';

export const dynamic = 'force-dynamic';
export const metadata: Metadata = { title: 'نتیجه پرداخت', robots: { index: false, follow: false } };

export default function Page() {
  return (
    <Suspense fallback={<div className="min-h-[50vh]" />}>
      <CheckoutResult />
    </Suspense>
  );
}
