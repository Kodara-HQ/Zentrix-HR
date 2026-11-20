<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class FileAsset extends Model
{
    use BelongsToCompany, HasUlids;

    protected $table = 'file_assets';

    protected $guarded = ['id'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'confidential' => 'boolean',
        ];
    }
}
