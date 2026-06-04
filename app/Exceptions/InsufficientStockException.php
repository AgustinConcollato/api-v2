<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /** @var array<int,array<string,mixed>> */
    public array $errors;

    /**
     * @param array<int,array<string,mixed>> $errors Lista de ítems sin stock (product_id, variant_id, name, available, requested)
     */
    public function __construct(array $errors, string $message = 'Stock insuficiente.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }
}
