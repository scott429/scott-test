<style>
:root {
  --aiv-primary: #1fb788;        /* Shoptimised teal/green */
  --aiv-primary-dark: #081836;   /* ink navy */
  --aiv-blue: #005983;           /* brand blue (secondary) */
  --aiv-accent: #57c8e3;         /* sky */
  --aiv-bg: #f5f7fa;             /* canvas */
  --aiv-surface: #ffffff;
  --aiv-border: rgba(8, 24, 54, .08);
  --aiv-text: #081836;           /* ink */
  --aiv-muted: #6b7a93;          /* ink-soft */
  --aiv-low-bg: #eef2f7;   --aiv-low-fg: #6b7a93;
  --aiv-med-bg: #fff4e0;   --aiv-med-fg: #9a6300;   /* amber */
  --aiv-high-bg: #fde7ea;  --aiv-high-fg: #c4324a;  /* red */
  --aiv-ok-bg: rgba(31, 183, 136, .12);  --aiv-ok-fg: #0f7a58;  /* teal */
  --aiv-font: 'Baloo 2', ui-rounded, 'Segoe UI', system-ui, sans-serif;
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
.aiv-badge.is-info { background: rgba(0, 89, 131, .1); color: var(--aiv-blue); }
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
.aiv-ellip { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.aiv-row:hover { border-color: var(--aiv-primary); }
.aiv-modal-overlay { position: fixed; inset: 0; background: rgba(8, 24, 54, .45); display: flex; align-items: flex-start; justify-content: center; padding: 5vh 1rem; z-index: 50; overflow-y: auto; }
.aiv-modal { background: var(--aiv-surface); border-radius: var(--aiv-radius); max-width: 640px; width: 100%; padding: 1.25rem 1.5rem; box-shadow: 0 10px 40px rgba(8, 24, 54, .18); }
.aiv-qrow { border: 1px solid var(--aiv-border); border-radius: 8px; padding: .65rem .85rem; }
</style>
