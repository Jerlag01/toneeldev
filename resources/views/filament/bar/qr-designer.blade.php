<x-filament-panels::page>
    @include('shop.qr.styles')
    <link href="https://fonts.bunny.net/css?family={{ collect(\App\Filament\Pages\Bar\QrDesigner::FONTS)->map(fn ($f) => \Illuminate\Support\Str::slug($f).':400,700')->implode('|') }}&display=swap" rel="stylesheet">
    <style>
        .qd { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr) minmax(20rem, 26rem); align-items: start; }
        @media (max-width: 1100px) { .qd { grid-template-columns: 1fr; } }
        .qd-stage { position: sticky; top: 5rem; display: flex; flex-direction: column; align-items: center; gap: .75rem; padding: 2rem 1rem; border-radius: 1rem; background: repeating-conic-gradient(rgb(0 0 0 / .04) 0% 25%, transparent 0% 50%) 50% / 20px 20px; }
        .qd-stage .qr-card { box-shadow: 0 10px 40px rgb(0 0 0 / .18); }
        .qd-stage .qr-el { cursor: grab; outline: 1px dashed transparent; outline-offset: 2px; user-select: none; touch-action: none; }
        .qd-stage .qr-el:hover { outline-color: #b4232a; }
        .qd-stage .qr-el.is-dragging { cursor: grabbing; outline-color: #b4232a; }
        .qd-panel { display: flex; flex-direction: column; gap: 1rem; }
        .qd-field { display: flex; flex-direction: column; gap: .3rem; font-size: .85rem; font-weight: 600; }
        .qd-field input[type=text], .qd-field input[type=number], .qd-field select { border: 1px solid rgb(0 0 0 / .15); border-radius: .5rem; padding: .4rem .6rem; background: transparent; font-weight: 400; }
        .dark .qd-field input, .dark .qd-field select { border-color: rgb(255 255 255 / .2); color-scheme: dark; }
        .qd-field input[type=color] { width: 100%; height: 2.25rem; border-radius: .5rem; border: 1px solid rgb(0 0 0 / .15); padding: .15rem; background: transparent; }
        .qd-field input[type=range] { accent-color: #b4232a; }
        .qd-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .qd-el { border: 1px solid rgb(0 0 0 / .08); border-radius: .6rem; padding: .7rem .8rem; display: flex; flex-direction: column; gap: .55rem; }
        .dark .qd-el { border-color: rgb(255 255 255 / .1); }
        .qd-el-head { display: flex; align-items: center; justify-content: space-between; font-weight: 700; }
        .qd-el-head label { display: flex; align-items: center; gap: .4rem; font-weight: 500; font-size: .85rem; }
        .qd-hint { font-size: .75rem; opacity: .65; font-weight: 400; }
        .qd-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .qd-btn { padding: .5rem .95rem; border-radius: .5rem; font-weight: 600; font-size: .875rem; border: 1px solid rgb(0 0 0 / .12); }
        .dark .qd-btn { border-color: rgb(255 255 255 / .15); }
        .qd-btn-primary { background: #b4232a; border-color: #b4232a; color: #fff; }
    </style>

    <div class="qd">
        <div class="qd-stage"
             x-data="{
                drag: null,
                start(e) {
                    const el = e.target.closest('.qr-el[data-key]');
                    if (!el) return;
                    e.preventDefault();
                    const card = el.closest('.qr-card');
                    this.drag = { el, card, key: el.dataset.key };
                    el.classList.add('is-dragging');
                    el.setPointerCapture?.(e.pointerId);
                },
                move(e) {
                    if (!this.drag) return;
                    const r = this.drag.card.getBoundingClientRect();
                    const x = Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100));
                    const y = Math.max(0, Math.min(100, (e.clientY - r.top) / r.height * 100));
                    this.drag.el.style.left = x + '%';
                    this.drag.el.style.top = y + '%';
                    this.drag.x = x; this.drag.y = y;
                },
                end() {
                    if (!this.drag) return;
                    this.drag.el.classList.remove('is-dragging');
                    if (this.drag.x !== undefined) $wire.moveElement(this.drag.key, this.drag.x, this.drag.y);
                    this.drag = null;
                }
             }"
             @pointerdown="start($event)" @pointermove.window="move($event)" @pointerup.window="end()">
            <div wire:key="preview-{{ md5(json_encode($design)) }}">
                @include('shop.qr.card', ['table' => $this->sampleTable(), 'design' => $design])
            </div>
            <span class="qd-hint">Sleep de onderdelen naar de juiste plaats. Voorbeeld met tafel</span>
            <select wire:model.live="sample" class="qd-btn" style="padding:.3rem .6rem">
                @foreach (\App\Models\ShopTable::query()->ordered()->get() as $t)
                    <option value="{{ $t->label() }}">{{ $t->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="qd-panel">
            <x-filament::section heading="Kaart">
                <div style="display:flex; flex-direction:column; gap:.75rem;">
                    <label class="qd-field">Formaat
                        <select wire:change="setPaper($event.target.value)">
                            <option value="">Kies een standaardformaat...</option>
                            @foreach (\App\Filament\Pages\Bar\QrDesigner::SIZES as $value => $label)
                                <option value="{{ $value }}" @selected($value === ((int) $design['width_mm']).'x'.((int) $design['height_mm']))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="qd-row">
                        <label class="qd-field">Breedte (mm)<input type="number" min="40" max="300" wire:model.live.debounce.400ms="design.width_mm"></label>
                        <label class="qd-field">Hoogte (mm)<input type="number" min="40" max="300" wire:model.live.debounce.400ms="design.height_mm"></label>
                    </div>
                    <div class="qd-row">
                        <label class="qd-field">Achtergrond<input type="color" wire:model.live.debounce.300ms="design.background_color"></label>
                        <label class="qd-field">Tekst<input type="color" wire:model.live.debounce.300ms="design.text_color"></label>
                        <label class="qd-field">Titel (accent)<input type="color" wire:model.live.debounce.300ms="design.accent_color"></label>
                        <label class="qd-field">QR-code<input type="color" wire:model.live.debounce.300ms="design.qr_color"></label>
                    </div>
                    <label class="qd-field">Lettertype
                        <select wire:model.live="design.font">
                            @foreach (\App\Filament\Pages\Bar\QrDesigner::FONTS as $font)
                                <option value="{{ $font }}" style="font-family: '{{ $font }}'">{{ $font }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="qd-field">Achtergrondafbeelding <span class="qd-hint">Vult de hele kaart. Gebruik een rustige afbeelding: de QR-code moet leesbaar blijven.</span>
                        <input type="file" accept="image/*" wire:model="backgroundUpload">
                    </label>
                    @if (filled($design['background_image'] ?? null))
                        <button type="button" class="qd-btn" wire:click="removeBackground">Achtergrondafbeelding verwijderen</button>
                    @endif
                    <label class="qd-field">Eigen logo voor de kaart <span class="qd-hint">Leeg = het logo of favicon van de website.</span>
                        <input type="file" accept="image/*" wire:model="logoUpload">
                    </label>
                    @if (filled($design['logo'] ?? null))
                        <button type="button" class="qd-btn" wire:click="removeLogo">Eigen logo verwijderen</button>
                    @endif
                    <label class="qd-field" style="flex-direction:row; align-items:center; gap:.5rem;"><input type="checkbox" wire:model.live="design.border"> Snijlijn rond de kaart afdrukken</label>
                </div>
            </x-filament::section>

            <x-filament::section heading="Onderdelen">
                <div style="display:flex; flex-direction:column; gap:.75rem;">
                    @foreach (\App\Filament\Pages\Bar\QrDesigner::elementInfo() as $key => $info)
                        <div class="qd-el" wire:key="el-{{ $key }}">
                            <div class="qd-el-head">
                                <span>{{ $info['label'] }}</span>
                                <label><input type="checkbox" wire:model.live="design.elements.{{ $key }}.visible"> tonen</label>
                            </div>
                            @if ($info['text'])
                                <label class="qd-field">Tekst @isset($info['hint'])<span class="qd-hint">{{ $info['hint'] }}</span>@endisset
                                    <input type="text" maxlength="120" wire:model.live.debounce.400ms="design.elements.{{ $key }}.text">
                                </label>
                            @endif
                            <label class="qd-field">Grootte: {{ $design['elements'][$key]['size'] ?? '' }}
                                <input type="range" min="1" max="{{ in_array($key, ['qr', 'logo']) ? 100 : 30 }}" step="0.5" wire:model.live="design.elements.{{ $key }}.size">
                            </label>
                            <div class="qd-row">
                                <label class="qd-field">Horizontaal (%)<input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.400ms="design.elements.{{ $key }}.x"></label>
                                <label class="qd-field">Verticaal (%)<input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.400ms="design.elements.{{ $key }}.y"></label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="qd-actions">
                <button type="button" class="qd-btn qd-btn-primary" wire:click="save">Opslaan</button>
                <a class="qd-btn" href="{{ route('admin.qr.print') }}" target="_blank">Alle tafels afdrukken</a>
                <button type="button" class="qd-btn" wire:click="resetDesign" wire:confirm="Het standaardontwerp laden? (Pas na Opslaan definitief.)">Standaardontwerp</button>
            </div>
            <p class="qd-hint">Afdrukken gebeurt met het opgeslagen ontwerp. Sla dus eerst op.</p>
        </div>
    </div>
</x-filament-panels::page>
