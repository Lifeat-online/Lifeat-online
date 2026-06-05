<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CallbackRejectionException extends RuntimeException
{
    public function __construct(string $message, int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message, $statusCode);
    }
}
