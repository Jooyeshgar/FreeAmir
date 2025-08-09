<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum FiscalYearSection: string
{
	case CONFIGS = 'configs';
	case BANKS = 'banks';
	case BANK_ACCOUNTS = 'bank_accounts';
	case CUSTOMERS = 'customers'; // Represents both customer_groups + customers
	case PRODUCTS = 'products'; // Represents both product_groups + products
	case SUBJECTS = 'subjects';
	case DOCUMENTS = 'documents'; // Represents both documents + transactions

	public function label(): string
	{
		return Lang::get($this->value);
	}

	public static function cli(): array
	{
		return array_reduce(self::cases(), function ($carry, $case) {
			$carry[$case->value] = $case->label();
			return $carry;
		}, []);
	}

	public static function ui(): array
	{
		return [
			self::CONFIGS->value => self::CONFIGS->label(),
			self::BANKS->value => self::BANKS->label(),
			self::CUSTOMERS->value => self::CUSTOMERS->label(),
			self::PRODUCTS->value => self::PRODUCTS->label(),
			self::SUBJECTS->value => self::SUBJECTS->label(),
		];
	}
}
