<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Upmind\ProvisionBase\Provider\Contract\ProviderInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
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
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Data\Configuration;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers\DefaultResponseHandler;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers\LicenseKeyResponseHandler;
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\ResponseHandlers\UsageDataResponseHandler;

class Provider extends Category implements ProviderInterface
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @inheritDoc
     */
    public static function aboutProvider(): AboutData
    {
        return AboutData::create()
            ->setName('Generic')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/generic-logo.png')
            ->setDescription('A highly-configurable generic provider for provisioning license keys');
    }

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @inheritDoc
     */
    public function create(CreateParams $params): CreateResult
    {
        $method = strtoupper($this->configuration->create_endpoint_http_method);
        $endpointUrl = $this->configuration->create_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new LicenseKeyResponseHandler($this->request($method, $endpointUrl, $requestParams));

        return CreateResult::create()
            ->setLicenseKey($handler->getLicenseKey())
            ->setServiceIdentifier($handler->getServiceIdentifier() ?? $params->service_identifier)
            ->setPackageIdentifier($handler->getPackageIdentifier() ?? $params->package_identifier);
    }

    /**
     * @inheritDoc
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        if (!$this->configuration->has_change_package) {
            return $this->errorResult('No change package endpoint set in this configuration');
        }

        $method = strtoupper($this->configuration->change_package_endpoint_http_method);
        $endpointUrl = $this->configuration->change_package_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new LicenseKeyResponseHandler($this->request($method, $endpointUrl, $requestParams));

        return ChangePackageResult::create()
            ->setLicenseKey($handler->getLicenseKey())
            ->setPackageIdentifier($handler->getPackageIdentifier() ?? $params->package_identifier);
    }

    /**
     * @inheritDoc
     */
    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        if (!$this->configuration->has_usage_data) {
            return $this->errorResult('No usage data endpoint set in this configuration');
        }

        $method = strtoupper($this->configuration->create_endpoint_http_method);
        $endpointUrl = $this->configuration->create_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new UsageDataResponseHandler($this->request($method, $endpointUrl, $requestParams));

        return GetUsageResult::create()
            ->setUnitsConsumed($handler->getUnitsConsumed())
            ->setUsageData((array)$handler->getData());
    }

    /**
     * @inheritDoc
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        if (!$this->configuration->has_usage_data) {
            return $this->errorResult('Reissuance of this license is not possible');
        }

        $method = strtoupper($this->configuration->reissue_endpoint_http_method);
        $endpointUrl = $this->configuration->reissue_endpoint_url;

        $handler = new LicenseKeyResponseHandler($this->request($method, $endpointUrl, $params->toArray()));

        return ReissueResult::create()
            ->setLicenseKey($handler->getLicenseKey());
    }

    /**
     * @inheritDoc
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        if (!$this->configuration->has_suspension) {
            return $this->errorResult('No suspend endpoint set in this configuration');
        }

        $method = strtoupper($this->configuration->suspend_endpoint_http_method);
        $endpointUrl = $this->configuration->suspend_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new DefaultResponseHandler($this->request($method, $endpointUrl, $requestParams));
        $handler->assertResponseSuccess();

        return ResultData::create();
    }

    /**
     * @inheritDoc
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        if (!$this->configuration->has_suspension) {
            return $this->errorResult('No unsuspend endpoint set in this configuration');
        }

        $method = strtoupper($this->configuration->unsuspend_endpoint_http_method);
        $endpointUrl = $this->configuration->unsuspend_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new DefaultResponseHandler($this->request($method, $endpointUrl, $requestParams));
        $handler->assertResponseSuccess();

        return ResultData::create();
    }

    /**
     * @inheritDoc
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        if (!$this->configuration->has_terminate) {
            return $this->errorResult('No terminate endpoint set in this configuration');
        }

        $method = strtoupper($this->configuration->terminate_endpoint_http_method);
        $endpointUrl = $this->configuration->terminate_endpoint_url;

        $requestParams = $params->toArray();
        $requestParams = array_merge($requestParams, Arr::pull($requestParams, 'extra', [])); // merge extra params

        $handler = new DefaultResponseHandler($this->request($method, $endpointUrl, $requestParams));
        $handler->assertResponseSuccess();

        return ResultData::create();
    }

    protected function request(string $method, string $uri, array $requestParams): Response
    {
        return $this->client()->request($method, $uri, $this->getRequestOptions($method, $requestParams));
    }

    protected function getRequestOptions(string $method, array $requestParams): array
    {
        $options = [
            RequestOptions::HTTP_ERRORS => false, // handle in ResponseHandlers
        ];

        if ($this->configuration->access_token) {
            $options[RequestOptions::HEADERS] = [
                'Authorization' => sprintf('Bearer %s', $this->configuration->access_token),
            ];
        }

        if ($method === 'GET') {
            $options[RequestOptions::QUERY] = $requestParams;
        } else {
            $options[RequestOptions::FORM_PARAMS] = $requestParams;
        }

        return $options;
    }

    protected function client(): Client
    {
        return new Client([
            RequestOptions::HTTP_ERRORS => false,
            'handler' => $this->getGuzzleHandlerStack(!!$this->configuration->debug),
        ]);
    }
}
