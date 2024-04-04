<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subjects') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('subjects.create') }}" class="btn btn-primary">Create Subject</a>
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Code</th>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Parent</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                    <tr>
                        <td class="px-4 py-2">{{ $subject->code }}</td>
                        <td class="px-4 py-2"><a href="{{ route('subjects.index', ['parent_id' => $subject->id]) }}" class="text-primary"> {{ $subject->name }}</a></td>
                        <td class="px-4 py-2">{{ $subject->parent ? $subject->parent->name : '-' }}</td>
                        <td class="px-4 py-2">{{ $subject->type ? ucfirst($subject->type) : '-' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-info">Edit</a>
                            <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>