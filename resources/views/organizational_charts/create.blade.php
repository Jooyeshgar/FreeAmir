<x-app-layout>
    <form action="{{ route('payroll.organizational_charts.store') }}" method="POST" class="relative mt-4">
        <div class="card bg-gray-100 shadow-xl rounded-xl">

            @csrf
            <div class="card-body p-4">

                <x-show-message-bags />

                @include('organizational_charts.form')

            </div>

        </div>
        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Create') }}
            </button>
        </div>
    </form>
</x-app-layout>
