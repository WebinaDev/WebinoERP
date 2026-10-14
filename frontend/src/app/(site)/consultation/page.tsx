import { getLocale, getTranslations } from 'next-intl/server';
import { loadPublishedPage, StorefrontDocument } from '@/builder/public-document';
import { prefetchCorporate } from '@/themes/webina-corporate-v1/builder/prefetch';
import { ConsultationForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

export async function generateMetadata() {
  const locale = await getLocale();
  if (locale !== 'fa') return {};
  return {
    title: 'جلسه مشاوره تخصصی توسعه کسب‌وکار',
    description: 'یک جلسه شفاف برای بررسی وضعیت فعلی کسب‌وکار شما و طراحی مسیر رشد. جلسه ۳۰ تا ۴۵ دقیقه‌ای و بدون تعهد خرید.',
  };
}

export default async function ConsultationPage() {
  const t = await getTranslations();
  const locale = await getLocale();
  // Built in the page builder (seeded by CorporatePageDocuments); the classic form is the fallback.
  const published = locale === 'fa' ? await loadPublishedPage('consultation') : null;
  if (published) return <StorefrontDocument document={published} themeClass="webina-builder" runtime={{ prefetch: await prefetchCorporate(published) }} />;
  return (
    <SitePage kicker={t('site.nav.freeConsultation')} title={t('site.nav.consultation')} lead={t('site.page.consultationLead')}>
      <ConsultationForm source="consultation" />
    </SitePage>
  );
}
