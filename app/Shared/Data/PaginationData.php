<?php

namespace Shared\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class PaginationData extends Data
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
        );
    }
}
