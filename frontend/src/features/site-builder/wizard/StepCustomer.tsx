'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Plus, Search, UserRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StepHeroArt } from './illustrations';
import { SelectableCard } from './WizardShell';

export type CrmAccount = { id: number; name?: string; company_name?: string };

type Props = {
  accounts: CrmAccount[];
  crmAccountId: number | null;
  onSelect: (id: number) => void;
  newCustomerName: string;
  onNewName: (v: string) => void;
  onCreate: () => void;
  pending: boolean;
};

export function StepCustomer({
  accounts,
  crmAccountId,
  onSelect,
  newCustomerName,
  onNewName,
  onCreate,
  pending,
}: Props) {
  const t = useTranslations('siteBuilder');
  const [q, setQ] = useState('');
  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return accounts;
    return accounts.filter((a) => {
      const label = `${a.company_name ?? ''} ${a.name ?? ''}`.toLowerCase();
      return label.includes(needle) || String(a.id).includes(needle);
    });
  }, [accounts, q]);

  return (
    <div className="grid gap-6" data-testid="wizard-step-customer">
      <StepHeroArt step={0} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepCustomerTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepCustomerSubtitle')}</p>
      </div>

      <div className="relative">
        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
        <Input
          className="ps-9"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={t('searchCustomer')}
          data-testid="customer-search"
        />
      </div>

      <div className="grid max-h-72 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
        {filtered.map((a) => {
          const label = a.company_name || a.name || `#${a.id}`;
          const selected = crmAccountId === a.id;
          return (
            <SelectableCard
              key={a.id}
              selected={selected}
              onClick={() => onSelect(a.id)}
              testId={`customer-card-${a.id}`}
            >
              <div className="flex items-center gap-3">
                <span className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-xl">
                  <UserRound className="h-5 w-5" />
                </span>
                <div className="min-w-0">
                  <p className="truncate font-medium">{label}</p>
                  <p className="text-muted-foreground text-xs">#{a.id}</p>
                </div>
              </div>
            </SelectableCard>
          );
        })}
        {filtered.length === 0 ? (
          <p className="text-muted-foreground col-span-full text-sm">{t('noCustomers')}</p>
        ) : null}
      </div>

      <div className="border-border/70 rounded-2xl border border-dashed p-4">
        <Label className="mb-2 flex items-center gap-2">
          <Plus className="h-4 w-4" />
          {t('createCustomer')}
        </Label>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Input
            value={newCustomerName}
            onChange={(e) => onNewName(e.target.value)}
            placeholder={t('createCustomer')}
            data-testid="new-customer-name"
          />
          <Button
            type="button"
            variant="outline"
            disabled={pending || !newCustomerName.trim()}
            onClick={onCreate}
            data-testid="create-customer-btn"
          >
            {t('save')}
          </Button>
        </div>
      </div>
    </div>
  );
}
