<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subjects') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <a href="{{ route('subjects.create') }}" class="btn btn-primary">Create Subject</a>

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
                                    <td class="px-4 py-2">{{ $subject->name }}</td>
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
        </div>
    </div>
</x-app-layout>