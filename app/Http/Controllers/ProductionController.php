<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;

class ProductionController extends Controller
{
    public function index(): View
    {
        return view('productions.index', [
            'upcoming' => Production::query()->published()->upcoming()->get(),
            'past' => Production::query()->published()->past()->get(),
        ]);
    }

    public function show(Production $production): View
    {
        abort_unless($production->is_published || auth()->user()?->hasPermission(Permissions::PRODUCTIONS), 404);

        return view('productions.show', ['production' => $production]);
    }
}
