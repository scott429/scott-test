<style>
:root {
  --aiv-primary: #1d4ed8;        /* Shoptimised blue (placeholder) */
  --aiv-primary-dark: #0f172a;   /* navy */
  --aiv-accent: #60a5fa;         /* lighter blue */
  --aiv-bg: #f8fafc;             /* page background */
  --aiv-surface: #ffffff;
  --aiv-border: #e2e8f0;
  --aiv-text: #0f172a;
  --aiv-muted: #64748b;
  --aiv-low-bg: #f1f5f9;   --aiv-low-fg: #475569;
  --aiv-med-bg: #faeeda;   --aiv-med-fg: #854f0b;
  --aiv-high-bg: #fcebeb;  --aiv-high-fg: #a32d2d;
  --aiv-ok-bg: #e1f5ee;    --aiv-ok-fg: #0f6e56;
  --aiv-font: 'InterVariable', system-ui, -apple-system, sans-serif;
  --aiv-radius: 12px;
}
.aiv-wrap { max-width: 1040px; margin: 0 auto; padding: 1.5rem 1rem 4rem; color: var(--aiv-text); font-family: var(--aiv-font); }
.aiv-h1 { font-size: 1.5rem; font-weight: 600; margin: 0; }
.aiv-h2 { font-size: 1rem; font-weight: 600; margin: 1.75rem 0 .75rem; }
.aiv-sub { color: var(--aiv-muted); font-size: .85rem; margin-top: .25rem; }
.aiv-method { font-size: .78rem; line-height: 1.5; color: var(--aiv-muted); background: var(--aiv-bg); border: 1px solid var(--aiv-border); border-radius: 8px; padding: .6rem .85rem; margin: 1rem 0; }
.aiv-card { background: var(--aiv-surface); border: 1px solid var(--aiv-border); border-radius: var(--aiv-radius); padding: 1rem 1.25rem; }
.aiv-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.aiv-metric { background: var(--aiv-bg); border-radius: 8px; padding: 1rem; }
.aiv-metric-label { font-size: .8rem; color: var(--aiv-muted); }
.aiv-metric-value { font-size: 1.5rem; font-weight: 600; margin-top: .25rem; }
.aiv-score { font-size: 2.1rem; font-weight: 600; color: var(--aiv-primary); }
.aiv-bar { height: 8px; border-radius: 999px; background: var(--aiv-border); overflow: hidden; }
.aiv-bar-fill { height: 100%; background: var(--aiv-primary); border-radius: 999px; }
.aiv-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.aiv-table th { text-align: left; font-weight: 500; color: var(--aiv-muted); background: var(--aiv-bg); padding: .55rem .75rem; }
.aiv-table td { padding: .6rem .75rem; border-top: 1px solid var(--aiv-border); }
.aiv-badge { font-size: .72rem; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
.aiv-badge.is-low { background: var(--aiv-low-bg); color: var(--aiv-low-fg); }
.aiv-badge.is-medium { background: var(--aiv-med-bg); color: var(--aiv-med-fg); }
.aiv-badge.is-high { background: var(--aiv-high-bg); color: var(--aiv-high-fg); }
.aiv-badge.is-ok { background: var(--aiv-ok-bg); color: var(--aiv-ok-fg); }
.aiv-btn { display: inline-flex; align-items: center; gap: 6px; font: inherit; font-size: .85rem; padding: .5rem .9rem; border: 1px solid var(--aiv-border); background: var(--aiv-surface); color: var(--aiv-text); border-radius: 8px; cursor: pointer; text-decoration: none; }
.aiv-btn:hover { background: var(--aiv-bg); }
.aiv-btn-primary { background: var(--aiv-primary); color: #fff; border-color: var(--aiv-primary); }
.aiv-btn-primary:hover { filter: brightness(.95); }
.aiv-btn[disabled] { opacity: .5; cursor: not-allowed; }
.aiv-row { display: flex; align-items: center; gap: 12px; background: var(--aiv-surface); border: 1px solid var(--aiv-border); border-radius: 8px; padding: .65rem .9rem; }
.aiv-flex { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.aiv-between { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.aiv-stack { display: flex; flex-direction: column; gap: 8px; }
.aiv-mut { color: var(--aiv-muted); font-size: .8rem; }
</style>
