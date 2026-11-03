import Link from 'next/link'

import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { BrandMark } from '@/themes/webina-corporate-v1/components/BrandMark'

export function SiteBrand({
  title,
  subtitle,
  homeTo,
}: {
  title: string
  subtitle: string
  homeTo: string
}) {
  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <SidebarMenuButton size="lg" asChild>
          <Link href={homeTo}>
            {/* No forced bg — BrandMark is transparent SVG (blue + currentColor ink). */}
            <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden">
              <BrandMark className="h-7 w-auto text-sidebar-foreground" title={title} />
            </div>
            <div className="grid flex-1 text-start text-sm leading-tight">
              <span className="truncate font-semibold">{title}</span>
              <span className="truncate text-xs text-sidebar-foreground/70">{subtitle}</span>
            </div>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
