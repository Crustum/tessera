<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Login stub for unauthenticated OAuth redirects.
 */
class LoginController extends Controller
{
    /**
     * Empty login page used by Authentication unauthenticatedRedirect.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        return $this->response->withStringBody('login');
    }
}
