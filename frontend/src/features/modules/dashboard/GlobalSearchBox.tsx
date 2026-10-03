'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { Input } from '@/components/ui/input';
import { dashboardHref } from '@/lib/route-resolver';

type Hit = {
  id: number;
  name?: string;
  title?: string;
  subject?: string;
  first_name?: string;
  last_name?: string;
  topic?: string;
  account_id?: number;
};
type Payload = {
  accounts?: Hit[];
  contacts?: Hit[];
  deals?: Hit[];
  projects?: Hit[];
  tasks?: Hit[];
  leads?: Hit[];
};

export function GlobalSearchBox() {
  const t = useTranslations('suite');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<Payload | null>(null);

  async function search(value: string) {
    setQuery(value);
    if (value.trim().length < 2) {
      setHits(null);
      return;
    }
    const res = await apiClient.get('/v1/core/search', { params: { q: value.trim() } });
    setHits(unwrapData<Payload>(res));
  }

  const groups: { key: keyof Payload; href: (row: Hit) => string; label: (row: Hit) => string }[] = [
    { key: 'accounts', href: (row) => `crm/customers/${row.id}`, label: (row) => row.name || String(row.id) },
    { key: 'deals', href: () => 'crm/deals', label: (row) => row.name || String(row.id) },
    { key: 'projects', href: (row) => `pm/projects/${row.id}`, label: (row) => row.name || String(row.id) },
    { key: 'tasks', href: () => 'pm/tasks', label: (row) => row.title || String(row.id) },
    { key: 'leads', href: () => 'crm/leads', label: (row) => row.topic || [row.first_name, row.last_name].filter(Boolean).join(' ') || String(row.id) },
    { key: 'contacts', href: (row) => row.account_id ? `crm/customers/${row.account_id}` : 'crm/customers', label: (row) => [row.first_name, row.last_name].filter(Boolean).join(' ') || String(row.id) },
  ];

  const total = groups.reduce((sum, group) => sum + (hits?.[group.key]?.length ?? 0), 0);

  return (
    <div className="max-w-xl space-y-2">
      <label className="sr-only" htmlFor="global-search">{t('search')}</label>
      <Input
        id="global-search"
        value={query}
        placeholder={t('search')}
        onChange={(event) => void search(event.target.value)}
        onKeyDown={(event) => {
          if (event.key === 'Enter') void search(query);
        }}
      />
      {hits && total === 0 ? <p className="text-sm text-muted-foreground">{t('searchEmpty')}</p> : null}
      {hits && total > 0 ? (
        <div className="space-y-1 rounded-lg border bg-card p-2 text-sm">
          {groups.map((group) => (hits[group.key] ?? []).slice(0, 4).map((row) => (
            <Link key={`${group.key}-${row.id}`} className="block rounded px-2 py-1 hover:bg-muted" href={dashboardHref(locale, group.href(row))}>
              {group.label(row)}
            </Link>
          )))}
        </div>
      ) : null}
    </div>
  );
}
