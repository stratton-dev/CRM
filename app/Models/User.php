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
        'leadowiec_opiekun_id',
        'leadowiec_commission_rate',
        'parent_id',
        'parent_supabase_id',
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
        'supabase_id',
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
            'address_json' => 'array',
        'documents_json' => 'array',
        'is_removed_from_structure' => 'boolean',
        'is_blocked' => 'boolean',
        'renewal_commission_rate' => 'decimal:4',
        'override_commission_rate' => 'decimal:4',
        'leadowiec_commission_rate' => 'decimal:4',
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

    public function opiekun(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'leadowiec_opiekun_id');
    }

    public function leadowcy(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'leadowiec_opiekun_id');
    }

    public function addedCompanies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Company::class, 'added_by_user_id');
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

        // Differentiate UUID-shaped values from numeric IDs. Comparing a UUID
        // against the bigint `id` column blows up on Postgres (SQLSTATE 22P02).
        if (is_string($value) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
            return $query->where('supabase_id', $value)->firstOrFail();
        }

        if (is_numeric($value)) {
            return $query->where('id', $value)->firstOrFail();
        }

        return $query->where('supabase_id', $value)->firstOrFail();
    }
}
