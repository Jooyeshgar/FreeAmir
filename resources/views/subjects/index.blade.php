<x-app-layout :title="__('Subjects')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body" x-data="{
            transferSourceId: null,
            transferSourceName: '',
            openTransferModal(id, name) {
                this.transferSourceId = id;
                this.transferSourceName = name;
                this.$nextTick(() => document.getElementById('transferModal').showModal());
            }
        }">
            <div class="card-actions ">
                <div>
                    <a href="{{ route('subjects.create', ['parent_id' => request('parent_id', null)]) }}" class="btn btn-primary ">{{ __('Create Subject') }}</a>
                </div>
                @if($currentParent)
                    @php
                        $upUrl = route('subjects.index');
                        if ($currentParent->parent_id) {
                            $upUrl .= '?parent_id=' . $currentParent->parent_id;
                        }
                    @endphp

                    <span class="ml-2 text-lg leading-[3rem] font-semibold grow">{{ $currentParent->name }}</span>
                    <a href="{{ $upUrl }}" class="btn btn-outline">
                        {{ __('Back') }}
                    </a>
                    @if(! $currentParent->children()->exists())
                        <button class="btn btn-primary" x-on:click="openTransferModal('{{ $currentParent->id }}', '{{ $currentParent->name }}')" title="{{ __('Transfer transactions from this subject and its descendants to another') }}">
                            {{ __('Transfer Transactions') }}
                        </button>
                    @endif
                @endif
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Parent') }}</th>
                        <th class="px-4 py-2">{{ __('Type') }}</th>
                        <th class="px-4 py-2">{{ __('Permanent/Temporary') }}</th>
                        <th class="px-4 py-2">{{ __('Created at') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $subject)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('transactions.index', ['subject_id' => $subject->id]) }}" class="text-primary hover:underline" title="{{ __('View transactions for this subject') }}">
                                    {{ $subject->formattedCode() }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('subjects.index', ['parent_id' => $subject->id]) }}" class="text-primary"> {{ $subject->name }}</a>
                                @if ($subject->subjectable)
                                    <div class="badge badge-primary" title="{{ __('Related to') }}: {{ class_basename($subject->subjectable::class) }}">
                                        <a href="{{ route(model_route($subject->subjectable, 'show'), $subject->subjectable) }}">
                                        {{ __(class_basename($subject->subjectable::class)) }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $subject->parent ? $subject->parent->name : __('Main') }}</td>
                            <td class="px-4 py-2">{{ $subject->type ? __(ucfirst($subject->type)) : '-' }}</td>
                            <td class="px-4 py-2">{{ $subject->is_permanent ? __('Permanent') : __('Temporary') }}</td>
                            <td class="px-4 py-2">{{ $subject->created_at ? formatDate($subject->created_at) : '-' }}</td>
                            <td class="px-4 py-2">
                                @if ($subject->subjectable)
                                    <a href="{{ route(model_route($subject->subjectable, 'edit'), $subject->subjectable) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @else
                                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @endif

                                @if ($subject->subjectable || $subject->hasChildren())
                                    @php
                                        $reasons = [];
                                        if ($subject->subjectable) $reasons[] = __('Cannot delete subject with relationships');
                                        if ($subject->hasChildren()) $reasons[] = __('Cannot delete subject with children');
                                    @endphp
                                    <span class="tooltip tooltip-left" data-tip="{{ implode(' | ', $reasons) }}">
                                        <button class="btn btn-sm btn-error" disabled>{{ __('Delete') }}</button>
                                    </span>
                                @else
                                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($subjects->isEmpty())
                <div class="text-center py-2">
                    <p>
                        {{ __('There are no subjects in the current level.') }}
                        {{ __('You can for this level') }} 
                        <a href="{{ route('subjects.create', ['parent_id' => request('parent_id', null)]) }}" class="link link-primary">{{ __('create a subject') }}</a>
                        @if($currentParent)
                            {{ __('or') }}
                            <a href="#" x-on:click.prevent="openTransferModal('{{ $currentParent->id }}', '{{ $currentParent->name }}')" class="link link-primary">{{ __('transfer this subject to this level') }}</a>.
                        @endif
                    </p>
                </div>
            @endif

            <dialog id="transferModal" class="modal">
                <div class="modal-box w-11/12 max-w-2xl overflow-visible relative">
                    <h3 class="font-bold text-lg">{{ __('Transfer Subject') }}</h3>

                    <form action="{{ route('subjects.transfer') }}" method="POST" x-data="{ createNewSubject: true }">
                        @csrf
                        <x-input name="source_subject_id" x-bind:value="transferSourceId" hidden />

                        <div class="py-4 space-y-4">
                            <template x-if="transferSourceId">
                                <div>
                                    <label class="label">{{ __('Source Subject') }}</label>
                                    <div class="input input-bordered w-full flex items-center gap-2">
                                        <span x-text="transferSourceName" class="grow"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!createNewSubject">
                                <div x-data="{
                                    selectedName: '',
                                    selectedCode: '',
                                    selectedId: '',
                                }">
                                    <x-subject-select :subjects="$subjectTree" title="{{ __('Destination Subject') }}" placeholder="{{ __('Select destination subject') }}"
                                        @selected="
                                            selectedName = $event.detail.name;
                                            selectedCode = $event.detail.code;
                                            selectedId = $event.detail.id;
                                        " />
                                    <x-input name="destination_subject_id" x-bind:value="selectedId" hidden />
                                </div>
                            </template>

                            <template x-if="createNewSubject">
                                <div x-data="{
                                    selectedName: '',
                                    selectedCode: '',
                                    selectedId: '',
                                }">
                                    <x-subject-select :subjects="$subjectTree" title="{{ __('Parent Destination Subject') }}" placeholder="{{ __('Select parent destination subject') }}"
                                        @selected="
                                            selectedName = $event.detail.name;
                                            selectedCode = $event.detail.code;
                                            selectedId = $event.detail.id;
                                        " />
                                    <x-input name="parent_destination_subject_id" x-bind:value="selectedId" hidden />
                                </div>
                            </template>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 pt-4 border-t border-base-300">
                                <x-checkbox name="create_new_subject" id="create_new_subject" x-model="createNewSubject" value="1" checked title="{{ __('Create new subject under destination subject') }}" />
                                <x-checkbox name="transfer_subjectable" id="transfer_subjectable" value="1" checked title="{{ __('Transfer source subject relation') }}" />
                                <x-checkbox name="remove_source_subject" id="remove_source_subject" value="1" checked title="{{ __('Remove source subject after transfer') }}" />
                            </div>
                        </div>

                        <div class="modal-action">
                            <button type="button" onclick="document.getElementById('transferModal').close()" class="btn">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Transfer Transactions') }}</button>
                        </div>
                    </form>
                </div>
            </dialog>
        </div>
    </div>
</x-app-layout>
