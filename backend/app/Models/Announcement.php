<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'category',
        'target_roles',
        'expires_at',
        'author_id',
        'organization_id',
        'image_url',
        'attachment_url',
        'attachment_name',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'expires_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeActive(Builder $query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForUser(Builder $query, User $user)
    {
        // If user is Admin, they might see everything, or this scope is for "Viewing" feed.
        // For feed, we check roles.
        // We need to map user's role (string) to the JSON array.
        // Assuming User model has a method or attribute to get the role string code (e.g., 'SALES').

        // Based on App.vue, the roles are string literals (ADMIN, SALES, etc.)
        // But User.php has role_id. We need the role name.
        // We will assume the frontend sends/User model has 'role_name' or similar accessed via relationship.
        // However, usually specific queries are tricky with JSON in SQL depending on DB.
        // Laravel's whereJsonContains works well.

        $userRole = $user->role->name ?? $user->role_cached ?? null; // Adjust based on actual Role model setup

        if (!$userRole) return $query;

        // If "Wszyscy" (target_roles is null or empty array -- wait, prompt said selectable.
        // We'll store ["ALL"] or just list all roles. Easier if we use a convention.
        // Let's assume if target_roles contains "ALL" or if it contains $userRole.

        return $query->where(function($q) use ($userRole) {
             $q->whereJsonContains('target_roles', $userRole)
               ->orWhereJsonContains('target_roles', 'ALL');
        });
    }
}
