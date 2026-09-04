'use client';

import type { LucideIcon } from 'lucide-react';
import {
  Building2,
  Coffee,
  Globe2,
  KeyRound,
  LayoutTemplate,
  Package,
  Rocket,
  ShoppingBag,
  UserRound,
  Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export const WIZARD_STEP_ICONS: LucideIcon[] = [
  Users,
  LayoutTemplate,
  Building2,
  Package,
  UserRound,
  Globe2,
  KeyRound,
  Rocket,
];

export function SiteTypeIllustration({
  slug,
  className,
}: {
  slug?: string | null;
  className?: string;
}) {
  const key = (slug ?? 'corporate').toLowerCase();
  const Icon =
    key.includes('cafe') || key.includes('restaurant')
      ? Coffee
      : key.includes('ecom') || key.includes('shop') || key.includes('store')
        ? ShoppingBag
        : key.includes('resume') || key.includes('cv')
          ? UserRound
          : key.includes('magazine') || key.includes('blog')
            ? LayoutTemplate
            : Building2;

  return (
    <div
      className={cn(
        'relative flex h-28 w-full items-center justify-center overflow-hidden rounded-2xl',
        'bg-gradient-to-br from-primary/10 via-accent/20 to-transparent',
        'dark:from-primary/20 dark:via-accent/10',
        className,
      )}
    >
      <div className="bg-primary/20 absolute -top-6 -right-6 h-24 w-24 rounded-full blur-2xl" />
      <div className="bg-accent/30 absolute -bottom-8 -left-4 h-28 w-28 rounded-full blur-2xl" />
      <Icon className="text-primary relative z-10 h-12 w-12 drop-shadow-sm" strokeWidth={1.5} />
    </div>
  );
}

export function StepHeroArt({
  step,
  className,
}: {
  step: number;
  className?: string;
}) {
  const Icon = WIZARD_STEP_ICONS[step] ?? Rocket;
  return (
    <div
      className={cn(
        'relative mx-auto flex h-36 w-full max-w-sm items-center justify-center overflow-hidden rounded-3xl',
        'border border-border/60 bg-card/40 backdrop-blur-md',
        'shadow-[0_20px_60px_-30px_hsl(var(--primary)/0.45)]',
        className,
      )}
      aria-hidden
    >
      <svg viewBox="0 0 320 160" className="absolute inset-0 h-full w-full opacity-70" preserveAspectRatio="xMidYMid slice">
        <defs>
          <linearGradient id={`wiz-g-${step}`} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="hsl(var(--primary))" stopOpacity="0.18" />
            <stop offset="55%" stopColor="hsl(var(--accent))" stopOpacity="0.12" />
            <stop offset="100%" stopColor="hsl(var(--primary))" stopOpacity="0.05" />
          </linearGradient>
        </defs>
        <rect width="320" height="160" fill={`url(#wiz-g-${step})`} />
        <circle cx="260" cy="36" r="48" fill="hsl(var(--primary))" fillOpacity="0.08" />
        <circle cx="48" cy="120" r="56" fill="hsl(var(--accent))" fillOpacity="0.12" />
        <path
          d="M0 118 C70 90 120 140 180 110 C240 80 280 120 320 96 L320 160 L0 160 Z"
          fill="hsl(var(--primary))"
          fillOpacity="0.06"
        />
      </svg>
      <div className="bg-background/70 relative z-10 flex h-16 w-16 items-center justify-center rounded-2xl border border-border/70 shadow-lg backdrop-blur">
        <Icon className="text-primary h-8 w-8" strokeWidth={1.6} />
      </div>
    </div>
  );
}
