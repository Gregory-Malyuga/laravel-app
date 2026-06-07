<?php

namespace Shared\Testing\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-require-extends TestCase
 */
trait InteractsWithRepository
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function assertPaginatorTotal(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertSame($expected, $paginator->total());
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function assertPaginatorPerPage(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertSame($expected, $paginator->perPage());
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function assertPaginatorCount(int $expected, LengthAwarePaginator $paginator): void
    {
        $this->assertCount($expected, $paginator->items());
    }
}
