import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

type TestimonialItem = { id: number; author: string; quote: string; company?: string | null; rating?: number | null };

export default async function TestimonialsPage() {
  let items: TestimonialItem[] = [];
  try {
    const res = await apiServer<{ data: TestimonialItem[] }>('/v1/public/testimonials');
    items = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">دیدگاه مشتریان</h1>
      <div className="mt-8 grid gap-6 md:grid-cols-2">
        {items.map((t) => (
          <blockquote key={t.id} className="rounded-xl border p-5 text-sm">
            &ldquo;{t.quote}&rdquo;
            <footer className="mt-3 font-medium">
              {t.author}
              {t.company ? ` — ${t.company}` : ''}
            </footer>
          </blockquote>
        ))}
      </div>
    </div>
  );
}
