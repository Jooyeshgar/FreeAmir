<div>partial-broken-output</div>
<x-extension-test-wrapper>
    @php
        throw new \RuntimeException('view failed');
    @endphp
</x-extension-test-wrapper>
