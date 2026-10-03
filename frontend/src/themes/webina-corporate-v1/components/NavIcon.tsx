import * as Lucide from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const registry = Lucide as unknown as Record<string, LucideIcon>;

export function NavIcon({ name, className = 'size-4' }: { name: string; className?: string }) {
  const Icon = registry[name];
  if (!Icon) return <span className={className} aria-hidden />;
  return <Icon className={className} aria-hidden />;
}
