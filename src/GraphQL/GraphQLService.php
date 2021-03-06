<?php

namespace App\GraphQL;

use App\CustomException;
use GraphQL\GraphQL;
use GraphQL\Error\FormattedError;
use GraphQL\Type\Schema;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpKernel\HttpException;

class GraphQLService
{
    const CONTENT_TYPE = 'json';
    const MUTATION_METHOD = 'POST';
    const QUERY_METHOD = 'GET';

    private $content;
    private $context;
    private $debug = false;
    private $method;

    public function __construct($origin, \stdClass $context = null)
    {
        $this->context = $context;

        if ($origin instanceof HttpRequest) {
            $this->instantiateFromRequest($origin);
        } elseif (true === is_array($origin)) {
            $this->instantiateFromArray($origin);
        } else {
            throw new \InvalidArgumentException('Argument 1 (origin) passed to the App\GraphQL\GraphQLService::__construct has to be an instance of Symfony\Component\HttpFoundation\Request or type of array');
        }
    }

    public function errorHandler(array $exceptions): array
    {
        $exceptionArray =
        [
            'category' => 'user',
            'message' => 'Errors are listed.',
            'list' => [],
        ];

        foreach ($exceptions as $exception) {
            $previousException = $exception->getPrevious();

            if ($previousException instanceof CustomException) {
                $exceptionArray['list'][] = $previousException->getList();
            } else {
                $defaultFormatter = FormattedError::prepareFormatter(null, $this->debug);

                return $defaultFormatter($exception);
            }
        }

        return $exceptionArray;
    }

    public function execute(bool $debug = false): array
    {
        $this->debug = true;

        $schema = new Schema(
        [
            'query' => Types::query(),
            'mutation' => Types::mutation(),
        ]
        );

        $query = true === isset($this->content['query']) ? $this->content['query'] : null;
        $variables = true === isset($this->content['variables']) ? $this->content['variables'] : null;
        $operationName = true === isset($this->content['operationName']) ? $this->content['operationName'] : null;
        $rootValue = null;
        $context = $this->context;
        $fieldResolver = null;
        $validationRules = null;

        $result = GraphQL::executeQuery(
            $schema,
            $query,
            $rootValue,
            $context,
            $variables,
            $operationName,
            $fieldResolver,
            $validationRules
        )->setErrorsHandler([$this, 'errorHandler']);

        return $result->toArray($debug);
    }

    private function getRequestContent(): array
    {
        if (self::QUERY_METHOD === $this->method) {
            return $_GET;
        } else {
            return json_decode(file_get_contents('php://input'), true);
        }
    }

    private function instantiateFromArray(array $array): void
    {
        $this->content = $array;
    }

    private function instantiateFromRequest(HttpRequest $request): void
    {
        $this->method = $request->getMethod();

        if (false === $this->validateMethod($this->method)) {
            throw new HttpException\MethodNotAllowedHttpException([self::QUERY_METHOD, self::MUTATION_METHOD], 'Use '.self::QUERY_METHOD.' for queries and '.self::MUTATION_METHOD.' for mutations');
        }

        if (false === $this->validateContentType($request)) {
            throw new HttpException\UnsupportedMediaTypeHttpException('Use '.self::CONTENT_TYPE.' Content-Type header');
        }

        $this->content = $this->getRequestContent();

        if (false === $this->validateContentForm($this->content)) {
            throw new HttpException\BadRequestHttpException('Use correct parameters in a request: query (required), variables (optional), operationName (optional).');
        }
    }

    private function validateMethod(string $method): bool
    {
        return self::QUERY_METHOD === $method || self::MUTATION_METHOD === $method ? true : false;
    }

    private function validateContentForm(array $content): bool
    {
        return isset($content['query']);
    }

    private function validateContentType(HttpRequest $request): bool
    {
        return self::MUTATION_METHOD === $this->method && self::CONTENT_TYPE !== $request->getContentType() ? false : true;
    }
}
