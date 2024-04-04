@foreach($messages as $message)
    <div role="alert" class="my-3 alert alert-{{ $message['type'] }}">
        <span>
            @foreach($message['message'] as $msg) 
            {{ $msg }}<br/>
            @endforeach
        </span>
    </div>
@endforeach
