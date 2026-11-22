<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Support\Permissions;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Without the publish permission a new page always starts as a draft outside the menu.
        if (! auth()->user()?->hasPermission(Permissions::PAGES_PUBLISH)) {
            $data['is_published'] = false;
            $data['show_in_menu'] = false;
        }

        return $data;
    }
}
