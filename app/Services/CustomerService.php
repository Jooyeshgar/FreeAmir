<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    public function __construct(private readonly SubjectCreatorService $subjectCreator) {}

    public function create(array $data): Customer
    {
        $data['company_id'] ??= session('active-company-id');

        $customer = Customer::create($data);

        $this->syncSubject($customer);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->save();

        $this->syncSubject($customer);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->subject?->delete();

        $customer->delete();
    }



    protected function syncSubject(Customer $customer): void
    {
        $companyId = $customer->company_id ?? session('active-company-id');
        $parentId = $customer->group?->subject_id ?? 0;

        $subject = $customer->subject;

        if (! $subject) {
            $subject = $this->subjectCreator->createSubject([
                'name' => $customer->name,
                'parent_id' => $parentId,
                'company_id' => $companyId,
            ]);

            // Link polymorphic relation
            $subject->subjectable()->associate($customer);
            if ($subject->isDirty(['subjectable_id', 'subjectable_type'])) {
                $subject->save();
            }
        } else {
            // Use the dedicated editor to handle name/parent changes and code regeneration
            $subject = $this->subjectCreator->editSubject($subject, [
                'name' => $customer->name,
                'parent_id' => $parentId,
            ]);

            // Ensure the subject is associated to this customer (in case it wasn't)
            $subject->subjectable()->associate($customer);
            if ($subject->isDirty(['subjectable_id', 'subjectable_type'])) {
                $subject->save();
            }
        }

        $customer->setRelation('subject', $subject);
    }
}
