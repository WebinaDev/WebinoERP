import { getTranslations } from 'next-intl/server';
import { ContactForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export default async function ContactPage() {
  const t = await getTranslations();
  return (
    <SitePage kicker={t('site.nav.company')} title={t('site.nav.contact')} lead={t('site.page.contactLead')}>
      <ContactForm />
    </SitePage>
  );
}
