import type { Metadata } from 'next';
import { Suspense } from 'react';
import { CartView } from '@/features/shop/CartView';

export const dynamic = 'force-dynamic';
export const metadata: Metadata = { title: 'سبد خرید', robots: { index: false, follow: false } };

export default function Page() {
  return (
    <Suspense fallback={<div className="min-h-[50vh]" />}>
      <CartView />
    </Suspense>
  );
}
