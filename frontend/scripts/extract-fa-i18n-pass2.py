#!/usr/bin/env python3
"""Second-pass i18n: JSX text nodes + server components via getTranslations."""
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

FA_PATH = Path("messages/fa.json")
EN_PATH = Path("messages/en.json")
fa = json.loads(FA_PATH.read_text(encoding="utf-8"))
en = json.loads(EN_PATH.read_text(encoding="utf-8"))

# JSX text that contains Persian (may include punctuation / +)
JSX_TEXT_RE = re.compile(r""">([^<>{]*[\u0600-\u06FF][^<>{}]*)</""")
SKIP = {"node_modules", ".next"}


def ns_for(path: Path) -> str:
    rel = path.as_posix().replace("src/", "").replace(".tsx", "")
    parts = [
        p
        for p in rel.split("/")
        if p not in ("components", "dashboard", "pages", "ui", "features", "modules", "themes")
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
files = [f for f in Path("src").rglob("*.tsx") if not any(s in f.parts for s in SKIP)]

for path in files:
    text = path.read_text(encoding="utf-8")
    if not re.search(r"[\u0600-\u06FF]{2,}", text):
        continue

    is_client = "'use client'" in text or '"use client"' in text
    ns = ns_for(path)
    bucket_fa = ensure_ns(fa, ns)
    bucket_en = ensure_ns(en, ns)
    new_text = text
    used = [False]

    def jsx_repl(m: re.Match[str]) -> str:
        content = m.group(1)
        stripped = content.strip()
        # Skip if already a t() call remnant or empty
        if not stripped or "t(" in stripped:
            return m.group(0)
        key = slug(stripped)
        if key not in bucket_fa:
            bucket_fa[key] = stripped
            bucket_en[key] = stripped
            stats["strings"] += 1
        full_key = f"{ns}.{key}"
        used[0] = True
        return f">{{t('{full_key}')}}<"

    new_text = JSX_TEXT_RE.sub(jsx_repl, new_text)
    if not used[0] or new_text == text:
        continue

    if is_client:
        if "useTranslations" not in new_text:
            new_text = re.sub(
                r"(['\"]use client['\"];?\n)",
                r"\1\nimport { useTranslations } from 'next-intl';\n",
                new_text,
                count=1,
            )
        if not re.search(r"useTranslations\s*\(", new_text):
            m = re.search(r"(export\s+)?function\s+\w+\s*\([^)]*\)\s*\{", new_text)
            if not m:
                m = re.search(
                    r"(?:export\s+)?const\s+\w+\s*=\s*\([^)]*\)\s*=>\s*\{", new_text
                )
            if m:
                new_text = (
                    new_text[: m.end()]
                    + "\n  const t = useTranslations();\n"
                    + new_text[m.end() :]
                )
    else:
        # Server component
        if "getTranslations" not in new_text:
            new_text = (
                "import { getTranslations } from 'next-intl/server';\n" + new_text
            )
        if "await getTranslations" not in new_text:
            # Make default export async if needed
            new_text = re.sub(
                r"export\s+default\s+async\s+function",
                "export default async function",
                new_text,
                count=1,
            )
            new_text = re.sub(
                r"export\s+default\s+function\s+",
                "export default async function ",
                new_text,
                count=1,
            )
            m = re.search(
                r"export\s+default\s+async\s+function\s+\w+\s*\([^)]*\)\s*\{",
                new_text,
            )
            if m:
                new_text = (
                    new_text[: m.end()]
                    + "\n  const t = await getTranslations();\n"
                    + new_text[m.end() :]
                )
            else:
                # named async server component
                m = re.search(r"export\s+async\s+function\s+\w+\s*\([^)]*\)\s*\{", new_text)
                if m:
                    new_text = (
                        new_text[: m.end()]
                        + "\n  const t = await getTranslations();\n"
                        + new_text[m.end() :]
                    )
                else:
                    # skip non-async server helpers
                    continue

    path.write_text(new_text, encoding="utf-8")
    stats["files"] += 1
    print("updated", path)

FA_PATH.write_text(json.dumps(fa, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
EN_PATH.write_text(json.dumps(en, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print("STATS", stats)

remaining = sum(
    1
    for f in files
    if re.search(r"[\u0600-\u06FF]{3,}", f.read_text(encoding="utf-8", errors="ignore"))
)
total_strings = sum(
    len(re.findall(r"[\u0600-\u06FF]{3,}", f.read_text(encoding="utf-8", errors="ignore")))
    for f in files
)
print("remaining files", remaining, "strings", total_strings)
