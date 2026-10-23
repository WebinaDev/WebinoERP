'use client';

import type { ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Clock } from 'lucide-react';
import { useHubList } from '../hub-api';
import { useHub } from '../HubContext';
import { Empty, HubCard, Loading, Pager } from '../ui';
import type { ContractArea } from './phase3-api';
import type { TabMeta } from './shared';

/**
 * Skeleton tab: layout + loading/empty states + meta.can plumbing.
 * Fetches only once its contract area is ready; until then shows a
 * "waiting for API contract" notice.
 */
export function PendingTab({ area, title, actions, children }: {
  area: ContractArea; title: ReactNode;
  actions?: (can: Record<string, boolean>) => ReactNode;
  children?: (rows: Record<string, unknown>[]) => ReactNode;
}) {
  const t = useTranslations('crm.hub.c360');
  const { id, version } = useHub();
  const list = useHubList(id, area.path ?? area.key, {}, area.ready && !!area.path, version);
  const meta = list.meta as TabMeta | null;
  const can = (meta?.can ?? {}) as Record<string, boolean>;
  return (
    <HubCard title={title} actions={area.ready && actions ? actions(can) : null}>
      {!area.ready ? (
        <div className="flex items-center gap-2 rounded-md border border-dashed p-4 text-sm text-muted-foreground" data-testid="contract-pending">
          <Clock className="h-4 w-4" />{t('pending')}
        </div>
      ) : list.loading && !list.rows.length ? <Loading /> : !list.rows.length ? <Empty /> : (
        <>{children ? children(list.rows) : null}<Pager meta={list.meta} onPage={list.setPage} /></>
      )}
    </HubCard>
  );
}
