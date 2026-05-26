@php
    $rating = old('rating', round((float) ($comment->rating ?? 0) * 2) / 2);
@endphp

<div class="grid grid-cols-2 gap-4">
    <x-input name="user_id" value="{{ Auth::id() ?? '' }}" hidden />
    <x-input name="customer_id" value="{{ $comment->customer_id ?? ($customer->id ?? '') }}" hidden />

    <div class="col-span-2 md:col-span-1 w-1/3">
        <x-input disabled="true" title="{{ __('Customer') }}" name="customer" id="customer" :value="old('customer', $comment->customer->name ?? ($customer->name ?? ''))" />
    </div>

    <div class="col-span-2 md:col-span-1" x-data="{ rating: {{ $rating }} }">
        <label class="label">{{ __('Rating') }}</label>

        <div x-data="{ rating: {{ $comment->rating ?? 0 }} }" class="flex items-center gap-3">
            <button type="button" class="text-xs text-gray-500 cursor-pointer dark:text-gray-300 hover:underline" @click="rating = 0" >{{ __('Reset') }}</button>

            <div class="rating rating-sm rating-half">
                <input type="radio" class="rating-hidden" name="rating" value="0" x-model="rating" />

                @for ($i = 1; $i <= 10; $i++)
                    @php $starValue = $i * 0.5; @endphp

                    <input type="radio" name="rating" value="{{ $starValue }}" x-model="rating" class="mask mask-star-2 cursor-pointer
                            bg-yellow-400 hover:bg-yellow-500 dark:bg-sky-400 dark:hover:bg-sky-500 {{ $i % 2 ? 'mask-half-1' : 'mask-half-2' }}" />
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
