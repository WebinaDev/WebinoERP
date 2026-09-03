#!/usr/bin/env python3
import json
import re
from pathlib import Path

fa = json.loads(Path("messages/fa.json").read_text(encoding="utf-8"))
en = json.loads(Path("messages/en.json").read_text(encoding="utf-8"))
common = fa.setdefault("common", {})
common_en = en.setdefault("common", {})
common.update(
    {
        "confirmDeleteNamed": "آیا از حذف «{name}» اطمینان دارید؟",
        "confirmDeleteNamedIrreversible": "آیا از حذف «{name}» اطمینان دارید؟ این عمل قابل بازگشت نیست.",
        "confirmDeleteReceipt": "آیا از حذف رسید شماره «{number}» اطمینان دارید؟",
        "pageOf": "صفحه {page} از {pageCount}",
        "pageOfItems": "نمایش صفحه {page} از {pageCount} — {total} مورد",
        "pageSlash": "صفحه {page} / {pageCount}",
        "rowsPage": "{rows} ردیف — صفحه {page} / {pageCount}",
        "wizardContract": "ویزارد قرارداد — مرحله {step} از ۴",
        "wizardProject": "پروژه جدید — مرحله {step} از ۲",
        "totalTasks": "جمع وظایف: {count}",
        "totalLabel": "جمع: {total}",
        "customerLabel": "مشتری: {name}",
        "teamReady": "تیم {name} آماده پاسخگویی به سوالات شماست",
        "invalidJson": "JSON نامعتبر است",
        "invalidInstallmentsJson": "JSON اقساط نامعتبر است",
        "invalidModuleReposJson": "module_repos JSON نامعتبر است",
        "invalidGitJson": "git JSON نامعتبر است",
        "cooperationTitle": "همکاری با ما",
        "cooperationSubmit": "ارسال درخواست همکاری",
        "developedBy": "طراحی و توسعه توسط",
    }
)
common_en.update(
    {
        "confirmDeleteNamed": 'Delete "{name}"?',
        "confirmDeleteNamedIrreversible": 'Delete "{name}"? This cannot be undone.',
        "confirmDeleteReceipt": 'Delete receipt "{number}"?',
        "pageOf": "Page {page} of {pageCount}",
        "pageOfItems": "Page {page} of {pageCount} — {total} items",
        "pageSlash": "Page {page} / {pageCount}",
        "rowsPage": "{rows} rows — page {page} / {pageCount}",
        "wizardContract": "Contract wizard — step {step} of 4",
        "wizardProject": "New project — step {step} of 2",
        "totalTasks": "Total tasks: {count}",
        "totalLabel": "Total: {total}",
        "customerLabel": "Customer: {name}",
        "teamReady": "The {name} team is ready to answer your questions",
        "invalidJson": "Invalid JSON",
        "invalidInstallmentsJson": "Invalid installments JSON",
        "invalidModuleReposJson": "Invalid module_repos JSON",
        "invalidGitJson": "Invalid git JSON",
        "cooperationTitle": "Work with us",
        "cooperationSubmit": "Submit cooperation request",
        "developedBy": "Designed and developed by",
    }
)

multi_patterns = [
    (
        re.compile(
            r"<AlertDialogDescription>آیا از حذف «\{([^}]+)\}» اطمینان دارید\؟</AlertDialogDescription>",
            re.S,
        ),
        lambda m: "<AlertDialogDescription>{t('common.confirmDeleteNamed', { name: %s })}</AlertDialogDescription>"
        % m.group(1),
    ),
    (
        re.compile(
            r"<AlertDialogDescription>آیا از حذف «\{([^}]+)\}» اطمینان دارید\؟ این عمل قابل بازگشت نیست\.?</AlertDialogDescription>",
            re.S,
        ),
        lambda m: "<AlertDialogDescription>{t('common.confirmDeleteNamedIrreversible', { name: %s })}</AlertDialogDescription>"
        % m.group(1),
    ),
    (
        re.compile(
            r"<AlertDialogDescription>آیا از حذف رسید شماره «\{([^}]+)\}» اطمینان دارید\؟</AlertDialogDescription>",
            re.S,
        ),
        lambda m: "<AlertDialogDescription>{t('common.confirmDeleteReceipt', { number: %s })}</AlertDialogDescription>"
        % m.group(1),
    ),
]

count = 0
for path in Path("src").rglob("*.tsx"):
    text = path.read_text(encoding="utf-8")
    orig = text
    for rx, repl in multi_patterns:
        text = rx.sub(repl, text)

    text = re.sub(
        r">نمایش صفحه \{([^}]+)\} از \{([^}]+)\} — \{([^}]+)\} مورد<",
        ">{t('common.pageOfItems', { page: \\1, pageCount: \\2, total: \\3 })}<",
        text,
    )
    text = re.sub(
        r">صفحه \{([^}]+)\} / \{([^}]+)\}<",
        ">{t('common.pageSlash', { page: \\1, pageCount: \\2 })}<",
        text,
    )
    text = re.sub(
        r">صفحه \{([^}]+)\} از \{([^}]+)\}<",
        ">{t('common.pageOf', { page: \\1, pageCount: \\2 })}<",
        text,
    )
    text = re.sub(
        r">\{([^}]+)\} ردیف — صفحه \{([^}]+)\} / \{([^}]+)\}<",
        ">{t('common.rowsPage', { rows: \\1, page: \\2, pageCount: \\3 })}<",
        text,
    )
    text = re.sub(
        r"<DialogTitle>ویزارد قرارداد — مرحله \{([^}]+)\} از ۴</DialogTitle>",
        "<DialogTitle>{t('common.wizardContract', { step: \\1 })}</DialogTitle>",
        text,
    )
    text = re.sub(
        r"<DialogTitle>پروژه جدید — مرحله \{([^}]+)\} از ۲</DialogTitle>",
        "<DialogTitle>{t('common.wizardProject', { step: \\1 })}</DialogTitle>",
        text,
    )
    text = re.sub(
        r">جمع وظایف: \{([^}]+)\}<",
        ">{t('common.totalTasks', { count: \\1 })}<",
        text,
    )
    text = re.sub(
        r'<p className="font-medium">جمع: \{([^}]+)\}</p>',
        '<p className="font-medium">{t(\'common.totalLabel\', { total: \\1 })}</p>',
        text,
    )
    text = re.sub(
        r">مشتری: \{([^}]+)\}",
        ">{t('common.customerLabel', { name: \\1 })}",
        text,
    )
    text = re.sub(
        r">تیم \{([^}]+)\} آماده پاسخگویی به سوالات شماست",
        ">{t('common.teamReady', { name: \\1 })}",
        text,
    )
    text = text.replace("طراحی و توسعه توسط{' '}", "{t('common.developedBy')}{' '}")
    text = text.replace("setError('JSON اقساط نامعتبر است')", "setError(t('common.invalidInstallmentsJson'))")
    text = text.replace('title="همکاری با ما"', "title={t('common.cooperationTitle')}")
    text = text.replace(
        'submitLabel="ارسال درخواست همکاری"', "submitLabel={t('common.cooperationSubmit')}"
    )

    if text != orig:
        path.write_text(text, encoding="utf-8")
        count += 1
        print("updated", path)

Path("messages/fa.json").write_text(
    json.dumps(fa, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
)
Path("messages/en.json").write_text(
    json.dumps(en, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
)
print("files", count)

pat = re.compile(r"[\u0600-\u06FF]{3,}")
files = [
    f
    for f in Path("src").rglob("*.tsx")
    if pat.search(f.read_text(encoding="utf-8", errors="ignore"))
]
print(
    "remaining files",
    len(files),
    "strings",
    sum(len(pat.findall(f.read_text(encoding="utf-8", errors="ignore"))) for f in files),
)
for f in files:
    print(len(pat.findall(f.read_text(encoding="utf-8", errors="ignore"))), f)
