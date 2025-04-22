<x-report-layout :title="__('Document Report')">
    @forelse ($documents as $document)
        @include('documents.document', ['document' => $document])

        @if (!$loop->last)
            <div class="break-after-page"></div>
        @endif

    @empty
        <p class="text-center text-gray-600">{{ __('No documents found matching the criteria.') }}</p>
    @endforelse
</x-report-layout>
