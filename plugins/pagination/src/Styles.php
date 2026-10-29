<?php
namespace Wawoo\Plugin\Pagination;

final class Styles
{
    public function handle(?array $vars = null): void
    {
        echo '<style>'
            . '.pagination{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:.4rem;margin:1.5rem 0}'
            . '.pagination .page-num{display:inline-flex;align-items:center;justify-content:center;min-width:2.25rem;padding:.35rem .7rem;border:1px solid var(--border,#ccc);border-radius:.4rem;line-height:1.4;text-decoration:none;color:inherit;background:transparent;box-shadow:none}'
            . '.pagination a.page-num:hover,.pagination a.page-num:focus{border-color:var(--accent,#888);background:rgba(0,0,0,.06)}'
            . '.pagination .page-num.current{font-weight:700;border-color:var(--accent,#888);background:rgba(0,0,0,.08)}'
            . '.pagination .page-num.disabled{opacity:.45;pointer-events:none}'
            . '.pagination .pagination-ellipsis{padding:0 .2rem;background:transparent;box-shadow:none;color:var(--muted,#999)}'
            . '</style>' . "\n";
    }
}
