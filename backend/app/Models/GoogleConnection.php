<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleConnection extends Model
{
    protected $fillable = [
        'admin_id',
        'google_user_id',
        'email',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes'           => 'array',
            'revoked_at'       => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function sheetLinks()
    {
        return $this->hasMany(FormSheetLink::class);
    }

    public function isConnected(): bool
    {
        return $this->revoked_at === null && !empty($this->refresh_token);
    }
}
