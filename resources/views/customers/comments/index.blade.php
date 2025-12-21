<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Comments') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex justify-between gap-4 ">
                <a href="{{ route('comments.create', $customer->id) }}"
                    class="btn btn-primary ">{{ __('Add Comment') }}</a>
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Commented By') }}</th>
                        <th class="px-4 py-2">{{ __('Content') }}</th>
                        <th class="px-4 py-2">{{ __('Rating') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($comments as $comment)
                        <tr>
                            <td class="px-4 py-2">{{ $comment->commentBy->name }}</td>
                            <td class="px-4 py-2 w-1/2">{{ $comment->content }}</td>
                            <td class="px-4 py-2">
                                <div class="rating rating-sm rating-half">
                                    @for ($i = 1; $i <= 10; $i++)
                                        @php
                                            $starValue = $i / 2;
                                            $isFilled = $starValue <= $comment->rating;
                                        @endphp
                                        <input type="radio" disabled
                                            class="mask mask-star-2 @if ($i % 2 == 1) mask-half-1 @else mask-half-2 @endif @if ($isFilled) bg-orange-400 @else bg-orange-250 @endif" />
                                    @endfor
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('comments.edit', $comment) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('comments.destroy', $comment) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($comments->hasPages())
                <div class="mt-6 flex justify-center">
                    {!! $comments->links() !!}
                </div>
            @endif

            <div class="card-actions justify-between mt-8 text-left">
                <a href="{{ route('customers.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
