import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type FaqItem = { id: number; question: string; answer: string; group?: string | null };

export default async function FaqPage() {
  const t = await getTranslations();
  let items: FaqItem[] = [];
  try {
    const res = await apiServer<{ data: FaqItem[] }>('/v1/public/faq');
    items = res.data ?? [];
  } catch {
    items = [];
  }
  const fallback = [1, 2, 3, 4, 5].map((n) => ({
    id: n,
    question: t(`site.landing.faq.q${n}`),
    answer: t(`site.landing.faq.a${n}`),
    group: null,
  }));
  const rows = items.length ? items : fallback;

  return (
    <SitePage kicker={t('site.nav.resources')} title={t('site.nav.faq')} lead={t('site.page.faqLead')}>
      {items.length ? null : <EmptyNote>{t('site.page.faqCmsNote')}</EmptyNote>}
      <div className="faq mt-4">
        {rows.map((item) => (
          <details key={item.id} open>
            <summary>{item.question}</summary>
            <dd>{item.answer}</dd>
          </details>
        ))}
      </div>
    </SitePage>
  );
}
