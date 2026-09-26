<?php

namespace App\Contracts;

interface MobileMoneyProviderReadinessInterface
{
    /**
     * Return a non-secret readiness assessment for provider submission.
     *
     * @return array{status:string,missing?:array<int,string>,reason?:?string}
     */
    public function readiness(): array;
}
