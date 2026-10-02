<?php

namespace App\Domain;

final class DomainError extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus = 422, public readonly array $details = [], public readonly array $fields = [])
    {
        parent::__construct($errorCode);
    }
}
