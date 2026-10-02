<?php

namespace App\Providers;

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
        // Load global helpers if present
        $helpers = app_path('Helpers/helpers.php');
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        \Illuminate\Pagination\Paginator::useTailwind();
        
        // Blade Feature Directives
        \Illuminate\Support\Facades\Blade::if('hasfeature', function (string $featureKey) {
            $org = \App\Helpers\OrganizationHelper::getActiveOrganization();
            return $org ? $org->hasFeature($featureKey) : false;
        });

        \Illuminate\Support\Facades\Blade::if('hasanyfeature', function (array $featureKeys) {
            $org = \App\Helpers\OrganizationHelper::getActiveOrganization();
            if (!$org) {
                return false;
            }
            foreach ($featureKeys as $featureKey) {
                if ($org->hasFeature($featureKey)) {
                    return true;
                }
            }
            return false;
        });
    }
}
