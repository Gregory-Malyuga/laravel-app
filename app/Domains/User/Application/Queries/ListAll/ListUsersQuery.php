<?php

namespace Domains\User\Application\Queries\ListAll;

use Domains\User\Application\Data\UserFilterData;
use Illuminate\Http\Request;
use Shared\Bus\ListEntityQuery;

class ListUsersQuery extends ListEntityQuery
{
    protected const array SORTABLE = ['id', 'name', 'email', 'created_at'];

    protected static function filtersFromRequest(Request $request): UserFilterData
    {
        return UserFilterData::from($request);
    }
}
