export type DashboardModule = {
  id: string;
  title: string;
  path: string;
  icon?: string;
  children?: DashboardModule[];
  pinned?: boolean;
};
