<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use App\Controller\AppController as BaseController;

/**
 * Base controller for Tessera HTML endpoints (consent / device forms).
 *
 * Mirrors CakeDC Users: extend the host AppController so theme, menu, and
 * shared app setup apply. Token/API controllers stay on Cake\Controller\Controller.
 */
class AppController extends BaseController
{
    /**
     * Tessera HTML controllers are not table-backed CRUD.
     *
     * @var string|null
     */
    protected ?string $defaultTable = '';

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
    }
}
