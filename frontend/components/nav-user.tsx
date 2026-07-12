'use client';

import { ChevronsUpDown, LogOut, Moon, Palette, Sun, User } from 'lucide-react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTheme } from 'next-themes';
import { useTranslations } from 'next-intl';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { ACCENT_MENU_ITEMS } from '@/lib/accent';
import { setAccent } from '@/components/providers/theme-provider';
import { logout } from '@/lib/auth';

type Props = {
  user: { name: string; email: string; avatar?: string };
  logoutLabel: string;
  onLogout: () => void;
};

export function NavUser({ user, logoutLabel, onLogout }: Props) {
  const { isMobile } = useSidebar();
  const { setTheme } = useTheme();
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const initials = user.name
    .split(' ')
    .map((p) => p[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="size-8 rounded-lg">
                <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-start text-sm leading-tight">
                <span className="truncate font-semibold">{user.name}</span>
                <span className="truncate text-xs text-sidebar-foreground/70">{user.email}</span>
              </div>
              <ChevronsUpDown className="ms-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
            side={isMobile ? 'bottom' : 'right'}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="p-0 font-normal">
              <div className="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                <Avatar className="size-8 rounded-lg">
                  <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-start text-sm leading-tight">
                  <span className="truncate font-semibold">{user.name}</span>
                  <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                </div>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href="/dashboard/profile">
                <User className="me-2 size-4" />
                {t('nav.erp.profile')}
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSub>
              <DropdownMenuSubTrigger>
                <Sun className="me-2 size-4" />
                {t('settings.theme')}
              </DropdownMenuSubTrigger>
              <DropdownMenuSubContent>
                <DropdownMenuItem onClick={() => setTheme('light')}>
                  <Sun className="me-2 size-4" />
                  {t('settings.themeLight')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setTheme('dark')}>
                  <Moon className="me-2 size-4" />
                  {t('settings.themeDark')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => setTheme('system')}>
                  {t('settings.themeSystem')}
                </DropdownMenuItem>
              </DropdownMenuSubContent>
            </DropdownMenuSub>
            <DropdownMenuSub>
              <DropdownMenuSubTrigger>
                <Palette className="me-2 size-4" />
                {t('settings.accent')}
              </DropdownMenuSubTrigger>
              <DropdownMenuSubContent>
                {ACCENT_MENU_ITEMS.map((item) => (
                  <DropdownMenuItem key={item.value} onClick={() => setAccent(item.value)}>
                    {t(item.labelKey)}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuSubContent>
            </DropdownMenuSub>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => {
                void logout();
                onLogout();
              }}
            >
              <LogOut className="me-2 size-4" />
              {logoutLabel}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}
