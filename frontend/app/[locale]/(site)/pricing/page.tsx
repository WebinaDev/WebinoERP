import Link from 'next/link';
import { siteHref } from '@/src/lib/api-server';
import { Button } from '@/components/ui/button';

export default function PricingPage({ params: { locale } }: { params: { locale: string } }) {
  return (
    <div className="container mx-auto px-4 py-12 text-center">
      <h1 className="text-3xl font-bold">تعرفه خدمات</h1>
      <p className="text-muted-foreground mx-auto mt-4 max-w-lg">برای دریافت تعرفه اختصاصی با ما تماس بگیرید.</p>
      <Button asChild className="mt-8 bg-[#0066FF] hover:bg-[#0052cc]">
        <Link href={siteHref(undefined, 'consultation')}>درخواست مشاوره</Link>
      </Button>
    </div>
  );
}
