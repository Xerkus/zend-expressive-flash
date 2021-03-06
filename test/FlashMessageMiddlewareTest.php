<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-flash for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-flash/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Flash;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Zend\Expressive\Flash\Exception;
use Zend\Expressive\Flash\FlashMessageMiddleware;
use Zend\Expressive\Flash\FlashMessagesInterface;
use Zend\Expressive\Session\SessionInterface;
use Zend\Expressive\Session\SessionMiddleware;

class FlashMessageMiddlewareTest extends TestCase
{
    public function testConstructorRaisesExceptionIfFlashMessagesClassIsNotAClass()
    {
        $this->expectException(Exception\InvalidFlashMessagesImplementationException::class);
        $this->expectExceptionMessage('not-a-class');
        new FlashMessageMiddleware('not-a-class');
    }

    public function testConstructorRaisesExceptionIfFlashMessagesClassDoesNotImplementCorrectInterface()
    {
        $this->expectException(Exception\InvalidFlashMessagesImplementationException::class);
        $this->expectExceptionMessage('stdClass');
        new FlashMessageMiddleware(stdClass::class);
    }

    public function testProcessRaisesExceptionIfRequestSessionAttributeDoesNotReturnSessionInterface()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE, false)->willReturn(false);
        $request->withAttribute(
            FlashMessageMiddleware::FLASH_ATTRIBUTE,
            Argument::type(FlashMessagesInterface::class)
        )->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequestInterface::class))->shouldNotBeCalled();

        $middleware = new FlashMessageMiddleware();

        $this->expectException(Exception\MissingSessionException::class);
        $this->expectExceptionMessage(FlashMessageMiddleware::class);

        $middleware->process($request->reveal(), $handler->reveal());
    }

    public function testProcessUsesConfiguredClassAndSessionKeyAndAttributeKeyToCreateFlashMessagesAndPassToHandler()
    {
        $session = $this->prophesize(SessionInterface::class)->reveal();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE, false)->willReturn($session);
        $request->withAttribute(
            'non-standard-flash-attr',
            Argument::that(function (TestAsset\FlashMessages $flash) use ($session) {
                $this->assertSame($session, $flash->session);
                $this->assertSame('non-standard-flash-next', $flash->sessionKey);
                return $flash;
            })
        )->will([$request, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->willReturn($response);

        $middleware = new FlashMessageMiddleware(
            TestAsset\FlashMessages::class,
            'non-standard-flash-next',
            'non-standard-flash-attr'
        );

        $this->assertSame(
            $response,
            $middleware->process($request->reveal(), $handler->reveal())
        );
    }
}
