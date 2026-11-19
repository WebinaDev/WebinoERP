'use client';

import { useEffect, useState } from 'react';
import { Cpu, Printer, Router, Plug, Activity } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WebinoHardwareGatewayClient, type GatewayHealth } from '@/lib/hardware-gateway-client';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC } from '@/features/shared/erp-resource';

const NS = 'erp.device_gateway';

/** Status of the helper app on this computer (http://127.0.0.1:9100). */
function LocalAgentStatus() {
  const t = useTranslations(NS);
  const [health, setHealth] = useState<GatewayHealth | null>(null);
  const [checking, setChecking] = useState(true);
  const check = () => {
    setChecking(true);
    new WebinoHardwareGatewayClient()
      .checkHealth()
      .then(setHealth)
      .catch(() => setHealth(null))
      .finally(() => setChecking(false));
  };
  useEffect(check, []);
  const online = Boolean(health && health.status !== 'offline');
  return (
    <div className="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-start gap-3">
        <span className={`mt-1.5 size-2.5 shrink-0 rounded-full ${online ? 'bg-emerald-500' : 'bg-amber-500'}`} />
        <div>
          <p className="text-sm font-semibold">
            {t('localAgent')} <Badge variant="outline">{checking ? t('checking') : online ? t('online') : t('offline')}</Badge>
          </p>
          <p className="mt-1 text-xs text-muted-foreground">{online ? t('localAgentOnline', { version: health?.version ?? '' }) : t('localAgentOffline')}</p>
        </div>
      </div>
      <Button size="sm" variant="outline" onClick={check} disabled={checking}>
        <Activity className="size-4 me-1" />
        {t('recheck')}
      </Button>
    </div>
  );
}

export function DeviceGatewayOverviewPage() {
  return (
    <ErpModuleHub
      ns={NS}
      stats={<LocalAgentStatus />}
      links={[
        { route: 'device-gateway/gateways', titleKey: 'gateways', descKey: 'gatewaysDesc', icon: <Router className="size-5" /> },
        { route: 'device-gateway/peripherals', titleKey: 'peripherals', descKey: 'peripheralsDesc', icon: <Plug className="size-5" /> },
        { route: 'device-gateway/print-jobs', titleKey: 'printJobs', descKey: 'printJobsDesc', icon: <Printer className="size-5" /> },
      ]}
    />
  );
}

export function DeviceGatewayGatewaysPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="gatewaysDesc"
      endpoint="/v1/device-gateway/gateways"
      createLabel="newGateway"
      columns={[
        { key: 'name', label: 'name' },
        { key: 'os_platform', label: 'os_platform', type: 'badge' },
        { key: 'company.name', label: 'company' },
        { key: 'branch.name', label: 'branch' },
        { key: 'ip_address', label: 'ip_address' },
        { key: 'gateway_key', label: 'gateway_key', render: (r) => <code className="text-xs" dir="ltr">{String(r.gateway_key ?? '')}</code> },
        { key: 'last_heartbeat_at', label: 'last_heartbeat_at', type: 'datetime' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'name', label: 'name', required: true },
        { name: 'os_platform', label: 'os_platform', type: 'select', options: ['windows', 'linux', 'macos', 'android', 'ios'], required: true, defaultValue: 'windows' },
        { name: 'company_id', label: 'company', type: 'remote', remote: SRC.companies },
        { name: 'branch_id', label: 'branch', type: 'remote', remote: SRC.branches },
        { name: 'warehouse_id', label: 'warehouse', type: 'remote', remote: SRC.warehouses },
        { name: 'ip_address', label: 'ip_address' },
      ]}
    >
      <LocalAgentStatus />
    </ErpResourcePage>
  );
}

export function DeviceGatewayPeripheralsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="peripheralsDesc"
      endpoint="/v1/device-gateway/peripherals"
      createLabel="newPeripheral"
      columns={[
        { key: 'name', label: 'name' },
        { key: 'device_type', label: 'device_type', type: 'badge' },
        { key: 'model', label: 'model' },
        { key: 'gateway.name', label: 'gateway' },
        { key: 'connection_type', label: 'connection_type', type: 'badge' },
        { key: 'port_identifier', label: 'port_identifier' },
        { key: 'is_default', label: 'is_default', type: 'bool' },
      ]}
      createFields={[
        { name: 'gateway_id', label: 'gateway', type: 'remote', remote: SRC.gateways, required: true },
        { name: 'device_type', label: 'device_type', type: 'select', options: ['label_printer', 'receipt_printer', 'barcode_scanner', 'scale', 'pos_terminal'], required: true },
        { name: 'name', label: 'name', required: true },
        { name: 'model', label: 'model' },
        { name: 'connection_type', label: 'connection_type', type: 'select', options: ['usb', 'network', 'serial', 'bluetooth'], required: true },
        { name: 'port_identifier', label: 'port_identifier' },
        { name: 'is_default', label: 'is_default', type: 'bool' },
      ]}
    />
  );
}

export function DeviceGatewayPrintJobsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="printJobsDesc"
      endpoint="/v1/device-gateway/print-jobs"
      createEndpoint="/v1/device-gateway/print-jobs/dispatch"
      createLabel="newPrintJob"
      columns={[
        { key: 'id', label: 'number' },
        { key: 'peripheral.name', label: 'peripheral' },
        { key: 'peripheral.gateway.name', label: 'gateway' },
        { key: 'job_type', label: 'job_type', type: 'badge' },
        { key: 'creator.name', label: 'created_by' },
        { key: 'created_at', label: 'date', type: 'datetime' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      createFields={[
        { name: 'peripheral_id', label: 'peripheral', type: 'remote', remote: SRC.peripherals, required: true },
        { name: 'job_type', label: 'job_type', type: 'select', options: ['barcode_label', 'receipt', 'packing_slip', 'shipping_label'], required: true },
        { name: 'payload', label: 'payload', type: 'textarea', required: true },
      ]}
    />
  );
}
