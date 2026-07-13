import Image from 'next/image'
import Link from 'next/link'

import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'

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
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
              <Image
                src="/brand/logo.png"
                alt=""
                width={32}
                height={32}
                className="size-6 rounded-sm object-contain"
              />
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
