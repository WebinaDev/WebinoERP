'use client';

import type { LucideIcon } from 'lucide-react';

type Props = {
  icon?: LucideIcon;
  message: string;
};

export function PmEmptyState({ icon: Icon, message }: Props) {
  return (
    <div className="text-muted-foreground flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed py-12 text-center text-sm">
      {Icon ? <Icon className="h-10 w-10 opacity-40" /> : null}
      <p>{message}</p>
    </div>
  );
}
