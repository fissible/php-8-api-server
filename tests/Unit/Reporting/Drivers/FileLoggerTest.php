<?php declare(strict_types=1);

namespace Tests\Unit;

use Ajthenewguy\Php8ApiServer\Filesystem\Directory;
use Ajthenewguy\Php8ApiServer\Reporting\Logger;
use Ajthenewguy\Php8ApiServer\Reporting\Drivers\FileLogger;
use Tests\TestCase;

class FileLoggerTest extends TestCase
{
    public function testLog()
    {
        $path = __DIR__.DIRECTORY_SEPARATOR.'logs';
        $LogDir = new Directory($path);
        $Logger = new FileLogger([
            'path' => $path
        ]);
        $expected = date('Y-m-d H:i:s').' - info: Here is a value'."\n";
        $Logger->log('Here is a value', Logger::INFO);
        $out = $LogDir->files()[0]->read();

        foreach ($LogDir->files() as $File) {
            $File->delete();
        }
        $LogDir->delete();

        $this->assertEquals($expected, $out);
    }
}