<?php

namespace App\Providers;

use App\Models\Organizer;
use App\Services\Media\FfprobeMediaInspector;
use App\Services\Media\MediaInspector;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replace with a real SMS provider binding in production.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
        $this->app->bind(MediaInspector::class, FfprobeMediaInspector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly on mass assignment of non-fillable attributes outside production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->shareBackOfficeNavigation();
    }

    /**
     * Data needed by the back-office sidebar, whatever page is rendered.
     */
    private function shareBackOfficeNavigation(): void
    {
        View::composer('components.bo.sidebar', function ($view): void {
            if (request()->routeIs('admin.*')) {
                $counts = Organizer::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
                $view->with('adminCounts', ['all' => $counts->sum(), ...$counts->all()]);

                return;
            }

            $view->with('navOrganizers', auth('web')->user()?->organizers()->orderBy('name')->get() ?? collect());
        });
    }
}
