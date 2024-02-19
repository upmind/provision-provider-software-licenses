<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\CPanel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

    public const STATUS_ACTIVE = 1;
    public const STATUS_EXPIRED = 2;
    public const STATUS_SUSPENDED = 4;

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
            ->setName('cPanel')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/cpanel-logo.png')
            ->setDescription('Resell, provision and manage cPanel licenses with manage2');
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
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get license data by key.
     *
     * @throws Throwable
     */
    protected function getLicense(string $license_key, bool $getExpired = false, bool $orFail = true): ?array
    {
        try {
            $command = 'XMLlicenseInfo';

            $query = [
                "liscid" => $license_key,
            ];

            if ($getExpired) {
                $query["expired"] = 1; // return expired licenses only
            }

            $data = (array)$this->makeRequest($command, $query);

            if (empty($data['licenses']['L' . $license_key])) {
                if (!$getExpired) {
                    return $this->getLicense($license_key, true); // try to get expired license
                }

                if ($orFail) {
                    $this->errorResult('License not found');
                }
            }

            return $data;
        } catch (Throwable $e) {
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
            $licenseData = $this->getLicense($params->license_key);

            $query = [
                'ip' => $licenseData['licenses']['L' . $params->license_key]['ip'] ?? null,
                'newpackageid' => $params->package_identifier
            ];

            $this->makeRequest('XMLpackageUpdate', $query);

            return ChangePackageResult::create()
                ->setLicenseKey($params->license_key)
                ->setPackageIdentifier($params->package_identifier)
                ->setMessage('Package changed');
        } catch (Throwable $e) {
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
     *
     * @throws Throwable
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        if ($this->isLicenseSuspended($params->license_key)) {
            return EmptyResult::create()->setMessage('License already suspended');
        }

        // All we can do is expire the license
        return $this->expireLicense($params->license_key, 'License suspended');
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        if ($this->isLicenseActive($params->license_key)) {
            return EmptyResult::create()->setMessage('License already active');
        }

        try {
            $query = [
                'liscid' => $params->license_key,
            ];

            $this->makeRequest('XMLlicenseReActivate', $query);
            return EmptyResult::create()->setMessage('License unsuspended');
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * @inheritDoc
     *
     * @throws Throwable
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        if ($this->isLicenseExpired($params->license_key)) {
            return EmptyResult::create()->setMessage('License already expired');
        }

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

    /**
     * @throws GuzzleException
     */
    public function makeRequest(string $command, ?array $params = null, ?string $method = 'GET'): ?array
    {
        $requestParams = [
            'query' => [],
            'http_errors' => false,
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
     * Is a license active?
     */
    private function isLicenseActive(string $licenseKey): bool
    {
        return $this->getLicenseStatus($licenseKey) === self::STATUS_ACTIVE;
    }

    /**
     * Is a license suspended?
     */
    private function isLicenseSuspended(string $licenseKey): bool
    {
        // since suspended licenses are also expired, we need to check for both statuses
        return in_array($this->getLicenseStatus($licenseKey), [self::STATUS_SUSPENDED, self::STATUS_EXPIRED]);
    }

    /**
     * Is a license expired?
     */
    private function isLicenseExpired(string $licenseKey): bool
    {
        return $this->getLicenseStatus($licenseKey) === self::STATUS_EXPIRED;
    }

    /**
     * Get license status integer; one of self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_EXPIRED.
     */
    private function getLicenseStatus(string $licenseKey): int
    {
        $licenseData = $this->getLicense($licenseKey);
        $status = $licenseData['licenses']['L' . $licenseKey]['status'] ?? null;

        if ($status === null) {
            throw $this->errorResult('Unable to determine license status');
        }

        return (int)$status;
    }

    /**
     * Expire a cPanel license.
     *
     * @throws Throwable
     */
    private function expireLicense(string $licenseKey, string $message = 'License cancelled'): EmptyResult
    {
        try {
            $query = [
                'liscid' => $licenseKey,
            ];

            $this->makeRequest('XMLlicenseExpire', $query);

            return EmptyResult::create()->setMessage($message);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }
}
