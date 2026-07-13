import { apiServer } from '@/src/lib/api-server';

export const revalidate = 60;

type TeamMember = { id: number; name: string; role?: string | null; bio?: string | null; photo_url?: string | null };

export default async function TeamPage() {
  let members: TeamMember[] = [];
  try {
    const res = await apiServer<{ data: TeamMember[] }>('/v1/public/team');
    members = res.data ?? [];
  } catch {
    /* empty */
  }

  return (
    <div className="container mx-auto px-4 py-12">
      <h1 className="text-3xl font-bold">تیم ما</h1>
      <div className="mt-8 grid gap-6 md:grid-cols-3">
        {members.map((m) => (
          <div key={m.id} className="rounded-xl border p-5">
            <h2 className="font-semibold">{m.name}</h2>
            {m.role ? <p className="text-[#0066FF] text-sm">{m.role}</p> : null}
            {m.bio ? <p className="text-muted-foreground mt-2 text-sm">{m.bio}</p> : null}
          </div>
        ))}
      </div>
    </div>
  );
}
