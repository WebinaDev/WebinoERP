'use client';

import { useEffect, useState } from 'react';
import { getCurrentUser, User } from '@/lib/auth';

export function Sidebar() {
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
      { label: 'مشتریان', href: '/crm/accounts' },
      { label: 'فرصت‌ها', href: '/crm/deals' },
    ] : []),
  ];

  return (
    <aside className="w-64 border-r bg-background p-4">
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

