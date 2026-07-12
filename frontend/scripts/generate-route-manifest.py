#!/usr/bin/env python3
"""Fallback manifest generator when Node is unavailable. Mirrors manifest-builder.mjs."""
from __future__ import annotations

import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT = SCRIPT_DIR.parent
REPO = ROOT.parent

API_WILDCARD = {
    "/v1/core/chat/*": "/api/v1/core/chat/channels",
    "/v1/integrations/bale/*": "/api/webinocrm/v1/bale/settings",
    "/webinocrm/v1/hosting/*": "/api/webinocrm/v1/hosting/settings",
    "/v1/core/auth/*": None,
}

API_OVERRIDES = {
    "/v1/core/users/me": "/api/v1/core/auth/user",
    "/v1/marketplace/gitea": "/api/v1/marketplace/gitea/settings",
    "/v1/integrations/modirpayamak/users": "/api/v1/integrations/modirpayamak/admin/dashboard",
    "/v1/integrations/modirpayamak/tickets": "/api/v1/integrations/modirpayamak/admin/dashboard",
    "/v1/integrations/modirpayamak/drafts": "/api/v1/integrations/modirpayamak/admin/dashboard",
    "/v1/integrations/modirpayamak/send": "/api/v1/integrations/modirpayamak/send",
    "/v1/integrations/modirpayamak/reports": "/api/v1/integrations/modirpayamak/reports",
    "/v1/integrations/modirpayamak/patterns": "/api/v1/integrations/modirpayamak/patterns",
    "/v1/integrations/modirpayamak/numbers": "/api/v1/integrations/modirpayamak/numbers",
    "/v1/integrations/modirpayamak/phonebooks": "/api/v1/integrations/modirpayamak/phonebooks",
    "/v1/integrations/modirpayamak/packages": "/api/v1/integrations/modirpayamak/admin/packages",
    "/v1/integrations/modirpayamak/orders": "/api/v1/integrations/modirpayamak/admin/orders",
    "/v1/integrations/modirpayamak/settings": "/api/v1/integrations/modirpayamak/settings",
    "/v1/integrations/modirpayamak/customers": "/api/v1/integrations/modirpayamak/admin/customers",
}

FEATURE_TEST_ROUTES = {
    "",
    "hrm/staff",
    "finance/journals",
    "crm/deals",
    "pm/projects",
    "scm/inbound",
    "sales/invoices",
    "admin/integrations/modirpayamak",
    "admin/integrations/modirpayamak/send",
    "admin/integrations/modirpayamak/tickets",
    "admin/integrations/modirpayamak/users",
    "admin/integrations/modirpayamak/drafts",
    "docs/contracts",
    "admin/marketplace/modules/new",
    "admin/licenses",
    "admin/settings",
    "admin/integrations/bale",
    "mfg",
    "mfg/boms",
    "mfg/work-orders",
    "mfg/quality",
    "mfg/planning",
    "admin/hosting-infra",
}


def build_inventory() -> list[dict]:
    """Mirror route-inventory.mjs entries (including spread arrays)."""
    rows: list[dict] = [
        {"module": "shell", "route": "", "component": "DashboardHomePage", "api": "/v1/core/dashboard"},
        {"module": "shell", "route": "login", "component": "LoginForm", "api": "/v1/core/auth/*"},
        {"module": "shell", "route": "reports", "component": "ReportsPageView", "api": "/v1/core/reports"},
        {"module": "shell", "route": "profile", "component": "ProfilePageView", "api": "/v1/core/users/me"},
        {"module": "hrm", "route": "hrm/staff", "component": "StaffPage", "api": "/v1/hrm/employees"},
        {"module": "hrm", "route": "hrm/staff/:id", "component": "StaffDetailPage", "api": "/v1/hrm/employees/{id}"},
        {"module": "hrm", "route": "hrm/attendance", "component": "AttendancePage", "api": "/v1/hrm/attendance"},
        {"module": "hrm", "route": "hrm/leave", "component": "LeavePage", "api": "/v1/hrm/leave"},
        {"module": "hrm", "route": "hrm/payroll", "component": "PayrollPage", "api": "/v1/hrm/payroll"},
        {"module": "hrm", "route": "hrm/payroll/:id", "component": "PayrollRunDetailPage", "api": "/v1/hrm/payroll/runs/{id}"},
        {"module": "hrm", "route": "hrm/recruitment", "component": "RecruitmentPage", "api": "/v1/hrm/recruitment"},
        {"module": "hrm", "route": "hrm/performance", "component": "PerformancePage", "api": "/v1/hrm/performance"},
        {"module": "hrm", "route": "hrm/training", "component": "TrainingPage", "api": "/v1/hrm/training"},
    ]

    finance = [
        ("finance", "AccountingDashboardPage", "/v1/accounting/summary"),
        ("finance/persons", "PersonsPage", "/v1/accounting/persons"),
        ("finance/products", "FinanceProductsPage", "/v1/accounting/products"),
        ("finance/invoices", "FinanceInvoicesPage", "/v1/accounting/invoices"),
        ("finance/cash-accounts", "CashAccountsPage", "/v1/accounting/cash-accounts"),
        ("finance/receipts", "ReceiptsPage", "/v1/accounting/receipts"),
        ("finance/checks", "ChecksPage", "/v1/accounting/checks"),
        ("finance/chart", "ChartOfAccountsPage", "/v1/accounting/chart"),
        ("finance/journals", "JournalsPage", "/v1/accounting/journals"),
        ("finance/ledger", "LedgerPage", "/v1/accounting/ledger"),
        ("finance/reports", "AccountingReportsPage", "/v1/accounting/reports"),
        ("finance/fiscal-year", "FiscalYearPage", "/v1/accounting/fiscal-years"),
        ("finance/settings", "AccountingSettingsPage", "/v1/accounting/settings"),
    ]
    for route, component, api in finance:
        rows.append({"module": "finance", "route": route, "component": component, "api": api})

    rows.extend([
        {"module": "crm", "route": "crm/leads", "component": "LeadsListPage", "api": "/v1/crm/leads"},
        {"module": "crm", "route": "crm/customers", "component": "CustomersListPage", "api": "/v1/crm/accounts"},
        {"module": "crm", "route": "crm/customers/:id", "component": "EntityDetailPage", "api": "/v1/crm/accounts/{id}"},
        {"module": "crm", "route": "crm/tickets", "component": "TicketsListPage", "api": "/v1/projects/tickets"},
        {"module": "crm", "route": "crm/consultations", "component": "ConsultationsListPage", "api": "/v1/crm/consultations"},
        {"module": "crm", "route": "crm/deals", "component": "DealsKanbanPage", "api": "/v1/crm/deals"},
        {"module": "crm", "route": "crm/pipelines", "component": "PipelinesPage", "api": "/v1/crm/pipelines"},
        {"module": "pm", "route": "pm/projects", "component": "ProjectsListPage", "api": "/v1/projects/projects"},
        {"module": "pm", "route": "pm/projects/:id", "component": "EntityDetailPage", "api": "/v1/projects/projects/{id}/details"},
        {"module": "pm", "route": "pm/tasks", "component": "TasksKanbanPage", "api": "/v1/projects/tasks"},
        {"module": "pm", "route": "pm/chat", "component": "ChatPage", "api": "/v1/core/chat/*"},
        {"module": "pm", "route": "pm/time-tracking", "component": "TimeTrackingPage", "api": "/v1/projects/time-entries"},
        {"module": "pm", "route": "pm/appointments", "component": "AppointmentsListPage", "api": "/v1/projects/appointments"},
    ])

    scm_components = ["WarehousesPage", "StockPage", "InboundPage", "OutboundPage", "AuditPage"]
    for i, seg in enumerate(["warehouses", "stock", "inbound", "outbound", "audit"]):
        rows.append({
            "module": "scm",
            "route": f"scm/{seg}",
            "component": scm_components[i],
            "api": f"/v1/scm/{seg}",
        })

    rows.extend([
        {"module": "sales", "route": "sales/invoices", "component": "SalesInvoicesPage", "api": "/v1/sales/invoices"},
        {"module": "sales", "route": "sales/catalog", "component": "CatalogPage", "api": "/v1/sales/catalog"},
        {"module": "sales", "route": "sales/campaigns", "component": "CampaignsPage", "api": "/v1/sales/campaigns"},
        {"module": "sales/modirpayamak", "route": "admin/integrations/modirpayamak", "component": "ModirpayamakPage", "api": "/v1/integrations/modirpayamak/admin/dashboard"},
    ])

    for s in ["send", "reports", "customers", "packages", "orders", "patterns", "phonebooks", "numbers", "settings"]:
        api = f"/v1/integrations/modirpayamak/{'admin/customers' if s == 'customers' else s}"
        rows.append({
            "module": "sales/modirpayamak",
            "route": f"admin/integrations/modirpayamak/{s}",
            "component": f"Modirpayamak{s[0].upper()}{s[1:]}Page",
            "api": api,
        })

    for s in ["users", "tickets", "drafts"]:
        rows.append({
            "module": "sales/modirpayamak",
            "route": f"admin/integrations/modirpayamak/{s}",
            "component": f"Modirpayamak{s[0].upper()}{s[1:]}Page",
            "api": f"/v1/integrations/modirpayamak/{s}",
        })

    rows.append({"module": "sales/bale", "route": "admin/integrations/bale", "component": "BaleBusinessDashboard", "api": "/v1/integrations/bale/*"})
    rows.extend([
        {"module": "docs", "route": "docs/contracts", "component": "ContractsPage", "api": "/v1/docs/contracts"},
        {"module": "docs", "route": "docs/contracts/:id", "component": "EntityDetailPage", "api": "/v1/docs/contracts/{id}"},
        {"module": "docs", "route": "docs/files", "component": "FilesPage", "api": "/v1/docs/files"},
        {"module": "distribution", "route": "admin/marketplace/products", "component": "ProductsPage", "api": "/v1/marketplace/products"},
        {"module": "distribution", "route": "admin/marketplace/categories", "component": "CategoriesPage", "api": "/v1/marketplace/categories"},
        {"module": "distribution", "route": "admin/marketplace/orders", "component": "OrdersPage", "api": "/v1/marketplace/orders"},
        {"module": "distribution", "route": "admin/marketplace/gitea", "component": "GiteaPage", "api": "/v1/marketplace/gitea"},
        {"module": "distribution", "route": "admin/marketplace/modules/new", "component": "ModuleDetailPage (new)", "api": "/v1/marketplace/modules"},
        {"module": "distribution", "route": "admin/marketplace/modules/:id", "component": "ModuleDetailPage", "api": "/v1/marketplace/modules/{id}"},
        {"module": "distribution", "route": "admin/licenses", "component": "LicensesPageView", "api": "/v1/core/licenses"},
        {"module": "admin", "route": "admin/logs", "component": "LogsPageView", "api": "/v1/core/logs"},
        {"module": "admin", "route": "admin/analytics/visitors", "component": "VisitorStatsPageView", "api": "/v1/core/visitor-stats"},
        {"module": "admin", "route": "admin/settings", "component": "SettingsHubPage", "api": "/v1/core/settings"},
        {"module": "admin", "route": "admin/settings/general/:tab?", "component": "SettingsHubPage", "api": "/v1/core/settings"},
        {"module": "admin", "route": "admin/settings/projects/:tab?", "component": "SettingsHubPage", "api": "/v1/core/settings"},
        {"module": "admin", "route": "admin/settings/crm/:tab?", "component": "SettingsHubPage", "api": "/v1/core/settings"},
        {"module": "admin", "route": "admin/settings/bots", "component": "SettingsHubPage", "api": "/v1/core/settings"},
        {"module": "admin", "route": "admin/settings/accounting/:tab?", "component": "SettingsHubPage", "api": "/v1/core/settings"},
    ])

    for route, api in [
        ("mfg", "/v1/mfg/overview"),
        ("mfg/boms", "/v1/mfg/boms"),
        ("mfg/work-orders", "/v1/mfg/work-orders"),
        ("mfg/quality", "/v1/mfg/inspections"),
        ("mfg/planning", "/v1/mfg/planning/mrp"),
    ]:
        rows.append({"module": "mfg", "route": route, "component": route, "api": api})

    rows.append({"module": "admin", "route": "admin/hosting-infra", "component": "HostingInfraPageView", "api": "/webinocrm/v1/hosting/*"})
    return rows


def resolve_e2e_path(route: str) -> str:
    return route.replace(":id", "1").replace(":tab?", "general")


def resolve_api_path(api_hint: str | None) -> str | None:
    if not api_hint or api_hint == "—":
        return None
    if api_hint in API_OVERRIDES:
        return API_OVERRIDES[api_hint]
    if api_hint in API_WILDCARD:
        return API_WILDCARD[api_hint]
    path = api_hint
    if path.startswith("/webinocrm/"):
        path = f"/api{path}"
    elif path.startswith("/v1/"):
        path = f"/api{path}"
    path = path.replace("{id}", "1")
    if "*" in path:
        return None
    return path


def infer_role(row: dict) -> str:
    route = row["route"]
    if route.startswith("finance") or row["module"] == "finance":
        return "finance_manager"
    return "system_manager"


def needs_mfg(row: dict) -> bool:
    return row["module"] == "mfg" or row["route"].startswith("mfg/")


def build_manifest(inventory: list[dict]) -> dict:
    routes = []
    seen: set[str] = set()
    for row in inventory:
        route = row["route"]
        if route in seen:
            continue
        seen.add(route)
        api_path = resolve_api_path(row.get("api"))
        is_detail = ":id" in route or "{id}" in (row.get("api") or "")
        entry = {
            "route": route,
            "module": row["module"],
            "apiHint": row.get("api"),
            "e2ePath": resolve_e2e_path(route),
            "e2eAuth": route != "login",
            "component": row.get("component", ""),
        }
        if api_path:
            smoke = {
                "method": "GET",
                "path": api_path,
                "role": infer_role(row),
                "allowNotFound": is_detail,
            }
            if needs_mfg(row):
                smoke["requiresModules"] = ["mfg"]
            entry["apiSmoke"] = smoke
        if route in FEATURE_TEST_ROUTES:
            entry["featureTest"] = True
        routes.append(entry)
    return {
        "generatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z"),
        "version": 1,
        "routes": routes,
    }


def write_manifest(manifest: dict) -> None:
    text = json.dumps(manifest, indent=2) + "\n"
    fe_out = ROOT / "e2e" / "routes.manifest.json"
    be_out = REPO / "backend" / "tests" / "fixtures" / "routes.manifest.json"
    be_out.parent.mkdir(parents=True, exist_ok=True)
    fe_out.write_text(text)
    be_out.write_text(text)


def try_node() -> bool:
    mjs = SCRIPT_DIR / "generate-route-manifest.mjs"
    try:
        subprocess.run(["node", str(mjs)], check=True, cwd=ROOT)
        return True
    except (FileNotFoundError, subprocess.CalledProcessError):
        return False


def main() -> int:
    if try_node():
        print("Generated via Node")
        return 0
    manifest = build_manifest(build_inventory())
    write_manifest(manifest)
    api_count = sum(1 for r in manifest["routes"] if "apiSmoke" in r)
    e2e_count = sum(1 for r in manifest["routes"] if r.get("e2eAuth"))
    print(f"Generated {len(manifest['routes'])} routes (Python fallback)")
    print(f"  API smoke: {api_count} | E2E auth: {e2e_count}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
