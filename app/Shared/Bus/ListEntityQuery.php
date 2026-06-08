<?php

namespace Shared\Bus;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Shared\Data\PaginationData;
use Shared\Data\SortData;
use Spatie\LaravelData\Data;

/** @phpstan-consistent-constructor */
abstract class ListEntityQuery implements BaseQuery
{
    /** @var list<string> */
    protected const SORTABLE = ['id'];

    public function __construct(
        public readonly Data $filters,
        public readonly SortData $sort,
        public readonly PaginationData $pagination,
    ) {}

    abstract protected static function filtersFromRequest(Request $request): Data;

    public static function fromRequest(Request $request): static
    {
        $request->validate(array_merge(
            SortData::rules(),
            ['sort' => ['sometimes', 'string', Rule::in(static::SORTABLE)]],
        ));

        return new static(
            filters: static::filtersFromRequest($request),
            sort: SortData::fromRequest($request),
            pagination: PaginationData::fromRequest($request),
        );
    }
}
