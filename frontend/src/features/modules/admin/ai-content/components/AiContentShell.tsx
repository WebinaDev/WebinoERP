'use client';

import type { ReactNode } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { dashboardHref } from '@/lib/route-resolver';

const LINKS = [
  { path: 'ai-content', key: 'overview' as const },
  { path: 'ai-content/jobs', key: 'jobs' as const },
  { path: 'ai-content/calendar', key: 'calendar' as const },
  { path: 'ai-content/products', key: 'products' as const },
  { path: 'ai-content/titles', key: 'titles' as const },
  { path: 'ai-content/pages', key: 'pages' as const },
  { path: 'ai-content/taxonomies', key: 'taxonomies' as const },
  { path: 'ai-content/attributes', key: 'attributes' as const },
  { path: 'ai-content/settings', key: 'settings' as const },
];

export function AiContentShell({
  children,
  active,
}: {
  children: ReactNode;
  active?: string;
}) {
  const t = useTranslations('nav.erp.aiContent');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';

  return (
    <div className="space-y-4 text-start">
      <div className="flex flex-wrap gap-2">
        {LINKS.map((item) => (
          <Button key={item.path} asChild size="sm" variant={active === item.key ? 'default' : 'outline'}>
            <Link href={dashboardHref(locale, item.path)}>{t(item.key)}</Link>
          </Button>
        ))}
      </div>
      {children}
    </div>
  );
}
