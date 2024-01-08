<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel;

use GuzzleHttp\Client;
use Throwable;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Upmind\ProvisionProviders\SoftwareLicenses\Category;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ChangePackageParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\ChangePackageResult;
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
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel\Data\Configuration;

/**
 * CPanel provider.
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
            ->setName('CPanel')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/cpanel-logo.png')
            ->setDescription('Resell, provision and manage CPanel licenses');
    }

    /**
     * @inheritDoc
     */
    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        return GetUsageResult::create()
            ->setUsageData($this->getLicense($params->license_key));
    }

    /**
     * @inheritDoc
     */
    public function create(CreateParams $params): CreateResult
    {
        if (!isset($params->package_identifier)) {
            $this->errorResult('Package identifier is required!');
        }

        try {
            $command = 'XMLlicenseAdd';

            $query = [
                'packageid' => $params->package_identifier,
                'ip' => $params->ip,
            ];

            if ($this->configuration->group_id) {
                $query['groupid'] = $this->configuration->group_id;
            }

            $response = $this->makeRequest($command, $query);

            return CreateResult::create(['license_key' => (string)$response['licenseid']])
                ->setMessage('License created');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get license data by key.
     */
    protected function getLicense(string $license_key): ?array
    {
        try {
            $command = 'XMLlicenseInfo';

            $query = [
                "liscid" => $license_key,
            ];

            return (array)$this->makeRequest($command, $query);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        if (!isset($params->package_identifier)) {
            $this->errorResult('Package identifier is required!');
        }

        try {
            $result = $this->getLicense($params->license_key);

            $query = [
                'ip' => $result['licenses']['L' . $params->license_key]['ip'] ?? null,
                'newpackageid' => $params->package_identifier
            ];

            $this->makeRequest('XMLpackageUpdate', $query);

            return ChangePackageResult::create()
                ->setLicenseKey($params->license_key)
                ->setPackageIdentifier($params->package_identifier)
                ->setMessage('Package changed');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        return $this->expireLicense($params->license_key);
    }

    /**
     * @inheritDoc
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        return $this->expireLicense($params->license_key);
    }

    protected function client(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $credentials = base64_encode("{$this->configuration->username}:{$this->configuration->password}");

        $client = new Client([
            'base_uri' => 'https://manage2.cpanel.net',
            'headers' => [
                'Authorization' => ['Basic ' . $credentials],
            ],
            'connect_timeout' => 10,
            'timeout' => 60,
            'handler' => $this->getGuzzleHandlerStack((bool) $this->configuration->debug),
        ]);

        return $this->client = $client;
    }

    /**
     * @return no-return
     * @throws Throwable
     *
     */
    protected function handleException(Throwable $e): void
    {
        throw $e;
    }

    public function makeRequest(string $command, ?array $params = null, ?string $method = 'GET'): ?array
    {
        $requestParams = [
            'query' => [],
        ];

        if ($params) {
            $requestParams['query'] = $params;
        }

        $requestParams['query']['output'] = 'json';

        $response = $this->client()->request($method, "/{$command}.cgi", $requestParams);
        $result = $response->getBody()->getContents();

        $response->getBody()->close();

        if ($result === '') {
            return null;
        }

        return $this->parseResponseData($result);
    }

    private function parseResponseData(string $result): ?array
    {
        $parsedResult = json_decode($result, true);

        if (!$parsedResult && $parsedResult != []) {
            throw ProvisionFunctionError::create('Unknown Provider API Error')
                ->withData([
                    'response' => $result,
                ]);
        }

        if ($error = $this->getResponseErrorMessage($parsedResult)) {
            throw ProvisionFunctionError::create($error)
                ->withData([
                    'response' => $parsedResult,
                ]);
        }

        return $parsedResult;
    }

    protected function getResponseErrorMessage($responseData): ?string
    {
        $status = $responseData['status'] ?? null;

        if ($status == 0) {
            if (isset($responseData['reason']) && $responseData['reason'] == 'Empty license.') {
                $errorMessage = 'License does not exist';
            } else {
                $errorMessage = $responseData['reason'] ?? null;
            }
        }

        return $errorMessage ?? null;
    }

    /**
     * Expire a cPanel license.
     */
    private function expireLicense(string $licenseKey): EmptyResult
    {
        try {
            $query = [
                'liscid' => $licenseKey,
            ];

            $this->makeRequest('XMLlicenseExpire', $query);
            return EmptyResult::create()->setMessage('License cancelled');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}
