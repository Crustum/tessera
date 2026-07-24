<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller\Trait;

use Cake\Http\Response;
use Closure;

/**
 * Renders Tessera consent / device views from a template name or callback.
 *
 * @phpstan-require-extends \Cake\Controller\Controller
 */
trait RendersConfiguredViewsTrait
{
    /**
     * Render a configured template name, or invoke a callback when one is set.
     *
     * @param (\Closure(array<string, mixed>): mixed)|string|null $view Template or callback
     * @param array<string, mixed> $vars View variables
     * @return \Cake\Http\Response
     */
    protected function renderConfiguredView(Closure|string|null $view, array $vars): Response
    {
        $this->set($vars);

        if ($view instanceof Closure) {
            $result = $view($vars);

            if ($result instanceof Response) {
                return $result;
            }

            if (is_array($result)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody((string)json_encode($result));
            }

            return $this->response->withStringBody((string)$result);
        }

        if (is_string($view) && $view !== '') {
            return $this->render($view);
        }

        return $this->render();
    }
}
