import type { LucideIcon } from 'lucide-react';

export type NavMainChild = {
  id: string;
  to: string;
  title: string;
  icon: LucideIcon;
  children?: NavMainChild[];
};

export type NavMainSection =
  | { kind: 'item'; id: string; to: string; title: string; icon: LucideIcon }
  | { kind: 'group'; id: string; title: string; icon: LucideIcon; children: NavMainChild[] };

export type NavSidebarSection = {
  id: string;
  label: string;
  items: NavMainSection[];
};
