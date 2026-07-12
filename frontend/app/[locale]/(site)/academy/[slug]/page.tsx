import { apiServer } from '@/src/lib/api-server';
export const revalidate = 60;
export default async function AcademyCoursePage({ params: { slug } }: { params: { slug: string } }) {
  let course: { title: string; description?: string | null; lessons?: { title: string; slug: string }[] } | null = null;
  try { const res = await apiServer<{ data: typeof course }>(`/v1/public/academy/${slug}`); course = res.data; } catch {}
  if (!course) return <div className="container mx-auto px-4 py-12">یافت نشد.</div>;
  return (
    <div className="container mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-3xl font-bold">{course.title}</h1>
      {course.description ? <p className="mt-4 text-muted-foreground">{course.description}</p> : null}
      <ul className="mt-8 space-y-2">{course.lessons?.map((l) => <li key={l.slug} className="rounded border px-4 py-2">{l.title}</li>)}</ul>
    </div>
  );
}
