'use client';

import { useTranslations } from 'next-intl';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ERP_MODULES } from '@/lib/module-registry';
import { dashboardHref } from '@/lib/route-resolver';

const SETTINGS_HUBS = [
  { id: 'general', titleKey: 'settings.hub.general' },
  { id: 'projects', titleKey: 'settings.hub.projects' },
  { id: 'crm', titleKey: 'settings.hub.crm' },
  { id: 'bots', titleKey: 'settings.hub.bots' },
  { id: 'accounting', titleKey: 'settings.hub.accounting' },
] as const;

type Props = Record<string, never>;

export function SettingsHubPage(_props?: Props) {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold">{t('settings.hub.title')}</h2>
        <p className="text-sm text-muted-foreground">{t('settings.hub.subtitle')}</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {SETTINGS_HUBS.map((hub) => (
          <Link key={hub.id} href={dashboardHref(locale, `admin/settings/${hub.id}`)}>
            <Card className="h-full transition-colors hover:bg-muted/50">
              <CardHeader>
                <CardTitle className="text-base">{t(hub.titleKey)}</CardTitle>
                <CardDescription>{hub.id}</CardDescription>
              </CardHeader>
              <CardContent />
            </Card>
          </Link>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('settings.modules.title')}</CardTitle>
          <CardDescription>{t('settings.modules.subtitle')}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {ERP_MODULES.map((mod) => (
            <div key={mod.id} className="rounded-md border px-3 py-2 text-sm">
              <p className="font-medium">{t(mod.sidebarCategoryKey)}</p>
              <p className="text-xs text-muted-foreground">{mod.id}</p>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  );
}
