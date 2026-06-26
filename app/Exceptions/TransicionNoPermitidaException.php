<?php
namespace App\Exceptions;
use Exception;

class TransicionNoPermitidaException extends Exception
{
    public function __construct(string $mensaje = 'Transición no permitida.')
    {
        parent::__construct($mensaje);
    }
}
