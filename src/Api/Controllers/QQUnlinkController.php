<?php

namespace HamCQ\QQAuth\Api\Controllers;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Flarum\Http\RequestUtil;
use HamCQ\QQAuth\QQIdentity;

class QQUnlinkController implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (!QQIdentity::unlink($actor)) {
            return new EmptyResponse(StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        return new EmptyResponse(StatusCodeInterface::STATUS_OK);
    }
}
