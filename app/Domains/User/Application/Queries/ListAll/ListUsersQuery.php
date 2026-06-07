<?php

namespace Domains\User\Application\Queries\ListAll;

use Domains\User\Domain\UserFilterData;
use Illuminate\Http\Request;
use Shared\Bus\BaseQuery;
use Shared\Data\PaginationData;
use Shared\Data\SortData;

readonly class ListUsersQuery implements BaseQuery
{
    public function __construct(
        public readonly UserFilterData $filters,
        public readonly SortData $sort,
        public readonly PaginationData $pagination,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            filters: UserFilterData::from($request->all()),
            sort: SortData::fromRequest($request),
            pagination: PaginationData::fromRequest($request),
        );
    }
}
