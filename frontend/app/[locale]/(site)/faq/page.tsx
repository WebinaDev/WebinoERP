import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

type FaqItem = { id: number; question: string; answer: string; group?: string | null };

export default async function FaqPage() {
  let items: FaqItem[] = [];
  try {
    const res = await apiServer<{ data: FaqItem[] }>('/v1/public/faq');
    items = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">سوالات متداول</h1>
      <dl className="mt-8 space-y-6">
        {items.map((item) => (
          <div key={item.id} className="rounded-xl border p-5">
            <dt className="font-semibold">{item.question}</dt>
            <dd className="text-muted-foreground mt-2 text-sm">{item.answer}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
