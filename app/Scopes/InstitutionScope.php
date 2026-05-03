<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InstitutionScope implements Scope
{
    protected static $applyingScope = false;

    public function apply(Builder $builder, Model $model)
    {
        // Prevent recursive application
        if (self::$applyingScope) {
            return;
        }

        self::$applyingScope = true;

        try {
            if (Auth::check() && Auth::user()->role == 'Admin') {
                $builder->where('institution_id', Auth::user()->institution_id);
            }
        } finally {
            self::$applyingScope = false;
        }
    }
}
