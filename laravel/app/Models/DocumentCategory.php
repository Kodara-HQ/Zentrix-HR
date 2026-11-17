<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;


// Generated from the Prisma schema of the Node version; edit freely.
class DocumentCategory extends Model
{
    use HasUlids;

    protected $table = 'document_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'confidential' => 'boolean',
        ];
    }
}
