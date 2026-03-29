<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="label">
            <span class="label-text">{{ __('Cheque') }}</span>
        </label>
        <select name="cheque_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($cheques as $id => $label)
                <option value="{{ $id }}" @selected(old('cheque_id', $chequeHistory->cheque_id ?? '') == $id)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input title="{{ __('Action Type') }}" name="action_type" id="action_type" :value="old('action_type', $chequeHistory->action_type ?? '')" />
    </div>

    <div>
        <x-input title="{{ __('From Status') }}" name="from_status" id="from_status" :value="old('from_status', $chequeHistory->from_status ?? '')" />
    </div>

    <div>
        <x-input title="{{ __('To Status') }}" name="to_status" id="to_status" :value="old('to_status', $chequeHistory->to_status ?? '')" />
    </div>

    <div>
        <x-input type="datetime-local" title="{{ __('Action At') }}" name="action_at" id="action_at"
            :value="old('action_at', $chequeHistory->action_at ?? '')" />
    </div>

    <div>
        <x-input type="number" step="0.01" title="{{ __('Amount') }}" name="amount" id="amount"
            :value="old('amount', $chequeHistory->amount ?? '')" />
    </div>

    <div class="md:col-span-2">
        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $chequeHistory->desc ?? '')" />
    </div>
</div>
