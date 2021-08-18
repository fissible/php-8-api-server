<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Routing;

class RouteParameter
{
    public function __construct(
        private string $name,
        private bool $required = true
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}