import { getTranslations } from 'next-intl/server';
import Link from 'next/link';
import { siteHref } from '@/lib/public-api-server';
import { Button } from '@/components/ui/button';

export default async function PricingPage({ params }: { params?: Promise<Record<string, string>> }) {
  const t = await getTranslations();

  return (
    <div className="container mx-auto px-4 py-12 text-center">
      <h1 className="text-3xl font-bold">{t('auto._site__pricing_page.s_a3e6d8e5')}</h1>
      <p className="text-muted-foreground mx-auto mt-4 max-w-lg">{t('auto._site__pricing_page.s_d5da6303')}</p>
      <Button asChild className="mt-8 bg-[#0066FF] hover:bg-[#0052cc]">
        <Link href={siteHref(undefined, 'consultation')}>{t('auto._site__pricing_page.s_9ae4aff1')}</Link>
      </Button>
    </div>
  );
}
