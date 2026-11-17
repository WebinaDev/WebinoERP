# WebinoERP Architectural Blueprint & Full-System Vision

This document details the complete enterprise architectural blueprint, design decisions, data models, module topologies, and integration contracts of **WebinoERP**.

---

## 1. Executive Summary & Design Principles

WebinoERP is an enterprise-grade ERP & Business Operating System engineered for multi-entity, multi-tenant corporate operations with strict data sovereignty, modular extensibility, offline-tolerant edge gateways, and a unified access control plane.

### Core Architectural Axioms
1. **Multi-Organization & Branch First (`Org`)**: Every transaction, ledger entry, order, ticket, and asset belongs to an explicit corporate entity (`org_id`) and operational branch (`branch_id`).
2. **Unified Access Control Plane (`Authz`)**: A centralized 4-tier decision engine (Membership & Scopes $\to$ RBAC $\to$ ABAC/Policies $\to$ Field-Level Redaction) replaces ad-hoc gate checks across all modules.
3. **Decoupled Workflow & Approval Engine (`Workflow`)**: Cross-department approvals (PRs, credit limits, journal entries, CAPA) run through an asynchronous state machine with SLA tracking and delegations.
4. **Physical-Digital Edge Convergence (`DeviceGateway`)**: Remote branches, warehouses, and POS counters interface via localized edge gateways handling thermal printing (ESC/POS, ZPL), barcode scanning, and weighing scales.
5. **Continuous Quality & Asset Reliability (`Qm` & `Assets`)**: Comprehensive closed-loop defect tracking, Non-Conformance Reports (NCR), CAPA, straight-line/declining asset depreciation, and maintenance schedules.
6. **Unified Procurement & 3-Way Match (`Procurement`)**: Rigorous spend control from Purchase Requests (PR) and RFQ tender comparison to Purchase Orders (PO), Goods Receipts (GRN), and invoice matching against Accounts Payable.
7. **Business Intelligence & Executive Observability (`Bi`)**: Operational data mart aggregations, multi-tenant KPI dashboards, alert thresholds, and automated report subscriptions.

---

## 2. Complete Enterprise Module Topography

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              WebinoERP Control Plane                            │
│                                                                                 │
│   ┌───────────────────────────┐           ┌─────────────────────────────────┐   │
│   │   Org & Multi-Company     │           │   Unified Access Plane (Authz)  │   │
│   │   Entities, Branches, Seq │           │   RBAC + ABAC + FLS + Audit Log │   │
│   └─────────────┬─────────────┘           └────────────────┬────────────────┘   │
│                 │                                          │                    │
│                 ▼                                          ▼                    │
│   ┌─────────────────────────────────────────────────────────────────────────┐   │
│   │                   Workflow & Approval Engine (Workflow)                 │   │
│   │                   State Machine, SLA Watchdog, Delegations              │   │
│   └─────────────────────────────────────┬───────────────────────────────────┘   │
└─────────────────────────────────────────┼───────────────────────────────────────┘
                                          │
        ┌─────────────────────────────────┴─────────────────────────────────┐
        ▼                                                                   ▼
┌───────────────────────────────┐                               ┌───────────────────────────────┐
│       Core Operations         │                               │     Finance & Enterprise      │
├───────────────────────────────┤                               ├───────────────────────────────┤
│ • Procurement (PR/RFQ/PO/GRN) │                               │ • Finance (GL, AP, AR, Fiscal)│
│ • SCM (Inventory, Multi-Wh)   │                               │ • Treasury (Cash, Bank, Cheq) │
│ • Mfg (BOM, Routing, Orders)  │                               │ • Fixed Assets (Depr, Maint)  │
│ • DeviceGateway (Edge/Print)  │                               │ • BI & Executive KPI Marts    │
└───────────────┬───────────────┘                               └───────────────┬───────────────┘
                │                                                               │
                └───────────────────────────────┬───────────────────────────────┘
                                                ▼
                                ┌───────────────────────────────┐
                                │      Engagement & Growth      │
                                ├───────────────────────────────┤
                                │ • CRM (Leads, Deals, Funnel)  │
                                │ • HRM (Staff, Payroll, Shifts)│
                                │ • FieldService (FSM Dispatch) │
                                │ • Quality Management (QM/NCR) │
                                └───────────────────────────────┘
```

---

## 3. Deep Dive: Architectural Subsystems

### 3.1 Organization & Operational Boundaries (`backend/Modules/Org`)
- **Multi-Company Entity Hierarchy**: `org_entities` supports holding companies, subsidiaries, legal business units, and franchise operators.
- **Branches & Cost Centers**: `org_branches` defines geographic and operational sites (warehouses, plants, retail stores).
- **Document Numbering Engine**: `SequenceGeneratorService` generates sequential document codes per branch and fiscal period (e.g., `TEH-INV-2026-00421`) preventing collision across concurrent nodes.
- **Inter-Branch Transfers**: Formal transfers between branches with in-transit reconciliation and audit logging.

### 3.2 Unified Access Control Plane (`backend/Modules/Authz`)
- **4-Tier Evaluation Pipeline**:
  1. *Membership & Tenant Check*: Validates user active membership and assigned branch scope.
  2. *RBAC Core*: Role inheritance and explicit permission catalog (e.g., `procurement.orders.create`).
  3. *ABAC / Policy Engine*: Dynamic rules evaluating resource attributes (e.g., `amount <= 50000000`, `department == 'logistics'`).
  4. *Field-Level Security (FLS)*: Masking or redaction of sensitive columns (e.g., `unit_cost`, `bank_account_number`) for specific viewer roles.
- **Access Simulation & Forensics**: Dedicated simulator API (`/api/v1/authz/simulate`) and immutable audit trails (`authz_access_logs`).

### 3.3 Central Workflow & Approval Engine (`backend/Modules/Workflow`)
- **State Machine Definition**: Configurable workflow definitions with sequential, parallel, or threshold-based approval steps.
- **Universal Inbox & Escalations**: Consolidated task inbox for managers across all business modules with SLA tracking and auto-escalation.
- **Delegation Matrix**: Temporary delegation of authority during manager leaves with time-bounded expiration and full audit lineage.

### 3.4 Sourcing & Procurement (`backend/Modules/Procurement`)
- **Purchase Requests (PR)**: Inter-departmental requisitions with budgetary pre-validation.
- **Request for Quotation (RFQ)**: Multi-vendor bidding, vendor comparison sheets, and quote finalization.
- **Purchase Orders (PO)**: Vendor order binding with delivery terms, tax breakdown, and currency handling.
- **Goods Receipt Notes (GRN)**: Physical inspection, warehouse bin assignment, and quantity verification.
- **3-Way Matching Engine**: `ProcurementMatchingService` verifies invoice quantities and unit costs against PO and GRN, flagging discrepancies over defined tolerance limits.

### 3.5 Edge Hardware & Device Gateway (`backend/Modules/DeviceGateway`)
- **Edge Gateway Nodes**: Lightweight client agents deployed in branch offices and logistics centers sending heartbeats over secure websockets/HTTPS.
- **Peripheral Topology**: Hardware abstraction for ESC/POS thermal receipt printers, ZPL/EPL industrial barcode label printers, USB/Serial scanners, and digital scale interfaces.
- **Job Dispatcher Queue**: Reliable, asynchronous print job spooling (`dev_print_jobs`) with retry limits, payload checksums, and delivery acknowledgments.

### 3.6 Quality Management (`backend/Modules/Qm`)
- **Inspection Framework**: Pre-defined inspection checklists across Receiving (GRN), In-Process (Manufacturing), and Outbound (Shipping).
- **Non-Conformance Reports (NCR)**: Formal tracking of material deviations, quarantine protocols, and root cause analyses.
- **CAPA Action Plans**: Corrective and Preventive Actions with owner assignment, implementation milestones, and effectiveness verification.

### 3.7 Fixed Asset Lifecycle (`backend/Modules/Assets`)
- **Asset Master Registry**: Categorization, location hierarchy, serial numbers, warranty terms, and QR tracking.
- **Depreciation Engine**: Straight-line and declining-balance depreciation schedules with automated monthly General Ledger journal voucher generation.
- **Asset Maintenance & Custody**: Preventive maintenance work orders, breakdown logs, and inter-branch custody transfer history.

### 3.8 Field Service Management (`backend/Modules/FieldService`)
- **Work Order Lifecycle**: Service requests, triage, SLA categorization, and engineer dispatch.
- **Technician Dispatch & Routing**: Skills-based scheduling, GPS route optimization, and parts inventory reservation.
- **On-Site Execution**: Digital customer sign-off, technician labor logs, and consumed spare parts billing.

### 3.9 Business Intelligence & Executive Analytics (`backend/Modules/Bi`)
- **Enterprise Data Mart**: Scheduled denormalization of multi-module OLTP records into fast analytical tables.
- **Role-Based Executive Dashboards**: C-Level revenue, liquidity, lead conversion, production yield, and inventory turnover KPIs.
- **Alert & Anomaly Engine**: Configurable thresholds triggering real-time alerts upon margin erosion or critical inventory depletion.
- **Scheduled Subscriptions**: Automated daily/weekly PDF and Excel digest delivery to stakeholders.

---

## 4. Quality Assurance, Strict Audits & Verification

All modules adhere to strict repository standards verified via automated tooling:
1. **Route Inventory & Manifest Parity**: 154 full ERP routes generated across `routes.manifest.json` with 0 parity gaps (`audit:status:strict`).
2. **Type Safety & Code Quality**: Clean TypeScript verification (`tsc --noEmit`) with 0 errors across all module components, page maps, and registry definitions.
3. **Bilingual Completeness**: 100% localization parity across Persian (`fa.json`) and English (`en.json`) verified via `check:i18n:usage`.
4. **Backend Syntax Verification**: 100% of newly authored PHP services, controllers, entities, and migrations parsed and verified through AST engines without syntax violations.
