<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Document Files') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">



            <div class="card-actions flex justify-between gap-4 ">
                <a href="{{ route('document-files.create', $document->id) }}" class="btn btn-primary ">{{ __('Add Document File') }}</a>
            </div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Attached By') }}</th>
                        <th class="px-4 py-2">{{ __('File Title') }}</th>
                        <th class="px-4 py-2">{{ __('Create at') }}</th>
                        <th class="px-4 py-2">{{ __('Update at') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($documentFiles as $documentFile)
                        <tr>
                            <td class="px-4 py-2">{{ $documentFile->attachBy->name }}</td>
                            <td class="px-4 py-2">{{ $documentFile->title }}</td>
                            <td class="px-4 py-2">{{ $documentFile->created_at }}</td>
                            <td class="px-4 py-2">{{ $documentFile->updated_at }}</td>
                            <td class="px-4 py-2 flex gap-2 items-center">
                                <a href="{{ route('document-files.view', [$document, $documentFile]) }}" class="btn btn-sm btn-info btn-square" title="{{ __('View') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                                -1.274 4.057-5.064 7-9.542 7 -4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                <a href="{{ route('document-files.download', [$document, $documentFile]) }}" class="btn btn-sm btn-info btn-square" title="{{ __('Download') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10l5 5 5-5M12 15V3" />
                                    </svg>
                                </a>
                                <a href="{{ route('document-files.edit', [$document, $documentFile]) }}" class="btn btn-sm btn-info btn-square" title="{{ __('Edit') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                <form action="{{ route('document-files.destroy', [$document->id, $documentFile]) }}" method="POST" class="m-0" onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error btn-square" title="{{ __('Delete') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (count($documentFiles) === 0)
                <p class="text-xs text-center text-gray-500 mt-1">{{ __('There is no document files.') }}</p>
            @endif

            @if ($documentFiles->count() > 25)
                <div class="mt-4 flex justify-center">
                    {!! $documentFiles->links() !!}
                </div>
            @endif

            <div class="card-actions justify-between mt-4 text-left">
                <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
