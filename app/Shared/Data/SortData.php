<?php

namespace Shared\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class SortData extends Data
{
    public function __construct(
        public readonly string $field = 'id',
        public readonly string $direction = 'asc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            field: $request->string('sort', 'id')->toString(),
            direction: $request->string('direction', 'asc')->toString(),
        );
    }
}
