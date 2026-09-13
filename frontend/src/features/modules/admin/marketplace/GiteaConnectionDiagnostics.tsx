'use client';

import { useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { GiteaConnectionDiagnosticsResult } from '@/lib/api/marketplace';
import { cn } from '@/lib/utils';

const STEP_ORDER = ['version', 'auth', 'owner', 'owner_repos'] as const;

const HINT_KEYS: Record<string, string> = {
  ssl_wrong_version: 'testHintSsl',
  token_missing: 'testHintToken',
  unauthorized: 'testHintUnauthorized',
  org_not_found: 'testHintOrg',
  owner_not_found: 'testHintOwnerNotFound',
  owner_is_user: 'testHintOwnerUser',
  config_incomplete: 'testHintConfig',
};

const STEP_HINT_KEYS: Record<string, string> = {
  ssl_wrong_version: 'testStepHintSsl',
  unauthorized: 'testStepHintUnauthorized',
  not_found: 'testStepHintNotFound',
  network_error: 'testStepHintNetwork',
  http_error: 'testStepHintHttp',
  ok: 'testStepHintOk',
};

type Props = {
  result: GiteaConnectionDiagnosticsResult | null;
  loading?: boolean;
};

export function GiteaConnectionDiagnostics({ result, loading }: Props) {
  const t = useTranslations('marketplace.gitea');

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('testDiagnostics')}</CardTitle>
          <CardDescription>{t('testDiagnosticsDesc')}</CardDescription>
        </CardHeader>
        <CardContent className="text-muted-foreground text-sm">{t('testRunning')}</CardContent>
      </Card>
    );
  }

  if (!result) return null;

  const diag = result.diag ?? {};
  const steps = result.steps ?? {};

  const hintLabel = (hint: string) => {
    if (!hint) return '—';
    const key = STEP_HINT_KEYS[hint];
    return key ? t(key as 'testStepHintOk') : hint;
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('testDiagnostics')}</CardTitle>
        <CardDescription>{t('testDiagnosticsDesc')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <p className={cn(result.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive')}>
          {result.ok
            ? result.user
              ? t('testOkUser', { user: result.user })
              : t('testOk')
            : result.message?.trim() || t('testFail')}
        </p>

        {result.hints && result.hints.length > 0 ? (
          <div className="space-y-2">
            {result.hints.map((hint) => {
              const key = HINT_KEYS[hint];
              return (
                <div
                  key={hint}
                  className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs"
                >
                  {key ? t(key as 'testHintToken') : hint}
                </div>
              );
            })}
          </div>
        ) : null}

        {diag.resolved_url_sample || diag.ip_scheme || diag.owner_kind || result.provider ? (
          <div className="text-muted-foreground grid gap-1 text-xs">
            {result.provider ? (
              <p>
                Provider:{' '}
                <span className="text-foreground font-mono" dir="ltr">
                  {result.provider}
                </span>
              </p>
            ) : null}
            {diag.owner_kind ? (
              <p>
                {t('ownerKind')}:{' '}
                <span className="text-foreground font-mono" dir="ltr">
                  {String(diag.owner_kind)}
                </span>
              </p>
            ) : null}
            {diag.ip_scheme ? (
              <p>
                {t('ipScheme')}:{' '}
                <span className="text-foreground font-mono" dir="ltr">
                  {String(diag.ip_scheme)}
                  {diag.resolved_scheme ? ` → ${String(diag.resolved_scheme)}` : ''}
                </span>
              </p>
            ) : null}
            {diag.resolved_url_sample ? (
              <p className="text-foreground break-all font-mono" dir="ltr">
                {String(diag.resolved_url_sample)}
              </p>
            ) : null}
          </div>
        ) : null}

        {Object.keys(steps).length > 0 ? (
          <div className="border-border overflow-x-auto rounded-md border">
            <table className="w-full min-w-[20rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border">
              <thead>
                <tr className="bg-muted/40">
                  <th className="p-2 text-start font-medium">{t('testProbeName')}</th>
                  <th className="p-2 text-start font-medium">{t('testProbeHttp')}</th>
                  <th className="p-2 text-start font-medium">{t('testProbeHint')}</th>
                  <th className="p-2 text-start font-medium">{t('testProbeMsg')}</th>
                </tr>
              </thead>
              <tbody>
                {STEP_ORDER.map((key) => {
                  const row = steps[key];
                  if (!row) return null;
                  return (
                    <tr key={key}>
                      <td className="p-2">{row.label || key}</td>
                      <td className="p-2 font-mono tabular-nums" dir="ltr">
                        {row.http ? row.http : '—'}
                        {row.ms != null ? ` (${row.ms}ms)` : ''}
                      </td>
                      <td className="p-2">
                        <Badge variant={row.ok ? 'default' : 'destructive'} className="font-normal">
                          {hintLabel(String(row.hint ?? ''))}
                        </Badge>
                      </td>
                      <td
                        className="text-muted-foreground max-w-[14rem] truncate p-2"
                        title={String(row.message ?? row.curl_error ?? '')}
                      >
                        {String(row.message ?? row.curl_error ?? '')}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : null}

        <details className="text-xs">
          <summary className="text-muted-foreground hover:text-foreground cursor-pointer">
            {t('testRawJson')}
          </summary>
          <pre
            className="border-border bg-muted/40 mt-2 max-h-48 overflow-auto rounded-md border p-2"
            dir="ltr"
          >
            {JSON.stringify(result, null, 2)}
          </pre>
        </details>
      </CardContent>
    </Card>
  );
}
