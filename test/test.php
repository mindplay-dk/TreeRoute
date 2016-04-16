<?php

use mindplay\timber\Controller;
use mindplay\timber\Dispatcher;
use mindplay\timber\Result;
use mindplay\timber\Router;
use mindplay\timber\UrlHelper;

require dirname(__DIR__) . '/vendor/autoload.php';

class SampleUrlHelper extends UrlHelper
{
    /**
     * @param int $id
     * @param string $title
     *
     * @return string
     */
    public function content($id, $title)
    {
        return "/content/{$id}/{$this->slug($title)}";
    }
}

class SampleController implements Controller
{
    public function run($id, $title) {
        return array($id, $title);
    }
}

function check_error(Result $result, $code) {
    ok($result->error !== null, 'expected Error instance');
    
    if ($result->error) {
        eq($result->error->code, $code);
    }
}

function check_success(Result $result) {
    ok(empty($result->error));
}

$router = new Router();

$router->route('/')->get('handler0');

test('should find existing route', function () use ($router) {
    $result = $router->resolve('GET', '/');

    check_success($result);

    eq($result->handler, 'handler0');
});

test('should return 404 error for non-existing route', function () use ($router) {
    $result = $router->resolve('GET', '/nothing/here/dude');

    check_error($result, 404);
});

test('should return 405 error for unsupported method', function () use ($router) {
    $result = $router->resolve('POST', '/');

    check_error($result, 405);

    eq($result->error->allowed, ['GET']);
});

test('should define route with short methods', function () use ($router) {
    $router->route('create')->post('handler1');
    $result = $router->resolve('POST', '/create');

    eq($result->handler, 'handler1');
});

test('should extract route params', function () use ($router) {
    $router->route('news/<id:int>')->get('handler2');

    $result = $router->resolve('GET', '/news/1');

    check_success($result);

    eq($result->handler, 'handler2');
    eq($result->params['id'], 1);
    ok(1 === $result->params['id'], 'int Symbol should convert to int');

    $result = $router->resolve('GET', '/news/foo');

    check_error($result, 404);
});

test('should match regexp in params', function () use ($router) {
    $router->route('users/<name:^[a-zA-Z]+$>')->get('handler3');
    $router->route('users/<id:int>')->get('handler4');

    $result = $router->resolve('GET', '/users/@test');

    check_error($result, 404);

    $result = $router->resolve('GET', '/users/bob');

    eq($result->handler, 'handler3');
    eq($result->params['name'], 'bob');

    $result = $router->resolve('GET', '/users/123');

    eq($result->handler, 'handler4');
    eq($result->params['id'], 123);
});

test('should throw for unexpected query string', function () use ($router) {
    expect(
        'RuntimeException',
        'unexpected query string in $url: /users/bob?crazy-yo',
        function () use ($router) {
            $router->resolve('GET', '/users/bob?crazy-yo');
        }
    );
});

test('should normalize URL in Result', function () use ($router) {
    eq($router->resolve('GET', 'users/bob')->url, '/users/bob');
    eq($router->resolve('GET', '/users/bob')->url, '/users/bob');
});

test('should give greater priority to statically defined route', function () use ($router) {
    $router->route('users/help')->get('handler5');

    $result = $router->resolve('GET', '/users/help');

    eq($result->handler, 'handler5');

    ok(empty($result->params));
});

test('should save and restore routes', function () use ($router) {
    $routes = $router->getRoutes();

    $router = new Router();

    $result = $router->resolve('GET', '/');

    check_error($result, 405);

    $router->setRoutes($routes);

    $result = $router->resolve('GET', '/');

    eq($result->handler, 'handler0');
});

test('should handle pattern substitutions', function () use ($router) {
    $router->route('year/<year:int>')->get('year');

    $result = $router->resolve('GET', '/year/2020');

    eq($result->handler, 'year');
    eq($result->params['year'], 2020);
});

test('should match multiple params in one part', function () use ($router) {
    $router->route('archive-<year:int>-<month:int>-<day:int>')->get('archive');

    $result = $router->resolve('GET', '/archive-2015-31-01');

    eq($result->handler, 'archive');
    eq($result->params['year'], 2015);
    eq($result->params['month'], 31);
    eq($result->params['day'], 1);
});

test('can build routes progressively', function () {
    $router = new Router();

    // using statement-groups to clarify the created structure:

    $admin = $router->route('admin');
    {
        $upload = $admin->route('upload')->post('upload');

        $menu = $admin->route('menu');
        {
            $menu->route('load')->get('load');
            $menu->route('save')->get('save');
        }
    }

    eq($upload->pattern, '/admin/upload');
    eq($router->route('admin/menu/load'), $menu->route('load'));
    eq($router->resolve('GET', '/admin/menu/load')->handler, 'load');
    eq($router->resolve('GET', '/admin/menu/save')->handler, 'save');
    eq($router->resolve('POST', '/admin/upload')->handler, 'upload');
    eq($router->route('admin/menu/load')->pattern, '/admin/menu/load');
});

test('should dispatch handlers with parameters', function () {
    $router = new Router();
    $router->route('content/<id:int>-<title:slug>')->get('SampleController');

    $dispatcher = new Dispatcher($router);

    $result = $dispatcher->run('GET', '/content/123-hello-world');

    eq($result, [123, 'hello-world']);
});

test('can create URL', function () {
    $router = new Router();
    $router->route('content/<id:int>/<title:slug>')->get('content');

    $url = new SampleUrlHelper();
    $content_url = $url->content(123, 'Hello, World!');

    eq($content_url, '/content/123/hello-world');
    eq($router->resolve('GET', $content_url)->handler, 'content');
});

test('can use wildcard in patterns', function () {
    $router = new Router();

    $router->route('categories/<id:int>')->get('cat_id');
    $router->route('categories/fish')->get('cat_fish');
    $router->route('categories/*')->get('cat_wild');

    eq($router->resolve('GET', '/categories/123')->handler, 'cat_id');
    eq($router->resolve('GET', '/categories/fish')->handler, 'cat_fish');
    eq($router->resolve('GET', '/categories/what/ever')->handler, 'cat_wild');

    expect(
        'RuntimeException',
        'the asterisk wildcard route is terminal',
        function () use ($router) {
            $router->route('categories/*/oh-noes');
        }
    );
});

configure()->enableCodeCoverage(__DIR__ . '/build/logs/clover.xml', dirname(__DIR__) . '/src');

exit(run());