<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="addWebsite">
    <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 min-w-24 max-w-32 text-center text-gray-500 pt-3">
            {{ __('Website') }}
        </div>
    </div>

    <div class="overflow-y-auto">
        <div id="websites" x-data="{ activeTab: {{ count($websites) }} }">
            <template x-for="(website, index) in websites" :key="website.id">
                <div :class="{ 'active': activeTab === index }" class="flex gap-2 items-center px-4 pb-3" @click="activeTab = index">

                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-2 website-count-container">
                        <span class="website-count block" x-text="index + 1"></span>
                        <button @click.stop="websites.splice(index, 1)" type="button" class="absolute left-0 top-0 removeButton">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                class="px-2 size-8 rounded-md h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="website.link" placeholder="{{ __('Website') }}"
                            x-bind:name="'websites[' + index + '][link]'" label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white "></x-text-input>
                    </div>

                </div>
            </template>

            <button class="flex justify-content gap-4 align-center w-full px-4 mb-2" id="addWebsite" @click="addWebsite(); activeTab = websites.length;"
                type="button">
                <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                    <span class="text-2xl">+</span>
                    {{ __('Add Website') }}
                </div>
            </button>
        </div>
    </div>

    
</x-card>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('addWebsite', () => ({
                websites: @json(old('websites', $websites), JSON_UNESCAPED_UNICODE),
                addWebsite() {
                    const newId = this.websites.length ? this.websites[this.websites.length - 1].id + 1 : 1;
                    this.websites.push({
                        id: newId,
                        link: '',
                    });
                }
            }));
        });
    </script>
@endPushOnce