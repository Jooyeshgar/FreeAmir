<?php

namespace App\Enums;

enum AncillaryCostDescriptionsType: string
{
    case Initial_Purchase_Price_Of_Goods = 'Initial_Purchase_Price_Of_Goods';
    case Transportation_Costs = 'Transportation_Costs';
    case Shipping_Insurance = 'Shipping_Insurance';
    case Customs_Fees_And_Import_Duties = 'Customs_Fees_And_Import_Duties';
    case Non_Refundable_Taxes = 'Non_Refundable_Taxes';
    case Loading_And_Unloading_Costs = 'Loading_And_Unloading_Costs';
    case Other_Costs = 'Other_Costs';

    // Get translated label
    public function label(): string
    {
        return match ($this) {
            self::Initial_Purchase_Price_Of_Goods => __('Initial Purchase Price Of Goods'),
            self::Transportation_Costs => __('Transportation Costs'),
            self::Shipping_Insurance => __('Shipping Insurance'),
            self::Customs_Fees_And_Import_Duties => __('Customs Fees And Import Duties'),
            self::Non_Refundable_Taxes => __('Non Refundable Taxes'),
            self::Loading_And_Unloading_Costs => __('Loading And Unloading Costs'),
            self::Other_Costs => __('Other Costs'),
        };
    }
}
