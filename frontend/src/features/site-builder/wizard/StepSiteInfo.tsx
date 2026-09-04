'use client';

import { useTranslations } from 'next-intl';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PlatformServer } from '@/lib/api/platform';
import { StepHeroArt } from './illustrations';

const SITE_TYPE_LABELS: Record<string, string> = {
  ecommerce: 'فروشگاه اینترنتی',
  magazine: 'مجله آموزشی',
  cafe: 'کافه و رستوران',
  resume: 'رزومه',
  corporate: 'شرکتی',
};

type Props = {
  siteName: string;
  slug: string;
  adminName: string;
  adminEmail: string;
  currency: string;
  siteTypeSlug: string;
  serverId: number | null;
  servers: PlatformServer[];
  previewDomain: string;
  onChange: (patch: Partial<{
    siteName: string;
    slug: string;
    adminName: string;
    adminEmail: string;
    currency: string;
    siteTypeSlug: string;
    serverId: number | null;
  }>) => void;
  onSiteName: (value: string) => void;
};

export function StepSiteInfo(props: Props) {
  const t = useTranslations('siteBuilder');
  const {
    siteName,
    slug,
    adminName,
    adminEmail,
    currency,
    siteTypeSlug,
    serverId,
    servers,
    previewDomain,
    onChange,
    onSiteName,
  } = props;

  return (
    <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]" data-testid="wizard-step-site-info">
      <div className="grid gap-4">
        <StepHeroArt step={4} className="max-w-none" />
        <div className="space-y-1">
          <h2 className="text-xl font-semibold">{t('stepSiteInfoTitle')}</h2>
          <p className="text-muted-foreground text-sm">{t('stepSiteInfoSubtitle')}</p>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="grid gap-2 sm:col-span-2">
            <Label>{t('siteName')}</Label>
            <Input value={siteName} onChange={(e) => onSiteName(e.target.value)} data-testid="site-name" />
          </div>
          <div className="grid gap-2">
            <Label>{t('slug')}</Label>
            <Input
              value={slug}
              onChange={(e) => onChange({ slug: e.target.value })}
              dir="ltr"
              className="font-mono"
              data-testid="site-slug"
            />
          </div>
          <div className="grid gap-2">
            <Label>{t('currency')}</Label>
            <Input
              value={currency}
              onChange={(e) => onChange({ currency: e.target.value })}
              dir="ltr"
            />
          </div>
          <div className="grid gap-2">
            <Label>{t('adminName')}</Label>
            <Input value={adminName} onChange={(e) => onChange({ adminName: e.target.value })} />
          </div>
          <div className="grid gap-2">
            <Label>{t('adminEmail')}</Label>
            <Input
              type="email"
              value={adminEmail}
              onChange={(e) => onChange({ adminEmail: e.target.value })}
              dir="ltr"
            />
          </div>
          <div className="grid gap-2 sm:col-span-2">
            <Label>{t('siteTypeSlug')}</Label>
            <select
              className="bg-background border-input h-10 rounded-md border px-3"
              value={siteTypeSlug}
              onChange={(e) => onChange({ siteTypeSlug: e.target.value })}
              data-testid="site-type-select"
            >
              {['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'].map((st) => (
                <option key={st} value={st}>
                  {SITE_TYPE_LABELS[st] ?? st}
                </option>
              ))}
              {siteTypeSlug &&
              !['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'].includes(siteTypeSlug) ? (
                <option value={siteTypeSlug}>{siteTypeSlug}</option>
              ) : null}
            </select>
          </div>
          <div className="grid gap-2 sm:col-span-2">
            <Label>
              {t('platformServer')} ({t('optional')})
            </Label>
            <select
              className="bg-background border-input h-10 rounded-md border px-3"
              value={serverId ?? ''}
              onChange={(e) => onChange({ serverId: e.target.value ? Number(e.target.value) : null })}
            >
              <option value="">{t('localServerHint')}</option>
              {servers.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.ip})
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <aside className="border-border/60 from-primary/10 via-card/80 to-accent/10 h-fit rounded-3xl border bg-gradient-to-br p-5 shadow-inner">
        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
          {t('livePreview')}
        </p>
        <p className="mt-3 text-2xl font-semibold">{siteName || t('siteName')}</p>
        <p className="text-muted-foreground mt-1 font-mono text-sm" dir="ltr">
          {slug || 'my-shop'}
        </p>
        <div className="bg-background/70 mt-6 rounded-2xl border p-4 backdrop-blur">
          <p className="text-muted-foreground text-xs">{t('finalDomain')}</p>
          <p className="mt-1 break-all font-mono text-sm font-medium" dir="ltr">
            https://{previewDomain}
          </p>
        </div>
      </aside>
    </div>
  );
}
