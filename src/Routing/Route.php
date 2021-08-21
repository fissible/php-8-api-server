<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Routing;

use Ajthenewguy\Php8ApiServer\Collection;
use Ajthenewguy\Php8ApiServer\Exceptions\Http\MethodNotAllowedError;
use Ajthenewguy\Php8ApiServer\Exceptions\Http\NotFoundError;
use Ajthenewguy\Php8ApiServer\Http\Request;
use Ajthenewguy\Php8ApiServer\Str;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise;

class Route
{
    public static Collection $Table;

    private Collection $Parameters;

    private static array $globalMiddleware;

    public function __construct(
        private string $method,
        private string $uri,
        private $action,
        private ?Guard $Guard = null,
        private array $middleware = []
    )
    {}

    public static function middleware(array $globalMiddleware = [])
    {
        static::$globalMiddleware = $globalMiddleware;
    }

    public static function group(callable $group)
    {
        $group();
        unset(static::$globalMiddleware);
    }

    public static function delete(string $uri, callable|array $action, ?Guard $Guard = null)
    {
        static::registerRoute('DELETE', $uri, $action, $Guard);
    }

    public static function get(string $uri, callable|array $action, ?Guard $Guard = null)
    {
        static::registerRoute('GET', $uri, $action, $Guard);
    }

    public static function lookup(string $method, string $url)
    {
        $Matches = static::$Table->filter(function (Route $Route) use ($url) {
            return $Route->matches($url);
        });

        if ($Matches->empty()) {
            throw new NotFoundError($url);
        }

        $Matches = $Matches->filter(function (Route $Route) use ($method) {
            if ($Route->getMethod() !== 'ANY' && $Route->getMethod() !== strtoupper($method)) {
                return false;
            }
            return true;
        });

        if ($Matches->empty()) {
            throw new MethodNotAllowedError($method, $url);
        }

        $Matches = $Matches->sort(function (Route $RouteA, Route $RouteB) use ($url) {
            $compareA = Str::before($RouteA->getUri(), '{') ?: $RouteA->getUri();
            $compareB = Str::before($RouteB->getUri(), '{') ?: $RouteB->getUri();
            $levA = levenshtein($url, $compareA);
            $levB = levenshtein($url, $compareB);

            if ($levA === $levB) {
                return 0;
            }
            return $levB > $levA ? -1 : 1;
        });

        if (!$Matches->empty()) {
            
        } else {
            // throw new NotFoundError($url);
        }

        return $Matches->first();
    }

    public static function patch(string $uri, callable|array $action, ?Guard $Guard = null)
    {
        static::registerRoute('PATCH', $uri, $action, $Guard);
    }

    public static function post(string $uri, callable|array $action, ?Guard $Guard = null)
    {
        static::registerRoute('POST', $uri, $action, $Guard);
    }

    public static function put(string $uri, callable|array $action, ?Guard $Guard = null)
    {
        static::registerRoute('PUT', $uri, $action, $Guard);
    }

    public static function table(): Collection
    {
        return static::$Table;
    }

    public function dispatch(ServerRequestInterface $request, array $parameters): Promise\PromiseInterface
    {
        $action = $this->getAction();
        $response = null;
        $request = new Request($request);
        array_unshift($parameters, $request);

        if (is_array($action)) {
            $response = call_user_func_array($action, ...$parameters);
        } elseif (is_callable($action)) {
            $response = $action(...$parameters);
        }

        if ($response !== null) {
            if ($response instanceof Response) {
                return Promise\resolve($response);
            } elseif ($response instanceof Promise\Promise) {
                return $response->then(function (Response $response) {
                    return $response;
                });
            }
            if (strlen($response) > 0) {
                return Promise\resolve(new Response(200, ['Content-Type' => 'application/json'], $response));
            }
            return Promise\resolve(new Response(204, ['Content-Type' => 'application/json'], $response));
        }

        return Promise\resolve(new Response(404, ['Content-Type' => 'application/json'], 'Not found'));
    }

    public function getAction(): array|callable
    {
        return $this->action;
    }

    public function getGuard(): ?Guard
    {
        return $this->Guard;
    }

    public function getId(): string
    {
        return $this->method . ':' . $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getParameter(string $name): ?RouteParameter
    {
        return $this->getParameters()->first(function (RouteParameter $Parameter) use ($name) {
            return $Parameter->getName() === $name;
        });
    }

    public function getParameters(): Collection
    {
        if (!isset($this->Parameters)) {
            $this->Parameters = $this->parseParameters();
        }

        return $this->Parameters;
    }
    
    public function getUri(): string
    {
        return $this->uri;
    }

    public function hasParams(): bool
    {
        return $this->paramCount() > 0;
    }

    public function matches(string $url): bool
    {
        if ($this->getUri() === $url && !$this->hasParams()) {
            return true;
        }

        $matches = $this->pregMatch($url);

        if ($matches === false) {
            return false;
        }

        $Parameters = $this->getParameters();
        $RequiredParameters = $Parameters->filter(function (RouteParameter $Parameter) {
            return $Parameter->isRequired();
        });

        if (empty($matches) && $RequiredParameters->count() > 0) {
            return false;
        }

        $Parameters->each(function (RouteParameter $Parameter) use ($matches) {
            if ($Parameter->isRequired() && !isset($matches[$Parameter->getName()])) {
                return false;
            }
        });

        return true;
    }

    /**
     * Given a requested URL match against the config.
     * 
     * @param string $url
     * @return array
     */
    public function pregMatch(string $url)
    {
        $values = [];
        $routeUri = $this->getUri();
        $pattern = '#^' . $routeUri;

        $this->getParameters()->each(function ($Parameter) use (&$pattern) {
            $parameterName = $Parameter->getName();
            $search = '';
            $replace = '';
            if ($Parameter->isRequired()) {
                $search = '{' . $parameterName . '}';
                $replace = '(?P<' . $parameterName . '>[a-z0-9_-]+)';
            } else {
                $search = '/{' . $parameterName . '?}';
                $replace = '(?:/(?P<' . $parameterName . '>[a-z0-9_-]+))?';
            }
            $pattern = str_replace($search, $replace, $pattern);
        });
        $pattern  .= '$#i';

        if (preg_match_all($pattern, $url, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER)) {
            $this->getParameters()->each(function (RouteParameter $Parameter) use ($matches, &$values) {
                foreach ($matches as $key => $match) {
                    if ($key === $Parameter->getName() && strlen($match[0][0]) > 0) {
                        $values[$key] = $match[0][0];
                    }
                }
            });
        } else {
            return false;
        }

        return $values;
    }

    public function paramCount(): int
    {
        return $this->getParameters()->count();
    }

    /**
     * Parse the parameter placeholders in the URI.
     * 
     * @return Collection
     */
    private function parseParameters(): Collection
    {
        $offset = 0;
        $closePos = 0;
        $Parameters = new Collection();

        while ($openPos = strpos($this->uri, '{', $offset)) {
            if ($closePos = strpos($this->uri, '}', $openPos)) {
                $length = $closePos - $openPos - 1;
                $name = substr($this->uri, $openPos + 1, $length);
                $required = true;

                if (Str::endsWith($name, '?')) {
                    $required = false;
                    $name = rtrim($name, '?');
                }

                $Parameters->push(new RouteParameter($name, $required));
                $offset = $closePos;
            }
        }

        return $Parameters;
    }

    private static function registerRoute(string $method, string $uri, callable|array $action, ?Guard $Guard = null)
    {
        if (!isset(static::$Table)) {
            static::$Table = new Collection();
        }

        $method = strtoupper($method);
        $id = $method . ':' . $uri;
        $middleware = static::$globalMiddleware ?? [];

        static::$Table->set($id, new static($method, $uri, $action, $Guard, $middleware));
    }
}