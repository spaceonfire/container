<?php

declare(strict_types=1);

namespace spaceonfire\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use spaceonfire\Container\Exception\ContainerException;
use spaceonfire\Container\Exception\NotFoundException;

class ContainerChainTest extends TestCase
{
    private function createContainerMock(array $definitions = [], $interface = ContainerInterface::class): ObjectProphecy
    {
        $isSpaceonfireContainer = $interface === ContainerInterface::class || is_subclass_of($interface, ContainerInterface::class);

        $otherInterfaces = [];
        if (is_array($interface)) {
            $otherInterfaces = $interface;
            $interface = array_shift($otherInterfaces);
        }

        $prophecy = $this->prophesize($interface);

        foreach ($otherInterfaces as $otherInterface) {
            $prophecy->willImplement($otherInterface);
        }

        $prophecy->has(Argument::type('string'))->willReturn(false);

        if ($isSpaceonfireContainer) {
            $prophecy->get(Argument::type('string'), Argument::type('array'))->willThrow(new NotFoundException());
        } else {
            $prophecy->get(Argument::type('string'))->willThrow(new NotFoundException());
        }

        foreach ($definitions as $id => $result) {
            $prophecy->has($id)->willReturn(true);

            if ($interface === ContainerInterface::class || is_subclass_of($interface, ContainerInterface::class)) {
                $prophecy->get($id, Argument::type('array'))->willReturn($result);
            } else {
                $prophecy->get($id)->willReturn($result);
            }
        }

        return $prophecy;
    }

    public function testHas(): void
    {
        $chain = new ContainerChain([
            $this->createContainerMock(['foo' => 'foo'], PsrContainerInterface::class)->reveal(),
            $this->createContainerMock(['bar' => 'bar'])->reveal(),
        ]);

        self::assertTrue($chain->has('foo'));
        self::assertTrue($chain->has('bar'));
        self::assertFalse($chain->has('baz'));
    }

    public function testGet(): void
    {
        $chain = new ContainerChain([
            $this->createContainerMock(['foo' => 'foo'], PsrContainerInterface::class)->reveal(),
            $this->createContainerMock(['bar' => 'bar'])->reveal(),
        ]);

        self::assertSame('foo', $chain->get('foo'));
        self::assertSame('bar', $chain->get('bar'));

        $this->expectException(NotFoundException::class);
        $chain->get('baz');
    }

    public function testSetContainer(): void
    {
        $containerAware = $this->createContainerMock(['bar' => 'bar'], [ContainerInterface::class, ContainerAwareInterface::class]);
        $containerAware->setContainer(Argument::any())->shouldBeCalled();

        $chain = new ContainerChain([
            $this->createContainerMock(['foo' => 'foo'], PsrContainerInterface::class)->reveal(),
            $containerAware->reveal(),
        ]);

        $chain->setContainer($chain);
    }

    public function testAddServiceProvider(): void
    {
        $chain = new ContainerChain([]);

        $primary = $this->createContainerMock(['bar' => 'bar'], ContainerWithServiceProvidersInterface::class);
        $primary->addServiceProvider(Argument::any())->shouldBeCalled();

        $containers = [
            $this->createContainerMock([], PsrContainerInterface::class)->reveal(),
            $this->createContainerMock(['bar' => 'bar'])->reveal(),
            $primary->reveal(),
        ];

        $chain->addContainers($containers);

        $chain->addServiceProvider('provider');
    }

    public function testAddServiceProviderFailed(): void
    {
        $this->expectException(ContainerException::class);
        $chain = new ContainerChain([
            $this->createContainerMock([], PsrContainerInterface::class)->reveal(),
            $this->createContainerMock(['bar' => 'bar'])->reveal(),
        ]);
        $chain->addServiceProvider('provider');
    }

    public function testNoPrimaryContainerInChain(): void
    {
        $this->expectException(ContainerException::class);
        $chain = new ContainerChain([
            $this->createContainerMock([], PsrContainerInterface::class)->reveal(),
        ]);
        $chain->addServiceProvider('provider');
    }

    public function testMake(): void
    {
        $containerProphecy = $this->createContainerMock();
        $containerProphecy->make(Argument::type('string'), Argument::type('array'))->shouldBeCalled();

        $chain = new ContainerChain([$containerProphecy->reveal()]);

        $chain->make('foo');
    }

    public function testInvoke(): void
    {
        $containerProphecy = $this->createContainerMock();
        $containerProphecy->invoke(Argument::type('callable'), Argument::type('array'))->shouldBeCalled();

        $chain = new ContainerChain([$containerProphecy->reveal()]);

        $chain->invoke(static function () {
        });
    }

    public function testAdd(): void
    {
        $containerProphecy = $this->createContainerMock();
        $containerProphecy->add(Argument::type('string'), Argument::any(), Argument::type('bool'))->shouldBeCalled();

        $chain = new ContainerChain([$containerProphecy->reveal()]);

        $chain->add('foo', 'bar');
    }

    public function testShare(): void
    {
        $containerProphecy = $this->createContainerMock();
        $containerProphecy->share(Argument::type('string'), Argument::any())->shouldBeCalled();

        $chain = new ContainerChain([$containerProphecy->reveal()]);

        $chain->share('foo', 'bar');
    }
}
