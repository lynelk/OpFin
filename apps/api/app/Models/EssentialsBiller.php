<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EssentialsBiller extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'category', 'account_label', 'route', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
