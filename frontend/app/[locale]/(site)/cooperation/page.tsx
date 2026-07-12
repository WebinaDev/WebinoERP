import { ConsultationForm } from '@/src/themes/webina-corporate-v1/components/ConsultationForm';

export default function CooperationPage() {
  return (
    <div className="container mx-auto px-4 py-12">
      <ConsultationForm source="cooperation" title="همکاری با ما" submitLabel="ارسال درخواست همکاری" />
    </div>
  );
}
