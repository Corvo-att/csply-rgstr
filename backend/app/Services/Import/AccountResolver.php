<?php

namespace App\Services\Import;

use App\Models\Cosplayer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountResolver
{
    /**
     * Resolve or create a User and their Cosplayer record from imported identity fields.
     *
     * @param array{
     *     email: string,
     *     name?: ?string,
     *     character_name?: ?string,
     *     series?: ?string,
     *     experience_level?: ?string,
     *     bio?: ?string
     * } $identity
     */
    public function resolve(array $identity): Cosplayer
    {
        $rawEmail = $identity['email'] ?? '';
        $email = strtolower(trim($rawEmail));
        if (empty($email)) {
            throw new \InvalidArgumentException('Email is required to resolve user account.');
        }

        $rawName = $identity['name'] ?? null;
        $name = !empty(trim((string) $rawName))
            ? trim((string) $rawName)
            : explode('@', $email)[0];

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name'           => $name,
                'email'          => $email,
                'password'       => Hash::make(Str::random(32)),
                'is_placeholder' => true,
                'invited_at'     => null,
            ]);
        }

        $cosplayer = Cosplayer::where('user_id', $user->id)->first();

        $charName = trim((string) ($identity['character_name'] ?? '')) ?: 'TBD';
        $series   = trim((string) ($identity['series'] ?? '')) ?: 'TBD';
        $exp      = trim((string) ($identity['experience_level'] ?? '')) ?: 'Beginner';
        $bio      = isset($identity['bio']) ? trim((string) $identity['bio']) : null;

        if (!$cosplayer) {
            $cosplayer = Cosplayer::create([
                'user_id'          => $user->id,
                'character_name'   => $charName,
                'series'           => $series,
                'experience_level' => $exp,
                'bio'              => $bio,
            ]);
        } else {
            // Update fields if cosplayer currently has placeholder values and new ones are provided
            $updates = [];
            if ($charName !== 'TBD' && $cosplayer->character_name === 'TBD') {
                $updates['character_name'] = $charName;
            }
            if ($series !== 'TBD' && $cosplayer->series === 'TBD') {
                $updates['series'] = $series;
            }
            if ($exp !== 'Beginner' && $cosplayer->experience_level === 'Beginner') {
                $updates['experience_level'] = $exp;
            }
            if (!empty($bio) && empty($cosplayer->bio)) {
                $updates['bio'] = $bio;
            }

            if (!empty($updates)) {
                $cosplayer->update($updates);
            }
        }

        return $cosplayer;
    }
}
