import { getTranslations } from 'next-intl/server';
import { ConsultationForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export default async function ConsultationPage() {
  const t = await getTranslations();
  return (
    <SitePage kicker={t('site.nav.freeConsultation')} title={t('site.nav.consultation')} lead={t('site.page.consultationLead')}>
      <ConsultationForm source="consultation" />
    </SitePage>
  );
}
