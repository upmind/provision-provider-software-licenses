<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\SoftwareLicenses\Data;

use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Provider\DataSet\ResultData;
use Upmind\ProvisionBase\Provider\DataSet\Rules;

/**
 * @property-read int|null $units_consumed Number of units used by this license
 * @property-read array|null $usage_data Detailed usage data
 */
class GetUsageResult extends ResultData
{
    public static function rules(): Rules
    {
        return new Rules([
            'units_consumed' => ['nullable', 'integer'],
            'usage_data' => ['nullable', 'array'],
        ]);
    }

    /**
     * Set the result units_consumed.
     */
    public function setUnitsConsumed(?int $unitsConsumed): self
    {
        $this->setValue('units_consumed', $unitsConsumed);
        return $this;
    }

    /**
     * Set the result usage_data.
     */
    public function setUsageData(?array $usageData): self
    {
        $this->setValue('usage_data', $usageData);
        return $this;
    }
}
