'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useRouter } from 'next/navigation';
import {
  Ban,
  CheckCircle2,
  Circle,
  ExternalLink,
  Loader2,
  Play,
  RefreshCw,
  Square,
  TerminalSquare,
  XCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { dashboardHref } from '@/lib/route-resolver';
import {
  cancelProvision,
  fetchProvisionLogs,
  launchProvision,
  pollProvisionStatus,
  retryProvision,
  startProvision,
  stopProvision,
  type ProvisionProgress,
  type SiteProvision,
} from '@/lib/api/site-builder';
import { formatProvisionError, getAxiosMessage } from '@/lib/api-helpers';
import { StepHeroArt } from './illustrations';

const PHASE_ORDER = [
  'queued',
  'fetch_source',
  'build_images',
  'write_stack',
  'compose_up',
  'health',
  'bootstrap',
  'ssl',
  'done',
] as const;

type PhaseKey = (typeof PHASE_ORDER)[number];

function formatEta(seconds: number | null | undefined, locale: string): string {
  if (seconds == null || seconds <= 0) return '—';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  if (locale.startsWith('fa')) {
    if (m <= 0) return `حدود ${s} ثانیه`;
    if (s === 0) return `حدود ${m} دقیقه`;
    return `حدود ${m} دقیقه و ${s} ثانیه`;
  }
  if (m <= 0) return `~${s}s`;
  if (s === 0) return `~${m}m`;
  return `~${m}m ${s}s`;
}

function phaseStatus(
  phase: string | undefined,
  current: string | undefined,
  provisionStatus: string,
): 'waiting' | 'active' | 'done' | 'error' {
  if (provisionStatus === 'failed') {
    if (phase === current) return 'error';
    const ci = PHASE_ORDER.indexOf((current as PhaseKey) || 'queued');
    const pi = PHASE_ORDER.indexOf((phase as PhaseKey) || 'queued');
    return pi < ci ? 'done' : 'waiting';
  }
  if (provisionStatus === 'cancelled') {
    return phase === 'queued' || (current && PHASE_ORDER.indexOf(phase as PhaseKey) <= PHASE_ORDER.indexOf(current as PhaseKey))
      ? 'error'
      : 'waiting';
  }
  if (provisionStatus === 'ready' || current === 'done') {
    return 'done';
  }
  const ci = PHASE_ORDER.indexOf((current as PhaseKey) || 'queued');
  const pi = PHASE_ORDER.indexOf((phase as PhaseKey) || 'queued');
  if (pi < ci) return 'done';
  if (pi === ci) return 'active';
  return 'waiting';
}

type Props = {
  provision: SiteProvision | null;
  finalDomain: string;
  packageSku?: string;
  usesCustomDomain: boolean;
  onProvision: (p: SiteProvision) => void;
  onError: (msg: string | null) => void;
};

export function LaunchControlPanel({
  provision,
  finalDomain,
  packageSku,
  usesCustomDomain,
  onProvision,
  onError,
}: Props) {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [logs, setLogs] = useState('');
  const terminalRef = useRef<HTMLPreElement>(null);
  const running =
    provision?.status === 'pending' ||
    provision?.status === 'provisioning' ||
    provision?.status === 'ssl_pending';

  const progress: ProvisionProgress | null = provision?.progress ?? null;
  const percent = progress?.percent ?? (provision?.status === 'ready' ? 100 : running ? 8 : 0);

  const phaseLabels = useMemo(
    () =>
      ({
        queued: t('phaseQueued'),
        fetch_source: t('phaseFetchSource'),
        build_images: t('phaseBuildImages'),
        write_stack: t('phaseWriteStack'),
        compose_up: t('phaseComposeUp'),
        health: t('phaseHealth'),
        bootstrap: t('phaseBootstrap'),
        ssl: t('phaseSsl'),
        done: t('phaseDone'),
      }) as Record<PhaseKey, string>,
    [t],
  );

  useEffect(() => {
    if (!provision?.id || !running) return;
    let cancelled = false;
    const tick = async () => {
      try {
        const st = await pollProvisionStatus(provision.id);
        if (cancelled) return;
        onProvision(st);
        try {
          const raw = await fetchProvisionLogs(provision.id);
          const text =
            typeof raw === 'string'
              ? raw
              : raw && typeof raw === 'object' && 'logs' in raw
                ? String((raw as { logs?: string }).logs ?? '')
                : JSON.stringify(raw);
          if (!cancelled) setLogs(text);
        } catch {
          /* logs may not exist yet */
        }
      } catch (e) {
        if (!cancelled) onError(getAxiosMessage(e) || t('launchError'));
      }
    };
    void tick();
    const id = window.setInterval(() => void tick(), 3000);
    return () => {
      cancelled = true;
      window.clearInterval(id);
    };
  }, [provision?.id, running, onProvision, onError, t]);

  useEffect(() => {
    if (terminalRef.current) {
      terminalRef.current.scrollTop = terminalRef.current.scrollHeight;
    }
  }, [logs]);

  async function launch() {
    if (!provision?.id) return;
    setPending(true);
    onError(null);
    try {
      const row = await launchProvision(provision.id);
      onProvision(row);
    } catch (e) {
      onError(getAxiosMessage(e) || t('launchError'));
    } finally {
      setPending(false);
    }
  }

  async function cancel() {
    if (!provision?.id) return;
    setPending(true);
    onError(null);
    try {
      const row = await cancelProvision(provision.id);
      onProvision(row);
    } catch (e) {
      onError(getAxiosMessage(e) || t('cancelError'));
    } finally {
      setPending(false);
    }
  }

  async function retry() {
    if (!provision?.id) return;
    setPending(true);
    onError(null);
    try {
      const row = await retryProvision(provision.id);
      onProvision(row);
    } catch (e) {
      onError(getAxiosMessage(e) || t('launchError'));
    } finally {
      setPending(false);
    }
  }

  async function start() {
    if (!provision?.id) return;
    setPending(true);
    try {
      const row = await startProvision(provision.id);
      onProvision(row);
    } catch (e) {
      onError(getAxiosMessage(e) || t('launchError'));
    } finally {
      setPending(false);
    }
  }

  async function stop() {
    if (!provision?.id) return;
    setPending(true);
    try {
      const row = await stopProvision(provision.id);
      onProvision(row);
    } catch (e) {
      onError(getAxiosMessage(e) || t('launchError'));
    } finally {
      setPending(false);
    }
  }

  const currentPhase = progress?.phase ?? (running ? 'queued' : provision?.status === 'ready' ? 'done' : undefined);

  return (
    <div className="grid gap-6" data-testid="wizard-step-launch">
      <StepHeroArt step={7} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepLaunchTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepLaunchSubtitle')}</p>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="space-y-4">
          <div className="border-border/60 from-card/80 to-primary/5 rounded-2xl border bg-gradient-to-br p-4">
            <div className="flex flex-wrap items-end justify-between gap-2">
              <div>
                <p className="text-muted-foreground text-xs">{t('reviewDomain')}</p>
                <p className="mt-1 font-mono text-sm font-semibold" dir="ltr">
                  {provision?.domain || finalDomain}
                </p>
              </div>
              <div className="text-end">
                <p className="text-muted-foreground text-xs">{t('status')}</p>
                <p className="text-sm font-medium" data-testid="launch-status">
                  {provision?.status ?? '—'}
                </p>
              </div>
            </div>
            {packageSku ? <p className="text-muted-foreground mt-2 font-mono text-xs">{packageSku}</p> : null}
            {provision?.license?.license_key ? (
              <p className="mt-2 break-all font-mono text-[11px]" dir="ltr">
                {provision.license.license_key}
              </p>
            ) : null}
            {usesCustomDomain ? (
              <p className="text-muted-foreground mt-2 text-xs">{t('dnsCustomHint')}</p>
            ) : null}
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs">
              <span className="font-medium">
                {locale.startsWith('fa') ? progress?.label_fa : progress?.label_en || progress?.label_fa}
              </span>
              <span className="text-muted-foreground">{percent}%</span>
            </div>
            <Progress value={percent} className="h-3" data-testid="launch-progress" />
            <p className="text-muted-foreground text-xs">
              {t('etaLabel')}: {formatEta(progress?.eta_seconds, locale)}
              {progress?.images_cached === false ? ` · ${t('firstBuildHint')}` : null}
              {progress?.images_cached === true ? ` · ${t('cachedImagesHint')}` : null}
            </p>
          </div>

          <ul className="grid gap-2">
            {PHASE_ORDER.filter((p) => p !== 'done').map((phase) => {
              const st = phaseStatus(phase, currentPhase, provision?.status ?? '');
              return (
                <li
                  key={phase}
                  className="border-border/60 bg-card/50 flex items-center gap-3 rounded-xl border px-3 py-2 text-sm"
                >
                  {st === 'done' ? (
                    <CheckCircle2 className="text-primary h-4 w-4 shrink-0" />
                  ) : st === 'active' ? (
                    <Loader2 className="text-primary h-4 w-4 shrink-0 animate-spin" />
                  ) : st === 'error' ? (
                    <XCircle className="text-destructive h-4 w-4 shrink-0" />
                  ) : (
                    <Circle className="text-muted-foreground h-4 w-4 shrink-0" />
                  )}
                  <span className={st === 'active' ? 'font-medium' : 'text-muted-foreground'}>
                    {phaseLabels[phase]}
                  </span>
                </li>
              );
            })}
          </ul>
        </div>

        <div className="space-y-3">
          <div className="flex items-center gap-2 text-sm font-medium">
            <TerminalSquare className="h-4 w-4" />
            {t('terminalTitle')}
          </div>
          <pre
            ref={terminalRef}
            dir="ltr"
            className="bg-zinc-950 text-zinc-100 dark:bg-black/80 max-h-80 min-h-52 overflow-auto rounded-2xl border p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap"
            data-testid="launch-terminal"
          >
            {logs || t('terminalEmpty')}
          </pre>

          {provision?.status === 'failed' && provision.error_log ? (
            <pre className="bg-destructive/10 text-destructive max-h-28 overflow-auto rounded-xl p-3 text-xs whitespace-pre-wrap">
              {formatProvisionError(provision.error_log).slice(0, 800)}
            </pre>
          ) : null}
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {!running && provision?.status !== 'ready' ? (
          <Button disabled={pending || !provision?.id} onClick={() => void launch()} data-testid="launch-btn">
            <RocketIcon />
            {t('launch')}
          </Button>
        ) : null}
        {running ? (
          <>
            <Button variant="destructive" disabled={pending} onClick={() => void cancel()} data-testid="cancel-btn">
              <Ban className="me-1 h-4 w-4" />
              {t('cancelLaunch')}
            </Button>
            <Button
              variant="outline"
              onClick={() => router.push(dashboardHref(locale, 'admin/platform/sites'))}
              data-testid="background-btn"
            >
              {t('continueInBackground')}
            </Button>
          </>
        ) : null}
        {provision?.status === 'ready' ? (
          <>
            {provision.domain ? (
              <Button asChild>
                <a href={`https://${provision.domain}`} target="_blank" rel="noopener noreferrer">
                  <ExternalLink className="me-1 h-4 w-4" />
                  {t('openSite')}
                </a>
              </Button>
            ) : null}
            <Button variant="outline" disabled={pending} onClick={() => void start()}>
              <Play className="me-1 h-4 w-4" />
              {t('start')}
            </Button>
            <Button variant="outline" disabled={pending} onClick={() => void stop()}>
              <Square className="me-1 h-4 w-4" />
              {t('stop')}
            </Button>
            <Button
              variant="outline"
              onClick={() => router.push(dashboardHref(locale, 'admin/platform/sites'))}
            >
              {t('done')}
            </Button>
          </>
        ) : null}
        {(provision?.status === 'failed' || provision?.status === 'cancelled') && (
          <Button disabled={pending} onClick={() => void retry()} data-testid="retry-btn">
            <RefreshCw className="me-1 h-4 w-4" />
            {t('retry')}
          </Button>
        )}
      </div>
    </div>
  );
}

function RocketIcon() {
  return (
    <svg className="me-1 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
      <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z" />
      <path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z" />
      <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0" />
      <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5" />
    </svg>
  );
}
