<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class NotificationSetting extends Model
{
    use HasUlids;

    protected $table = 'notification_settings';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }
}
