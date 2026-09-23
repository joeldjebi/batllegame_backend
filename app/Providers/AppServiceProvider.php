<?php

namespace App\Providers;

use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\JuryScore;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Performance;
use App\Models\Preselection;
use App\Models\PreselectionLike;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Models\PublicVote;
use App\Models\Stage;
use App\Realtime\BroadcastModelChanges;
use App\Realtime\Realtime;
use App\Services\Media\FfprobeMediaInspector;
use App\Services\Media\FfprobeTagReader;
use App\Services\Media\MediaInspector;
use App\Services\Media\MediaTagReader;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Queue;
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
        $this->app->bind(MediaTagReader::class, FfprobeTagReader::class);
        $this->app->singleton(Realtime::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly on mass assignment of non-fillable attributes outside production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->shareBackOfficeNavigation();
        $this->registerRealtime();
    }

    /**
     * Socket.IO updates: observe the important models, send the buffer once per request / job.
     */
    private function registerRealtime(): void
    {
        foreach ([Competition::class, Participant::class, Payment::class, Preselection::class, PreselectionSubmission::class,
            PreselectionLike::class, PreselectionScore::class, Performance::class, PublicVote::class, JuryScore::class,
            BattleMatch::class, Stage::class, Judge::class] as $model) {
            $model::observe(BroadcastModelChanges::class);
        }

        // `composer dev` also starts the Socket.IO server.
        if ($this->app->runningInConsole() && config('realtime.enabled')) {
            DevCommands::node('realtime', 'realtime');
        }

        $flush = fn () => $this->app->make(Realtime::class)->flush();
        $this->app->terminating($flush);
        Queue::after($flush);
        Queue::failing($flush);
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
