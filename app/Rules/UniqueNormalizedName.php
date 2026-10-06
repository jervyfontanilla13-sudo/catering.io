<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Closure;

class UniqueNormalizedName implements ValidationRule
{
    public function __construct(
        private Model $record,
        private string $recordType,
    ) {}

    public static function normalize(string $name): string
    {
        return mb_strtolower(Str::squish($name));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalizedName = self::normalize($value);
        $query = $this->record->newQuery();

        if ($this->record->exists) {
            $query->whereKeyNot($this->record->getKey());
        }

        $duplicateExists = $query->pluck('name')
            ->contains(fn (string $name): bool => self::normalize($name) === $normalizedName);

        if ($duplicateExists) {
            $fail("A {$this->recordType} with this name already exists. Please choose a different name.");
        }
    }
}
