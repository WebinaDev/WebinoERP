#!/usr/bin/env python3
"""Bootstrap OpenAPI 3.1 spec from Laravel route files (until scramble:export runs in CI)."""

from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MODULES = ROOT / "backend" / "Modules"
OUT = ROOT / "docs-site" / "openapi" / "openapi.json"

MODULE_PREFIXES = {
    "Core/Routes/api.php": "api/v1/core",
    "Crm/Routes/api.php": "api/v1/crm",
    "Hrm/Routes/api.php": "api/v1/hrm",
    "Accounting/Routes/api.php": "api/v1/accounting",
    "Projects/Routes/api.php": "api/v1/projects",
    "Scm/Routes/api.php": "api/v1/scm",
    "Sales/Routes/api.php": "api/v1/sales",
    "Docs/Routes/api.php": "api/v1/docs",
    "Marketplace/Routes/api.php": "api/v1/marketplace",
    "Integrations/Routes/api.php": "api/v1/integrations",
}

ROUTE_RE = re.compile(
    r"Route::(get|post|put|patch|delete|apiResource)\s*\(\s*['\"]([^'\"]+)['\"]"
)
PREFIX_RE = re.compile(r"Route::prefix\(['\"]([^'\"]+)['\"]\)")

HTTP_METHODS = {
    "get": "get",
    "post": "post",
    "put": "put",
    "patch": "patch",
    "delete": "delete",
}

API_RESOURCE_OPS = {
    "index": "get",
    "store": "post",
    "show": "get",
    "update": "put",
    "destroy": "delete",
}


def normalize_path(prefix: str, route_path: str, group_prefixes: list[str]) -> str:
    parts = [p.strip("/") for p in group_prefixes if p]
    route_path = route_path.lstrip("/")
    if route_path in ("", "/"):
        path = "/".join(parts)
    else:
        path = "/".join(parts + [route_path])
    full = f"/{prefix}/{path}".replace("//", "/")
    full = re.sub(r"/+", "/", full)
    return full.rstrip("/") if full != "/" else full


def tag_from_path(full_path: str) -> str:
    m = re.match(r"/api/v1/([^/]+)", full_path)
    return m.group(1).upper() if m else "API"


def parse_file(rel_path: str, prefix: str) -> dict[str, dict]:
    content = (MODULES / rel_path).read_text(encoding="utf-8")
    paths: dict[str, dict] = {}
    group_stack: list[str] = []

    for line in content.splitlines():
        stripped = line.strip()
        if "->group(function" in stripped or stripped.endswith("function () {"):
            pm = PREFIX_RE.search(stripped)
            if pm:
                group_stack.append(pm.group(1))
            continue
        if stripped == "});" and group_stack:
            group_stack.pop()
            continue

        m = ROUTE_RE.search(stripped)
        if not m:
            continue

        verb, route_path = m.group(1), m.group(2)
        full_path = normalize_path(prefix, route_path, group_stack)

        if verb == "apiResource":
            resource = route_path.strip("/")
            singular = re.sub(r"s$", "", resource) if resource.endswith("s") else resource
            id_param = f"{{{singular}}}"
            for action, method in API_RESOURCE_OPS.items():
                if action == "index":
                    p = full_path
                elif action == "store":
                    p = full_path
                elif action == "show":
                    p = f"{full_path}/{id_param}"
                elif action == "update":
                    p = f"{full_path}/{id_param}"
                else:
                    p = f"{full_path}/{id_param}"
                paths.setdefault(p, {})[method] = {
                    "summary": f"{action.capitalize()} {resource}",
                    "tags": [tag_from_path(p)],
                    "responses": {"200": {"description": "OK"}},
                }
            continue

        method = HTTP_METHODS[verb]
        summary = f"{verb.upper()} {full_path}"
        paths.setdefault(full_path, {})[method] = {
            "summary": summary,
            "tags": [tag_from_path(full_path)],
            "responses": {"200": {"description": "OK"}},
        }

    return paths


def main() -> None:
    all_paths: dict[str, dict] = {}
    for rel, prefix in MODULE_PREFIXES.items():
        all_paths.update(parse_file(rel, prefix))

    # Public forms submit route
    forms_path = "/api/v1/forms/{slug}/submit"
    all_paths[forms_path] = {
        "post": {
            "summary": "Submit public form",
            "tags": ["FORMS"],
            "responses": {"200": {"description": "OK"}},
        }
    }

    spec = {
        "openapi": "3.1.0",
        "info": {
            "title": "Webino ERP API",
            "version": "1.0.0",
            "description": (
                "OpenAPI specification for WebinoERM Laravel API. "
                "Regenerate via `cd backend && composer export-openapi`."
            ),
        },
        "servers": [{"url": "/api"}],
        "security": [{"bearerAuth": []}],
        "components": {
            "securitySchemes": {
                "bearerAuth": {
                    "type": "http",
                    "scheme": "bearer",
                    "bearerFormat": "JWT",
                    "description": "Laravel Sanctum personal access token",
                }
            }
        },
        "paths": dict(sorted(all_paths.items())),
        "tags": sorted(
            {tag for p in all_paths.values() for op in p.values() for tag in op.get("tags", [])}
        ),
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(spec, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {len(all_paths)} paths to {OUT}")


if __name__ == "__main__":
    main()
