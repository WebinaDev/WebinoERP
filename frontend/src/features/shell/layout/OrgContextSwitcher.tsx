'use client';

import { Building2, ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import api from '@/lib/api-client';

interface Company {
  id: number;
  name: string;
  is_default?: boolean;
}

interface Branch {
  id: number;
  company_id: number;
  name: string;
  code?: string;
}

export function OrgContextSwitcher() {
  const t = useTranslations('erp.org');
  const [companies, setCompanies] = useState<Company[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [activeCompany, setActiveCompany] = useState<Company | null>(null);
  const [activeBranch, setActiveBranch] = useState<Branch | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function fetchContext() {
      try {
        const [coRes, ctxRes] = await Promise.all([
          api.get('/api/v1/org/companies').catch(() => ({ data: { data: [] } })),
          api.get('/api/v1/org/context/active').catch(() => ({ data: { data: {} } })),
        ]);
        if (cancelled) return;
        const list: Company[] = coRes.data?.data ?? [];
        setCompanies(list);

        const ctx = ctxRes.data?.data;
        if (ctx?.company) {
          setActiveCompany(ctx.company);
          if (ctx.branch) setActiveBranch(ctx.branch);
          // fetch branches for active company
          const brRes = await api.get(`/api/v1/org/branches?company_id=${ctx.company.id}`).catch(() => ({ data: { data: [] } }));
          if (!cancelled) setBranches(brRes.data?.data ?? []);
        } else if (list.length > 0) {
          setActiveCompany(list[0]);
        }
      } catch {
        // graceful fallback
      }
    }
    fetchContext();
    return () => {
      cancelled = true;
    };
  }, []);

  const handleSwitchCompany = async (co: Company) => {
    setLoading(true);
    try {
      await api.post('/api/v1/org/context/switch', { company_id: co.id });
      setActiveCompany(co);
      setActiveBranch(null);
      const brRes = await api.get(`/api/v1/org/branches?company_id=${co.id}`).catch(() => ({ data: { data: [] } }));
      setBranches(brRes.data?.data ?? []);
    } catch {
      // fallback
    } finally {
      setLoading(false);
    }
  };

  const handleSwitchBranch = async (br: Branch) => {
    if (!activeCompany) return;
    setLoading(true);
    try {
      await api.post('/api/v1/org/context/switch', { company_id: activeCompany.id, branch_id: br.id });
      setActiveBranch(br);
    } catch {
      // fallback
    } finally {
      setLoading(false);
    }
  };

  if (!activeCompany && companies.length === 0) {
    return null;
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          disabled={loading}
          className="h-8 gap-1.5 px-2.5 text-xs font-normal border-dashed text-foreground/80 hover:text-foreground"
        >
          <Building2 className="h-3.5 w-3.5 text-primary" />
          <span className="max-w-[120px] truncate font-medium">
            {activeCompany ? activeCompany.name : t('selectCompany')}
          </span>
          {activeBranch && (
            <span className="text-muted-foreground hidden sm:inline">
              / {activeBranch.name}
            </span>
          )}
          <ChevronDown className="h-3 w-3 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56" >
        <DropdownMenuLabel className="text-xs text-muted-foreground">{t('companies')}</DropdownMenuLabel>
        {companies.map((co) => (
          <DropdownMenuItem
            key={co.id}
            onClick={() => handleSwitchCompany(co)}
            className={`cursor-pointer justify-between ${activeCompany?.id === co.id ? 'font-semibold text-primary' : ''}`}
          >
            <span>{co.name}</span>
            {activeCompany?.id === co.id && <span className="text-xs text-primary">✓</span>}
          </DropdownMenuItem>
        ))}

        {branches.length > 0 && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs text-muted-foreground">{t('branches')}</DropdownMenuLabel>
            {branches.map((br) => (
              <DropdownMenuItem
                key={br.id}
                onClick={() => handleSwitchBranch(br)}
                className={`cursor-pointer justify-between ${activeBranch?.id === br.id ? 'font-semibold text-primary' : ''}`}
              >
                <span>{br.name}</span>
                {activeBranch?.id === br.id && <span className="text-xs text-primary">✓</span>}
              </DropdownMenuItem>
            ))}
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
