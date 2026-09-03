import { DashboardShell } from '@/features/shell/layout/DashboardShell';
import { DashboardAuthGuard } from '@/features/shell/auth/DashboardAuthGuard';

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <DashboardAuthGuard>
      <DashboardShell>{children}</DashboardShell>
    </DashboardAuthGuard>
  );
}
