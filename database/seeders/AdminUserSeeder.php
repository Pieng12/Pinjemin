<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (blank(config('admin.password'))) {
            $this->command?->warn('ADMIN_PASSWORD belum diisi; pembuatan akun admin dilewati.');

            return;
        }

        $credentials = Validator::make([
            'email' => Str::lower(trim((string) config('admin.email'))),
            'name' => config('admin.name'),
            'password' => config('admin.password'),
        ], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email' => 'ADMIN_EMAIL harus berupa alamat email yang valid.',
            'name' => 'ADMIN_NAME harus diisi, maksimal 255 karakter.',
            'password' => 'ADMIN_PASSWORD harus berupa teks minimal 8 karakter.',
        ])->validate();

        $result = DB::transaction(function () use ($credentials): string {
            $admin = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();
            if ($admin && ! $admin->isAdmin()) {
                throw new RuntimeException('ADMIN_EMAIL sudah dipakai akun non-admin. Gunakan email lain untuk akun admin.');
            }
            if ($admin) {
                if (! config('admin.reset_password')) {
                    return 'Akun admin sudah ada; password dan profil tidak diubah.';
                }
                $admin->forceFill(['password' => $credentials['password'], 'remember_token' => null])->save();

                return 'Password admin berhasil diperbarui. Nonaktifkan ADMIN_RESET_PASSWORD setelah selesai.';
            }

            (new User)->forceFill([
                'email' => $credentials['email'],
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'role' => User::RoleAdmin,
            ])->save();

            return 'Akun admin berhasil dibuat tanpa data demo.';
        }, attempts: 3);

        $this->command?->info($result);
    }
}
