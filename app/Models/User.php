<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'role_id',
        'parent_id',
        'parent_keycloak_id',
        'hierarchical_id',
        'hierarchical_code',
        'crm_number',
        'rank',
        'contract_status',
        'type',
        'address_json',
        'documents_json',
        'is_removed_from_structure',
        'is_blocked',
        'points',
        'renewal_commission_rate',
        'override_commission_rate',
        'keycloak_id',
        'keycloak_username',
        'team_id',
        'team_group_path',
        'role_cached',
        'name',
        'email',
        'phone',
        'password',
        'active',
        'enabled',
        'pending_setup',
        'last_synced_at',
        'sync_error',
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
            'active' => 'boolean',
            'enabled' => 'boolean',
            'pending_setup' => 'boolean',
            'last_synced_at' => 'datetime',
            'address_json' => 'array',
        'documents_json' => 'array',
        'is_removed_from_structure' => 'boolean',
        'is_blocked' => 'boolean',
        'renewal_commission_rate' => 'decimal:4',
        'override_commission_rate' => 'decimal:4',
    ];
    }

    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function meetings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function metrics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function crmMailConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CrmMailConfig::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->newQuery();
        if ($field) {
            return $query->where($field, $value)->firstOrFail();
        }

        return $query->where('id', $value)
            ->orWhere('keycloak_id', $value)
            ->firstOrFail();
    }
}
