'use client';

import { useTranslations } from 'next-intl';

import { useEffect, useState } from 'react';
import { getCurrentUser, User } from '@/lib/auth';

export function Sidebar() {
  const t = useTranslations();

  const [user, setUser] = useState<User | null>(null);
  const [activeModules, setActiveModules] = useState<string[]>([]);

  useEffect(() => {
    getCurrentUser().then((userData) => {
      if (userData) {
        setUser(userData);
        setActiveModules(userData.active_modules || []);
      }
    });
  }, []);

  const menuItems = [
    ...(activeModules.includes('crm') ? [
      { label: t('auto.layout_Sidebar.s_1e5f08c7'), href: '/crm/accounts' },
      { label: t('auto.layout_Sidebar.s_db70186e'), href: '/crm/deals' },
    ] : []),
  ];

  return (
    <aside className="w-64 border-e bg-background p-4">
      <nav className="space-y-2">
        {menuItems.map((item) => (
          <a
            key={item.href}
            href={item.href}
            className="block rounded-lg px-4 py-2 hover:bg-accent"
          >
            {item.label}
          </a>
        ))}
      </nav>
    </aside>
  );
}

