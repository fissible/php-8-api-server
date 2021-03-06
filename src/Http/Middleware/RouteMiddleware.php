<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Http\Middleware;

use Ajthenewguy\Php8ApiServer\Application;
use Ajthenewguy\Php8ApiServer\Exceptions\Http\ServerError;
use Ajthenewguy\Php8ApiServer\Facades\Log;
use Ajthenewguy\Php8ApiServer\Http\JsonResponse;
use Ajthenewguy\Php8ApiServer\Http\Request;
use Ajthenewguy\Php8ApiServer\Routing\Route;
use Ajthenewguy\Php8ApiServer\Routing\RouteParameter;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class RouteMiddleware extends Middleware
{
    public function __invoke(Request $request)
    {
        try {
            $requestMethod = $request->getMethod();
            $requestTarget = $request->getRequestTarget();

            try {
                if ($Route = Route::lookup($requestMethod, $requestTarget)) {
                    $parameters = [];

                    if ($Route->hasParams()) {
                        $parameterKeys = $Route->getParameters()->map(function (RouteParameter $Parameter) {
                            return $Parameter->getName();
                        })->toArray();

                        $parameters = $Route->pregMatch($requestTarget);

                        foreach ($parameterKeys as $name) {
                            if (!array_key_exists($name, $parameters)) {
                                $parameters[$name] = null;
                            }
                        }
                    }

                    return $Route->dispatch($request, $parameters)->then(function (Response $response) {
                        return $response;
                    }, function (\Throwable $e) {
                        Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

                        return JsonResponse::make($e->getMessage(), 500);
                    });
                }
            } catch (\Exception|\Throwable $e) {
                Log::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

                return JsonResponse::make($e->getMessage(), $e->getCode());
            }
        } catch (ServerError $e) {
            return JsonResponse::make($e->getMessage(), $e->getCode());
        }

        return JsonResponse::make('Internal Server Error', 500);
    }
}