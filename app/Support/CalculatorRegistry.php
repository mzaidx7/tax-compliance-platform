<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ObligationCalculator;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final readonly class CalculatorRegistry
{
    public function __construct(private Container $container) {}

    public function get(string $key): ObligationCalculator
    {
        foreach ($this->configuredClasses() as $class) {
            $calculator = $this->container->make($class);

            if (! $calculator instanceof ObligationCalculator) {
                throw new InvalidArgumentException("Configured calculator {$class} does not implement the calculator contract.");
            }

            if (hash_equals($calculator->key(), $key)) {
                return $calculator;
            }
        }

        throw new InvalidArgumentException("No registered calculator has key {$key}.");
    }

    /** @return list<ObligationCalculator> */
    public function all(): array
    {
        return array_map(fn (string $class): ObligationCalculator => $this->getByClass($class), $this->configuredClasses());
    }

    /** @return list<string> */
    private function configuredClasses(): array
    {
        $classes = config('platform.rules.calculators', []);

        if (! is_array($classes)) {
            throw new InvalidArgumentException('Configured calculators must be a list.');
        }

        return array_values(array_filter($classes, 'is_string'));
    }

    private function getByClass(string $class): ObligationCalculator
    {
        $calculator = $this->container->make($class);

        if (! $calculator instanceof ObligationCalculator) {
            throw new InvalidArgumentException("Configured calculator {$class} does not implement the calculator contract.");
        }

        return $calculator;
    }
}
