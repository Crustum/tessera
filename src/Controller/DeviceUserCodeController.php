<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Http\Response;
use Crustum\Tessera\Controller\Trait\RendersConfiguredViewsTrait;
use Crustum\Tessera\Tessera;

/**
 * Shows the form used to enter a device user code, or redirects when one is provided.
 */
class DeviceUserCodeController extends AppController
{
    use RendersConfiguredViewsTrait;

    /**
     * Redirect to device authorize when a user code is present, otherwise render the entry form.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $userCode = $this->getRequest()->getQuery('user_code');
        if (is_string($userCode) && $userCode !== '') {
            return $this->redirect([
                '_name' => 'tessera:device.authorizations.authorize',
                '?' => ['user_code' => $userCode],
            ]);
        }

        return $this->renderConfiguredView(Tessera::$deviceUserCodeView, [
            'request' => $this->getRequest(),
        ]);
    }
}
