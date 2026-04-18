<x-app-layout :title="__('Organization Chart')">
    <div class="card bg-base-100">
        <div class="card-body" x-data="{ allExpanded: true }">

            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Organization Chart') }}</h2>

                <div class="flex gap-2">
                    <button type="button" class="btn" @click="allExpanded = true; $dispatch('toggle-all', { state: true })">
                        {{ __('Expand all') }}
                    </button>
                    <button type="button" class="btn" @click="allExpanded = false; $dispatch('toggle-all', { state: false })">
                        {{ __('Collapse all') }}
                    </button>
                </div>
            </div>

            @if ($roots->isEmpty())
                <div class="border border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                    {{ __('No organization chart nodes found.') }}
                </div>
            @else
                @php
                    $canCreate = auth()->user()?->can('hr.org-charts.create');
                    $canEdit = auth()->user()?->can('hr.org-charts.edit');
                    $canDelete = auth()->user()?->can('hr.org-charts.delete');

                    $renderTreeNode = function ($node, $targetId) use (&$renderTreeNode, $canCreate, $canEdit, $canDelete) {
                        $children = $node->children ?? collect();
                        $hasChildren = $children->isNotEmpty();
                        $isActive = $node->id === $targetId;

                        $nodeClasses = $isActive ?
                            'w-full border border-gray-300 bg-base-300 px-3 py-2'
                            : 'w-full border border-gray-200 bg-white px-3 py-2';

                        $title = e($node->title);
                        $showUrl = e(route('hr.org-charts.show', $node));
                        $createUrl = $canCreate ? e(route('hr.org-charts.create', ['parent_id' => $node->id])) : '';
                        $editUrl = $canEdit ? e(route('hr.org-charts.edit', $node)) : '';
                        $deleteUrl = $canDelete ? e(route('hr.org-charts.destroy', $node)) : '';
                        $addChildLabel = e(__('Add Child'));
                        $editLabel = e(__('Edit'));
                        $removeLabel = e(__('Remove'));
                        $html = '<li class="w-full ps-2 mb-4" x-data="{ open: true }" @toggle-all.window="open = $event.detail.state">';

                        $html .= '<div class="flex items-start">';

                        if ($hasChildren) {
                            $html .= '<div class="w-6 flex justify-center">
                                <button type="button" class="mt-1 text-gray-400" @click="open = !open">
                                    <span x-show="open">–</span>
                                    <span x-show="!open">+</span>
                                </button>
                            </div>';
                        } else {
                            $html .= '<div class="w-6"></div>';
                        }

                        $html .= '<div class="flex-1 ' . $nodeClasses . '">';
                        $html .= '<div class="text-sm text-gray-800 flex items-center justify-between gap-3">';
                        $html .= '<div class="flex items-center gap-2">';
                        $html .= '<a href="' . $showUrl . '">' . $title . '</a>';

                        if (!empty($node->description)) {
                            $html .= '<span class="text-xs text-gray-400">' . e($node->description) . '</span>';
                        }

                        $html .= '</div>';
                        $html .= '<div class="flex items-center gap-2 shrink-0">';
                        if ($canCreate) {
                            $html .= 
                                '<a href="' .
                                $createUrl .
                                '" class="btn btn-xs btn-primary" title="' .
                                $addChildLabel .
                                '" aria-label="' .
                                $addChildLabel .
                                '">';
                            $html .=
                                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>';
                            $html .= '</a>';
                        }
                        if ($canEdit) {
                            $html .=
                                '<a href="' .
                                $editUrl .
                                '" class="btn btn-xs btn-info" title="' .
                                $editLabel .
                                '" aria-label="' .
                                $editLabel .
                                '">';
                            $html .=
                                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5h2m-7 14h12a2 2 0 0 0 2-2v-8.586a2 2 0 0 0-.586-1.414l-2.414-2.414A2 2 0 0 0 15.586 4H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2Zm8.414-11.414L9 13v2h2l6.414-6.414-2-2Z" /></svg>';
                            $html .= '</a>';
                        }
                        if ($canDelete) {
                            $html .=
                                '<form action="' .
                                $deleteUrl .
                                '" method="POST" class="inline-block m-0" onsubmit="return confirm(\'' .
                                e(__('Are you sure?')) .
                                '\')">';
                            $html .= csrf_field();
                            $html .= method_field('DELETE');
                            $html .=
                                '<button type="submit" class="btn btn-xs btn-error" title="' .
                                '<button type="submit" class="btn btn-xs btn-error no-animation transition-none hover:bg-error hover:border-error hover:text-error-content" title="' .
                                $removeLabel .
                                '" aria-label="' .
                                $removeLabel .
                                '">';
                            $html .=
                                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 0V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7" /></svg>';
                            $html .= '</button>';
                            $html .= '</form>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';
                        $html .= '</div>';

                        if ($hasChildren) {
                            $html .= '<div class="ps-1 mt-4" x-show="open">';
                            $html .= '<ul class="border-s-[1px] ps-7 border-[#ADB5BD] space-y-2">';
                            foreach ($children as $child) {
                                $html .= $renderTreeNode($child, $targetId);
                            }
                            $html .= '</ul>';
                            $html .= '</div>';
                        }

                        $html .= '</li>';

                        return $html;
                    };
                @endphp

                <div class="mt-4 overflow-x-auto bg-white p-3">
                    <ul class="space-y-2 min-w-max">
                        @foreach ($roots as $node)
                            {!! $renderTreeNode($node, $orgChart->id) !!}
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-end mt-3">
                <a href="{{ route('hr.org-charts.index') }}" class="btn btn-primary">{{ __('Back') }}</a>
            </div>

        </div>
    </div>
</x-app-layout>
