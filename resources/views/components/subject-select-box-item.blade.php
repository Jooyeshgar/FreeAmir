@if ($subject->children->count() > 0 && (is_null($level) || ($level = $level - 1) > 0))
    <div class="w-full ps-2 mb-4">
        @if ($allSelectable)
            <a href="javascript:void(0)" class="selfSelectBoxItems flex justify-between mb-4"
                @click="updateSelection('{{ $subject->name }}', '{{ $subject->code }}', '{{ $subject->id }}')">
                <span class="selfItemTitle">
                    {{ $subject->name }}
                </span>
                <span class="codeList" data-name="{{ $subject->name }}" data-code="{{ $subject->code }}" data-id="{{ $subject->id }}" hidden></span>
                <span class="selfItemCode">
                    {{ $subject->formattedCode() }}
                </span>
                <span class="selfItemId hidden">{{ $subject->id }}</span>
            </a>
        @else
            <div class="flex justify-between">
                <span>
                    {{ $subject->name }}
                </span>
                <span>
                    {{ $subject->formattedCode() }}
                </span>
            </div>
        @endif
        <div class="ps-1 mt-4">
            <div class="border-[#ADB5BD]">
                @foreach ($subject->children as $child)
                    @include('components.subject-select-box-item', [
                        'subject' => $child,
                        'level' => $level,
                        'allSelectable' => $allSelectable,
                    ])
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="ps-1 mt-4">
        <div class="border-s-[1px] ps-7 border-[#ADB5BD]">
            <a href="javascript:void(0)" class="selfSelectBoxItems flex justify-between mb-4"
                @click="updateSelection('{{ $subject->name }}', '{{ $subject->code }}', '{{ $subject->id }}')">
                <span class="selfItemTitle">
                    {{ $subject->name }}
                </span>
                <span class="codeList" data-name="{{ $subject->name }}" data-code="{{ $subject->code }}" data-id="{{ $subject->id }}" hidden></span>
                <span class="selfItemCode" hidden>
                    {{ $subject->formattedCode() }}
                </span>
                <span class="selfItemId hidden">{{ $subject->id }}</span>
            </a>
        </div>
    </div>
@endif
