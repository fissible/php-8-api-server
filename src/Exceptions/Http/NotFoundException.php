<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Exceptions\Http;

use Exception;

class NotFoundException extends Exception
{
    public function __construct($url, Exception $previous = null)
    {
        $message = sprintf('"%s": not found', $url);
        parent::__construct($message, 404, $previous);
    }
}