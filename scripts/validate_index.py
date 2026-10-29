#!/usr/bin/env python3
"""Validate wawoo-cms-marketplace: index.json vs hosted plugins and bundles."""
from __future__ import annotations

import json
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
errors: list[str] = []


def err(msg: str) -> None:
    errors.append(msg)


idx_path = root / "index.json"
if not idx_path.is_file():
    print("FAIL: index.json missing")
    sys.exit(1)
try:
    idx = json.loads(idx_path.read_text(encoding="utf-8"))
except Exception as exc:  # noqa: BLE001
    print(f"FAIL: index.json invalid JSON: {exc}")
    sys.exit(1)

items = idx.get("items")
if not isinstance(items, list) or not items:
    err("index.items must be a non-empty list")
    items = []

names: set[str] = set()
hosted: dict[str, Path] = {}

for i, it in enumerate(items):
    if not isinstance(it, dict):
        err(f"items[{i}] is not an object")
        continue
    name = it.get("name")
    if not name:
        err(f"items[{i}] missing name")
        continue
    if name in names:
        err(f"duplicate name: {name}")
    names.add(name)
    for key in ("type", "version", "requires", "source"):
        if key not in it:
            err(f"{name}: missing {key}")
    src = it.get("source") if isinstance(it.get("source"), dict) else {}
    kind = src.get("kind")
    if kind == "hosted":
        p = (root / str(src.get("path", ""))).resolve()
        try:
            p.relative_to(root)
        except ValueError:
            err(f"{name}: hosted path escapes repo: {p}")
        manifest = p / ("plugin.json" if it.get("type") == "plugin" else "theme.json")
        if not manifest.is_file():
            err(f"{name}: missing manifest at {manifest}")
        else:
            m = json.loads(manifest.read_text(encoding="utf-8"))
            if m.get("name") != name:
                err(f"{name}: manifest name mismatch ({m.get('name')})")
            if m.get("version") != it.get("version"):
                err(f"{name}: version mismatch index={it.get('version')} manifest={m.get('version')}")
        hosted[name] = p
    elif kind == "git":
        if not str(src.get("url", "")).startswith(("http://", "https://")):
            err(f"{name}: git url must be http(s)")
    else:
        err(f"{name}: unknown source.kind {kind!r}")

pdir = root / "plugins"
if pdir.is_dir():
    for d in sorted(p.name for p in pdir.iterdir() if p.is_dir()):
        if d not in hosted:
            err(f"unindexed plugin dir: {d}")

bdir = root / "bundles"
if bdir.is_dir():
    for bf in sorted(bdir.glob("*.json")):
        try:
            b = json.loads(bf.read_text(encoding="utf-8"))
        except Exception as exc:  # noqa: BLE001
            err(f"{bf.name}: invalid JSON: {exc}")
            continue
        for n in b.get("plugins", []):
            if n not in names:
                err(f"{bf.name}: unknown plugin {n}")

if errors:
    print(f"FAIL ({len(errors)} errors)")
    for e in errors:
        print("  -", e)
    sys.exit(1)

print(f"OK: {len(items)} items, {len(hosted)} hosted, {len(names)} unique names")
