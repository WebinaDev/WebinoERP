import { getTranslations } from 'next-intl/server';
import { ProposalForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export default async function ProposalPage() {
  const t = await getTranslations();
  return (
    <SitePage kicker={t('site.nav.company')} title={t('site.nav.proposal')} lead={t('site.page.proposalLead')}>
      <ProposalForm />
    </SitePage>
  );
}
