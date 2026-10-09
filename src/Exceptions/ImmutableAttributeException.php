<?php

declare(strict_types=1);

namespace Taldres\ImmutableAttributes\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ImmutableAttributeException extends RuntimeException
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $attributes
     */
    public function __construct(
        public readonly string $model,
        public readonly mixed $key,
        public readonly array $attributes,
    ) {
        parent::__construct(sprintf(
            'Attempted to change immutable attribute(s) [%s] on model [%s]%s.',
            implode(', ', $attributes),
            $model,
            is_int($key) || is_string($key) ? " with key [{$key}]" : '',
        ));
    }

    /**
     * @param  list<string>  $attributes
     */
    public static function on(Model $model, array $attributes): self
    {
        return new self($model::class, $model->getKey(), $attributes);
    }
}
