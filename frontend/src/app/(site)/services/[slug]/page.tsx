import { apiServer } from '@/lib/public-api-server';
export const revalidate = 60;

type ServiceDetail = { title: string; excerpt?: string | null; body?: string | null };

export default async function ServicePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;

  let service: ServiceDetail | null = null;
  try { const res = await apiServer<{ data: ServiceDetail }>(`/v1/public/services/${slug}`); service = res.data; } catch {}
  if (!service) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{service.title}</h1>
      {service.excerpt ? <p className="text-muted-foreground mt-4">{service.excerpt}</p> : null}
      <div className="prose mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: service.body ?? '' }} />
    </div>
  );
}
