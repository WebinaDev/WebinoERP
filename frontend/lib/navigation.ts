import apiClient from './api-client';

export interface NavItem {
  type?: string;
  id?: string;
  title?: string;
  href?: string;
  icon?: string;
}

export interface NavigationResponse {
  data: {
    dashboard_role: string;
    items: NavItem[];
  };
}

export async function fetchNavigation(): Promise<NavigationResponse['data'] | null> {
  try {
    const { data } = await apiClient.get<NavigationResponse>('/v1/core/navigation');
    return data.data;
  } catch {
    return null;
  }
}
