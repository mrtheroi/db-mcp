<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'code_hash', 'expires_at'])]
class LoginCode extends Model
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Store a new code for the email, invalidating its previous ones, and return it in plain text.
     */
    public static function issue(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        self::where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        self::create([
            'email' => $email,
            'code_hash' => self::hash($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public static function latestUsableFor(string $email): ?self
    {
        return self::where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')->first();
    }

    public function isBurned(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, self::hash($code));
    }

    /**
     * Mark the code as consumed, atomically, so two concurrent requests cannot both use it.
     */
    public function consume(): bool
    {
        return self::whereKey($this->getKey())->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
