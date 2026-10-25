<?php

declare(strict_types=1);

namespace App\Support;

final class InventoryTaxonomy
{
    public const OTHER_REASON = 'Others, please type';

    /** @return list<string> */
    public static function wasteStages(): array
    {
        return [
            'Pre-consumer spoilage',
            'Storage / receiving',
            'Preparation / trimming',
            'Cooking / production',
            'Plate waste (returned)',
        ];
    }

    /** @return list<string> */
    public static function wasteReasons(): array
    {
        return ['Expired', 'Spoiled', 'Dropped / spilled', 'Overcooked / burnt', 'Wrong order', 'Customer returned', 'Overproduction', self::OTHER_REASON];
    }

    /** @return list<string> */
    public static function varianceReasons(): array
    {
        return [
            'Unexplained shrinkage', 'Miscount correction', 'Transfer / commissary movement',
            'Waste: Expired', 'Waste: Spoiled', 'Waste: Dropped / spilled',
            'Waste: Overcooked / burnt', 'Waste: Wrong order', 'Waste: Customer returned',
            'Waste: Overproduction', 'Waste: Other', 'Other',
        ];
    }
}
