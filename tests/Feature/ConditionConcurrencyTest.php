<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConditionConcurrencyTest extends TestCase
{
    public function test_simultaneous_mysql_condition_agreements_record_handover_once(): void
    {
        $mysql = config('database.connections.mysql');
        $mysql['database'] = null;
        $mysql['url'] = null;
        config(['database.connections.condition_test_admin' => $mysql]);
        try {
            DB::connection('condition_test_admin')->getPdo();
        } catch (\PDOException $exception) {
            $this->markTestSkipped('MySQL tidak tersedia untuk pengujian persetujuan bersamaan.');
        }
        $database = 'pinjemin_condition_test_'.bin2hex(random_bytes(6));
        $this->assertMatchesRegularExpression('/^pinjemin_condition_test_[a-f0-9]{12}$/', $database);
        try {
            DB::connection('condition_test_admin')->statement('CREATE DATABASE `'.$database.'`');
        } catch (QueryException $exception) {
            $this->markTestSkipped('Akun MySQL tidak dapat membuat database pengujian sementara.');
        }
        $originalDefault = config('database.default');
        $workers = [];
        try {
            $mysql['database'] = $database;
            config(['database.connections.condition_mysql_test' => $mysql, 'database.default' => 'condition_mysql_test']);
            Artisan::call('migrate', ['--database' => 'condition_mysql_test', '--force' => true, '--no-interaction' => true]);
            $owner = User::factory()->create();
            $renter = User::factory()->create();
            $item = Item::factory()->for($owner)->create(['quantity' => 1]);
            $date = now(Booking::RentalTimezone)->toDateString();
            $booking = Booking::factory()->for($item)->for($renter, 'renter')->create([
                'start_date' => $date, 'end_date' => $date, 'quantity' => 1,
                'status' => 'approved', 'approved_at' => now(), 'payment_legacy' => false, 'total_amount' => 100000,
                'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'photos_required' => false, 'identity_required' => false]],
            ]);
            BookingPayment::factory()->for($booking)->create(['status' => 'verified', 'amount' => 100000]);
            $session = ConditionSession::factory()->for($booking)->create(['status' => 'review', 'opened_at' => now(), 'expires_at' => now()->addMinutes(30)]);
            ConditionReport::factory()->for($session, 'session')->for($owner, 'user')->create(['role' => 'owner']);
            ConditionReport::factory()->for($session, 'session')->for($renter, 'user')->create(['role' => 'renter']);
            $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'DB_HOST' => (string) $mysql['host'], 'DB_PORT' => (string) $mysql['port'],
                'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => $mysql['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
            $code = <<<'PHP'
                require $argv[1].'/vendor/autoload.php';
                $app = require $argv[1].'/bootstrap/app.php';
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                $app->make(App\Services\ConditionInspectionService::class)->act(
                    App\Models\Booking::where('booking_code', $argv[2])->firstOrFail(),
                    App\Models\User::findOrFail($argv[3]), 'handover', ['action' => 'agree', 'session_id' => (int) $argv[4]]
                );
                PHP;
            foreach ([$owner, $renter] as $actor) {
                $worker = new Process([PHP_BINARY, '-r', $code, base_path(), $booking->booking_code, (string) $actor->id, (string) $session->id], base_path(), $environment, timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            }
            $this->assertSame('accepted', $session->fresh()->status);
            $this->assertNotNull($booking->fresh()->handed_over_at);
            $this->assertSame(2, $session->reports()->where('decision', 'agree')->count());
            $this->assertCount(2, $session->fresh()->events);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            DB::purge('condition_mysql_test');
            config(['database.default' => $originalDefault]);
            DB::connection('condition_test_admin')->statement('DROP DATABASE `'.$database.'`');
            DB::purge('condition_test_admin');
        }
    }
}
