<?php

namespace App\Contracts;

// Stage B: immutable reference observations, never change stored operation money.
interface ReferenceRateProvider
{
    /** @return array<array{currency_code:string,effective_on:string,mdl_per_unit:string,provider:string,published_nominal:string,published_value:string,provenance:array}> */
    public function ratesOn(string $date): array;
}
