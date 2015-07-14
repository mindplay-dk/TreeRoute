<?php

class RouterTest extends \Codeception\TestCase\Test
{
    use \Codeception\Specify;

    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    /**
     * @param $result
     * @param int $code
     */
    private function assertIsError($result, $code)
    {
        $this->assertInstanceOf('TreeRoute\Error', $result);

        if ($result instanceof TreeRoute\Error) {
            $this->assertEquals($code, $result->code);
        }
    }

    private function assertIsNotError($result)
    {
        $this->assertInstanceOf('TreeRoute\Result', $result);
        $this->assertNotInstanceOf('TreeRoute\Error', $result);
    }

    public function testRouter()
    {
        $router = new \TreeRoute\Router();

        $router->addRoute(['GET'], '/', 'handler0');

        $this->specify('should find existed route', function () use ($router) {
            $result = $router->resolve('GET', '/');
            $this->assertIsNotError($result);
            $this->assertEquals('handler0', $result->handler);
        });

        $this->specify('should return 404 error for not existed route', function () use ($router) {
            $result = $router->resolve('GET', '/not/existed/url');
            $this->assertIsError($result, 404);
        });

        $this->specify('should return 405 error for unsupported method', function () use ($router) {
            $result = $router->resolve('POST', '/');
            $this->assertIsError($result, 405);
            $this->assertEquals(['GET'], $result->allowed);
        });

        $this->specify('should define route with short methods', function () use ($router) {
            $router->post('/create', 'handler1');
            $result = $router->resolve('POST', '/create');

            $this->assertEquals('handler1', $result->handler);
        });

        $this->specify('should extract route params', function () use ($router) {
            $router->get('/news/{id}', 'handler2');
            $result = $router->resolve('GET', '/news/1');

            $this->assertEquals('handler2', $result->handler);
            $this->assertEquals('1', $result->params['id']);
        });

        $this->specify('should match regexp in params', function () use ($router) {
            $router->get('/users/{name:^[a-zA-Z]+$}', 'handler3');
            $router->get('/users/{id:^[0-9]+$}', 'handler4');

            $result = $router->resolve('GET', '/users/@test');
            $this->assertIsError($result, 404);

            $result = $router->resolve('GET', '/users/bob');
            $this->assertEquals('handler3', $result->handler);
            $this->assertEquals('bob', $result->params['name']);

            $result = $router->resolve('GET', '/users/123');
            $this->assertEquals('handler4', $result->handler);
            $this->assertEquals('123', $result->params['id']);
        });

        $this->specify('should give greater priority to statically defined route', function () use ($router) {
            $router->get('/users/help', 'handler5');
            $result = $router->resolve('GET', '/users/help');
            $this->assertEquals('handler5', $result->handler);
            $this->assertEmpty($result->params);
        });

        $this->specify('should save and restore routes', function () use ($router) {
            $routes = $router->getRoutes();
            $router = new \TreeRoute\Router();
            $result = $router->resolve('GET', '/');
            $this->assertIsError($result, 405);
            $router->setRoutes($routes);
            $result = $router->resolve('GET', '/');
            $this->assertEquals('handler0', $result->handler);
        });
    }
}