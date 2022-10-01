<?php

namespace Ideal\Controller;

use Ideal\Core\Config;
use Ideal\Resize\Resize;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResizeController
{
    public function index(Request $request): Response
    {
        $response = new Response();

        $statusCode = Response::HTTP_OK;

        $config = Config::getInstance();

        $r = new Resize($config->publicDir, '/images/resized/', explode("\n", $config->allowResize));
        try {
            $rImage = $r->resize($request->getPathInfo());

            foreach ($r->getHeaders() as $key => $value) {
                $response->headers->set($key, $value);
            }
        } catch (RuntimeException $e) {
            $rImage = '';
            $statusCode = Response::HTTP_NOT_FOUND;
        }

        return $response->setContent($rImage)->setStatusCode($statusCode);
    }
}