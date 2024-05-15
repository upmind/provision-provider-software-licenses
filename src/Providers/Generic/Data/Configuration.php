<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Providers\Generic\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read string|null $access_token Access bearer token to send in requests
 * @property-read string $create_endpoint_url Endpoint which creates a license key and returns a username
 * @property-read string $create_endpoint_http_method HTTP method to use for the create endpoint
 * @property-read boolean $has_change_package Whether or not this configuration has a changePackage endpoint
 * @property-read string|null $change_package_endpoint_url Endpoint which changes a license key's package
 * @property-read string|null $change_package_endpoint_http_method HTTP method to use for the changePackage endpoint
 * @property-read boolean $has_usage_data Whether or not this configuration has a getUsageData endpoint
 * @property-read string|null $get_usage_data_endpoint_url Endpoint which gets usage data of a license key
 * @property-read string|null $get_usage_data_endpoint_http_method HTTP method to use for the getUsageData endpoint
 * @property-read boolean $has_reissue Whether or not this configuration has a reissue endpoint
 * @property-read string|null $reissue_endpoint_url Endpoint which reissues a license key
 * @property-read string|null $reissue_endpoint_http_method HTTP method to use for the reissue endpoint
 * @property-read boolean $has_suspension Whether or not this configuration has suspend/unsuspend endpoints
 * @property-read string|null $suspend_endpoint_url Endpoint which suspends a license key
 * @property-read string|null $suspend_endpoint_http_method HTTP method to use for the suspend endpoint
 * @property-read string|null $unsuspend_endpoint_url Endpoint which un-suspends a license key
 * @property-read string|null $unsuspend_endpoint_http_method HTTP method to use for the unsuspend endpoint
 * @property-read boolean $has_termination Whether or not this configuration has a terminate endpoint
 * @property-read string|null $terminate_endpoint_url Endpoint which terminates a license key
 * @property-read string|null $terminate_endpoint_http_method HTTP method to use for the terminate endpoint
 */
class Configuration extends DataSet
{
    public static function rules(): Rules
    {
        return new Rules([
            'access_token' => [
                'nullable',
                'string'
            ],
            'create_endpoint_url' => [
                'required',
                'url', /* 'starts_with:https' */
            ],
            'create_endpoint_http_method' => [
                'required',
                'string',
                'in:post,put,patch,get'
            ],
            'has_change_package' => [
                'boolean'
            ],
            'change_package_endpoint_url' => [
                'required_if:has_change_package,1',
                'url', /* 'starts_with:https' */
            ],
            'change_package_endpoint_http_method' => [
                'required_if:has_change_package,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
            'has_usage_data' => [
                'boolean'
            ],
            'get_usage_data_endpoint_url' => [
                'required_if:has_usage_data,1',
                'url', /* 'starts_with:https' */
            ],
            'get_usage_data_endpoint_http_method' => [
                'required_if:has_usage_data,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
            'has_reissue' => [
                'boolean'
            ],
            'reissue_endpoint_url' => [
                'required_if:has_reissue,1',
                'url', /* 'starts_with:https' */
            ],
            'reissue_endpoint_http_method' => [
                'required_if:has_reissue,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
            'has_suspension' => [
                'boolean'
            ],
            'suspend_endpoint_url' => [
                'required_if:has_suspension,1',
                'url', /* 'starts_with:https' */
            ],
            'suspend_endpoint_http_method' => [
                'required_if:has_suspension,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
            'unsuspend_endpoint_url' => [
                'required_if:has_suspension,1',
                'url', /* 'starts_with:https' */
            ],
            'unsuspend_endpoint_http_method' => [
                'required_if:has_suspension,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
            'has_termination' => [
                'boolean'
            ],
            'terminate_endpoint_url' => [
                'required_if:has_termination,1',
                'url', /* 'starts_with:https' */
            ],
            'terminate_endpoint_http_method' => [
                'required_if:has_termination,1',
                'string',
                'in:post,put,patch,get,delete'
            ],
        ]);
    }
}
