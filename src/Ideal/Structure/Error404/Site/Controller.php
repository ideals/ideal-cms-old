<?php

namespace Ideal\Structure\Error404\Site;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller extends \Ideal\Core\Site\Controller
{
    public function errorAction(Request $request): Response
    {
        $this->model = new \Ideal\Structure\Home\Site\Model('');

        $this->templateInit('404.twig');

        $this->fillView();

        return new Response($this->view->render(), Response::HTTP_NOT_FOUND);
    }
}