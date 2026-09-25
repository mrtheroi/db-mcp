<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('memory:revoke {email : Email of the user whose tokens are revoked}')]
#[Description('Revoke every Sanctum token of a user, disconnecting all their agents')]
class RevokeMemoryTokens extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("User {$email} not found.");

            return self::FAILURE;
        }

        $revoked = $user->tokens()->delete();

        $this->line("Revoked {$revoked} tokens.");

        return self::SUCCESS;
    }
}
