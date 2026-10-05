<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    use HasFactory;

    public const REDACTED = '******';

    protected $fillable = [
        'to',
        'message',
        'status',
    ];
}
