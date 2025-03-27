<div {{ $attributes->merge(['class' => 'card bg-base-100 shadow-xl ' . $attributes->get('class')]) }}>
    <div class="card-body {{ $attributes->get('class_body') }}">
        {{ $slot }}
    </div>
</div>
