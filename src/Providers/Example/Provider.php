<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Example;

use GuzzleHttp\Client;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionProviders\SoftwareLicenses\Category;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CreateParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\CreateResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\EmptyResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\GetUsageParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\GetUsageResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ReissueResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\SuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\TerminateParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\UnsuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Example\Data\Configuration;

/**
 * Empty provider for demonstration purposes.
 */
class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected Client $client;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @inheritDoc
     */
    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Example Provider')
            // ->setLogoUrl('https://example.com/logo.png')
            ->setDescription('Empty provider for demonstration purposes');
    }

    /**
     * @inheritDoc
     */
    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        return GetUsageResult::create()
            ->setUnitsConsumed(100) // e.g., 100 websites provisioned
            ->setUsageData([
                'active_websites' => 100,
                'active_users' => 40,
                'lives_saved' => 1000,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function create(CreateParams $params): CreateResult
    {
        throw $this->errorResult('Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        throw $this->errorResult('Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        throw $this->errorResult('Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        throw $this->errorResult('Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        throw $this->errorResult('Not implemented');
    }

    /**
     * Get a Guzzle HTTP client instance.
     */
    protected function client(): Client
    {
        return $this->client ??= new Client([
            'handler' => $this->getGuzzleHandlerStack(boolval($this->configuration->debug)),
            'base_uri' => $this->configuration->api_url . '/api/v1/',
            'headers' => [
                'Authorization' => $this->configuration->api_token,
            ],
        ]);
    }
}
