# Upmind Provision Providers - Software Licenses

[![Latest Version on Packagist](https://img.shields.io/packagist/v/upmind/provision-provider-software-licenses.svg?style=flat-square)](https://packagist.org/packages/upmind/provision-provider-software-licenses)

This provision category contains the common functions used in provisioning + management flows for various software licenses.

- [Installation](#installation)
- [Usage](#usage)
  - [Quick-start](#quick-start)
- [Supported Providers](#supported-providers)
- [Functions](#functions)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)
- [Upmind](#upmind)

## Installation

```bash
composer require upmind/provision-provider-software-licenses
```

## Usage

This library makes use of [upmind/provision-provider-base](https://packagist.org/packages/upmind/provision-provider-base) primitives which we suggest you familiarize yourself with by reading the usage section in the README.

### Quick-start

The easiest way to see this provision category in action and to develop/test changes is to install it in [upmind/provision-workbench](https://github.com/upmind-automation/provision-workbench#readme).

Alternatively you can start using it for your business immediately with [Upmind.com](https://upmind.com/start) - the ultimate web hosting billing and management solution.

**If you wish to develop a new Provider, please refer to the [WORKFLOW](WORKFLOW.md) guide.**

## Supported Providers

The following providers are currently implemented:
  - Generic (a generic highly configurable provider)
  - Enhance

## Functions

| Function | Parameters | Return Data | Description |
|---|---|---|---|
| getUsageData() | [_GetUsageParams_](src/Data/GetUsageParams.php) | [_GetUsageResult_](src/Data/GetUsageResult.php) | Get usage stats about a license key |
| create() | [_CreateParams_](src/Data/CreateParams.php) | [_CreateResult_](src/Data/CreateResult.php) | Create a new license key |
| changePackage() | [_ChangePackageParams_](src/Data/ChangePackageParams.php) | [_ChangePackageResult_](src/Data/ChangePackageResult.php) | Upgrade or downgrade a software license package |
| reissue() | [_ReissueParams_](src/Data/ReissueParams.php) | [_ReissueResult_](src/Data/ReissueResult.php) | Reissue an existing license key |
| suspend() | [_SuspendParams_](src/Data/SuspendParams.php) | [_EmptyResult_](src/Data/EmptyResult.php) | Suspend a license key |
| unsuspend() | [_UnsuspendParams_](src/Data/UnsuspendParams.php) | [_EmptyResult_](src/Data/EmptyResult.php) | Unsuspend a license key |
| terminate() | [_TerminateParams_](src/Data/TerminateParams.php) | [_EmptyResult_](src/Data/EmptyResult.php) | Delete a license key |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

 - [Harry Lewis](https://github.com/uphlewis)
 - [All Contributors](../../contributors)

## License

GNU General Public License version 3 (GPLv3). Please see [License File](LICENSE.md) for more information.

## Upmind

Sell, manage and support web hosting, domain names, ssl certificates, website builders and more with [Upmind.com](https://upmind.com/start)
