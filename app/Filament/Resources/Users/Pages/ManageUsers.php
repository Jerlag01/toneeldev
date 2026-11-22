<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;
use Throwable;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    private bool $sendWelcome = false;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    // Without a password the account gets a random one; the welcome mail lets the user choose their own.
                    $this->sendWelcome = (bool) ($data['send_welcome'] ?? false);
                    unset($data['send_welcome']);
                    $data['password'] ??= Str::password(32);

                    return $data;
                })
                ->after(function (User $record): void {
                    if (! $this->sendWelcome) {
                        return;
                    }

                    try {
                        $record->sendWelcomeMail();
                        Notification::make()->success()->title('Welkomstmail verstuurd naar '.$record->email)->send();
                    } catch (Throwable $exception) {
                        Notification::make()->warning()->persistent()->title('Gebruiker aangemaakt, maar de welkomstmail is niet verstuurd')->body($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
