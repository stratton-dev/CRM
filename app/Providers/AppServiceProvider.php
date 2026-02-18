<?php

namespace App\Providers;

use App\Models\Calculation;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\User;
use App\Policies\CalculationPolicy;
use App\Policies\ClientPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OfferPolicy;
use App\Services\Auth\TokenContext;
use App\Services\Structure\StructureAuthorization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            // Admin and Director role bypass for all permissions
            // Including check for common admin email and cached role
            $roleCode = $user->role_cached ?? $user->role?->code;
            if (in_array($roleCode, ['ADMIN', 'DIRECTOR', 'director', 'admin'], true) || $user->email === 'admin@stratton.pl') {
                return true;
            }
        });

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Calculation::class, CalculationPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);

        foreach ([
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'organizations.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            'client-contacts.view',
            'client-contacts.create',
            'client-contacts.update',
            'client-contacts.delete',
            'consents.view',
            'consents.create',
            'consents.update',
            'consents.delete',
            'client-consents.view',
            'client-consents.create',
            'client-consents.update',
            'client-consents.delete',
            'meetings.view',
            'meetings.create',
            'meetings.update',
            'meetings.delete',
            'meeting-analyses.view',
            'meeting-analyses.create',
            'meeting-analyses.update',
            'meeting-analyses.delete',
            'offers.view',
            'offers.create',
            'offers.update',
            'offers.delete',
            'offer-items.view',
            'offer-items.create',
            'offer-items.update',
            'offer-items.delete',
            'offer-verifications.view',
            'offer-verifications.create',
            'offer-verifications.update',
            'offer-verifications.delete',
            'payrolls.view',
            'payrolls.create',
            'payrolls.update',
            'payrolls.delete',
            'payroll-items.view',
            'payroll-items.create',
            'payroll-items.update',
            'payroll-items.delete',
            'calculations.view',
            'calculations.create',
            'calculations.update',
            'calculations.delete',
            'payroll-calculations.view',
            'payroll-calculations.create',
            'payroll-calculations.update',
            'payroll-calculations.delete',
            'calculator-configs.view',
            'calculator-configs.create',
            'calculator-configs.update',
            'calculator-configs.delete',
            'notifications.view',
            'notifications.create',
            'notifications.update',
            'notifications.delete',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'metrics.view',
            'metrics.create',
            'metrics.update',
            'metrics.delete',
        ] as $ability) {
            Gate::define($ability, function (User $user) use ($ability): bool {
                if (!$user->role) {
                    return false;
                }

                return $user->role
                    ->permissions()
                    ->where('code', $ability)
                    ->exists();
            });
        }

        Gate::define('structure.create', function (User $user, ?User $parent, string $targetRole, string $teamPath): bool {
            $context = app(TokenContext::class);
            return app(StructureAuthorization::class)->canCreate($context, $parent, $targetRole, $teamPath);
        });

        Gate::define('structure.move', function (User $user, User $target, ?User $newParent, ?string $targetTeamPath): bool {
            $context = app(TokenContext::class);
            return app(StructureAuthorization::class)->canMove($context, $target, $newParent, $targetTeamPath);
        });

        Gate::define('structure.remove', function (User $user, User $target): bool {
            $context = app(TokenContext::class);
            return app(StructureAuthorization::class)->canRemove($context, $target);
        });
    }
}
