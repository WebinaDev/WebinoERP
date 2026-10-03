'use client';

import { useState } from 'react';
import { BrandMark } from './BrandMark';

export function LogoLockup({
  siteName,
  logoUrl,
  compact = false,
}: {
  siteName: string;
  logoUrl?: string | null;
  compact?: boolean;
}) {
  const [failed, setFailed] = useState(false);
  const src = logoUrl || '/brand/logo.png';

  return (
    <span className="logo-lockup">
      {failed ? (
        <BrandMark className={compact ? 'size-10 shrink-0' : 'size-11 shrink-0'} />
      ) : (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={src}
          alt=""
          width={compact ? 72 : 88}
          height={compact ? 36 : 44}
          className={compact ? 'h-9 w-auto max-w-[6.5rem] shrink-0 object-contain' : 'h-11 w-auto max-w-[7.5rem] shrink-0 object-contain'}
          onError={() => setFailed(true)}
        />
      )}
      <strong>{siteName}</strong>
    </span>
  );
}
