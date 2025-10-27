<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Ispmanager;

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
use Upmind\ProvisionProviders\SoftwareLicenses\Providers\Ispmanager\Data\Configuration;

/**
 * ispmanager provider
 */
class Provider extends Category implements ProviderInterface
{
    protected Configuration $configuration;
    protected Client|null $client = null;

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
            ->setName('ispmanager')
            ->setLogoUrl('https://api.upmind.io/images/logos/provision/ispmanager-logo.png')
            ->setDescription('Resell, provision and manage ispmanager licenses');
    }

    /**
     * @inheritDoc
     */
    public function getUsageData(GetUsageParams $params): GetUsageResult
    {
        $data = $this->makeRequest([
            'func' => 'soft.edit',
            'elid' => $params->license_key,
        ]);
        $data = $data['model'];
        $statuses = ['Unknown', 'Ordered', 'Active', 'Suspended', 'Deleted', 'Processing'];

        return GetUsageResult::create()->setUsageData([
            'id' => $data['id'],
            'name' => $data['name'],
            'licname' => $data['licname'],
            'ip' => $data['ip'],
            'lickey' => $data['lickey'] ?? '',
            'status' => $statuses[$data['status']] ?? $data['status'],
            'createdate' => $data['createdate'],
            'expiredate' => $data['expiredate'],
        ]);
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function create(CreateParams $params): CreateResult
    {
        $period = $params->billing_cycle_months;
        if (in_array($params->package_identifier, [55239, 55240])) $period = -100;
        $result = $this->makeRequest([
            'func' => 'soft.order.param',
            'pricelist' => $params->package_identifier,
            'period' => $period,
            'ip' => $params->ip,
            'licname' => $params->service_identifier,
            'sok' => 'ok',
            'skipbasket' => 'on',
            'remoteid' => $params->service_identifier,
            'clicked_button' => 'finish',
            'autoprolong' => 'null',
        ]);

        return CreateResult::create()
            ->setLicenseKey($result['id']['v'])
            ->setMessage('License created');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function renew(RenewParams $params): RenewResult
    {
        $this->makeRequest([
            'func' => 'service.prolong',
            'elid' => $params->license_key,
            'period' => $params->billing_cycle_months,
            'sok' => 'ok',
        ]);
        return RenewResult::create()
            ->setLicenseKey($params->license_key)
            ->setPackageIdentifier($params->package_identifier)
            ->setMessage('License renewed');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function changePackage(ChangePackageParams $params): ChangePackageResult
    {
        $this->makeRequest([
            'func' => 'service.changepricelist',
            'elid' => $params->license_key,
            'pricelist' => $params->package_identifier,
            'period' => $params->billing_cycle_months,
            'sok' => 'ok',
        ]);

        return ChangePackageResult::create()
            ->setLicenseKey($params->license_key)
            ->setPackageIdentifier($params->package_identifier)
            ->setMessage('Package changed');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function reissue(ReissueParams $params): ReissueResult
    {
        $this->makeRequest([
            'func' => 'soft.edit',
            'elid' => $params->license_key,
            'clicked_button' => 'newkey',
            'sok' => 'ok',
        ]);

        return ReissueResult::create()
            ->setLicenseKey($params->license_key)
            ->setMessage('License reissued');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function suspend(SuspendParams $params): EmptyResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function unsuspend(UnsuspendParams $params): EmptyResult
    {
        $this->errorResult('Operation not supported');
    }

    /**
     * @inheritDoc
     *
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function terminate(TerminateParams $params): EmptyResult
    {
        $this->makeRequest([
            'func' => 'soft.delete',
            'elid' => $params->license_key,
            'sok' => 'ok',
        ]);

        return EmptyResult::create()->setMessage('License cancelled');
    }

    /**
     * Get a Guzzle HTTP client instance.
     */
    protected function client(): Client
    {
        return $this->client ??= new Client([
            'handler' => $this->getGuzzleHandlerStack(),
            'base_uri' => 'https://eu.ispmanager.com',
            'timeout' => 60,
        ]);
    }

    public function makeRequest(array $params)
    {
        if (!isset($params['out'])) $params['out'] = 'bjson';
        if (!isset($params['lang'])) $params['lang'] = 'en';
        $params['authinfo'] = $this->configuration->username . ':' . $this->configuration->password;

        $response = $this->client()->post('/billmgr', ['body' => http_build_query($params)]);
        $result = $response->getBody()->getContents();
        $response->getBody()->close();

        if (empty($result)) $this->errorResult('Unexpected Empty Provider API response', ['http_code' => $response->getStatusCode()]);

        $result = json_decode($result, true);
        if (isset($result['doc'])) $result = $result['doc'];
        if (!empty($result['error']['msg'])) $this->errorResult($result['error']['msg']['$'] ?? $result['error']['msg']);

        return $result;
    }
}
