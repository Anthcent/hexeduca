<?php

namespace Modules\Users\Application\DTOs;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
