<style>
    .fp-wrap { border-radius: .9rem; padding: .75rem; background: rgb(0 0 0 / .03); overflow: auto; }
    .dark .fp-wrap { background: rgb(255 255 255 / .03); }
    .fp-canvas { position: relative; width: 100%; min-width: 640px; background-color: #fbf8f4; border-radius: .4rem; box-shadow: inset 0 0 0 2px rgb(0 0 0 / .15);
        background-image: linear-gradient(rgb(0 0 0 / .05) 1px, transparent 1px), linear-gradient(90deg, rgb(0 0 0 / .05) 1px, transparent 1px);
        background-size: calc(100% / var(--cols)) calc(100% / var(--rows)); }
    .dark .fp-canvas { background-color: #1f1c1a; background-image: linear-gradient(rgb(255 255 255 / .05) 1px, transparent 1px), linear-gradient(90deg, rgb(255 255 255 / .05) 1px, transparent 1px); }
    .fp-item { position: absolute; display: flex; align-items: center; justify-content: center; text-align: center; box-sizing: border-box; overflow: visible; }
    .fp-el { border-radius: .25rem; font-size: clamp(.55rem, 1.1vw, .8rem); font-weight: 700; line-height: 1.1; padding: 2px; }
    .fp-el .fp-label { overflow: hidden; text-overflow: ellipsis; max-height: 100%; letter-spacing: .03em; }
    .fp-type-stage { letter-spacing: .25em; text-transform: uppercase; border-bottom: 4px solid #b4232a; }
    .fp-type-corridor { background-image: repeating-linear-gradient(45deg, rgb(0 0 0 / .05) 0 6px, transparent 6px 12px) !important; font-weight: 600; opacity: .9; }
    .fp-type-corridor .fp-label, .fp-type-tribune .fp-label { opacity: .7; }
    .fp-type-door, .fp-type-exit { border-radius: 2px; font-size: clamp(.5rem, .9vw, .7rem); }
    .fp-type-wall { border-radius: 1px; font-size: 0; }
    .fp-type-pillar { border-radius: 2px; font-size: 0; }
    .fp-type-zone { border: 2px dashed rgb(0 0 0 / .25); opacity: .85; }
    .fp-type-text { font-size: clamp(.65rem, 1.2vw, .95rem); color: inherit; }
    .fp-table { border-radius: .4rem; background: #fff; border: 2px solid rgb(0 0 0 / .25); font-weight: 800; font-size: clamp(.6rem, 1.15vw, .9rem); color: #1d1a18; }
    .fp-table.is-round { border-radius: 999px; }
    .fp-table.is-inactive { opacity: .35; }
    .fp-table.has-orders { border-color: #b4232a; background: #fde8e8; }
    .fp-table.is-old { border-color: #f59e0b; background: #fef3c7; }
    .fp-table.is-current { box-shadow: 0 0 0 3px #1d1a18; z-index: 40; }
    .dark .fp-table.is-current { box-shadow: 0 0 0 3px #fff; }
    .fp-table .vb-badge { top: -.5rem; right: -.5rem; }
    .fp-live .fp-table { cursor: pointer; transition: transform .12s; }
    .fp-live .fp-table:hover { transform: scale(1.06); z-index: 30; }
</style>
