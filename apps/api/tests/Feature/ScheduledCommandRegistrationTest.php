<?php

namespace Tests\Feature;

use App\Console\Commands\ReconcileLongRangeFinancialIntents;
use App\Console\Commands\RunFinancialIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledCommandRegistrationTest extends TestCase
{
    public function test_every_scheduled_artisan_class_is_available_and_registered(): void
    {
        preg_match_all('/Schedule::command\(([A-Za-z_][A-Za-z0-9_]*)::class\)/', file_get_contents(base_path('routes/console.php')), $matches);
        $this->assertNotEmpty($matches[1]);
        $registered = Artisan::all();

        foreach ($matches[1] as $shortName) {
            $class = 'App\\Console\\Commands\\'.$shortName;
            $this->assertTrue(class_exists($class), 'Scheduled command class is missing: '.$class);
            $command = app($class);
            $this->assertInstanceOf(Command::class, $command);
            $this->assertArrayHasKey($command->getName(), $registered);
        }
    }

    public function test_financial_reconciliation_and_integrity_keep_five_minute_locks(): void
    {
        $events = app(Schedule::class)->events();

        foreach ([ReconcileLongRangeFinancialIntents::class, RunFinancialIntegrityAudit::class] as $class) {
            $name = app($class)->getName();
            $event = collect($events)->first(fn ($event) => is_string($event->command ?? null) && str_contains($event->command, $name));
            $this->assertNotNull($event, 'Financial schedule is missing: '.$name);
            $this->assertSame('*/5 * * * *', $event->expression);
            $this->assertTrue($event->withoutOverlapping);
            $this->assertTrue($event->onOneServer);
        }
    }
}
