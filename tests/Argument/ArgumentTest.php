<?php

declare(strict_types=1);

namespace spaceonfire\Container\Argument;

use spaceonfire\Container\AbstractTestCase;
use spaceonfire\Container\ContainerInterface;
use spaceonfire\Container\Exception\ContainerException;
use spaceonfire\Container\RawValueHolder;

class ArgumentTest extends AbstractTestCase
{
    public function testGetName(): void
    {
        $argument = new Argument('foo');
        self::assertSame('foo', $argument->getName());
    }

    public function testResolveFromContainer(): void
    {
        $containerProphecy = $this->prophesize(ContainerInterface::class);
        $containerProphecy->has('MyClass')->willReturn(true);
        $containerProphecy->get('MyClass')->willReturn('bar');

        /** @var ContainerInterface $container */
        $container = $containerProphecy->reveal();

        $argument = new Argument('foo', 'MyClass');

        self::assertSame('bar', $argument->resolve($container));
    }

    public function testResolveUsingDefaultValue(): void
    {
        $containerProphecy = $this->prophesize(ContainerInterface::class);
        $containerProphecy->has('MyClass')->willReturn(false);

        /** @var ContainerInterface $container */
        $container = $containerProphecy->reveal();

        $argument = new Argument('foo', 'MyClass', new RawValueHolder('baz'));

        self::assertSame('baz', $argument->resolve($container));
    }

    public function testResolveFailed(): void
    {
        $containerProphecy = $this->prophesize(ContainerInterface::class);
        $containerProphecy->has('MyClass')->willReturn(false);
        /** @var ContainerInterface $container */
        $container = $containerProphecy->reveal();

        $argument = new Argument('foo', 'MyClass');

        $this->expectException(ContainerException::class);
        $argument->resolve($container);
    }
}
