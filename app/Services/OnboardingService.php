<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;

class OnboardingService
{
    /**
     * Validate that a return_to parameter is a safe internal relative path.
     */
    public static function safeReturnUrl(mixed $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }

        if (preg_match('/[\x00-\x20\x7f]/', $returnTo) === 1) {
            return null;
        }

        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\')) {
            return null;
        }

        $parts = parse_url($returnTo);
        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $returnTo;
    }

    /**
     * Build the dashboard onboarding checklist state for the given user in the active fiscal year.
     *
     * Returns null when onboarding was permanently dismissed, no active fiscal year exists,
     * the user has no actionable onboarding permissions, or every visible step is already done.
     *
     * @return array{
     *     steps: list<array{
     *         key: string,
     *         title: string,
     *         completed: bool,
     *         current: bool,
     *         primaryAction: ?array{label: string, url: string},
     *         secondaryAction: ?array{label: string, url: string},
     *     }>,
     *     completedCount: int,
     *     totalCount: int,
     *     percentage: int,
     *     dismissUrl: string,
     * }|null
     */
    public function forUser(User $user): ?array
    {
        if ($user->hasDismissedOnboarding() || ! getActiveFiscalYear()) {
            return null;
        }

        $steps = [
            [
                'key' => 'company',
                'title' => __('Company & fiscal year'),
                'completed' => true,
                'current' => false,
                'primaryAction' => null,
                'secondaryAction' => null,
            ],
        ];

        if ($user->can('customers.create') || $user->can('customers.*')) {
            $steps[] = [
                'key' => 'customer',
                'title' => __('First customer'),
                'completed' => Customer::query()->exists(),
                'current' => false,
                'primaryAction' => [
                    'label' => __('Add customer'),
                    'url' => route('customers.create'),
                ],
                'secondaryAction' => null,
            ];
        }

        $canCreateProduct = $user->can('products.create') || $user->can('products.*');
        $canCreateService = $user->can('services.create') || $user->can('services.*');

        if ($canCreateProduct || $canCreateService) {
            $steps[] = [
                'key' => 'catalog',
                'title' => __('Product or service'),
                'completed' => Product::query()->exists() || Service::query()->exists(),
                'current' => false,
                'primaryAction' => $canCreateProduct
                    ? ['label' => __('Add product'), 'url' => route('products.create')]
                    : ['label' => __('Add service'), 'url' => route('services.create')],
                'secondaryAction' => ($canCreateProduct && $canCreateService)
                    ? ['label' => __('or service'), 'url' => route('services.create')]
                    : null,
            ];
        }

        $canCreateInvoice = $user->can('invoices.create') || $user->can('invoices.*');
        $canCreateDocument = $user->can('documents.create') || $user->can('documents.*');

        if ($canCreateInvoice || $canCreateDocument) {
            $steps[] = [
                'key' => 'transaction',
                'title' => __('Invoice or document'),
                'completed' => Invoice::query()->exists() || Document::query()->exists(),
                'current' => false,
                'primaryAction' => $canCreateInvoice
                    ? ['label' => __('Create invoice'), 'url' => route('invoices.create')]
                    : ['label' => __('Create document'), 'url' => route('documents.create')],
                'secondaryAction' => ($canCreateInvoice && $canCreateDocument)
                    ? ['label' => __('or document'), 'url' => route('documents.create')]
                    : null,
            ];
        }

        if ($user->can('bank-accounts.create') || $user->can('bank-accounts.*')) {
            $steps[] = [
                'key' => 'bank_account',
                'title' => __('Bank account'),
                'completed' => BankAccount::query()->exists(),
                'current' => false,
                'primaryAction' => [
                    'label' => __('Add bank account'),
                    'url' => route('bank-accounts.create'),
                ],
                'secondaryAction' => null,
            ];
        }

        if (count($steps) <= 1) {
            return null;
        }

        $completedCount = collect($steps)->where('completed', true)->count();
        $totalCount = count($steps);

        if ($completedCount === $totalCount) {
            $user->forceFill([
                'onboarding_dismissed_at' => now(),
            ])->save();

            return null;
        }

        foreach ($steps as &$step) {
            if (! $step['completed']) {
                $step['current'] = true;
                break;
            }
        }
        unset($step);

        return [
            'steps' => $steps,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'percentage' => (int) round(($completedCount / $totalCount) * 100),
            'dismissUrl' => route('home.onboarding.dismiss'),
        ];
    }
}
