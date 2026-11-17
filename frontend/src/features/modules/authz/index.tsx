'use client';

import { useTranslations } from 'next-intl';

export function AuthzOverviewPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
        <p className="text-muted-foreground">{t('description')}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('roles')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('rolesDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('permissions')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('permissionsDesc')}</p>
        </div>
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <h3 className="font-semibold">{t('simulator')}</h3>
          <p className="text-sm text-muted-foreground mt-2">{t('simulatorDesc')}</p>
        </div>
      </div>
    </div>
  );
}

export function AuthzRolesPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('roles')}</h1>
      <p className="text-sm text-muted-foreground">{t('rolesDesc')}</p>
    </div>
  );
}

export function AuthzPermissionsPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('permissions')}</h1>
      <p className="text-sm text-muted-foreground">{t('permissionsDesc')}</p>
    </div>
  );
}

export function AuthzPoliciesPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('policies')}</h1>
      <p className="text-sm text-muted-foreground">{t('policiesDesc')}</p>
    </div>
  );
}

export function AuthzSimulatorPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('simulator')}</h1>
      <p className="text-sm text-muted-foreground">{t('simulatorDesc')}</p>
    </div>
  );
}

export function AuthzAuditLogsPage() {
  const t = useTranslations('erp.authz');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('auditLogs')}</h1>
      <p className="text-sm text-muted-foreground">{t('auditLogsDesc')}</p>
    </div>
  );
}
