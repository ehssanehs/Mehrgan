<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Reseller\Models\Reseller;

use Modules\Ticketing\Models\Ticket;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'bot_state',
        'telegram_chat_id',
        'trial_accounts_taken',
        'balance',
        'referrer_id',
        'referral_code',
        'is_banned',
        'banned_at',
        'ban_reason',
        'banned_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'banned_by' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && ! $this->isBanned();
    }

    /**
     * Whether this user is currently banned.
     */
    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    /**
     * Ban the user (blocks site login and telegram bot access).
     */
    public function ban(?string $reason = null, ?int $bannedBy = null): void
    {
        $this->forceFill([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => $reason,
            'banned_by' => $bannedBy ?? auth('web')->id(),
            'bot_state' => null,
        ])->save();
    }

    /**
     * Unban the user and restore full access.
     */
    public function unban(): void
    {
        $this->forceFill([
            'is_banned' => false,
            'banned_at' => null,
            'ban_reason' => null,
            'banned_by' => null,
        ])->save();
    }

    /**
     * Scope: only non-banned users.
     */
    public function scopeNotBanned($query)
    {
        return $query->where('is_banned', false);
    }

    /**
     * Scope: only banned users.
     */
    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function notifications()
    {

        return $this->hasMany(Notification::class);
    }
    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false)->latest();
    }

    public function agent(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function reseller(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Reseller::class);
    }

    public function resellerRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\Modules\Reseller\Models\ResellerRequest::class);
    }


    public function isApprovedAgent(): bool
    {
        return $this->agent && $this->agent->status === 'approved';
    }
    
    public function isReseller(): bool
    {
        return $this->reseller && $this->reseller->status === 'active';
    }
}
