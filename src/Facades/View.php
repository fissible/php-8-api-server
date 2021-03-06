<?php declare(strict_types=1);

namespace Ajthenewguy\Php8ApiServer\Facades;

use Ajthenewguy\Php8ApiServer\Traits\RequiresServiceContainer;
use Illuminate\Contracts;
use Jenssegers\Blade\Blade;

class View
{
    use RequiresServiceContainer;

    public static function Blade(): Blade
    {
        $Blade = new Blade(self::app()->config()->get('views.path'), self::app()->config()->get('cache.path'));

        foreach (static::directives() as $name => $callable) {
            $Blade->directive($name, $callable);
        }

        return $Blade;
    }

    public static function make(string$view, Contracts\Support\Arrayable|array $data = [], array $mergeData = []): Contracts\View\View
    {
        return static::Blade()->make($view, $data, $mergeData);
    }

    public static function render(string $view, Contracts\Support\Arrayable|array $data = [], array $mergeData = []): string
    {
        return static::Blade()->render($view, $data, $mergeData);
    }

    protected static function directives(): array
    {
        return [
            'csrf' => function () {
                return "<?php echo '<input type=\"hidden\" name=\"_csrf\" value=\"' . Session()->token() . '\" />' ?>";
            }
        ];
    }
}