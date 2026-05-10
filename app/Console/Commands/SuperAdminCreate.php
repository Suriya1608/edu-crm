<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SuperAdminCreate extends Command
{
    protected $signature = 'superadmin:create
                            {--name= : Full name}
                            {--email= : Email address}
                            {--password= : Password}';

    protected $description = 'Create or update a super admin account';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Name');
        $email    = $this->option('email')    ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (empty($name) || empty($email) || empty($password)) {
            $this->error('Name, email, and password are all required.');
            return self::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        $existing = SuperAdmin::on('central_mysql')->where('email', $email)->first();

        if ($existing) {
            $existing->update([
                'name'     => $name,
                'password' => Hash::make($password),
            ]);
            $this->info("Super admin updated: {$email}");
        } else {
            SuperAdmin::create([
                'name'     => $name,
                'email'    => $email,
                'password' => Hash::make($password),
            ]);
            $this->info("Super admin created: {$email}");
        }

        return self::SUCCESS;
    }
}
