<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use DB;

class CustomerGroupService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Gather aggregate statistics for a customer group's overview page.
     */
    public function getStats(CustomerGroup $customerGroup): array
    {
        $customerIds = $customerGroup->customers()->pluck('id');

        $sellQuery = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled());

        $totalSales = (float) (clone $sellQuery)->sum('amount');
        $invoicesCount = (clone $sellQuery)->count();

        $totalReturns = (float) Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('invoice_type', InvoiceType::RETURN_SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->sum('amount');

        $topCustomerRows = (clone $sellQuery)
            ->selectRaw('customer_id, SUM(amount) as total_amount, COUNT(*) as invoices_count')
            ->groupBy('customer_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        $customers = Customer::whereIn('id', $topCustomerRows->pluck('customer_id'))->get()->keyBy('id');

        $topCustomers = $topCustomerRows
            ->map(fn ($row) => [
                'customer' => $customers->get($row->customer_id),
                'total' => (float) $row->total_amount,
                'count' => (int) $row->invoices_count,
            ])
            ->filter(fn ($row) => $row['customer'])
            ->values();

        $recentInvoices = Invoice::with('customer')
            ->whereIn('customer_id', $customerIds)
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return [
            'customersCount' => $customerIds->count(),
            'subjectBalance' => SubjectService::sumSubject($customerGroup->subject, true, false),
            'totalSales' => $totalSales,
            'totalReturns' => $totalReturns,
            'netSales' => $totalSales - $totalReturns,
            'invoicesCount' => $invoicesCount,
            'topCustomers' => $topCustomers,
            'recentInvoices' => $recentInvoices,
        ];
    }

    public function create(array $data): CustomerGroup
    {
        $data['company_id'] ??= getActiveCompany();

        $customerGroup = CustomerGroup::create($data);

        $this->syncSubject($customerGroup);

        return $customerGroup;
    }

    public function update(CustomerGroup $customerGroup, array $data): CustomerGroup
    {
        $customerGroup->fill($data);
        $customerGroup->save();

        $this->syncSubject($customerGroup);

        return $customerGroup;
    }

    public function delete(CustomerGroup $customerGroup): void
    {
        DB::transaction(function () use ($customerGroup) {
            foreach ($customerGroup->customers as $customer) {
                $customer->comments()->delete();
                $customer->delete();
                $customer->subject?->delete();
            }
            $customerGroup->delete();
            $customerGroup->subject?->delete();
        });
    }

    protected function syncSubject(CustomerGroup $customerGroup): void
    {
        $companyId = $customerGroup->company_id ?? getActiveCompany();

        $relation = 'subject';
        $parentId = config('amir.cust_subject');
        $subject = $customerGroup->$relation;

        if (! $subject) {
            $subject = $this->subjectService->createSubject([
                'name' => $customerGroup->name,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ]);
        }

        $needsSave = false;

        if ($subject->name !== $customerGroup->name) {
            $subject->name = $customerGroup->name;
            $needsSave = true;
        }

        if ($parentId && $subject->parent_id !== $parentId) {
            $subject->parent_id = $parentId;
            $needsSave = true;
        }

        if ($subject->subjectable_id !== $customerGroup->id || $subject->subjectable_type !== $customerGroup->getMorphClass()) {
            $subject->subjectable()->associate($customerGroup);
            $needsSave = true;
        }

        if ($needsSave) {
            $subject->save();
        }

        $customerGroup->setRelation($relation, $subject);

        if ($subject->id !== $customerGroup->subject_id) {
            $customerGroup->updateQuietly(['subject_id' => $subject->id]);
        }
    }
}
