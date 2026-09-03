'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, usePathname } from 'next/navigation';
import { Menu } from 'lucide-react';
import { fetchNavigation, type NavItem } from '@/lib/navigation';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

function NavLinks({
  items,
  locale,
  pathname,
}: {
  items: NavItem[];
  locale: string;
  pathname: string;
}) {
  return (
    <div className="flex flex-col gap-1 p-2">
      {items.map((item, i) => {
        if (item.type === 'category') {
          return (
            <div key={`cat-${i}`} className="px-2 pt-4 pb-1 text-xs font-semibold text-muted-foreground">
              {item.title}
            </div>
          );
        }
        if (!item.href) return null;
        const href = item.href;
        const active = pathname === href || pathname.startsWith(`${href}/`);
        return (
          <Link
            key={item.id ?? href}
            href={href}
            className={cn(
              'rounded-md px-3 py-2 text-sm transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
              active && 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
            )}
          >
            {item.title}
          </Link>
        );
      })}
    </div>
  );
}

export function DashboardSidebar() {
  const params = useParams();
  const pathname = usePathname();
  const locale = (params?.locale as string) || 'fa';
  const [items, setItems] = useState<NavItem[]>([]);
  const [role, setRole] = useState<string>('');

  useEffect(() => {
    fetchNavigation().then((data) => {
      if (data) {
        setItems(data.items);
        setRole(data.dashboard_role);
      }
    });
  }, []);

  const shell = (
    <>
      <div className="flex h-14 items-center border-b border-sidebar-border px-4">
        <span className="font-semibold">Webino CRM</span>
        {role ? (
          <span className="ms-auto text-xs text-muted-foreground" title="dashboard role">
            {role}
          </span>
        ) : null}
      </div>
      <ScrollArea className="h-[calc(100vh-3.5rem)]">
        <NavLinks items={items} locale={locale} pathname={pathname} />
      </ScrollArea>
    </>
  );

  return (
    <>
      <aside className="hidden w-64 shrink-0 border-e border-sidebar-border bg-sidebar text-sidebar-foreground md:flex md:flex-col">
        {shell}
      </aside>
      <div className="flex items-center border-b bg-background p-2 md:hidden">
        <Sheet>
          <SheetTrigger asChild>
            <Button variant="outline" size="icon" aria-label="Menu">
              <Menu className="h-5 w-5" />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" className="w-72 p-0 bg-sidebar text-sidebar-foreground">
            <SheetHeader className="sr-only">
              <SheetTitle>Navigation</SheetTitle>
            </SheetHeader>
            {shell}
          </SheetContent>
        </Sheet>
        <span className="ms-2 font-medium">Webino CRM</span>
      </div>
    </>
  );
}
