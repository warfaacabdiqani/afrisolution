<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PlatformService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'saas:create-admin';

    protected $description = 'Create a new platform administrator using an interactive password prompt';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Name'), 'email' => $this->ask('Email'), 'password' => $this->secret('Password (12+ characters, mixed case and a number)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = new User($data);
            $user->is_platform_admin = true;
            $user->save();
            app(PlatformService::class)->audit($user->id, 'admin.created', 'user', $user->id);
        });
        $this->info('Platform administrator created. Sign in at /app/login.');

        return self::SUCCESS;
    }
}
