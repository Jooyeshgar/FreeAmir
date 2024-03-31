@foreach($fields as $key=>$field)
    <div class="mb-6">
        <label for="{{$key}}"
               class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{$field['label']}}</label>
        <input value="{{ isset($bank) ? $bank[$key] : ''}}" type="{{$field['type']}}"
                       id="{{$key}}" name="{{$key}}"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
    </div>
@endforeach
