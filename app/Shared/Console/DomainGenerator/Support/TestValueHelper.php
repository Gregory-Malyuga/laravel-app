<?php

namespace Shared\Console\DomainGenerator\Support;

use Illuminate\Support\Str;

class TestValueHelper
{
    public function valueFor(string $fieldName, string $phpType, bool $alternate = false): string
    {
        if ($phpType === 'int') {
            return $alternate ? '99' : '42';
        }

        if ($phpType === 'float') {
            return $alternate ? '99.99' : '42.50';
        }

        if ($phpType === 'bool') {
            return $alternate ? 'false' : 'true';
        }

        if (str_ends_with($fieldName, 'email')) {
            return $alternate ? "'updated@example.com'" : "'test@example.com'";
        }

        if (str_ends_with($fieldName, 'phone') || str_ends_with($fieldName, 'tel')) {
            return $alternate ? "'+7 800 000 0001'" : "'+7 800 000 0000'";
        }

        if (str_contains($fieldName, 'first_name')) {
            return $alternate ? "'Jane'" : "'John'";
        }

        if (str_contains($fieldName, 'last_name')) {
            return $alternate ? "'Smith'" : "'Doe'";
        }

        $label = Str::headline($fieldName);

        return $alternate ? "'Updated {$label}'" : "'Test {$label}'";
    }
}
