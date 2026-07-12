import type { LucideIcon } from 'lucide-react'
import {
  BarChart2,
  Bot,
  Circle,
  CircleDollarSign,
  FileText,
  Images,
  LayoutDashboard,
  MessageSquare,
  Newspaper,
  Package,
  Percent,
  Puzzle,
  Settings,
  ShoppingBag,
  Store,
  Users,
} from 'lucide-react'

const MAP: Record<string, LucideIcon> = {
  'circle-dollar-sign': CircleDollarSign,
  'layout-dashboard': LayoutDashboard,
  newspaper: Newspaper,
  images: Images,
  'file-text': FileText,
  'shopping-bag': ShoppingBag,
  package: Package,
  store: Store,
  puzzle: Puzzle,
  percent: Percent,
  users: Users,
  'bar-chart-2': BarChart2,
  settings: Settings,
  bot: Bot,
  'message-square': MessageSquare,
}

export function moduleIcon(slug?: string): LucideIcon {
  if (!slug) return Circle
  return MAP[slug] ?? Circle
}
