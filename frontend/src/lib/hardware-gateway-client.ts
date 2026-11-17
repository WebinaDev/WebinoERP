/**
 * WebinoERP Hardware Gateway Client SDK
 * Handles local hardware gateway discovery, heartbeats, POS transactions, thermal printing, and cash drawer triggers.
 */

export interface GatewayHealth {
  version: string;
  uptimeSeconds: number;
  devicesCount: number;
  status: 'healthy' | 'degraded' | 'offline';
}

export interface PeripheralDevice {
  id: string;
  name: string;
  type: 'pos' | 'printer' | 'drawer' | 'attendance' | 'scale' | 'scanner';
  driver: string;
  status: 'online' | 'offline' | 'busy' | 'error';
  connectionType: 'usb' | 'serial' | 'network' | 'bluetooth';
  address?: string;
}

export interface PosChargeRequest {
  deviceId?: string;
  amount: number;
  invoiceNumber: string;
  payerMobile?: string;
  timeoutSeconds?: number;
}

export interface PosChargeResponse {
  success: boolean;
  maskedPan: string;
  rrn: string;
  traceNo: string;
  terminalId: string;
  transactionTime: string;
  message?: string;
}

export interface PrintReceiptRequest {
  deviceId?: string;
  lines: Array<{
    text: string;
    align?: 'left' | 'center' | 'right';
    bold?: boolean;
    doubleHeight?: boolean;
  }>;
  qrCode?: string;
  cutPaper?: boolean;
  openDrawer?: boolean;
}

export interface ScaleReading {
  weight: number;
  unit: 'kg' | 'g' | 'lb';
  isStable: boolean;
}

export class WebinoHardwareGatewayClient {
  private baseUrl: string;
  private authToken: string;

  constructor(host: string = '127.0.0.1', port: number = 9100, authToken: string = '') {
    this.baseUrl = `http://${host}:${port}`;
    this.authToken = authToken;
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.headers as Record<string, string>),
    };

    if (this.authToken) {
      headers['X-Gateway-Auth-Token'] = this.authToken;
    }

    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      throw new Error(`Gateway Error (${response.status}): ${response.statusText}`);
    }

    const payload = await response.json();
    return payload.data ?? payload;
  }

  /**
   * Check if Webino Device Gateway daemon is active and responsive on the client machine.
   */
  async checkHealth(): Promise<GatewayHealth> {
    try {
      return await this.request<GatewayHealth>('/health', { method: 'GET' });
    } catch {
      return {
        version: '0.0.0',
        uptimeSeconds: 0,
        devicesCount: 0,
        status: 'offline',
      };
    }
  }

  /**
   * Get list of connected peripherals (POS, printers, drawers, scales).
   */
  async getDevices(): Promise<PeripheralDevice[]> {
    return this.request<PeripheralDevice[]>('/devices', { method: 'GET' });
  }

  /**
   * Initiate a card purchase transaction on bank POS terminal.
   */
  async chargePos(req: PosChargeRequest): Promise<PosChargeResponse> {
    return this.request<PosChargeResponse>('/pos/charge', {
      method: 'POST',
      body: JSON.stringify(req),
    });
  }

  /**
   * Print formatted thermal receipt and optionally kick open the cash drawer.
   */
  async printReceipt(req: PrintReceiptRequest): Promise<{ jobId: string; status: string }> {
    return this.request<{ jobId: string; status: string }>('/print/receipt', {
      method: 'POST',
      body: JSON.stringify(req),
    });
  }

  /**
   * Trigger the cash drawer kick pulse.
   */
  async openCashDrawer(deviceId?: string): Promise<{ success: boolean }> {
    return this.request<{ success: boolean }>('/cash-drawer/open', {
      method: 'POST',
      body: JSON.stringify({ deviceId }),
    });
  }

  /**
   * Fetch current live weight reading from digital scale.
   */
  async getWeight(deviceId?: string): Promise<ScaleReading> {
    const query = deviceId ? `?deviceId=${encodeURIComponent(deviceId)}` : '';
    return this.request<ScaleReading>(`/scale/weight${query}`, { method: 'GET' });
  }
}
