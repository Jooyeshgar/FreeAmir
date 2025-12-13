<?php

namespace App\DTO;

class DomainValidationMessage
{
    public function __construct(
        public string $type,   // 'error' | 'warning' | 'notice'
        public string $text,
        public array $meta = []
    ) {}
}
