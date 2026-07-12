import { ChevronLeft, ChevronRight } from 'lucide-react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useLocale } from 'next-intl'
import { memo, useEffect, useMemo, useState } from 'react'

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import type { Sidebar08MainNavItem, Sidebar08NavSubItem } from '@/lib/nav-modules'
import { renderIcon } from '@/lib/react-icon'

function pathIsActive(pathname: string, to: string): boolean {
  const p = pathname.replace(/\/$/, '') || '/'
  if (to === '/') return p === '/' || p.endsWith('/dashboard')
  const t = to.replace(/\/$/, '')
  return p === t || p.startsWith(`${t}/`)
}

function subItemIsActive(item: Sidebar08NavSubItem, pathname: string): boolean {
  if (item.items?.length) {
    return item.items.some((child) => subItemIsActive(child, pathname))
  }
  return pathIsActive(pathname, item.url)
}

function activeGroupId(items: Sidebar08MainNavItem[], pathname: string): string | null {
  for (const item of items) {
    if (!item.items?.length) continue
    if (item.items.some((sub) => subItemIsActive(sub, pathname))) {
      return item.id
    }
  }
  return null
}

function activeNestedGroupId(items: Sidebar08NavSubItem[], pathname: string): string | null {
  for (const item of items) {
    if (!item.items?.length) continue
    if (item.items.some((sub) => subItemIsActive(sub, pathname))) {
      return item.id
    }
  }
  return null
}

function NavSubMenu({
  items,
  pathname,
  depth = 0,
}: {
  items: Sidebar08NavSubItem[]
  pathname: string
  depth?: number
}) {
  const locale = useLocale()
  const Chevron = locale === 'fa' ? ChevronLeft : ChevronRight
  const routeOpenId = useMemo(() => activeNestedGroupId(items, pathname), [items, pathname])
  const [openId, setOpenId] = useState<string | null>(() => routeOpenId)

  useEffect(() => {
    setOpenId(routeOpenId)
  }, [routeOpenId])

  return (
    <>
      {items.map((subItem) => {
        const nested = subItem.items
        if (nested?.length) {
          return (
            <SidebarMenuSubItem key={subItem.id}>
              <Collapsible
                open={openId === subItem.id}
                onOpenChange={(open) => {
                  setOpenId(open ? subItem.id : null)
                }}
                className="group/collapsible"
              >
                <CollapsibleTrigger asChild>
                  <SidebarMenuSubButton size="md" isActive={subItemIsActive(subItem, pathname)}>
                    <span>{subItem.title}</span>
                    <Chevron className="ms-auto size-3 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                  </SidebarMenuSubButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                  <SidebarMenuSub>
                    <NavSubMenu items={nested} pathname={pathname} depth={depth + 1} />
                  </SidebarMenuSub>
                </CollapsibleContent>
              </Collapsible>
            </SidebarMenuSubItem>
          )
        }

        const subActive = pathIsActive(pathname, subItem.url)
        return (
          <SidebarMenuSubItem key={subItem.id}>
            <SidebarMenuSubButton asChild isActive={subActive} size="md">
              <Link href={subItem.url}>
                <span>{subItem.title}</span>
              </Link>
            </SidebarMenuSubButton>
          </SidebarMenuSubItem>
        )
      })}
    </>
  )
}

function NavMainComponent({
  items,
  groupLabel,
}: {
  items: Sidebar08MainNavItem[]
  groupLabel?: string
}) {
  const pathname = usePathname() ?? ''
  const locale = useLocale()
  const Chevron = locale === 'fa' ? ChevronLeft : ChevronRight

  const routeOpenId = useMemo(() => activeGroupId(items, pathname), [items, pathname])

  const [openId, setOpenId] = useState<string | null>(() => routeOpenId)

  useEffect(() => {
    setOpenId(routeOpenId)
  }, [routeOpenId])

  return (
    <SidebarGroup>
      {groupLabel ? <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel> : null}
      <SidebarMenu>
        {items.map((item) => {
          const subItems = item.items
          const itemIcon = renderIcon(item.icon, 'size-4 shrink-0')

          if (!subItems?.length) {
            const active = pathIsActive(pathname, item.url)
            return (
              <SidebarMenuItem key={item.id}>
                <SidebarMenuButton asChild isActive={active} tooltip={item.title}>
                  <Link href={item.url}>
                    {itemIcon}
                    <span>{item.title}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          }

          return (
            <SidebarMenuItem key={item.id}>
              <Collapsible
                open={openId === item.id}
                onOpenChange={(open) => {
                  setOpenId(open ? item.id : null)
                }}
                className="group/collapsible"
              >
                <CollapsibleTrigger asChild>
                  <SidebarMenuButton tooltip={item.title}>
                    {itemIcon}
                    <span>{item.title}</span>
                    <Chevron className="ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                  </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                  <SidebarMenuSub>
                    <NavSubMenu items={subItems} pathname={pathname} />
                  </SidebarMenuSub>
                </CollapsibleContent>
              </Collapsible>
            </SidebarMenuItem>
          )
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}

export const NavMain = memo(NavMainComponent)

function NavPinnedComponent({ items }: { items: Sidebar08MainNavItem[] }) {
  return <NavMain items={items} />
}

export const NavPinned = memo(NavPinnedComponent)
