import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
export const revalidate = 60;

type AcademyCourse = {
  title: string
  description?: string | null
  lessons?: { title: string; slug: string }[]
}

export default async function AcademyCoursePage({ params }: { params: Promise<{ slug: string }> }) {
  const t = await getTranslations();

  const { slug } = await params;

  let course: AcademyCourse | null = null;
  try { const res = await apiServer<{ data: AcademyCourse }>(`/v1/public/academy/${slug}`); course = res.data; } catch {}
  if (!course) return <div className="container mx-auto px-4 py-12">{t('auto.academy__slug__page.s_d45e5bd4')}</div>;
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{course.title}</h1>
      {course.description ? <p className="mt-4 text-muted-foreground">{course.description}</p> : null}
      <ul className="mt-8 space-y-2">{course.lessons?.map((l) => <li key={l.slug} className="rounded border px-4 py-2">{l.title}</li>)}</ul>
    </div>
  );
}
