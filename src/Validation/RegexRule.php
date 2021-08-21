<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Validation;

use React\Promise;

class RegexRule extends Rule
{
    protected string $name = 'regex';

    protected string $regex;

    public function __construct(string $regex = '')
    {
        $this->setRegex($regex);
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The ":attribute" field format is invalid.';
    }

    /**
     * @param string $regex
     * @return self
     */
    public function setRegex(string $regex): self
    {
        $this->regex = $regex;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $input
     * @return Promise\PromiseInterface
     */
    public function passes(string $name, $input): Promise\PromiseInterface
    {
        if (strlen($input) < 1) {
            return $this->resolve($name, $input, true);
        }
        if ($this->regex) {
            $result = preg_match($this->regex, $input);
            if ($result === false) {
                throw new \Exception('RegexRule regular expression error: ' . preg_last_error_msg());
            }
            return $this->resolve($name, $input, (bool) $result);
        }

        return $this->resolve($name, $input, false);
    }
}