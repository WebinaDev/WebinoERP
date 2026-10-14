import Link from 'next/link';
import { notFound } from 'next/navigation';
import { apiServer } from '@/lib/public-api-server';
import { SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type Course = {
  title: string; description?: string | null; level?: string | null; duration?: string | null; instructor?: string | null;
  outcomes?: string | null; enroll_url?: string | null; is_free?: boolean;
  lessons?: { title: string; slug: string; summary?: string | null; content?: string | null; chapter_id?: number | null }[];
  chapters?: { id: number; title: string; summary?: string | null }[];
};

async function load(slug: string): Promise<Course | null> {
  try {
    return (await apiServer<{ data: Course }>(`/v1/public/academy/${encodeURIComponent(slug)}`)).data;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const c = await load((await params).slug);
  return c ? { title: c.title, description: c.description?.split('\n')[0] } : {};
}

export default async function AcademyCoursePage({ params }: { params: Promise<{ slug: string }> }) {
  const course = await load((await params).slug);
  if (!course) notFound();
  const outcomes = (course.outcomes ?? '').split('\n').map((s) => s.trim()).filter(Boolean);
  const facts = [['سطح', course.level], ['مدت', course.duration], ['مدرس / رویکرد', course.instructor], ['هزینه', course.is_free ? 'رایگان' : null]].filter((f) => f[1]);
  const enroll = course.enroll_url || '/consultation';
  const lessons = course.lessons ?? [];
  const chapters = course.chapters ?? [];
  const groups = chapters.length
    ? [...chapters.map((c) => ({ title: c.title, summary: c.summary, lessons: lessons.filter((l) => l.chapter_id === c.id) })), { title: '', summary: null, lessons: lessons.filter((l) => !chapters.some((c) => c.id === l.chapter_id)) }].filter((g) => g.lessons.length)
    : [{ title: '', summary: null, lessons }];
  return (
    <SitePage kicker="آکادمی" title={course.title} lead={course.description?.split('\n')[0] || undefined}>
      {facts.length ? <dl className="mb-8 flex flex-wrap gap-6 text-sm">{facts.map(([k, v]) => <div key={k}><dt className="opacity-60">{k}</dt><dd className="font-bold">{v}</dd></div>)}</dl> : null}
      {outcomes.length ? (
        <section className="mb-10">
          <h2 className="mb-3 text-xl font-extrabold">بعد از این دوره می‌توانید</h2>
          <ul className="list-disc space-y-1 ps-5">{outcomes.map((o) => <li key={o}>{o}</li>)}</ul>
        </section>
      ) : null}
      {course.lessons?.length ? (
        <section className="mb-10">
          <h2 className="mb-3 text-xl font-extrabold">سرفصل‌ها</h2>
          {groups.map((g, gi) => (
            <div key={gi} className="mb-6">
              {g.title ? <h3 className="mb-1 text-lg font-bold">فصل {gi + 1}: {g.title}</h3> : null}
              {g.summary ? <p className="mb-3 text-sm opacity-75">{g.summary}</p> : null}
              <ol className="space-y-3">
                {g.lessons.map((l, i) => (
                  <li key={l.slug} className="panel">
                    <p className="text-xs font-bold text-[var(--saffron-deep)]">{g.title ? `${gi + 1}.${i + 1}` : String(i + 1).padStart(2, '0')}</p>
                    <h4 className="font-bold">{l.title}</h4>
                    {l.summary || l.content ? <p className="mt-1 text-sm opacity-75">{(l.summary || l.content || '').replace(/<[^>]+>/g, '').slice(0, 220)}</p> : null}
                  </li>
                ))}
              </ol>
            </div>
          ))}
        </section>
      ) : null}
      <Link href={enroll} className="inline-block rounded-full bg-[var(--wb-primary,#0054ff)] px-6 py-3 font-bold text-white">{course.enroll_url ? 'ثبت‌نام در دوره' : 'هماهنگی و ثبت‌نام'}</Link>
    </SitePage>
  );
}
