#!/usr/bin/env python3
"""Extract hardcoded Persian string literals into next-intl messages and replace in client TSX."""
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path("src")
FA_PATH = Path("messages/fa.json")
EN_PATH = Path("messages/en.json")

fa = json.loads(FA_PATH.read_text(encoding="utf-8"))
en = json.loads(EN_PATH.read_text(encoding="utf-8"))

STR_RE = re.compile(r"""(['"])([\u0600-\u06FF][^'"]{0,200})\1""")
JSX_TEXT_RE = re.compile(r""">(\s*)([\u0600-\u06FF][^<>{]{1,200}?)(\s*)<""")
SKIP = {"node_modules", ".next"}


def ns_for(path: Path) -> str:
    rel = path.as_posix().replace("src/", "").replace(".tsx", "").replace(".ts", "")
    parts = [
        p
        for p in rel.split("/")
        if p not in ("components", "dashboard", "pages", "ui", "features", "modules")
    ]
    key = "_".join(parts[-3:]) if parts else "misc"
    key = re.sub(r"[^a-zA-Z0-9_]", "_", key)
    return f"auto.{key}"


def slug(text: str) -> str:
    return "s_" + hashlib.sha1(text.encode("utf-8")).hexdigest()[:8]


def ensure_ns(obj: dict, ns: str) -> dict:
    cur = obj
    for part in ns.split("."):
        if part not in cur or not isinstance(cur[part], dict):
            cur[part] = {}
        cur = cur[part]
    return cur


stats = {"files": 0, "strings": 0}

priority_dirs = [
    "src/components/dashboard/pages",
    "src/components/dashboard",
    "src/features",
    "src/themes",
    "src/app",
    "src/components",
]

files: list[Path] = []
for d in priority_dirs:
    p = Path(d)
    if not p.exists():
        continue
    for f in p.rglob("*.tsx"):
        if any(s in f.parts for s in SKIP):
            continue
        files.append(f)

seen: set[Path] = set()
files = [f for f in files if not (f in seen or seen.add(f))]

for path in files:
    text = path.read_text(encoding="utf-8")
    if not re.search(r"[\u0600-\u06FF]{3,}", text):
        continue

    # Only auto-edit client components — server components need async getTranslations.
    if "'use client'" not in text and '"use client"' not in text:
        continue

    ns = ns_for(path)
    bucket_fa = ensure_ns(fa, ns)
    bucket_en = ensure_ns(en, ns)
    new_text = text
    used_t = [False]

    for m in list(STR_RE.finditer(new_text))[::-1]:
        content = m.group(2)
        key = slug(content)
        if key not in bucket_fa:
            bucket_fa[key] = content
            bucket_en[key] = content
            stats["strings"] += 1
        full_key = f"{ns}.{key}"
        start, end = m.span()
        before = new_text[max(0, start - 4) : start]
        expr = f"t('{full_key}')"
        if re.search(r"=\s*$", before):
            replacement = "{" + expr + "}"
        else:
            replacement = expr
        new_text = new_text[:start] + replacement + new_text[end:]
        used_t[0] = True

    def jsx_repl(m: re.Match[str]) -> str:
        content = m.group(2).strip()
        if not content:
            return m.group(0)
        key = slug(content)
        if key not in bucket_fa:
            bucket_fa[key] = content
            bucket_en[key] = content
            stats["strings"] += 1
        full_key = f"{ns}.{key}"
        used_t[0] = True
        return f"{m.group(1)}{{t('{full_key}')}}{m.group(3)}<"

    new_text = JSX_TEXT_RE.sub(jsx_repl, new_text)

    if new_text == text or not used_t[0]:
        continue

    if "useTranslations" not in new_text:
        if re.search(r"import \{[^}]*\} from ['\"]next-intl['\"]", new_text):
            new_text = re.sub(
                r"import \{([^}]+)\} from (['\"])next-intl\2",
                lambda m: m.group(0)
                if "useTranslations" in m.group(1)
                else f"import {{{m.group(1).strip()}, useTranslations}} from {m.group(2)}next-intl{m.group(2)}",
                new_text,
                count=1,
            )
        else:
            new_text = re.sub(
                r"(['\"]use client['\"];?\n)",
                r"\1\nimport { useTranslations } from 'next-intl';\n",
                new_text,
                count=1,
            )

    if "useTranslations()" not in new_text and not re.search(
        r"useTranslations\([^)]*\)", new_text
    ):
        m = re.search(r"(export\s+)?function\s+\w+\s*\([^)]*\)\s*\{", new_text)
        if not m:
            m = re.search(
                r"(?:export\s+)?const\s+\w+\s*=\s*\([^)]*\)\s*=>\s*\{", new_text
            )
        if m:
            insert_at = m.end()
            new_text = (
                new_text[:insert_at]
                + "\n  const t = useTranslations();\n"
                + new_text[insert_at:]
            )

    path.write_text(new_text, encoding="utf-8")
    stats["files"] += 1
    print("updated", path)

FA_PATH.write_text(json.dumps(fa, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
EN_PATH.write_text(json.dumps(en, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print("STATS", stats)

remaining = 0
for f in files:
    if re.search(r"[\u0600-\u06FF]{3,}", f.read_text(encoding="utf-8", errors="ignore")):
        remaining += 1
print("remaining persian-containing files:", remaining)
