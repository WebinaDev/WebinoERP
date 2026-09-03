#!/usr/bin/env python3
"""Second-pass JSX repair: multiline missing '>' before {t(...)} children."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path("src")


def fix_multiline(text: str) -> str:
    lines = text.splitlines(keepends=True)
    out: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.rstrip("\n\r")
        rstripped = stripped.rstrip()
        if i + 1 < len(lines):
            nxt = lines[i + 1]
            nxt_l = nxt.lstrip()
            if nxt_l.startswith("{t(") and not rstripped.endswith(">"):
                # skip if clearly not a tag open
                if not (
                    rstripped.endswith(";")
                    or rstripped.endswith(",")
                    or rstripped.endswith("{")
                    or rstripped.endswith("=")
                    or rstripped.endswith("&&")
                    or rstripped.endswith("?")
                    or rstripped.endswith(":")
                ):
                    looks_tag = (
                        "<" in rstripped
                        or re.search(
                            r"\b(onClick|disabled|className|type|variant|size|href|value|asChild|defaultValue)=",
                            rstripped,
                        )
                    )
                    if looks_tag:
                        nl = "\n" if line.endswith("\n") else ("\r\n" if line.endswith("\r\n") else "")
                        # preserve trailing spaces? keep none
                        out.append(rstripped + ">" + nl)
                        i += 1
                        continue
        out.append(line)
        i += 1
    return "".join(out)


def fix_html_fallback_strings(text: str) -> str:
    """Fix: ?? '<p>{t('key')}</p>'  →  ?? `<p>${t('key')}</p>` or concatenate."""
    # Pattern with nested broken quotes
    def repl(m: re.Match[str]) -> str:
        key = m.group(1)
        return f"?? `<p>${{t('{key}')}}</p>`"

    text2 = re.sub(
        r"\?\?\s*'<p>\{t\('([^']+)'\)}</p>'",
        repl,
        text,
    )
    text2 = re.sub(
        r'\?\?\s*"<p>\{t\(\'([^\']+)\'\)}</p>"',
        repl,
        text2,
    )
    return text2


def ensure_use_translations(text: str, path: Path) -> str:
    if "useTranslations" not in text:
        return text
    if re.search(r"\bconst\s+t\s*=\s*useTranslations\s*\(", text):
        return text
    if "getTranslations" in text and "const t =" in text:
        return text
    # client components that call t( but forgot const t =
    if re.search(r"\bt\('", text) and "use client" in text[:200]:
        # insert after imports / first function start
        m = re.search(r"(export\s+default\s+function\s+\w+\s*\([^)]*\)\s*\{)", text)
        if m:
            insert = m.group(1) + "\n  const t = useTranslations();\n"
            text = text[: m.start()] + insert + text[m.end() :]
            return text
        m = re.search(r"(export\s+function\s+\w+\s*\([^)]*\)\s*\{)", text)
        if m:
            insert = m.group(1) + "\n  const t = useTranslations();\n"
            text = text[: m.start()] + insert + text[m.end() :]
            return text
        m = re.search(r"(function\s+\w+\s*\([^)]*\)\s*\{)", text)
        if m and "useTranslations" in text:
            insert = m.group(1) + "\n  const t = useTranslations();\n"
            text = text[: m.start()] + insert + text[m.end() :]
    return text


def main() -> None:
    n = 0
    for path in ROOT.rglob("*.tsx"):
        text = path.read_text(encoding="utf-8")
        orig = text
        text = fix_multiline(text)
        text = fix_html_fallback_strings(text)
        text = ensure_use_translations(text, path)
        if text != orig:
            path.write_text(text, encoding="utf-8")
            n += 1
            print("fixed", path)
    print("files", n)


if __name__ == "__main__":
    main()
