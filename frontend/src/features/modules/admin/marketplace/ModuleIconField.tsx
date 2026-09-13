'use client';

import { useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ImageIcon } from 'lucide-react';

type Props = {
  iconUrl: string;
  onChange: (value: string) => void;
  resetKey?: string | number;
};

function resolvePreviewUrl(iconUrl: string): string | undefined {
  const v = iconUrl.trim();
  if (!v) return undefined;
  if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
  return undefined;
}

export function ModuleIconField({ iconUrl, onChange, resetKey }: Props) {
  const t = useTranslations('marketplace');
  const fileRef = useRef<HTMLInputElement>(null);
  const [previewOverride, setPreviewOverride] = useState<string | null>(null);
  const preview = previewOverride ?? resolvePreviewUrl(iconUrl);

  useEffect(() => {
    setPreviewOverride(null);
  }, [resetKey]);

  const handleFile = (file: File) => {
    const url = URL.createObjectURL(file);
    setPreviewOverride(url);
    onChange(url);
  };

  const handleRemove = () => {
    setPreviewOverride(null);
    onChange('');
  };

  return (
    <div className="space-y-3">
      <Label>{t('moduleIcon')}</Label>
      <div className="border-border flex flex-col items-center gap-3 rounded-lg border p-4 sm:flex-row sm:items-start">
        {preview ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={preview} alt="" className="h-20 w-20 shrink-0 rounded-lg object-contain" />
        ) : (
          <div className="bg-muted flex h-20 w-20 shrink-0 items-center justify-center rounded-lg">
            <ImageIcon className="text-muted-foreground h-10 w-10" />
          </div>
        )}
        <div className="flex w-full flex-col gap-2">
          <Input
            value={iconUrl}
            onChange={(e) => {
              setPreviewOverride(null);
              onChange(e.target.value);
            }}
            placeholder={t('iconUrl')}
            dir="ltr"
          />
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => fileRef.current?.click()}>
              {t('uploadIcon')}
            </Button>
            {iconUrl ? (
              <Button type="button" size="sm" variant="ghost" onClick={handleRemove}>
                {t('removeIcon')}
              </Button>
            ) : null}
          </div>
        </div>
      </div>
      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) handleFile(file);
          e.target.value = '';
        }}
      />
    </div>
  );
}
