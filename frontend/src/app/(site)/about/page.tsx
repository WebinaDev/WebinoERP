import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';

export const revalidate = 60;

type AboutPageData = { title_fa?: string; body_fa?: string | null };

export default async function AboutPage() {
  const t = await getTranslations();

  let page: AboutPageData | null = null;
  try {
    const res = await apiServer<{ data: AboutPageData }>('/v1/public/pages/about');
    page = res.data;
  } catch {
    /* empty */
  }
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{page?.title_fa ?? t('auto.remaining.s_02b1718b')}</h1>
      <div
        className="prose prose-invert mt-8 max-w-none"
        dangerouslySetInnerHTML={{ __html: page?.body_fa ?? `<p>${t('auto._site__about_page.s_904c7507')}</p>` }}
      />
    </div>
  );
}
