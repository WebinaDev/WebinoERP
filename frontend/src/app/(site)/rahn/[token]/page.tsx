'use client';

import { useParams } from 'next/navigation';
import { RahnPublicEstimate } from '@/features/modules/sales/rahn_percent/rahn-public-estimate';

export default function RahnPublicPage() {
  const params = useParams();
  return <RahnPublicEstimate token={String(params?.token ?? '')} />;
}
