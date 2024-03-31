@foreach($fields as $key=>$field)
    <div class="mb-6">
        <label for="{{$key}}"
               class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{$field['label']}}</label>

        @switch($field['type'])

            @case('select')
                <select name="{{$key}}" id="{{$key}}">
                    <option value="">choose a option</option>
                    @foreach($field['options'] as $option)
                        <option
                            value="{{$option->id}}" {{ (isset($transaction) && $option->id === $transaction->customer_id) ? 'selected' : ''}}>{{$option->name}}</option>
                    @endforeach
                </select>
                @break

            @case('textarea')
                <textarea name="{{$key}}" id="{{$key}}" cols="30" rows="10" class="w-full border">
                    {{ isset($transaction) ? $transaction[$key] : old($key, $transaction[$key] ?? '')}}
                </textarea>
                @break

            @case('checkbox')
                <input {{ (isset($transaction) && $transaction[$key] == 1) ? 'checked' : ''}} type="checkbox"
                       id="{{$key}}" name="{{$key}}"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                @break

            @default
                <input value="{{ isset($transaction) ? $transaction[$key] : old($key, $transaction[$key] ?? '')}}" type="{{$field['type']}}"
                       id="{{$key}}" name="{{$key}}"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
        @endswitch
    </div>
@endforeach
