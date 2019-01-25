<?php

namespace eiriksm\CosyComposerTest\unit\Providers;

use eiriksm\CosyComposer\ProviderInterface;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\TestCase;

abstract class ProvidersTestBase extends TestCase
{

    protected $authenticateArguments = [];

    public function testAuthenticate()
    {
        $client = $this->getMockClient();
        $expect = $client->expects($this->once())
            ->method('authenticate');
        $this->configureArguments('authenticateArguments', $expect);
        $provider = $this->getProvider($client);
        $this->runAuthenticate($provider);
    }

    public function testAuthenticatePrivate()
    {
        $mock_client = $this->getMockClient();
        $expect = $mock_client->expects($this->once())
            ->method('authenticate');
        $this->configureArguments('authenticatePrivateArguments', $expect);
        $provider = $this->getProvider($mock_client);
        $this->runAuthenticate($provider, 'authenticatePrivate');
    }

    protected function configureArguments($key, InvocationMocker $object)
    {
        $arguments = $this->{$key};
        switch (count($arguments)) {
            case 2:
                list($one, $two) = $arguments;
                $object->with($one, $two);
                break;

            case 3:
                list($one, $two, $three) = $arguments;
                $object->with($one, $two, $three);
                break;

            default:
                throw new \Exception('Auth arguments not configured');
        }
    }

    protected function runAuthenticate(ProviderInterface $provider, $method = 'authenticate')
    {
        $user = 'testUser';
        $password = 'testPassword';
        $provider->{$method}($user, $password);
    }

    protected function getPrData()
    {
        return [
            'testUser',
            'testRepo',
            [
                'param1' => true,
            ],
        ];
    }
}
