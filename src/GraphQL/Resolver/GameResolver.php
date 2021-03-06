<?php

namespace App\GraphQL\Resolver;

use App\GraphQL\Buffer\GenreBuffer;
use GraphQL\Deferred;

class GameResolver extends Resolver
{
    public function resolveGenre(?\stdClass $source, array $args, ?\stdClass $context): Deferred
    {
        GenreBuffer::storeId($source->genre);

        return new \GraphQL\Deferred(function () use ($source, $context): \stdClass {
            GenreBuffer::fetchData($context->em);

            return GenreBuffer::getItem($source->genre);
        });
    }
}
