import { getTranslations } from 'next-intl/server';
import { ConsultationForm } from '@/themes/webina-corporate-v1/components/ConsultationForm';

export default async function CooperationPage() {
  const t = await getTranslations();
  return (
    <div className="container mx-auto px-4 py-12">
      <ConsultationForm
        source="cooperation"
        title={t('common.cooperationTitle')}
        submitLabel={t('common.cooperationSubmit')}
      />
    </div>
  );
}
