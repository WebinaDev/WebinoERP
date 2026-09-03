#!/usr/bin/env python3
"""Repair JSX breakage introduced by naive Persian→t() extraction."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path("src")

# 1) Wrong closing tags: {t('...')}<Foo>  →  {t('...')}</Foo>
WRONG_CLOSE = re.compile(
    r"(\{t\('(?:[^']|\\')+'\)(?:\s*,\s*\{[^}]*\})?\})<(?!/)([A-Za-z][A-Za-z0-9.]*)>"
)

# 2) Missing > before {t( when previous token looks like a tag/attrs end
#    Examples:
#      <SelectItem value="all"{t(...)}</SelectItem>
#      <CardTitle{t(...)}</CardTitle>
#      <p className="..."{t(...)}</p>
#      <Button ... onClick={...}{t(...)}</Button>
MISSING_GT = re.compile(
    r"(<(?:[A-Za-z][\w.]*)(?:\s+[^>{]*?)?)"  # opening tag start + attrs without >
    r"(\{t\(')"
)

# More precise: any non-> character before {t( that is still inside a tag
INSIDE_TAG_T = re.compile(
    r"(<(?:[A-Za-z][\w.]*)"  # <Tag
    r"(?:"
    r'\s+[A-Za-z_:][\w:.-]*'  # attr name
    r'(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|\{(?:[^{}]|\{[^{}]*\})*\}))?'  # optional value
    r")*)"  # zero+ attrs
    r"(\{t\()"  # immediately {t( — missing >
)


def fix_multiline_missing_gt(text: str) -> str:
    """Fix:
    <Button ... onClick={() => ...}
      {t('...')}
    </Button>
    """
    lines = text.splitlines(keepends=True)
    out: list[str] = []
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.rstrip("\n")
        # If line looks like unfinished JSX open (starts with spaces+< or continues attrs)
        # and next non-empty line starts with {t(
        if i + 1 < len(lines):
            nxt = lines[i + 1]
            nxt_s = nxt.lstrip()
            if nxt_s.startswith("{t(") and not stripped.rstrip().endswith(
                (">", "{", ";", ",", ")", "]", "}", "`")
            ):
                # Heuristic: previous line has JSX-ish content and no trailing >
                prev = stripped.rstrip()
                if (
                    ("<" in prev or "onClick" in prev or "disabled" in prev or "className" in prev)
                    and not prev.endswith(">")
                    and not prev.endswith(";")
                    and "{" in prev  # likely props
                ):
                    # Insert > at end of prev
                    nl = "\n" if line.endswith("\n") else ""
                    out.append(prev + ">" + nl)
                    i += 1
                    continue
        out.append(line)
        i += 1
    return "".join(out)


def fix_file(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    orig = text

    # Fix wrong close tags repeatedly
    while True:
        new = WRONG_CLOSE.sub(r"\1</\2>", text)
        if new == text:
            break
        text = new

    # Fix missing > before {t( inside tags
    while True:
        new = INSIDE_TAG_T.sub(r"\1>\2", text)
        if new == text:
            break
        text = new

    text = fix_multiline_missing_gt(text)

    if text != orig:
        path.write_text(text, encoding="utf-8")
        return True
    return False


def main() -> None:
    n = 0
    for path in ROOT.rglob("*.tsx"):
        if fix_file(path):
            n += 1
            print("fixed", path)
    print("files fixed", n)


if __name__ == "__main__":
    main()
