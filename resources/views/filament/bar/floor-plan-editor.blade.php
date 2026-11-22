<x-filament-panels::page>
    @include('filament.bar.floor-styles')
    <style>
        .fp-editor { display: grid; grid-template-columns: minmax(0, 1fr) 18rem; gap: 1rem; align-items: start; }
        @media (max-width: 1100px) { .fp-editor { grid-template-columns: 1fr; } }
        .fp-tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .75rem; }
        .fp-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .85rem; border-radius: .5rem; font-weight: 600; font-size: .85rem; border: 1px solid rgb(0 0 0 / .12); background: #fff; cursor: pointer; }
        .dark .fp-btn { background: rgb(255 255 255 / .05); border-color: rgb(255 255 255 / .15); }
        .fp-btn-primary { background: #b4232a; border-color: #b4232a; color: #fff; }
        .fp-btn[disabled] { opacity: .5; cursor: default; }
        .fp-side { display: flex; flex-direction: column; gap: 1rem; position: sticky; top: 5rem; }
        .fp-card { border-radius: .75rem; padding: .9rem; background: #fff; box-shadow: 0 0 0 1px rgb(0 0 0 / .07); }
        .dark .fp-card { background: rgb(255 255 255 / .04); box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
        .fp-card h3 { font-weight: 700; font-size: .9rem; margin: 0 0 .6rem; }
        .fp-palette { display: grid; grid-template-columns: 1fr 1fr; gap: .35rem; }
        .fp-palette button { display: flex; align-items: center; gap: .4rem; padding: .4rem .5rem; border-radius: .45rem; border: 1px solid rgb(0 0 0 / .1); font-size: .8rem; text-align: left; background: transparent; cursor: pointer; }
        .dark .fp-palette button { border-color: rgb(255 255 255 / .12); }
        .fp-palette button:hover { border-color: #b4232a; }
        .fp-field { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; font-weight: 600; margin-bottom: .55rem; }
        .fp-field input, .fp-field select { border: 1px solid rgb(0 0 0 / .15); border-radius: .45rem; padding: .35rem .5rem; background: transparent; font-weight: 400; }
        .dark .fp-field input, .dark .fp-field select { border-color: rgb(255 255 255 / .2); color-scheme: dark; }
        .fp-field input[type=color] { height: 2rem; padding: .1rem; }
        .fp-two { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
        .fp-unplaced { display: flex; flex-wrap: wrap; gap: .35rem; }
        .fp-unplaced button { padding: .25rem .55rem; border-radius: .4rem; border: 1px dashed rgb(0 0 0 / .3); font-weight: 700; font-size: .8rem; background: transparent; cursor: pointer; }
        .fp-hint { font-size: .75rem; opacity: .65; }
        .fp-canvas .fp-item { cursor: grab; touch-action: none; user-select: none; }
        .fp-canvas .fp-item.is-selected { outline: 2px solid #2563eb; outline-offset: 1px; z-index: 50; }
        .fp-handle { position: absolute; right: -6px; bottom: -6px; width: 12px; height: 12px; border-radius: 3px; background: #2563eb; border: 2px solid #fff; cursor: nwse-resize; touch-action: none; }
        .fp-dirty { color: #b45309; font-weight: 600; font-size: .85rem; }
        .fp-marquee { position: absolute; border: 1.5px dashed #2563eb; background: rgb(37 99 235 / .08); pointer-events: none; z-index: 60; }
        .fp-align { display: grid; grid-template-columns: repeat(3, 1fr); gap: .3rem; }
        .fp-align .fp-btn { justify-content: center; padding: .35rem .3rem; font-size: .75rem; }
    </style>

    <script>
        // Selection is a list of {kind: 'e'|'t', o: <element or table>}. Shift/Ctrl-click toggles, dragging on the
        // empty floor draws a box; dragging any selected item moves the whole group.
        window.floorPlanEditor = (initial, types) => ({
            plan: initial,
            types,
            sel: [],
            snap: 0.5,
            dirty: false,
            saving: false,
            tmp: 0,
            action: null,
            box: null,

            init() {
                this.plan.elements.forEach(e => e.tmp = ++this.tmp);
            },
            current() { return this.sel.length === 1 ? this.sel[0].o : {}; },
            single(kind) { return this.sel.length === 1 && this.sel[0].kind === kind; },
            isSel(o) { return this.sel.some(s => s.o === o); },
            selOf(kind) { return this.sel.filter(s => s.kind === kind).map(s => s.o); },
            pct(v, total) { return (v / total * 100) + '%'; },
            boxStyle(o) {
                return `left:${this.pct(o.x, this.plan.width)};top:${this.pct(o.y, this.plan.height)};width:${this.pct(o.w, this.plan.width)};height:${this.pct(o.h, this.plan.height)};`;
            },
            elStyle(el) {
                const color = el.color || (this.types[el.type]?.color ?? '#cccccc');
                if (el.type === 'text') return 'background:transparent;';
                return `background:${color};color:${this.textOn(color)};`;
            },
            textOn(hex) {
                const c = hex.replace('#', '').slice(0, 6);
                const [r, g, b] = [0, 2, 4].map(i => parseInt(c.substr(i, 2), 16) || 0);
                return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#1d1a18' : '#ffffff';
            },
            round(v) { return Math.round(v / this.snap) * this.snap; },
            unit() { return this.$refs.canvas.getBoundingClientRect().width / this.plan.width; },
            point(e) {
                const r = this.$refs.canvas.getBoundingClientRect(), u = r.width / this.plan.width;
                return { x: (e.clientX - r.left) / u, y: (e.clientY - r.top) / u };
            },
            clampBox(o) {
                o.w = Math.max(0.5, Math.min(this.plan.width, o.w));
                o.h = Math.max(0.5, Math.min(this.plan.height, o.h));
                o.x = Math.max(0, Math.min(this.plan.width - o.w, o.x));
                o.y = Math.max(0, Math.min(this.plan.height - o.h, o.y));
            },
            // Bounding box of a group of items.
            bounds(items) {
                return {
                    x0: Math.min(...items.map(o => o.x)), y0: Math.min(...items.map(o => o.y)),
                    x1: Math.max(...items.map(o => o.x + o.w)), y1: Math.max(...items.map(o => o.y + o.h)),
                };
            },
            // Moves the selection by dx/dy from the given start positions, keeping the group inside the hall.
            moveGroup(starts, dx, dy) {
                const b = this.bounds(starts);
                dx = Math.max(-b.x0, Math.min(this.plan.width - b.x1, dx));
                dy = Math.max(-b.y0, Math.min(this.plan.height - b.y1, dy));
                starts.forEach(s => { s.o.x = s.x + dx; s.o.y = s.y + dy; });
            },
            startDrag(e, kind, o) {
                const toggle = e.shiftKey || e.ctrlKey || e.metaKey;
                const was = this.isSel(o);
                if (toggle) {
                    if (was) { this.sel = this.sel.filter(s => s.o !== o); return; }
                    this.sel.push({ kind, o });
                } else if (!was) {
                    this.sel = [{ kind, o }];
                }
                const starts = this.sel.map(s => ({ o: s.o, x: s.o.x, y: s.o.y, w: s.o.w, h: s.o.h }));
                this.action = { type: 'move', o, starts, sx: e.clientX, sy: e.clientY, unit: this.unit(), moved: false, collapse: was && !toggle };
                this.listen();
            },
            startResize(e, o) {
                this.action = { type: 'resize', o, sx: e.clientX, sy: e.clientY, ow: o.w, oh: o.h, unit: this.unit() };
                this.listen();
            },
            startBox(e) {
                const p = this.point(e);
                if (!(e.shiftKey || e.ctrlKey || e.metaKey)) this.sel = [];
                this.action = { type: 'box', sx: e.clientX, sy: e.clientY, keep: [...this.sel] };
                this.box = { x0: p.x, y0: p.y, x: p.x, y: p.y, w: 0, h: 0 };
                this.listen();
            },
            boxSelect() {
                const b = this.box, inside = o => o.x >= b.x && o.y >= b.y && o.x + o.w <= b.x + b.w && o.y + o.h <= b.y + b.h;
                const hits = [
                    ...this.plan.elements.filter(inside).map(o => ({ kind: 'e', o })),
                    ...this.plan.tables.filter(t => t.x !== null && inside(t)).map(o => ({ kind: 't', o })),
                ];
                this.sel = [...this.action.keep, ...hits.filter(h => !this.action.keep.some(k => k.o === h.o))];
            },
            listen() {
                const move = (e) => {
                    const a = this.action; if (!a) return;
                    if (a.type === 'box') {
                        const p = this.point(e), b = this.box;
                        Object.assign(b, { x: Math.min(b.x0, p.x), y: Math.min(b.y0, p.y), w: Math.abs(p.x - b.x0), h: Math.abs(p.y - b.y0) });
                        this.boxSelect();
                        return;
                    }
                    const dx = (e.clientX - a.sx) / a.unit, dy = (e.clientY - a.sy) / a.unit;
                    if (Math.abs(dx) + Math.abs(dy) > 0.2) { a.moved = true; this.dirty = true; }
                    if (!a.moved) return;
                    if (a.type === 'resize') {
                        a.o.w = Math.max(0.5, this.round(a.ow + dx)); a.o.h = Math.max(0.5, this.round(a.oh + dy));
                        this.clampBox(a.o);
                    } else if (a.starts.length === 1) {
                        // One item snaps to the grid itself.
                        const s = a.starts[0];
                        s.o.x = this.round(s.x + dx); s.o.y = this.round(s.y + dy); this.clampBox(s.o);
                    } else {
                        // A group moves in grid steps and keeps its relative positions.
                        this.moveGroup(a.starts, this.round(dx), this.round(dy));
                    }
                };
                const up = () => {
                    const a = this.action;
                    // A plain click on an item of a group selects only that item.
                    if (a?.type === 'move' && !a.moved && a.collapse) this.sel = this.sel.filter(s => s.o === a.o);
                    this.action = null; this.box = null;
                    window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up);
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },
            key(e) {
                if (['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName)) return;
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') { e.preventDefault(); this.selectAll(); return; }
                if (!this.sel.length) return;
                const step = e.shiftKey ? 1 : this.snap;
                const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
                if (moves[e.key]) {
                    e.preventDefault();
                    this.moveGroup(this.sel.map(s => ({ o: s.o, x: s.o.x, y: s.o.y, w: s.o.w, h: s.o.h })), ...moves[e.key]);
                    this.dirty = true;
                }
                if ((e.key === 'Delete' || e.key === 'Backspace') && this.selOf('e').length) { e.preventDefault(); this.remove(); }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd' && this.selOf('e').length) { e.preventDefault(); this.duplicate(); }
                if (e.key === 'Escape') this.sel = [];
            },
            selectAll() {
                this.sel = [
                    ...this.plan.elements.map(o => ({ kind: 'e', o })),
                    ...this.plan.tables.filter(t => t.x !== null).map(o => ({ kind: 't', o })),
                ];
            },
            selectTables() { this.sel = this.plan.tables.filter(t => t.x !== null).map(o => ({ kind: 't', o })); },
            add(type) {
                const t = this.types[type];
                const el = { id: null, tmp: ++this.tmp, type, label: t.label, w: t.w, h: t.h, color: null,
                    x: this.round((this.plan.width - t.w) / 2), y: this.round((this.plan.height - t.h) / 2) };
                this.clampBox(el);
                this.plan.elements.push(el);
                this.sel = [{ kind: 'e', o: this.plan.elements[this.plan.elements.length - 1] }];
                this.dirty = true;
            },
            duplicate() {
                const copies = this.selOf('e').map(o => {
                    const copy = { ...JSON.parse(JSON.stringify(o)), id: null, tmp: ++this.tmp, x: o.x + 1, y: o.y + 1 };
                    this.clampBox(copy);
                    this.plan.elements.push(copy);
                    return { kind: 'e', o: this.plan.elements[this.plan.elements.length - 1] };
                });
                this.sel = copies;
                this.dirty = true;
            },
            rotate() {
                this.sel.forEach(({ o }) => { [o.w, o.h] = [o.h, o.w]; this.clampBox(o); });
                this.dirty = true;
            },
            remove() {
                const gone = this.selOf('e');
                this.plan.elements = this.plan.elements.filter(o => !gone.includes(o));
                this.sel = this.sel.filter(s => s.kind !== 'e');
                this.dirty = true;
            },
            unplace() {
                this.selOf('t').forEach(t => { t.x = null; t.y = null; });
                this.sel = this.sel.filter(s => s.kind !== 't');
                this.dirty = true;
            },
            // Align the selection to one side of the group, or centre it.
            align(side) {
                const items = this.sel.map(s => s.o), b = this.bounds(items);
                items.forEach(o => {
                    if (side === 'left') o.x = b.x0;
                    if (side === 'right') o.x = b.x1 - o.w;
                    if (side === 'top') o.y = b.y0;
                    if (side === 'bottom') o.y = b.y1 - o.h;
                    if (side === 'hcenter') o.x = this.round((b.x0 + b.x1 - o.w) / 2);
                    if (side === 'vcenter') o.y = this.round((b.y0 + b.y1 - o.h) / 2);
                    this.clampBox(o);
                });
                this.dirty = true;
            },
            // Equal gaps between the items, from the first to the last (needs 3 or more).
            distribute(axis) {
                const [pos, size] = axis === 'x' ? ['x', 'w'] : ['y', 'h'];
                const items = this.sel.map(s => s.o).sort((a, b) => a[pos] - b[pos]);
                if (items.length < 3) return;
                const first = items[0], last = items[items.length - 1];
                const total = items.reduce((sum, o) => sum + o[size], 0);
                const gap = (last[pos] + last[size] - first[pos] - total) / (items.length - 1);
                let at = first[pos];
                items.forEach(o => { o[pos] = Math.round(at * 2) / 2; at += o[size] + gap; this.clampBox(o); });
                this.dirty = true;
            },
            // Same size as the first selected item.
            sameSize() {
                const [first, ...rest] = this.sel.map(s => s.o);
                rest.forEach(o => { o.w = first.w; o.h = first.h; this.clampBox(o); });
                this.dirty = true;
            },
            freeSpot(w, h) {
                const placed = [...this.plan.tables.filter(t => t.x !== null), ...this.plan.elements];
                for (let y = 0; y <= this.plan.height - h; y += 1) {
                    for (let x = 0; x <= this.plan.width - w; x += 1) {
                        if (!placed.some(o => x < o.x + o.w && x + w > o.x && y < o.y + o.h && y + h > o.y)) return { x, y };
                    }
                }
                return { x: 0, y: 0 };
            },
            placeTable(i, add = false) {
                const t = this.plan.tables[i];
                t.w = t.w || 2; t.h = t.h || 2;
                Object.assign(t, this.freeSpot(t.w, t.h));
                this.sel = add ? [...this.sel, { kind: 't', o: t }] : [{ kind: 't', o: t }];
                this.dirty = true;
            },
            placeAll() {
                this.sel = [];
                this.plan.tables.forEach((t, i) => { if (t.x === null) this.placeTable(i, true); });
            },
            async suggest() {
                if (this.dirty && !confirm('Het voorstel vervangt het huidige plan (pas definitief na Opslaan). Doorgaan?')) return;
                const plan = await this.$wire.suggestion();
                plan.elements.forEach(e => e.tmp = ++this.tmp);
                this.plan = plan;
                this.sel = [];
                this.dirty = true;
            },
            async save() {
                this.saving = true;
                try {
                    const saved = await this.$wire.savePlan(JSON.parse(JSON.stringify(this.plan)));
                    saved.elements.forEach(e => e.tmp = ++this.tmp);
                    this.plan = saved;
                    this.sel = [];
                    this.dirty = false;
                } finally {
                    this.saving = false;
                }
            },
        });
    </script>
    <div wire:ignore x-data="floorPlanEditor(@js(\App\Support\Shop\FloorPlan::data()), @js(\App\Support\Shop\FloorPlan::types()))"
         x-on:keydown.window="key($event)" x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
        <div class="fp-tools">
            <button type="button" class="fp-btn fp-btn-primary" x-on:click="save()" :disabled="saving">
                <span x-text="saving ? 'Bezig...' : 'Opslaan'"></span>
            </button>
            <button type="button" class="fp-btn" x-on:click="suggest()">Voorstel laden (op basis van de tafelrijen)</button>
            <button type="button" class="fp-btn" x-on:click="if (!dirty || confirm('Niet-opgeslagen wijzigingen weggooien?')) location.reload()">Wijzigingen weggooien</button>
            <label class="fp-btn" style="gap:.5rem">Rooster
                <select x-model.number="snap" style="border:0;background:transparent;padding:0">
                    <option value="0.5">½</option><option value="1">1</option>
                </select>
            </label>
            <button type="button" class="fp-btn" x-on:click="selectAll()">Alles selecteren</button>
            <button type="button" class="fp-btn" x-on:click="selectTables()">Alle tafels selecteren</button>
            <span class="fp-dirty" x-show="dirty">Niet opgeslagen</span>
        </div>

        <div class="fp-editor">
            <div>
                <div class="fp-wrap">
                    <div class="fp-canvas" x-ref="canvas" :style="`aspect-ratio: ${plan.width} / ${plan.height}; --cols: ${plan.width}; --rows: ${plan.height};`"
                         x-on:pointerdown.self="startBox($event)">
                        <template x-for="(el, i) in plan.elements" :key="'e' + (el.id ?? el.tmp)">
                            <div class="fp-item fp-el" :class="['fp-type-' + el.type, isSel(el) ? 'is-selected' : '']"
                                 :style="boxStyle(el) + elStyle(el)"
                                 x-on:pointerdown.stop="startDrag($event, 'e', el)">
                                <span class="fp-label" x-text="el.label ?? ''"></span>
                                <span class="fp-handle" x-show="isSel(el) && sel.length === 1" x-on:pointerdown.stop="startResize($event, el)"></span>
                            </div>
                        </template>
                        <template x-for="(t, i) in plan.tables" :key="'t' + t.id">
                            <div class="fp-item fp-table" x-show="t.x !== null" :class="[t.shape === 'round' ? 'is-round' : '', t.active ? '' : 'is-inactive', isSel(t) ? 'is-selected' : '']"
                                 :style="boxStyle(t)" x-on:pointerdown.stop="startDrag($event, 't', t)">
                                <span class="fp-table-label" x-text="t.label"></span>
                                <span class="fp-handle" x-show="isSel(t) && sel.length === 1" x-on:pointerdown.stop="startResize($event, t)"></span>
                            </div>
                        </template>
                        <div class="fp-marquee" x-show="box" x-cloak :style="box ? boxStyle(box) : ''"></div>
                    </div>
                </div>
                <p class="fp-hint" style="margin-top:.5rem">Sleep om te verplaatsen, het blauwe hoekje om te vergroten. Meerdere tegelijk: Shift/Ctrl + klik, of sleep een kader op een lege plek van de vloer (Ctrl+A = alles). Een selectie sleep je in één keer mee. Pijltjestoetsen verplaatsen de selectie (Shift = 1 vakje), Delete verwijdert, Ctrl+D dupliceert. Rij A hoort vooraan bij het podium, nummer 1 links.</p>
            </div>

            <div class="fp-side">
                <div class="fp-card" x-show="sel.length">
                    <template x-if="single('e')">
                        <div>
                            <h3 x-text="(types[current().type]?.icon ?? '') + ' ' + (types[current().type]?.label ?? 'Element')"></h3>
                            <label class="fp-field">Tekst<input type="text" x-model="current().label" x-on:input="dirty = true" maxlength="100"></label>
                            <label class="fp-field">Soort
                                <select x-model="current().type" x-on:change="dirty = true">
                                    <template x-for="(type, key) in types" :key="key"><option :value="key" x-text="type.label" :selected="key === current().type"></option></template>
                                </select>
                            </label>
                            <div class="fp-two">
                                <label class="fp-field">Breedte<input type="number" step="0.5" min="0.5" x-model.number="current().w" x-on:input="dirty = true"></label>
                                <label class="fp-field">Hoogte<input type="number" step="0.5" min="0.5" x-model.number="current().h" x-on:input="dirty = true"></label>
                            </div>
                            <label class="fp-field">Eigen kleur<input type="color" :value="(current().color || types[current().type]?.color || '#cccccc').slice(0, 7)" x-on:input="current().color = $event.target.value; dirty = true"></label>
                            <div style="display:flex; gap:.4rem; flex-wrap:wrap">
                                <button type="button" class="fp-btn" x-on:click="rotate()">Draaien 90°</button>
                                <button type="button" class="fp-btn" x-on:click="duplicate()">Dupliceren</button>
                                <button type="button" class="fp-btn" x-on:click="current().color = null; dirty = true">Standaardkleur</button>
                                <button type="button" class="fp-btn" style="color:#b91c1c" x-on:click="remove()">Verwijderen</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="single('t')">
                        <div>
                            <h3 x-text="'Tafel ' + current().label"></h3>
                            <div class="fp-two">
                                <label class="fp-field">Breedte<input type="number" step="0.5" min="1" max="10" x-model.number="current().w" x-on:input="dirty = true"></label>
                                <label class="fp-field">Hoogte<input type="number" step="0.5" min="1" max="10" x-model.number="current().h" x-on:input="dirty = true"></label>
                            </div>
                            <label class="fp-field">Vorm
                                <select x-model="current().shape" x-on:change="dirty = true"><option value="rect">Rechthoekig</option><option value="round">Rond</option></select>
                            </label>
                            <div style="display:flex; gap:.4rem; flex-wrap:wrap">
                                <button type="button" class="fp-btn" x-on:click="rotate()">Draaien 90°</button>
                                <button type="button" class="fp-btn" x-on:click="unplace()">Van het plan halen</button>
                            </div>
                            <p class="fp-hint" style="margin-top:.5rem">Rij en nummer wijzig je bij Bar → Tafels.</p>
                        </div>
                    </template>
                    <template x-if="sel.length > 1">
                        <div>
                            <h3 x-text="sel.length + ' geselecteerd' + ' (' + selOf('t').length + ' tafels, ' + selOf('e').length + ' elementen)'"></h3>
                            <p class="fp-hint" style="margin:0 0 .4rem">Uitlijnen</p>
                            <div class="fp-align">
                                <button type="button" class="fp-btn" x-on:click="align('left')" title="Links uitlijnen">⇤ Links</button>
                                <button type="button" class="fp-btn" x-on:click="align('hcenter')" title="Horizontaal centreren">↔ Midden</button>
                                <button type="button" class="fp-btn" x-on:click="align('right')" title="Rechts uitlijnen">Rechts ⇥</button>
                                <button type="button" class="fp-btn" x-on:click="align('top')" title="Boven uitlijnen">⤒ Boven</button>
                                <button type="button" class="fp-btn" x-on:click="align('vcenter')" title="Verticaal centreren">↕ Midden</button>
                                <button type="button" class="fp-btn" x-on:click="align('bottom')" title="Onder uitlijnen">⤓ Onder</button>
                            </div>
                            <div style="display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem">
                                <button type="button" class="fp-btn" x-on:click="distribute('x')" :disabled="sel.length < 3">Gelijk verdelen ↔</button>
                                <button type="button" class="fp-btn" x-on:click="distribute('y')" :disabled="sel.length < 3">Gelijk verdelen ↕</button>
                                <button type="button" class="fp-btn" x-on:click="sameSize()">Zelfde grootte</button>
                                <button type="button" class="fp-btn" x-on:click="rotate()">Elk 90° draaien</button>
                                <button type="button" class="fp-btn" x-show="selOf('e').length" x-on:click="duplicate()">Elementen dupliceren</button>
                                <button type="button" class="fp-btn" x-show="selOf('t').length" x-on:click="unplace()">Tafels van het plan halen</button>
                                <button type="button" class="fp-btn" style="color:#b91c1c" x-show="selOf('e').length" x-on:click="remove()">Elementen verwijderen</button>
                                <button type="button" class="fp-btn" x-on:click="sel = []">Selectie opheffen</button>
                            </div>
                            <p class="fp-hint" style="margin-top:.5rem">"Zelfde grootte" neemt de maat van het eerst geselecteerde. Tafels worden nooit verwijderd, alleen van het plan gehaald.</p>
                        </div>
                    </template>
                </div>

                <div class="fp-card">
                    <h3>Toevoegen</h3>
                    <div class="fp-palette">
                        <template x-for="(type, key) in types" :key="key">
                            <button type="button" x-on:click="add(key)"><span x-text="type.icon"></span><span x-text="type.label"></span></button>
                        </template>
                    </div>
                </div>

                <div class="fp-card">
                    <h3>Tafels nog niet op het plan</h3>
                    <div class="fp-unplaced">
                        <template x-for="(t, i) in plan.tables" :key="'u' + t.id">
                            <button type="button" x-show="t.x === null" x-on:click="placeTable(i)" x-text="t.label"></button>
                        </template>
                    </div>
                    <p class="fp-hint" x-show="plan.tables.every(t => t.x !== null)">Alle tafels staan op het plan.</p>
                    <button type="button" class="fp-btn" style="margin-top:.6rem" x-show="plan.tables.some(t => t.x === null)" x-on:click="placeAll()">Alle ontbrekende tafels plaatsen</button>
                </div>

                <div class="fp-card">
                    <h3>Afmetingen van de zaal</h3>
                    <div class="fp-two">
                        <label class="fp-field">Breedte (vakjes)<input type="number" min="10" max="120" x-model.number="plan.width" x-on:input="dirty = true"></label>
                        <label class="fp-field">Diepte (vakjes)<input type="number" min="10" max="120" x-model.number="plan.height" x-on:input="dirty = true"></label>
                    </div>
                    <p class="fp-hint">Kies zelf de schaal, bv. 1 vakje = 50 cm.</p>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
