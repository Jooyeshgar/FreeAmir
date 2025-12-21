@php
    $rating = round((float) ($comment->rating ?? 0) * 2) / 2;
@endphp

<div class="grid grid-cols-2 gap-4">
    <input type="hidden" name="user_id" value="{{ Auth::id() ?? '' }}">
    <input type="hidden" name="customer_id" value="{{ $comment->customer_id ?? '' }}">

    <div class="col-span-2 md:col-span-1 w-1/3">
        <x-input disabled="true" title="{{ __('Customer') }}" name="customer" id="customer" :value="old('customer', $comment->customer->name ?? ($customer->name ?? ''))" />
    </div>

    <div class="col-span-2 md:col-span-1" x-data="{ rating: {{ $rating }} }">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Rating') }}
        </label>

        <div class="flex items-center gap-3">
            <div class="rating rating-m rating-half gap-0 scale-125">

                <input type="radio" name="rating" value="0" class="rating-hidden" @click="rating = 0"
                    :checked="rating === 0" />

                @for ($i = 1; $i <= 10; $i++)
                    @php $starValue = $i * 0.5; @endphp

                    <input type="radio" name="rating" value="{{ $starValue }}"
                        class="mask mask-star-2 bg-yellow-400 hover:bg-yellow-500 cursor-pointer
                        {{ $i % 2 == 1 ? 'mask-half-1' : 'mask-half-2' }}"
                        @click="rating = {{ $starValue }}" :checked="rating === {{ $starValue }}" />
                @endfor
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-2">
            {{ __('Click on stars to change rating') }}
        </p>
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-textarea title="{{ __('Content') }}" name="content" id="content" :value="old('content', $comment->content ?? '')" />
    </div>
</div>
