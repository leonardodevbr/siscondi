<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class NoOpenCashRegisterException extends Exception
{
    public function __construct()
    {
        parent::__construct('Não é possível realizar vendas sem um caixa aberto.');
    }
}
