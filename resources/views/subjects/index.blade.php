<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subjects') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions ">
                <div>
                    <a href="{{ route('subjects.create', ['parent_id' => request('parent_id', null)]) }}" class="btn btn-primary">{{ __('Create Subject') }}</a>
                </div>
                @if($currentParent)
                    @php
                        $upUrl = route('subjects.index');
                        if ($currentParent->parent_id) {
                            $upUrl .= '?parent_id=' . $currentParent->parent_id;
                        }
                    @endphp

                    <span class="ml-2 text-lg leading-[3rem] font-semibold grow">{{ $currentParent->name }}</span>
                    <a href="{{ $upUrl }}" class="btn btn-outline">
                        {{ __('Back') }}
                    </a>
                @endif
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Parent') }}</th>
                        <th class="px-4 py-2">{{ __('Type') }}</th>
                        <th class="px-4 py-2">{{ __('Permanent/Temporary') }}</th>
                        <th class="px-4 py-2">{{ __('Created at') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('transactions.index', ['subject_id' => $subject->id]) }}" class="text-primary hover:underline" title="{{ __('View transactions for this subject') }}">
                                    {{ $subject->formattedCode() }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('subjects.index', ['parent_id' => $subject->id]) }}" class="text-primary"> {{ $subject->name }}</a>
                                @if ($subject->subjectable)
                                    <div class="badge badge-primary" title="{{ __('Related to') }}: {{ class_basename($subject->subjectable::class) }}">
                                        <a href="{{ route(model_route($subject->subjectable, 'show'), $subject->subjectable) }}">
                                        {{ __(class_basename($subject->subjectable::class)) }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $subject->parent ? $subject->parent->name : __('Main') }}</td>
                            <td class="px-4 py-2">{{ $subject->type ? __(ucfirst($subject->type)) : '-' }}</td>
                            <td class="px-4 py-2">{{ $subject->is_permanent ? __('Permanent') : __('Temporary') }}</td>
                            <td class="px-4 py-2">{{ $subject->created_at ? formatDate($subject->created_at) : '-' }}</td>
                            <td class="px-4 py-2">
                                @if ($subject->subjectable)
                                    <a href="{{ route(model_route($subject->subjectable, 'edit'), $subject->subjectable) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    <span class="btn btn-sm btn-disabled" title="{{ __('Cannot delete subject with relationships') }}">{{ __('Delete') }}</span>
                                @else
                                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
