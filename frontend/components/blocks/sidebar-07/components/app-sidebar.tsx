import type { LucideIcon } from 'lucide-react'
import { memo } from 'react'

import { NavMain, NavPinned } from '@/components/blocks/sidebar-07/components/nav-main'
import { NavProjects } from '@/components/blocks/sidebar-07/components/nav-projects'
import { NavUser } from '@/components/blocks/sidebar-07/components/nav-user'
import { SiteBrand } from '@/components/blocks/sidebar-07/components/site-brand'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarRail,
} from '@/components/ui/sidebar'
import type { Sidebar08MainNavItem } from '@/lib/nav-modules'

export type AppSidebarProject = { name: string; url: string; icon: LucideIcon }

export type AppSidebarModuleSection = {
  id: string
  label?: string
  items: Sidebar08MainNavItem[]
}

function AppSidebarComponent({
  brandTitle,
  brandSubtitle,
  brandTo,
  pinnedItems = [],
  moduleSections = [],
  projects = [],
  projectsLabel,
  navLoading,
  footerHint,
  user,
  logoutLabel,
  onLogout,
  ...props
}: React.ComponentProps<typeof Sidebar> & {
  brandTitle: string
  brandSubtitle: string
  brandTo: string
  pinnedItems?: Sidebar08MainNavItem[]
  moduleSections?: AppSidebarModuleSection[]
  projects?: AppSidebarProject[]
  projectsLabel?: string
  navLoading: boolean
  footerHint?: string
  user: { name: string; email: string; avatar?: string }
  logoutLabel: string
  onLogout: () => void
}) {
  return (
    <Sidebar collapsible="icon" className="border-s" {...props}>
      <SidebarHeader>
        <SiteBrand title={brandTitle} subtitle={brandSubtitle} homeTo={brandTo} />
      </SidebarHeader>
      <SidebarContent>
        {navLoading ? (
          <SidebarGroup>
            <SidebarMenu>
              {[0, 1, 2, 3, 4, 5].map((i) => (
                <SidebarMenuItem key={i}>
                  <SidebarMenuSkeleton showIcon />
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroup>
        ) : (
          <>
            {pinnedItems.length > 0 ? <NavPinned items={pinnedItems} /> : null}
            {moduleSections.map((section) => (
              <NavMain key={section.id} items={section.items} groupLabel={section.label} />
            ))}
          </>
        )}
        {footerHint ? (
          <p className="text-sidebar-foreground/70 px-2 py-2 text-xs leading-relaxed group-data-[collapsible=icon]:hidden">
            {footerHint}
          </p>
        ) : null}
        {projects.length > 0 && projectsLabel ? (
          <NavProjects projects={projects} groupLabel={projectsLabel} />
        ) : null}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={user} logoutLabel={logoutLabel} onLogout={onLogout} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}

export const AppSidebar = memo(AppSidebarComponent)
