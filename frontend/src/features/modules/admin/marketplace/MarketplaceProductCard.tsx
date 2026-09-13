'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import type { MarketplaceModule } from '@/lib/api/marketplace';
import { dashboardHref } from '@/lib/route-resolver';
import { cn } from '@/lib/utils';
import { ExternalLink, Package, Settings, Tag, Trash2 } from 'lucide-react';

type Props = {
  module: MarketplaceModule;
  statusBusy: boolean;
  onStatusToggle: (active: boolean) => void;
  onDelete: () => void;
};

export function MarketplaceProductCard({ module: m, statusBusy, onStatusToggle, onDelete }: Props) {
  const t = useTranslations('marketplace');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const repoUrl = m.gitea_html_url || m.gitea_repo_url || '';
  const iconSrc = m.icon_url && !/^\d+$/.test(String(m.icon_url)) ? m.icon_url : undefined;

  const priceLabel = m.is_free
    ? t('free')
    : `${(m.price ?? 0).toLocaleString()} ${m.currency ?? 'IRT'}`;

  const categoryLabel = m.category_name?.trim() ? m.category_name : t('noCategory');

  return (
    <TooltipProvider>
      <Card className="relative overflow-hidden">
        <div className="absolute top-3 start-3 z-10 flex max-w-[70%] flex-wrap justify-start gap-1">
          <Badge variant="secondary" className="text-xs">
            {categoryLabel}
          </Badge>
          {m.status !== 'active' ? (
            <Badge variant="destructive" className="text-xs">
              {t('inactive')}
            </Badge>
          ) : null}
        </div>
        <CardContent className="flex h-full flex-col pt-10">
          <div className="mb-3 flex flex-col items-center text-center">
            {iconSrc ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={iconSrc} alt={m.name} className="mb-2 h-16 w-16 rounded-lg object-contain" />
            ) : (
              <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-lg bg-muted">
                <Package className="h-8 w-8 text-muted-foreground" />
              </div>
            )}
            <h5 className="line-clamp-2 font-medium leading-snug">{m.name}</h5>
            <p className="text-muted-foreground mt-0.5 text-sm font-medium">{priceLabel}</p>
            <p className="text-muted-foreground mt-1 text-xs" dir="ltr">
              {t('version')}: {m.latest_version ?? m.version ?? '—'}
            </p>
          </div>

          {m.description ? (
            <p className="text-muted-foreground mb-3 line-clamp-2 flex-1 text-center text-sm">
              {m.description}
            </p>
          ) : (
            <div className="mb-3 flex-1" />
          )}

          <div className="mb-3 flex flex-wrap justify-center gap-1">
            {m.is_core ? (
              <Badge variant="secondary" className="text-xs">
                {t('coreProduct')}
              </Badge>
            ) : null}
            {m.is_builtin && !m.is_core ? (
              <Badge variant="outline" className="text-xs">
                {t('bundledWithDashboard')}
              </Badge>
            ) : null}
            {m.parent_name ? (
              <Badge variant="outline" className="text-xs">
                {t('submoduleOf', { name: m.parent_name })}
              </Badge>
            ) : null}
          </div>

          <div className="border-border flex items-center justify-between gap-2 border-t pt-3">
            <div className="flex items-center gap-2">
              <Switch
                id={`mp-status-${m.id}`}
                checked={m.status === 'active'}
                disabled={m.is_core || statusBusy}
                onCheckedChange={onStatusToggle}
              />
              <Label htmlFor={`mp-status-${m.id}`} className="text-muted-foreground text-xs">
                {m.status === 'active' ? t('statusActive') : t('statusInactive')}
              </Label>
            </div>
            <div className="flex items-center gap-0.5">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                    <Link href={dashboardHref(locale, `admin/marketplace/modules/${m.id}`)}>
                      <Settings className="h-4 w-4" />
                      <span className="sr-only">{t('productCard.manage')}</span>
                    </Link>
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('productCard.manage')}</TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                    <Link href={`${dashboardHref(locale, `admin/marketplace/modules/${m.id}`)}#releases`}>
                      <Tag className="h-4 w-4" />
                      <span className="sr-only">{t('productCard.releases')}</span>
                    </Link>
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('productCard.releases')}</TooltipContent>
              </Tooltip>
              {repoUrl ? (
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                      <a href={repoUrl} target="_blank" rel="noreferrer">
                        <ExternalLink className="h-4 w-4" />
                        <span className="sr-only">{t('productCard.openRepo')}</span>
                      </a>
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>{t('productCard.openRepo')}</TooltipContent>
                </Tooltip>
              ) : null}
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={cn('h-8 w-8 text-destructive', m.is_core && 'invisible')}
                    disabled={m.is_core}
                    onClick={onDelete}
                  >
                    <Trash2 className="h-4 w-4" />
                    <span className="sr-only">{t('productCard.delete')}</span>
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{t('productCard.delete')}</TooltipContent>
              </Tooltip>
            </div>
          </div>
        </CardContent>
      </Card>
    </TooltipProvider>
  );
}
