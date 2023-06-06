<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Blesta;

use Throwable;
use GuzzleHttp\Client;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
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
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Blesta\Data\Configuration;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;

/**
 * Blesta provider.
 */
class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected Client $client;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Blesta')
            // ->setLogoUrl('https://example.com/logo.png')
            ->setDescription('A highly-configurable Blesta provider for provisioning license keys');
    }

    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        throw $this->errorResult('Not implemented');
    }

    public function create(CreateParams $params): CreateResult
    {
        if (!isset($params->package_identifier)) {
            throw $this->errorResult('Package identifier is required!');
        }

        $command = "addlicense";

        $body = [
            'vars[pricing_id]' => $this->getPackagePricing($params->package_identifier)
        ];

        try {
            $response = $this->makeRequest($command, null, $body, 'POST');

            return CreateResult::create(['license_key' => $response['response']]);
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    protected function getPackagePricing(string $packageId): string
    {
        $command = "getpackagepricing";

        $params = [
            'package_id' => $packageId
        ];

        try {
            $response = $this->makeRequest($command, $params);

            if ($response['response'] == []) {
                throw $this->errorResult('Package does not exist');
            }

            return (string)$response['response'][0]['id'];
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    protected function checkLicense(string $license_key): void
    {
        $command = "search";

        $params = [
            'vars[search]' => $license_key,
        ];

        try {
            $response = $this->makeRequest($command, $params);

            if ($response['response'] == []) {
                throw $this->errorResult('License does not exist');
            }

            foreach ($response['response'] as $license) {
                if ($license_key == $license['fields']['license_module_key']) {
                    return;
                }
            }

            throw $this->errorResult('License does not exist');
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        throw $this->errorResult('Not implemented');
    }

    public function reissue(ReissueParams $params): ReissueResult
    {
        $command = "update";

        $body = [
            'vars[license]' => $params->license_key,
            'vars[reissue_status]' => 'reissue',
        ];

        try {
            $this->checkLicense($params->license_key);
            $this->makeRequest($command, null, $body, 'POST');

            return ReissueResult::create([
                'license_key' => $params->license_key,
            ]);

        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    public function suspend(SuspendParams $params): EmptyResult
    {
        $command = "suspendlicense";

        $body = [
            'vars[license]' => $params->license_key
        ];
        try {
            $this->checkLicense($params->license_key);
            $this->makeRequest($command, null, $body, 'POST');

            return EmptyResult::create();
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        $command = "unsuspendlicense";

        $body = [
            'vars[license]' => $params->license_key
        ];

        try {
            $this->checkLicense($params->license_key);
            $this->makeRequest($command, null, $body, 'POST');

            return EmptyResult::create();
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }

    public function terminate(TerminateParams $params): EmptyResult
    {
        $command = "cancellicense";

        $body = [
            'vars[license]' => $params->license_key
        ];

        try {
            $this->checkLicense($params->license_key);
            $this->makeRequest($command, null, $body, 'POST');

            return EmptyResult::create();
        } catch (\Throwable $e) {
            $this->handleException($e, $params);
        }
    }


    protected function client(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $credentials = base64_encode("{$this->configuration->username}:{$this->configuration->password}");

        $client = new Client([
            'base_uri' => 'https://account.blesta.com',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => ['Basic ' . $credentials],
            ],
            'connect_timeout' => 10,
            'timeout' => 60,
            'handler' => $this->getGuzzleHandlerStack(boolval($this->configuration->debug)),
        ]);

        return $this->client = $client;
    }


    public function makeRequest(string $command, ?array $params = null, ?array $body = null, ?string $method = 'GET'): ?array
    {
        $requestParams = [];

        if ($params) {
            $requestParams['query'] = $params;
        }

        if ($body) {
            $requestParams['form_params'] = $body;
        }

        $response = $this->client()->request($method, "/plugin/blesta_reseller/v2/index/{$command}.json", $requestParams);
        $result = $response->getBody()->getContents();

        $response->getBody()->close();

        if ($result === '') {
            return null;
        }

        return $this->parseResponseData($result);
    }

    private function parseResponseData(string $result): array
    {
        $parsedResult = json_decode($result, true);

        if (!$parsedResult && $parsedResult != []) {
            throw ProvisionFunctionError::create('Unknown Provider API Error')
                ->withData([
                    'response' => $result,
                ]);
        }

        return $parsedResult;
    }

    protected function handleException(Throwable $e, $params = null): void
    {
        if (!$e instanceof ProvisionFunctionError) {
            $e = new ProvisionFunctionError('Unexpected Provider Error', $e->getCode(), $e);
        }

        throw $e->withDebug([
            'params' => $params,
        ]);
    }
}
