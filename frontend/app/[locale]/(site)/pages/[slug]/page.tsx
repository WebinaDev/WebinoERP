import { apiServer } from '@/src/lib/api-server';
export const revalidate = 60;

type CmsPageData = {
  title_fa?: string
  title_en?: string
  body_fa?: string | null
  body_en?: string | null
}

export default async function CmsPage({ params: { locale, slug } }: { params: { locale: string; slug: string } }) {
  let page: CmsPageData | null = null;
  try { const res = await apiServer<{ data: CmsPageData }>(`/v1/public/pages/${slug}`); page = res.data; } catch {}
  if (!page) return <div className="container mx-auto px-4 py-12">صفحه یافت نشد.</div>;
  const title = locale === 'en' && page.title_en ? page.title_en : page.title_fa ?? '';
  const body = locale === 'en' && page.body_en ? page.body_en : page.body_fa ?? '';
  return (
    <article className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{title}</h1>
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: body }} />
    </article>
  );
}
