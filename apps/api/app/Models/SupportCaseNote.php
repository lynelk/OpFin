<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseNote extends Model
{
    protected $fillable = ['support_case_id', 'created_by', 'note', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }
}
