<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    public function test_simultaneous_mysql_approvals_cannot_overbook_one_unit(): void
    {
        $mysql = config('database.connections.mysql');
        $mysql['database'] = null;
        $mysql['url'] = null;
        config(['database.connections.booking_test_admin' => $mysql]);
        try {
            DB::connection('booking_test_admin')->getPdo();
        } catch (\PDOException $exception) {
            $this->markTestSkipped('MySQL tidak tersedia untuk pengujian konkurensi terisolasi.');
        }

        $database = 'pinjemin_booking_test_'.bin2hex(random_bytes(6));
        $this->assertMatchesRegularExpression('/^pinjemin_booking_test_[a-f0-9]{12}$/', $database);
        try {
            DB::connection('booking_test_admin')->statement('CREATE DATABASE `'.$database.'`');
        } catch (QueryException $exception) {
            $this->markTestSkipped('Akun MySQL tidak dapat membuat database pengujian sementara.');
        }
        $originalDefault = config('database.default');
        $workers = [];
        try {
            $mysql['database'] = $database;
            config(['database.connections.booking_mysql_test' => $mysql, 'database.default' => 'booking_mysql_test']);
            Artisan::call('migrate', ['--database' => 'booking_mysql_test', '--force' => true, '--no-interaction' => true]);
            $item = Item::factory()->create(['quantity' => 1]);
            $date = now(Booking::RentalTimezone)->addDays(2)->toDateString();
            $bookings = Booking::factory()->count(2)->for($item)->create(['start_date' => $date, 'end_date' => $date, 'quantity' => 1]);
            $environment = [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'DB_HOST' => (string) $mysql['host'], 'DB_PORT' => (string) $mysql['port'],
                'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => $mysql['password'],
                'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            ];
            $code = <<<'PHP'
                require $argv[1].'/vendor/autoload.php';
                $app = require $argv[1].'/bootstrap/app.php';
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                try {
                    $app->make(App\Services\BookingService::class)->approve(App\Models\Booking::where('booking_code', $argv[2])->firstOrFail());
                    exit(0);
                } catch (Illuminate\Validation\ValidationException $exception) {
                    exit(3);
                }
                PHP;
            foreach ($bookings as $booking) {
                $worker = new Process([PHP_BINARY, '-r', $code, base_path(), $booking->booking_code], base_path(), $environment, timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            $exitCodes = [];
            foreach ($workers as $worker) {
                $exitCodes[] = $worker->wait();
                $this->assertContains($worker->getExitCode(), [0, 3], $worker->getErrorOutput());
            }
            sort($exitCodes);
            $this->assertSame([0, 3], $exitCodes);
            $this->assertSame(1, Booking::query()->where('status', BookingStatus::Approved->value)->count());
            $this->assertSame(1, Booking::query()->where('status', BookingStatus::Pending->value)->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            DB::purge('booking_mysql_test');
            config(['database.default' => $originalDefault]);
            DB::connection('booking_test_admin')->statement('DROP DATABASE `'.$database.'`');
            DB::purge('booking_test_admin');
        }

    }
}
