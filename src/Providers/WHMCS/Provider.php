<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\WHMCS;

use SimpleXMLElement;
use Throwable;
use GuzzleHttp\Client;
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
use Upmind\ProvisionProviders\SoftwareLicenses\Data\RenewParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\RenewResult;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\SuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\TerminateParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Data\UnsuspendParams;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\WHMCS\Data\Configuration;

/**
 * WHMCS provider.
 */
class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected Client|null $client = null;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('WHMCS')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/whmcs-logo.png')
            ->setDescription('Resell, provision and manage WHMCS licenses');
    }

    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        return GetUsageResult::create()
            ->setUsageData($this->getLicense($params->license_key));
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function create(CreateParams $params): CreateResult
    {
        if (!isset($params->package_identifier)) {
            $this->errorResult('Package identifier is required!');
        }

        try {
            $request = [
                "action" => "addlicense",
                "product" => $params->package_identifier
            ];

            $response = $this->makeRequest($request);

            return CreateResult::create(['license_key' => (string)$response->licensekey])
                ->setMessage('License created');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get license data by key.
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    protected function getLicense(string $license_key): ?array
    {
        try {
            $request = [
                "action" => "searchlicenses",
                "licensekey" => $license_key
            ];
            $response = $this->makeRequest($request);

            if (!$response->licenses) {
                $this->errorResult('License not found');
            }

            foreach ($response->licenses as $license) {
                if ($license_key == $license->license->key) {
                    return (array)$license;
                }
            }

            $this->errorResult('License not found');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function renew(RenewParams $params): RenewResult
    {
        return RenewResult::create()
            ->setLicenseKey($params->license_key)
            ->setPackageIdentifier($params->package_identifier)
            ->setMessage('Renewal not required for WHMCS licenses');
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     * @throws \Throwable
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        if (!isset($params->package_identifier)) {
            $this->errorResult('Package identifier is required!');
        }

        try {
            $request = [
                "action" => "upgrade",
                "key" => $params->license_key,
                "product" => $params->package_identifier
            ];

            $this->makeRequest($request);

            return ChangePackageResult::create()
                ->setLicenseKey($params->license_key)
                ->setPackageIdentifier($params->package_identifier)
                ->setMessage('Package changed');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws \Throwable
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        try {
            $request = [
                "action" => "reissue",
                "key" => $params->license_key
            ];

            $this->makeRequest($request);

            return ReissueResult::create([
                'license_key' => $params->license_key,
            ])->setMessage('License reissued');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @throws \Throwable
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        try {
            $request = [
                "action" => "cancel",
                "key" => $params->license_key
            ];

            $this->makeRequest($request);

            return EmptyResult::create()->setMessage('License cancelled');
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    protected function client(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $client = new Client([
            'base_uri' => 'https://licenseapi.whmcs.com',
            'connect_timeout' => 10,
            'timeout' => 60,
            'handler' => $this->getGuzzleHandlerStack(),
        ]);

        return $this->client = $client;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function makeRequest(array $params): SimpleXMLElement
    {
        $params = array_merge([
            'email' => $this->configuration->email,
            'apikey' => $this->configuration->api_key,
        ], $params);

        $response = $this->client()->get('/v2/reseller/', ['query' => $params]);
        $result = $response->getBody()->getContents();

        $response->getBody()->close();

        if (empty($result)) {
            $this->errorResult('Unexpected Empty Provider API response', [
                'http_code' => $response->getStatusCode(),
            ]);
        }

        return $this->parseResponseData($result);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    private function parseResponseData(string $result): SimpleXMLElement
    {
        // Try to parse the response
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $this->errorResult('Unknown Provider API Error', [
                'response_body' => $result,
            ]);
        }

        if ($xml->result != 'success') {
            $this->errorResult(sprintf('Provider API Error: %s', $xml->message), [
                'response_data' => $xml,
            ]);
        }

        return $xml;
    }

    /**
     * @return no-return
     * @throws \Throwable
     *
     */
    protected function handleException(Throwable $e): void
    {
        throw $e;
    }
}
