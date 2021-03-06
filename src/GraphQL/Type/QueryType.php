<?php

namespace App\GraphQL\Type;

use App\GraphQL\Resolver\QueryResolver;
use App\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class QueryType extends ObjectType
{
    private $resolver;

    public function __construct()
    {
        $this->resolver = new QueryResolver();

        $config =
        [
            'name' => 'Query',
            'fields' => [
                'game' => [
                    'type' => Types::game(),
                    'args' => [
                        'id' => Types::nonNull(Types::id()),
                    ],
                ],
                'games' => [
                    'type' => Types::listOf(Types::game()),
                ],
                'genre' => [
                    'type' => Types::genre(),
                    'args' => [
                        'id' => Types::nonNull(Types::id()),
                    ],
                ],
            ],
            'resolveField' => function ($source, $args, $context, ResolveInfo $info) {
                $method = 'resolve'.ucfirst($info->fieldName);

                if (method_exists($this->resolver, $method)) {
                    return $this->resolver->{$method}($source, $args, $context, $info);
                }
            },
        ];

        parent::__construct($config);
    }
}
