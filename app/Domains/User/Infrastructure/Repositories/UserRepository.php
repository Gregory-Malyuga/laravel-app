<?php

namespace Domains\User\Infrastructure\Repositories;

use Domains\User\Domain\Models\User;
use Domains\User\Domain\UserFilterData;
use Shared\Elasticsearch\ElasticsearchSearchable;
use Shared\Elasticsearch\InteractsWithElasticsearch;
use Shared\Filters\FilterInterface;
use Shared\Filters\NoOpFilter;
use Shared\Repository\BaseRepository;
use Spatie\LaravelData\Data;

class UserRepository extends BaseRepository implements ElasticsearchSearchable
{
    use InteractsWithElasticsearch;

    protected string $model = User::class;

    /** @var array<string, class-string<FilterInterface>> */
    protected array $filterMap = [
        'email' => NoOpFilter::class,
    ];

    public function shouldSearch(Data $filters): bool
    {
        return $filters instanceof UserFilterData && $filters->email !== null;
    }

    /** @return array<string, mixed> */
    public function buildEsQuery(Data $filters): array
    {
        assert($filters instanceof UserFilterData);

        return [
            'size' => 1000,
            '_source' => false,
            'query' => [
                'multi_match' => [
                    'query' => $filters->email,
                    'type' => 'bool_prefix',
                    'fields' => ['email', 'email._2gram', 'email._3gram'],
                ],
            ],
        ];
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        return $user;
    }
}
