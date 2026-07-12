import type { LucideIcon } from 'lucide-react';
import {
  BarChart3,
  Box,
  Building2,
  Calendar,
  FileText,
  FolderKanban,
  LayoutDashboard,
  MessageSquare,
  Package,
  Settings,
  ShoppingCart,
  Users,
  Warehouse,
  Wallet,
} from 'lucide-react';

const ICON_MAP: Record<string, LucideIcon> = {
  'ri-home-4-line': LayoutDashboard,
  'ri-bar-chart-box-line': BarChart3,
  'ri-team-line': Users,
  'ri-wallet-3-line': Wallet,
  'ri-customer-service-2-line': MessageSquare,
  'ri-folder-chart-line': FolderKanban,
  'ri-archive-line': Warehouse,
  'ri-shopping-bag-line': ShoppingCart,
  'ri-file-text-line': FileText,
  'ri-settings-3-line': Settings,
  'ri-building-line': Building2,
  'ri-calendar-line': Calendar,
  'ri-box-3-line': Box,
  'ri-store-2-line': Package,
};

export function moduleIcon(name?: string): LucideIcon {
  if (!name) return LayoutDashboard;
  return ICON_MAP[name] ?? LayoutDashboard;
}

export function renderIcon(Icon: LucideIcon, className?: string) {
  return <Icon className={className} />;
}
