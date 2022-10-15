<?php

namespace Ideal\Controller;

use Gregwar\Captcha\CaptchaBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для отображения капчи
 *
 * В сессии сохраняется код капчи в переменной, которая определяется именем файла картинки.
 * По умолчанию, используется имя файла /images/captcha.jpg, поэтому в сессии код доступен
 * в переменной $_SESSION['captcha']
 */
class CaptchaController
{
    /**
     * Создание капчи в Response и сохранение кода в $_SESSION['captcha']
     *
     * @param Request $request
     *
     * @return Response
     */
    public function imageAction(Request $request): Response
    {
        $builder = new CaptchaBuilder();
        $builder->build();

        $session = $request->getSession();
        $session->set(
            pathinfo($request->getRequestUri(), PATHINFO_FILENAME),
            $builder->getPhrase()
        );

        return new Response(
            $builder->get(),
            Response::HTTP_OK,
            ['Content-type' => 'image/jpeg']
        );
    }
}