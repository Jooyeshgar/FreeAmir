<x-app-layout>
    <form action="{{ route('payroll.organizational_charts.update', $organizationalChart) }}" method="POST" class="relative mt-4">
        @method('PUT')
        @csrf
        <div class="card bg-gray-100 shadow-xl rounded-xl">

            <div class="card-body p-4">

                <x-show-message-bags />

                @include('organizational_charts.form')

            </div>

        </div>
        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Update') }}
            </button>
        </div>
    </form>
</x-app-layout>
