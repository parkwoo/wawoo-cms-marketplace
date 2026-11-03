# ドキュメントの所在（迁移说明 / ポインタ）

**本 repo には文档正文を置かない。** 正本は Obsidian vault **`workspace`** 内。

## 正本路径（WSL）

| 種別 | 绝对路径 |
|------|----------|
| Vault 根 | `/home/git/workspace` |
| 本 project 文档 | `/home/git/workspace/repo-docs/wawoo-cms-marketplace` |
| 任务台帐 TASKS | `/home/git/workspace/repo-docs/wawoo-cms-marketplace/TASKS.md` |
| 管理规约 | `/home/git/workspace/repo-docs/wawoo-cms-marketplace/PROJECT_MANAGEMENT.md` |
| Agent 协议 | `/home/git/workspace/repo-docs/wawoo-cms-marketplace/AGENTS-PROGRESS.md` |
| 执行进度 PROGRESS | `/home/git/workspace/progress/wawoo-cms-marketplace.md` |

## 本 repo 内的入口（symlink）

克隆 vault 后，同目录结构下应存在：

- 根 `PROGRESS.md` → `/home/git/workspace/progress/wawoo-cms-marketplace.md`
- `docs/TASKS.md` → `/home/git/workspace/repo-docs/wawoo-cms-marketplace/TASKS.md`
- `docs/PROJECT_MANAGEMENT.md` → `/home/git/workspace/repo-docs/wawoo-cms-marketplace/PROJECT_MANAGEMENT.md`
- `docs/AGENTS-PROGRESS.md` → `/home/git/workspace/repo-docs/wawoo-cms-marketplace/AGENTS-PROGRESS.md`
- `docs/design/` 等 → `/home/git/workspace/repo-docs/wawoo-cms-marketplace/` 下各目录

**人**: Obsidian で vault `/home/git/workspace` を開く。  
**Agent**: 上記 symlink 路径（例 `@docs/TASKS.md`）を read/write してよい。実体は vault。

## symlink 再生成

```bash
/home/git/workspace/scripts/workspace_maintain.sh docs-migrate /home/git/wawoo-cms-marketplace --apply
```

## 全局规则

`/home/git/workspace/AGENTS-GLOBAL.md` · 方針 `/home/git/workspace/docs/DOC-MIGRATION-POLICY.md`

## CI

GitHub 仅 clone 本 repo 时 symlink 可能失效。workflow 需 sibling checkout `workspace` 后执行 `docs_relocate.py --link --apply`。
