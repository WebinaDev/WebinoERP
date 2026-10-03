import { getTranslations } from 'next-intl/server';
import { apiServer } from '@/lib/public-api-server';
import { EmptyNote, SitePage } from '@/themes/webina-corporate-v1/components/SitePage';

export const revalidate = 60;

type TeamMember = { id: number; name: string; role?: string | null; bio?: string | null };

export default async function TeamPage() {
  const t = await getTranslations();
  let members: TeamMember[] = [];
  try {
    const res = await apiServer<{ data: TeamMember[] }>('/v1/public/team');
    members = res.data ?? [];
  } catch {
    members = [];
  }

  return (
    <SitePage kicker={t('site.nav.company')} title={t('site.nav.team')} lead={t('site.page.teamLead')}>
      {members.length ? (
        <div className="card-grid cols-3">
          {members.map((m) => (
            <article key={m.id} className="panel">
              <h2 className="font-bold">{m.name}</h2>
              {m.role ? <p className="mt-1 text-sm text-[var(--saffron-deep)]">{m.role}</p> : null}
              {m.bio ? <p className="muted mt-2 text-sm">{m.bio}</p> : null}
            </article>
          ))}
        </div>
      ) : (
        <EmptyNote>{t('site.page.teamEmpty')}</EmptyNote>
      )}
    </SitePage>
  );
}
