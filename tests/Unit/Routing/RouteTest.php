<?php declare(strict_types=1);

namespace Tests\Unit\Routing;

use Ajthenewguy\Php8ApiServer\Collection;
use Ajthenewguy\Php8ApiServer\Exceptions\Http\NotFoundException;
use Ajthenewguy\Php8ApiServer\Routing\Route;
use Ajthenewguy\Php8ApiServer\Routing\RouteParameter;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testGetAction()
    {
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $action = $Route->getAction();

        $this->assertEquals('Home', $action());
    }

    public function testGetId()
    {
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $id = $Route->getId();

        $this->assertEquals('GET:/', $id);
    }

    public function testGetMethod()
    {
        Route::get('/', function () {
            return 'Home';
        });

        $Route = Route::table()->first();
        $method = $Route->getMethod();

        $this->assertEquals('GET', $method);
    }

    public function testGetParameters()
    {
        Route::get('/', function () {
            return 'Home';
        });

        Route::get('/products/{id}', function () {
            return 'Product';
        });

        $HomeRoute = Route::table()->first();
        $ProductRoute = Route::table()->last();
        $HomeParameters = $HomeRoute->getParameters();
        $ProductParameters = $ProductRoute->getParameters();

        $expected = new Collection();
        $expected->push(new RouteParameter('id'));

        $this->assertEquals(new Collection(), $HomeParameters);
        $this->assertEquals($expected, $ProductParameters);
    }

    public function testMatches()
    {
        $action = ['Controller', 'method'];
        $uri1 = '/products/{name}';
        $uri2 = '/page/{pageId}/user/{userId?}';
        $uri3 = '/business/{businessId}/employee/{employeeId}';
        $Route1 = new Route('GET', $uri1, $action);
        $Route2 = new Route('GET', $uri2, $action);
        $Route3 = new Route('GET', $uri3, $action);

        $this->assertFalse($Route1->matches('GET', '/products'));
        $this->assertFalse($Route1->matches('POST', '/products/sail-boat'));
        $this->assertTrue($Route1->matches('GET', '/products/sail-boat'));
        $this->assertFalse($Route2->matches('GET', '/page/user/46'));
        $this->assertTrue($Route2->matches('GET', '/page/34/user/46'));
        $this->assertTrue($Route2->matches('GET', '/page/34/user'));
        $this->assertFalse($Route3->matches('GET', '/business/13/employee'));
        $this->assertTrue($Route3->matches('GET', '/business/13/employee/64'));
    }

    public function testMatchParameters()
    {
        $method = 'GET';
        $action = ['Controller', 'method'];
        $uri1 = '/products/{name}';
        $uri2 = '/page/{pageId}/user/{userId?}';
        $uri3 = '/business/{businessId}/employee/{employeeId}';
        $Route1 = new Route('GET', $uri1, $action);
        $Route2 = new Route('GET', $uri2, $action);
        $Route3 = new Route('GET', $uri3, $action);
        
        $this->assertEquals(['name' => 'sail-boat'], $Route1->matchParameters('/products/sail-boat'));
        $this->assertEquals(['pageId' => '34', 'userId' => '46'], $Route2->matchParameters('/page/34/user/46'));
        $this->assertEquals(['pageId' => '34'], $Route2->matchParameters('/page/34/user'));
        $this->assertEquals(['businessId' => '13', 'employeeId' => '64'], $Route3->matchParameters('/business/13/employee/64'));
    }

    public function testLookup()
    {
        Route::get('/products/{name}', ['Controller', 'method']);
        Route::get('/page/{pageId}/user/{userId?}', ['Controller', 'method']);
        Route::get('/business/{businessId}/employee/{employeeId}', ['Controller', 'method']);

        $Route1 = Route::lookup('GET', '/products/Fish-Tank');
        $Route2 = Route::lookup('GET', '/page/24/user/14');
        $Route3 = Route::lookup('GET', '/page/25/user');
        $Route4 = Route::lookup('GET', '/business/45/employee/21');
        
        $this->assertEquals('/products/{name}', $Route1->getUri());
        $this->assertEquals('/page/{pageId}/user/{userId?}', $Route2->getUri());
        $this->assertEquals('/page/{pageId}/user/{userId?}', $Route3->getUri());
        $this->assertEquals('/business/{businessId}/employee/{employeeId}', $Route4->getUri());
    }

    public function testLookupNotFound()
    {
        Route::get('/business/{businessId}/employee/{employeeId}', ['Controller', 'method']);

        $this->assertNull(Route::lookup('GET', '/business/45/employee'));
    }
}