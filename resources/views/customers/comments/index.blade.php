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

            <table class="table w-full mt-4">
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
                            <td class="px-4 py-2 w-1/2">
                                <button type="button"
                                    class="text-right truncate max-w-xl hover:underline" data-full-content="{{ $comment->content }}"
                                    data-comment-author="{{ $comment->commentBy->name }}" data-comment-rating="{{ $comment->rating }}">
                                    {{ \Illuminate\Support\Str::limit($comment->content, 80, '…') }}
                                </button>
                            </td>
                            <td class="px-4 py-2">
                                <div class="rating rating-sm rating-half">
                                    @for ($i = 1; $i <= 10; $i++)
                                        @php
                                            $starValue = $i / 2;
                                            $isFilled = $starValue <= $comment->rating;
                                        @endphp
                                        <input type="radio" disabled
                                            class="pointer-events-none mask mask-star-2 @if ($i % 2 == 1) mask-half-1 @else mask-half-2 @endif @if ($isFilled) bg-orange-400 @else bg-orange-250 @endif" />
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

            @if(count($comments ) === 0)
                <p class="text-xs text-center text-gray-500 mt-1">{{ __('There is no comments.') }}</p>
            @endif

            @if ($comments->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $comments->links() !!}
                </div>
            @endif

            <div class="card-actions justify-between mt-4 text-left">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-ghost gap-2">
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

    <dialog id="comment-modal" class="modal">
        <div class="modal-box max-w-3xl no-scrollbar max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-lg" id="comment-modal-title"></h3>
            <div class="py-2" id="comment-modal-rating"></div>
            <p class="py-4 whitespace-pre-line break-words" id="comment-modal-body"></p>
            <div class="modal-action">
                <button class="btn" id="comment-modal-close">{{ __('Close') }}</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button aria-label="close"></button>
        </form>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('comment-modal');
            const title = document.getElementById('comment-modal-title');
            const body = document.getElementById('comment-modal-body');
            const rating = document.getElementById('comment-modal-rating');
            const closeBtn = document.getElementById('comment-modal-close');

            const renderStars = (value) => {
                const ratingValue = parseFloat(value) || 0;
                let stars = '';
                for (let i = 1; i <= 10; i++) {
                    const starValue = i / 2;
                    const isFilled = starValue <= ratingValue;
                    const halfClass = i % 2 === 1 ? 'mask-half-1' : 'mask-half-2';
                    const colorClass = isFilled ? 'bg-orange-400' : 'bg-orange-250';
                    stars += `<input type="radio" disabled class="pointer-events-none mask mask-star-2 ${halfClass} ${colorClass}">`;
                }
                return `<div class="rating rating-sm rating-half">${stars}</div>`;
            };

            document.querySelectorAll('[data-full-content]').forEach(button => {
                button.addEventListener('click', () => {
                    title.textContent = button.dataset.commentAuthor || '{{ __('Comment') }}';
                    body.textContent = button.dataset.fullContent || '';
                    rating.innerHTML = renderStars(button.dataset.commentRating);
                    modal.showModal();
                });
            });

            closeBtn.addEventListener('click', () => modal.close());
        });
    </script>
</x-app-layout>
