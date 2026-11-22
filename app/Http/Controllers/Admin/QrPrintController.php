<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopTable;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable QR cards for the tables, in the design from Bar → QR-ontwerp. Print with the browser (or save as PDF).
 */
class QrPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasPermission(Permissions::SHOP_TABLES), 403);

        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('tables', ''))));
        $tables = ShopTable::query()->where('is_active', true)->when($ids !== [], fn ($query) => $query->whereKey($ids))->ordered()->get();

        if ($request->filled('sample')) {
            $tables = collect([new ShopTable(['row' => 'A', 'number' => 1])]);
        }

        return view('shop.qr.print', ['tables' => $tables, 'design' => (array) settings()->get('qr')]);
    }
}
