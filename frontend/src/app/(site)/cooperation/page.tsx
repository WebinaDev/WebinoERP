import { getTranslations } from 'next-intl/server';
import { ConsultationForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export default async function CooperationPage() {
  const t = await getTranslations();
  return (
    <SitePage kicker={t('site.nav.company')} title={t('site.nav.cooperation')} lead={t('site.page.cooperationLead')}>
      <ConsultationForm source="cooperation" title={t('common.cooperationTitle')} submitLabel={t('common.cooperationSubmit')} />
    </SitePage>
  );
}
