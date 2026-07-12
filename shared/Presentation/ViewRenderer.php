<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Presentation;

use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Session\ISessionService;

final class ViewRenderer
{
    public function __construct(
        private readonly ConfigLoader $config,
        private readonly ?ISessionService $session = null,
    ) {}
    /**
     * @param array<string, mixed> $data
     */
    public function render(
        string $view,
        string $moduleName,
        array $data = [],
        bool $showSidebar = true,
        string $layout = 'app',
    ): Response {
        $config = $this->config;
        $view = ltrim($view, '/');
        if (str_contains($view, '..')) {
            throw new \InvalidArgumentException('Invalid view path.');
        }

        $viewFile = APP_ROOT . '/' . $view;
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        $layoutTitle = isset($pageTitle) ? (string) $pageTitle : 'WorkEddy';
        $currentView = $view;
        $currentUserContext = $this->session?->getUserContext();
        $layoutFile = APP_ROOT . '/shared/Views/Layouts/' . match ($layout) {
            'auth'   => 'auth',
            'portal' => 'portal',
            'error'  => 'error',
            default  => 'app',
        } . '.php';

        ob_start();
        require $layoutFile;

        return Response::html((string) ob_get_clean());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPublic(
        string $view,
        string $moduleName,
        array $data = [],
        bool $showSidebar = true,
        string $layout = 'marketing',
    ): Response {
        $config = $this->config;
        $view = ltrim($view, '/');
        if (str_contains($view, '..')) {
            throw new \InvalidArgumentException('Invalid view path.');
        }

        $viewFile = APP_ROOT . '/' . $view;
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        $layoutTitle = isset($pageTitle) ? (string) $pageTitle : 'NISEPA Revenue Collection';
        $currentView = $view;
        $currentUserContext = $this->session?->getUserContext();
        $layoutFile = APP_ROOT . '/shared/Views/Layouts/' . ($layout === 'auth' ? 'auth' : 'public') . '.php';

        ob_start();
        require $layoutFile;

        return Response::html((string) ob_get_clean());
    }

    public function renderErrorPage(int $status, string $message): Response
    {
        $view = '/shared/Views/Errors/error.php';
        $layout = 'error';
        $moduleName = 'Error';
        $data = ['status_code' => $status, 'message' => $message];
        return $this->render($view, $moduleName, $data, false, $layout);
    }


    /**
     * @param array<string, mixed> $data
     */
    public function renderMarketing(string $view, string $moduleName, array $data = []): Response
    {
        return $this->render($view, $moduleName, $data, false, 'marketing');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderAuth(string $view, string $moduleName, array $data = []): Response
    {
        return $this->renderPublic($view, $moduleName, $data, false, 'auth');
    }

    /**
     * Render a view using the customer portal shell.
     * The portal shell contains no admin sidebar, no admin navigation,
     * and no admin-scoped styling — safe to show to end-customers.
     *
     * @param array<string, mixed> $data
     */
    public function renderPortal(string $view, string $moduleName, array $data = []): Response
    {
        return $this->render($view, $moduleName, $data, false, 'portal');
    }
}
