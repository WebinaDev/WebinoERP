import { RoutePageSkeleton } from '@/features/shared/skeletons/RoutePageSkeleton';

export default function DashboardLoading() {
  return (
    <div className="p-4 md:p-6">
      <RoutePageSkeleton />
    </div>
  );
}
