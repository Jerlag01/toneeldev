<style>
    .vb-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
    .vb-status { display: inline-flex; align-items: center; gap: .5rem; font-weight: 600; padding: .4rem .85rem; border-radius: 999px; font-size: .9rem; }
    .vb-status.is-open { background: rgb(22 163 74 / .12); color: rgb(21 128 61); }
    .vb-status.is-closed { background: rgb(220 38 38 / .12); color: rgb(185 28 28); }
    .vb-status::before { content: ""; width: .55rem; height: .55rem; border-radius: 50%; background: currentColor; }
    .vb-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .9rem; border-radius: .5rem; font-weight: 600; font-size: .875rem; border: 1px solid rgb(0 0 0 / .12); background: #fff; cursor: pointer; }
    .dark .vb-btn { background: rgb(255 255 255 / .05); border-color: rgb(255 255 255 / .15); }
    .vb-btn-primary { background: #b4232a; border-color: #b4232a; color: #fff; }
    .vb-btn-primary:hover { background: #8e1b21; }
    .vb-muted { opacity: .65; font-size: .85rem; }
    .vb-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
    .vb-order { border-radius: .75rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / .06), 0 0 0 1px rgb(0 0 0 / .06); display: flex; flex-direction: column; overflow: hidden; animation: vb-in .4s ease-out; }
    .dark .vb-order { background: rgb(255 255 255 / .04); box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
    @keyframes vb-in { from { transform: scale(.97); opacity: 0; } to { transform: none; opacity: 1; } }
    .vb-order.is-old { box-shadow: 0 0 0 2px #f59e0b; }
    .vb-order-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .7rem .9rem; background: #1d1a18; color: #fff; }
    .vb-table { font-size: 1.6rem; font-weight: 800; line-height: 1; letter-spacing: -.01em; }
    .vb-order-head small { display: block; font-size: .75rem; opacity: .7; font-weight: 500; }
    .vb-time { text-align: right; font-size: .8rem; opacity: .85; }
    .vb-order-body { padding: .75rem .9rem; flex: 1; }
    .vb-name { font-weight: 700; font-size: 1.05rem; margin-bottom: .4rem; }
    .vb-items { list-style: none; margin: 0; padding: 0; }
    .vb-items li { display: flex; gap: .5rem; padding: .2rem 0; font-size: .95rem; }
    .vb-items li b { min-width: 1.75rem; color: #b4232a; }
    .vb-note { margin-top: .5rem; padding: .4rem .55rem; border-radius: .4rem; background: rgb(245 158 11 / .12); font-size: .85rem; }
    .vb-pay { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .9rem; border-top: 1px solid rgb(0 0 0 / .07); }
    .dark .vb-pay { border-color: rgb(255 255 255 / .1); }
    .vb-bonnen { font-weight: 800; font-size: 1.1rem; }
    .vb-paid { display: inline-flex; align-items: center; gap: .3rem; background: rgb(22 163 74); color: #fff; font-weight: 700; font-size: .85rem; padding: .25rem .6rem; border-radius: 999px; }
    .vb-empty { text-align: center; padding: 3rem 1rem; opacity: .6; }

    /* Table plan */
    .vb-plan { display: flex; flex-direction: column; gap: .6rem; align-items: center; padding: 1rem; border-radius: 1rem; background: rgb(0 0 0 / .03); overflow-x: auto; }
    .dark .vb-plan { background: rgb(255 255 255 / .03); }
    .vb-stage, .vb-back { width: min(100%, 44rem); text-align: center; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; font-size: .8rem; padding: .6rem; border-radius: .5rem; }
    .vb-stage { background: #1d1a18; color: #fff; border-bottom: 4px solid #b4232a; }
    .vb-back { background: rgb(0 0 0 / .08); }
    .dark .vb-back { background: rgb(255 255 255 / .08); }
    .vb-row { display: flex; align-items: center; gap: .6rem; }
    .vb-row-label { width: 2rem; text-align: center; font-weight: 800; opacity: .55; }
    .vb-cell { position: relative; width: 5rem; height: 3.6rem; border-radius: .6rem; border: 2px solid rgb(0 0 0 / .12); background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: transform .15s, box-shadow .15s; font-weight: 800; font-size: 1.1rem; }
    .dark .vb-cell { background: rgb(255 255 255 / .05); border-color: rgb(255 255 255 / .15); }
    .vb-cell:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgb(0 0 0 / .12); }
    .vb-cell.has-orders { border-color: #b4232a; background: rgb(180 35 42 / .08); }
    .vb-cell.is-old { border-color: #f59e0b; background: rgb(245 158 11 / .14); }
    .vb-cell.is-selected { box-shadow: 0 0 0 3px #1d1a18; }
    .dark .vb-cell.is-selected { box-shadow: 0 0 0 3px #fff; }
    .vb-cell.is-inactive { opacity: .35; }
    .vb-cell small { font-size: .7rem; font-weight: 600; opacity: .7; }
    .vb-badge { position: absolute; top: -.55rem; right: -.55rem; min-width: 1.4rem; height: 1.4rem; padding: 0 .3rem; border-radius: 999px; background: #b4232a; color: #fff; font-size: .75rem; display: grid; place-items: center; }
    .vb-legend { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .85rem; opacity: .75; justify-content: center; }
    .vb-legend span::before { content: ""; display: inline-block; width: .8rem; height: .8rem; border-radius: .2rem; margin-right: .35rem; vertical-align: -1px; border: 2px solid rgb(0 0 0 / .15); }
    .vb-legend .l-orders::before { border-color: #b4232a; background: rgb(180 35 42 / .1); }
    .vb-legend .l-old::before { border-color: #f59e0b; background: rgb(245 158 11 / .15); }
</style>
