'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { SettingsPageView } from '@/components/dashboard/pages/settings-view';
import { Button } from '@/components/ui/button';
import { dashboardHref } from '@/lib/route-resolver';
import type { SettingsHubId } from './settings-hub-config';

type Props = { hub: SettingsHubId; tab?: string };

export function SettingsHubPageView({ hub, tab }: Props) {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Link href={dashboardHref(locale, 'admin/settings')} className="hover:text-foreground">
          {t('settings.hub.title')}
        </Link>
        <span>/</span>
        <span className="text-foreground">{t(`settings.hub.${hub}`)}</span>
      </div>
      <SettingsPageView hub={hub} initialTab={tab} />
    </div>
  );
}

export function SettingsGeneralPage({ tab }: { tab?: string }) {
  return <SettingsHubPageView hub="general" tab={tab} />;
}

export function SettingsProjectsPage({ tab }: { tab?: string }) {
  return <SettingsHubPageView hub="projects" tab={tab} />;
}

export function SettingsCrmPage({ tab }: { tab?: string }) {
  return <SettingsHubPageView hub="crm" tab={tab} />;
}

export function SettingsBotsPage({ tab }: { tab?: string }) {
  return <SettingsHubPageView hub="bots" tab={tab} />;
}

export function SettingsAccountingPage({ tab }: { tab?: string }) {
  return <SettingsHubPageView hub="accounting" tab={tab} />;
}
