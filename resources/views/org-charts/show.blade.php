<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Organization Chart Node') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{ $orgChart->title }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">{{ __('Parent Node') }}</p>
                    <p class="text-base">{{ $orgChart->parent?->title ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">{{ __('Description') }}</p>
                    <p class="text-base">{{ $orgChart->description ?? '-' }}</p>
                </div>
            </div>

            @if ($orgChart->children->isNotEmpty())
                <div class="mt-6">
                    <p class="text-sm font-medium text-gray-500 mb-2">{{ __('Direct Reports') }}</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($orgChart->children as $child)
                            <li>
                                <a href="{{ route('org-charts.show', $child) }}" class="link link-primary">
                                    {{ $child->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card-actions justify-end mt-4">
                <a href="{{ route('org-charts.index') }}" class="btn btn-ghost">{{ __('Back') }}</a>
                @can('org-charts.edit')
                    <a href="{{ route('org-charts.edit', $orgChart) }}" class="btn btn-info">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
