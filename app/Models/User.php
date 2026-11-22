<?php

namespace App\Models;

use App\Mail\WelcomeMail;
use App\Support\Mailing;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Someone who can sign in to the admin site. What they may do follows from their roles.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active', 'welcome_sent_at', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'welcome_sent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @var array<string, true>|null
     */
    private ?array $permissionCache = null;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->roles->contains('is_admin', true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->isAdmin()
                ? array_fill_keys(Permissions::all(), true)
                : array_fill_keys($this->roles->pluck('permissions')->flatten()->filter()->all(), true);
        }

        return isset($this->permissionCache[$permission]);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function refreshPermissions(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /**
     * Mails the welcome message with a link to choose a password (valid as long as a reset link, see config/auth.php).
     */
    public function sendWelcomeMail(bool $reset = false): void
    {
        Mailing::ensureEnabled();

        $token = Password::broker()->createToken($this);
        $url = filament()->getPanel('admin')->getResetPasswordUrl($token, $this);

        Mail::to($this->email)->send(new WelcomeMail($this, $url, $reset));

        if (! $reset) {
            $this->forceFill(['welcome_sent_at' => now()])->saveQuietly();
        }
    }
}
