<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Transaction') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('transactions.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add transaction') }}</div>
                <x-show-message-bags />

                <div class="justify-between flex flex-wrap p-2">
                    <div>
                        <label>
                            <span>title</span>
                            <input type="text" class="form-control p-2 border" name="title" placeholder="title">
                        </label>
                    </div>
                    <div class="mt-4 lg:mt-0 flex flex-wrap gap-2">
                        <label>
                            <span>previous number</span>
                            <input value="{{$previousTransactionId}}" disabled type="text" class="form-control bg-info-content/5 p-2 border" placeholder="previous number">
                        </label>
                        <label>
                            <span>current number</span>
                            <input value="{{$previousTransactionId+1}}" disabled type="text" class="form-control bg-info-content/5 p-2 border" placeholder="current number">
                        </label>
                        <label>
                            <span>date</span>
                            <input type="text" disabled value="{{date('Y-m-d')}}" class="form-control bg-info-content/5 p-2 border" placeholder="date">
                        </label>
                    </div>
                </div>
                @include('transactions.form')
                <div class="mb-6">
                    <button type="submit" class="w-full btn bg-success/30  rounded-md hover:bg-success"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
