<?php

namespace Ideal\Structure\Error404\Site;

use Ideal\Structure\Home\Site\Model as HomeSiteModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller extends \Ideal\Core\Site\Controller
{
    /** @noinspection PhpUnusedParameterInspection */
    public function errorAction(Request $request): Response
    {
        $this->model = new HomeSiteModel('');

        $this->templateInit('404.twig');

        $this->fillView();

        return new Response($this->view->render(), Response::HTTP_NOT_FOUND);
    }
}