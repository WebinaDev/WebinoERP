# Webino Hardware Gateway — Complete Architectural Specification & Integration Blueprint

This document defines the production-ready architecture, API contracts, security envelope, client discovery SDK, and driver adapter topology for **Webino Hardware Gateway** (a local edge agent connecting WebinoERP with physical peripherals in branches, stores, and warehouses).

---

## 1. System Vision & Purpose

In retail POS counters, warehouse logistics, treasury desks, and branch offices, web browsers running ERP interfaces are sandboxed and cannot directly or reliably access:
1. **Bank POS Terminals (PC-POS)** via Serial/USB/Ethernet protocols (Saman Kish, Beh Pardakht, Asan Pardakht, Sadad, etc.).
2. **Thermal Receipt Printers & Cash Drawers** via ESC/POS protocol and RJ11 drawer kick pulse.
3. **Industrial Barcode & RFID Label Printers** via ZPL/EPL raw commands.
4. **Biometric Time & Attendance Machines** via ZKTeco UDP/TCP protocols (standalone clock-in logs).
5. **TWAIN/WIA Document & Check Scanners**.
6. **Electronic Weighing Scales & Serial Barcode Scanners**.

The **Webino Hardware Gateway** acts as a lightweight, secure local edge daemon installed on the cashier/clerk workstation or dedicated branch server, bridging WebinoERP (Next.js + Laravel API) to physical peripheral devices.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       WebinoERP Frontend (Browser)                          │
│                Next.js Client Components (POS, Billing, HR)                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ HTTP / WebSocket (Secure TLS)
                                       │ Localhost (127.0.0.1:9100) or Branch LAN IP
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        Webino Hardware Gateway                              │
│                                                                             │
│   ┌───────────────────────────────┐     ┌───────────────────────────────┐   │
│   │    REST & WebSocket Layer     │     │       Security & Auth         │   │
│   │   (Kestrel / Express / IPC)   │     │ (Pairing Key, Origin Whitelist)│  │
│   └───────────────┬───────────────┘     └───────────────┬───────────────┘   │
│                   │                                     │                   │
│                   ▼                                     ▼                   │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                     Device Hub & Dispatch Engine                    │   │
│   │        Lifecycle, Discovery, Spool Queue, Heartbeat, Fallback       │   │
│   └──────────────────────────────────┬──────────────────────────────────┘   │
│                                      │                                      │
│   ┌──────────────────────────────────┴──────────────────────────────────┐   │
│   │                          Driver Adapters                            │   │
│   ├──────────────┬──────────────┬──────────────┬──────────────┬─────────┤   │
│   │  PosAdapter  │PrintAdapter  │DrawerAdapter │AttendanceAdap│ Scanner │   │
│   │  (PSP PC-POS)│  (ESC/POS)   │  (RJ11 Kick) │(ZKTeco/Face) │ (TWAIN) │   │
│   └──────┬───────┴──────┬───────┴──────┬───────┴──────┬───────┴────┬────┘   │
└──────────┼──────────────┼──────────────┼──────────────┼────────────┼────────┘
           │              │              │              │            │
           ▼              ▼              ▼              ▼            ▼
      بانک کارتخوان    فیش‌پرینتر     کش‌دراور        حضور و غیاب    اسکنر اسناد
     (PC-POS LAN/COM) (ESC/POS USB) (RJ-11 Pin 2/5) (ZKTeco Stand) (TWAIN/WIA)
```

---

## 2. Core Architectural Components

### 2.1 API & Communication Layer
- **Local Endpoint**: `http://127.0.0.1:9100` (or `https://127.0.0.1:9101` with installed local root certificate authority).
- **WebSocket Gateway**: `ws://127.0.0.1:9100/ws` for bi-directional live telemetry:
  - Real-time card swipe detection and PIN-pad entry status.
  - Scale continuous weight streaming.
  - Printer paper-out and cover-open warnings.
  - Gateway connectivity heartbeat (every 5 seconds).

### 2.2 Security & Isolation Policy
1. **Host & Port Binding**: Default bind to `127.0.0.1:9100`. When multi-station LAN sharing is configured, the daemon explicitly binds to the branch LAN interface (e.g. `192.168.1.50:9100`) protected by subnet CIDR allowlists.
2. **CORS & Origin Whitelisting**:
   - Only approved domains (e.g., `*.webinoerp.com`, `http://localhost:3000` for development) can issue HTTP/WebSocket requests.
3. **Application Pairing Key (`X-Gateway-Auth-Token`)**:
   - The Gateway generates a secure 256-bit pairing token upon installation.
   - The ERP cashier settings store this token in browser local storage or branch settings.
   - Every API request must carry `X-Gateway-Auth-Token`.
4. **PCI-DSS Compliance / Zero-Card-Data Retention**:
   - The Gateway **never** persists, logs, or transmits plain PAN (Primary Account Number), CVV2, or PIN.
   - Only masked PAN (e.g., `6037-99**-****-1234`), Terminal ID, Trace No, and Reference No are captured and returned to ERP.

---

## 3. Peripheral Categorization & Driver Adapter Matrix

| Peripheral Group | Protocol / Interface | Supported Vendors & Hardware | Driver Adapter Name |
| :--- | :--- | :--- | :--- |
| **PC-POS (کارتخوان بانکی)** | RS-232 Serial, USB-to-UART, LAN/TCP | Saman Kish (SEP), Beh Pardakht Mellat, Asan Pardakht (Ap), Sadad Melli, Fanava, Iran Kish | `PosAdapter` |
| **Thermal Receipt (فیش‌پرینتر)** | ESC/POS via USB, Network TCP (Port 9100), Bluetooth | Bixolon, Epson, Sewoo, Rongta, Gainscha, Woosim | `PrinterAdapter` |
| **Cash Drawer (کش‌دراور)** | RJ-11 / RJ-12 Kick Pulse via Thermal Printer port | Standard 24V / 12V pulse pins (Pin 2 / Pin 5) | `CashDrawerAdapter` |
| **Barcode / Label Printer** | ZPL II, EPL, TSPL via USB or Ethernet | Zebra, TSC, Godex, Postek, Xprinter | `LabelPrinterAdapter` |
| **Time & Attendance (حضور و غیاب)** | UDP/TCP Socket 4370 (ZKTeco Protocol) | ZKTeco, Virdi, Kara2000, Timax, Suprema | `AttendanceAdapter` |
| **Document / Check Scanner** | TWAIN 2.4, WIA, USB mass scan | Avision, Canon, Fujitsu, Kodak | `ScannerAdapter` |
| **Digital Weighing Scales (ترازو)** | Serial RS-232 continuous streaming | Radin, Tozin Sadr, CAS, Mettler Toledo | `ScaleAdapter` |
| **Customer Display (نمایشگر مشتری)** | VFD / 20x2 alphanumeric serial commands | Posiflex, Bixolon, Generic VFD | `CustomerDisplayAdapter` |

---

## 4. REST & WebSocket Unified API Contract

All responses conform to the Webino Standard Gateway Envelope:

```typescript
export interface GatewayResponse<T = unknown> {
  success: boolean;
  code: 'OK' | 'DEVICE_OFFLINE' | 'DEVICE_BUSY' | 'TIMEOUT' | 'HARDWARE_ERROR' | 'UNAUTHORIZED';
  message: string;
  data: T;
  deviceId?: string;
  timestamp: string; // ISO-8601
}
```

### 4.1 System & Device Discovery Endpoints
- `GET /health`: Health check, daemon version, uptime, and active peripheral count.
- `GET /devices`: List of registered and auto-discovered peripherals with status (`online`, `offline`, `error`).
- `POST /devices`: Register or modify peripheral configuration (port, baud rate, timeout).
- `GET /devices/{id}/status`: Live ping/diagnostic on a specific device.

### 4.2 POS Payment Endpoints
- `POST /pos/charge`:
  - Request: `{ deviceId: "pos-01", amount: 1500000, payerMobile?: "09123456789", invoiceNumber: "INV-2026-0042" }`
  - Flow: Gateway locks POS, sends purchase packet, waits for customer card swipe and PIN, returns transaction confirmation with RRN (Retrieval Reference Number) and Trace No.
- `POST /pos/refund` / `POST /pos/reverse`: Transaction reversal in case of ERP ledger timeout.
- `GET /pos/last-result`: Query the result of the most recent swipe (for reconnection recovery).

### 4.3 Printing & Cash Drawer Endpoints
- `POST /print/receipt`: Send ESC/POS commands, formatted text, barcode, and QR code to a thermal printer.
- `POST /print/label`: Send raw ZPL / TSPL label scripts.
- `POST /cash-drawer/open`: Send trigger pulse to open the cash box (can also be linked automatically to `print/receipt`).

### 4.4 Biometric Attendance Endpoints
- `POST /attendance/sync`: Connect to ZKTeco clock-in device, fetch new unread punch logs.
- `GET /attendance/logs?since=...`: Return array of `{ userId, timestamp, verifyMode, punchType }`.
- `POST /attendance/users`: Provision or sync employee badges/IDs to device memory.

### 4.5 Digital Scale Endpoints
- `GET /scale/weight`: Return `{ weight: 1.450, unit: "kg", isStable: true }`.

---

## 5. Technology Stack & Deployment Model

1. **Recommended Daemon Stack (Windows First)**:
   - **Engine**: **C# / .NET 8 (AOT / Self-Contained)** or **Electron + Node.js (with `node-serialport` and `usb-device`)**.
   - **System Tray UI**: Runs quietly in the Windows notification tray with color-coded status (Green = All systems nominal, Orange = Device warning, Red = Disconnected).
   - **Service Mode**: Can run as a Windows Service (`sc.exe create WebinoGateway`) for headless branch servers.
2. **Auto-Updater**: Embedded Squirrel.Windows / WinSparkle checking GitHub Releases or Webino Private CDN.
3. **Deployment Topology**:
   - **Single-Till Store**: Gateway installed on the till PC (`127.0.0.1:9100`).
   - **Multi-Till Hypermarket / Branch**: Central Gateway on the branch server (`192.168.1.100:9100`), coordinating shared network receipt printers and branch attendance clocks.

---

## 6. Frontend Integration & Fallback Experience

WebinoERP includes a dedicated TypeScript client SDK (`WebinoGatewayClient`) with:
1. **Automatic Discovery & Heartbeat Monitoring**: Probes `127.0.0.1:9100/health` upon page mount.
2. **Stateful UI Banner**: When the Gateway is unreachable, hardware-dependent buttons (e.g., "پرداخت با کارتخوان", "چاپ سریع فیش", "باز کردن کشو") are gracefully disabled with an explanatory tooltip.
3. **Modal Dialog with Download Links**: Clear Persian/English instructions with one-click download of the latest installer (`WebinoGateway-Setup.exe`).
4. **Offline Resilience & Spool Recovery**: If the gateway is momentarily offline during a receipt print, jobs can be queued or routed to client-side standard browser print dialog (`window.print()`).
