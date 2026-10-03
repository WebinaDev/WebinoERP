import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type TestimonialItem = { id: number; author: string; quote: string; company?: string | null };

export default async function TestimonialsPage() {
  const t = await getTranslations();
  let items: TestimonialItem[] = [];
  try {
    const res = await apiServer<{ data: TestimonialItem[] }>('/v1/public/testimonials');
    items = res.data ?? [];
  } catch {
    items = [];
  }

  return (
    <SitePage kicker={t('site.nav.company')} title={t('site.home.testimonials')} lead={t('site.page.testimonialsLead')}>
      {items.length ? (
        <div className="card-grid cols-2">
          {items.map((item) => (
            <blockquote key={item.id} className="panel text-sm leading-7">
              «{item.quote}»
              <footer className="mt-3 font-bold">
                {item.author}
                {item.company ? <span className="mt-1 block text-xs font-normal text-[var(--muted)]">{item.company}</span> : null}
              </footer>
            </blockquote>
          ))}
        </div>
      ) : (
        <EmptyNote>{t('site.page.testimonialsEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
