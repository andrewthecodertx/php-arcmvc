<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Arc\Application;
use Arc\Http\Middleware\CsrfMiddleware;
use Arc\Http\Request;
use Arc\Http\Response;
use Arc\Support\Controller;

/**
 * End-to-end tests that dispatch a real request through the Application into a
 * controller that renders a view. These guard the controller→Renderer wiring,
 * which unit tests of the individual pieces did not exercise.
 */
class RequestLifecycleTest extends TestCase
{
    private string $viewsDir;

    protected function setUp(): void
    {
        Application::resetInstance();

        $this->viewsDir = sys_get_temp_dir() . '/arc_views_' . uniqid();
        mkdir($this->viewsDir . '/layouts', 0755, true);
        mkdir($this->viewsDir . '/home', 0755, true);

        file_put_contents(
            $this->viewsDir . '/layouts/main.phtml',
            '<main><?= $this->yield(\'content\') ?></main>'
        );
        file_put_contents(
            $this->viewsDir . '/home/index.phtml',
            '<?php $this->extend(\'main\') ?><h1><?= $this->e($title) ?></h1>'
        );
        file_put_contents(
            $this->viewsDir . '/home/form.phtml',
            '<form><?= $this->csrfField() ?></form>'
        );
    }

    protected function tearDown(): void
    {
        Application::resetInstance();

        array_map('unlink', glob($this->viewsDir . '/**/*.phtml'));
        @rmdir($this->viewsDir . '/layouts');
        @rmdir($this->viewsDir . '/home');
        @rmdir($this->viewsDir);
    }

    private function makeApp(): Application
    {
        $app = new Application();
        $app->config()->set('app.views_path', $this->viewsDir);
        return $app;
    }

    public function testControllerRendersViewThroughRouter(): void
    {
        $app = $this->makeApp();
        $app->router()->get('/', [LifecycleController::class, 'index']);

        $response = $app->handle(new Request(method: 'GET', uri: '/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<main><h1>Welcome to Arc</h1></main>', $response->getContent());
    }

    public function testCsrfTokenReachesRenderedView(): void
    {
        $app = $this->makeApp();
        $app->addMiddleware(new CsrfMiddleware());
        $app->router()->get('/form', [LifecycleController::class, 'form']);

        $response = $app->handle(new Request(method: 'GET', uri: '/form'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="_token" value="[0-9a-f]{64}">/',
            $response->getContent()
        );
    }
}

class LifecycleController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home.index', ['title' => 'Welcome to Arc']);
    }

    public function form(Request $request): Response
    {
        return $this->view('home.form');
    }
}
