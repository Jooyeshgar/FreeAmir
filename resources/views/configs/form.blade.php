<div>
    <fieldset id="subjectForm" class="grid grid-cols-1 sm:grid-cols-2 gap-6 border p-5 my-3">
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: @js($selectedSubject?->name ?? ''),
                selectedCode: @js($selectedSubject?->code ?? ''),
                selectedId: @js($selectedSubject?->id ?? ''),
            }">
                <x-input name="key" value="{{ $config->key }}" hidden />
                <x-input name="{{ $config->key }}" id="{{ $config->key }}" placeholder="{{ __('Select Subject Code') }}" title="{{ __('Subject Code') }}"
                    x-bind:value="$store.utils.formatCode(selectedCode)" hidden />
                <x-subject-select class="w-2/3" data-config-subject-select
                    x-init="
                        const root = $el;
                        const trigger = root.querySelector(':scope > button');
                        const panel = $refs.panel;
                        const keepPanelOpen = event => event.stopPropagation();

                        const positionPanel = () => {
                            if (!open) return;

                            $nextTick(() => {
                                const gap = 4;
                                const viewportPadding = 8;
                                const maxPanelHeight = 240;
                                const rect = trigger.getBoundingClientRect();
                                const width = Math.min(rect.width, window.innerWidth - (viewportPadding * 2));
                                const left = Math.min(Math.max(rect.left, viewportPadding), window.innerWidth - width - viewportPadding);
                                const spaceBelow = window.innerHeight - rect.bottom - gap - viewportPadding;
                                const spaceAbove = rect.top - gap - viewportPadding;
                                const panelHeight = Math.min(panel.scrollHeight, maxPanelHeight);
                                const dropUpPanel = spaceBelow < panelHeight && spaceAbove > spaceBelow;
                                const availableHeight = Math.max(0, dropUpPanel ? spaceAbove : spaceBelow);
                                const maxHeight = Math.min(maxPanelHeight, availableHeight);
                                const renderedHeight = Math.min(panel.scrollHeight, maxHeight);
                                const top = dropUpPanel ? rect.top - gap - renderedHeight : rect.bottom + gap;

                                panel.style.inset = 'auto';
                                panel.style.top = `${Math.max(viewportPadding, top)}px`;
                                panel.style.left = `${left}px`;
                                panel.style.width = `${width}px`;
                                panel.style.maxHeight = `${maxHeight}px`;
                            });
                        };

                        $nextTick(() => {
                            panel.classList.remove('absolute', 'left-0', 'right-0', 'z-[100]', 'w-full');
                            panel.classList.add('fixed', 'z-[1000]');
                            panel.addEventListener('click', keepPanelOpen);
                            document.body.append(panel);
                            positionPanel();
                        });
                        $watch('open', value => value && positionPanel());
                        $watch('flatOptions', () => positionPanel());
                        window.addEventListener('resize', positionPanel);
                        window.addEventListener('scroll', positionPanel, true);

                        $data.destroy = () => {
                            window.removeEventListener('resize', positionPanel);
                            window.removeEventListener('scroll', positionPanel, true);
                            panel.removeEventListener('click', keepPanelOpen);
                            panel.remove();
                        };
                    "
                    :subjects="$subjects" title="{{ $config->desc }}" placeholder="{{ __('Select a subject') }}"
                    @selected="
                        selectedName = $event.detail.name;
                        selectedCode = $event.detail.code;
                        selectedId = $event.detail.id;
                    " />
                <x-input name="code" x-bind:value="selectedCode" hidden />
                <x-input name="{{ $config->key }}" x-bind:value="selectedId" hidden />
            </div>
        </div>
    </fieldset>
</div>
