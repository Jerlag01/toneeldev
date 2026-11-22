<!DOCTYPE html>
<html lang="nl-BE">
<head>
    <meta charset="utf-8">
    <title>QR-codes tafels - {{ settings()->siteName() }}</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:700,800|source-sans-3:400,600,700|{{ \Illuminate\Support\Str::slug($design['font']) }}:400,700&display=swap" rel="stylesheet">
    @include('shop.qr.styles')
    <style>
        @page { size: A4; margin: 8mm; }
        body { margin: 0; font-family: "Source Sans 3", system-ui, sans-serif; background: #e9e6e1; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 1rem; align-items: center; padding: .75rem 1rem; background: #1d1a18; color: #fff; z-index: 5; }
        .toolbar button { background: #b4232a; color: #fff; border: 0; padding: .55rem 1.1rem; border-radius: .4rem; font-weight: 700; cursor: pointer; }
        .sheet { display: flex; flex-wrap: wrap; gap: 4mm; justify-content: center; padding: 8mm; }
        @media print { .toolbar { display: none; } body { background: #fff; } .sheet { padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Afdrukken / PDF</button>
    <span>{{ $tables->count() }} {{ $tables->count() === 1 ? 'kaart' : 'kaarten' }} · {{ (float) $design['width_mm'] }} × {{ (float) $design['height_mm'] }} mm · kies in het printvenster "Achtergrondafbeeldingen afdrukken" en schaal 100%.</span>
</div>
<div class="sheet">
    @foreach ($tables as $table)
        @include('shop.qr.card', ['table' => $table, 'design' => $design])
    @endforeach
</div>
</body>
</html>
