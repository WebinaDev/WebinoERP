import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

export default async function AboutPage() {
  let page: { title_fa?: string; body_fa?: string | null } | null = null;
  try {
    const res = await apiServer<{ data: typeof page }>('/v1/public/pages/about');
    page = res.data;
  } catch {
    /* empty */
  }
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{page?.title_fa ?? 'درباره ما'}</h1>
      <div
        className="prose mt-8 max-w-none"
        dangerouslySetInnerHTML={{ __html: page?.body_fa ?? '<p>وبینا شریک دیجیتال کسب‌وکارهاست.</p>' }}
      />
    </div>
  );
}
