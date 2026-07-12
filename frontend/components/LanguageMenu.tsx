'use client';

import { ChevronDown } from 'lucide-react';
import { useLocale, useTranslations } from 'next-intl';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePathname, useRouter } from '@/lib/i18n-navigation';

const LANGS = [
  { code: 'fa' as const, labelKey: 'settings.langFa', flag: '🇮🇷' },
  { code: 'en' as const, labelKey: 'settings.langEn', flag: '🇬🇧' },
];

export function LanguageMenu() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const current = LANGS.find((l) => l.code === locale) ?? LANGS[0];

  function select(nextLocale: 'fa' | 'en') {
    router.replace(pathname, { locale: nextLocale });
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8 gap-1.5 px-2 text-xs"
          aria-label={t('settings.language')}
        >
          <span aria-hidden>{current.flag}</span>
          <span className="uppercase">{current.code}</span>
          <ChevronDown className="size-3 opacity-60" aria-hidden />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {LANGS.map((l) => (
          <DropdownMenuItem key={l.code} className="gap-2" onSelect={() => select(l.code)}>
            <span aria-hidden>{l.flag}</span>
            {t(l.labelKey)}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
