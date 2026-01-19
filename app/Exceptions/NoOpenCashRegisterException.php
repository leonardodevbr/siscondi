<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class NoOpenCashRegisterException extends Exception
{
    public function __construct(string $message = 'Não é possível realizar vendas sem um caixa aberto.')
    {
        parent::__construct($message);
    }
}
