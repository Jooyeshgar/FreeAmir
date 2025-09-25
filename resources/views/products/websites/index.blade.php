<div class="grid grid-cols-2 gap-6">

<x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)"
                name="websites" id="websites" title="{{ __('Website') }}"
                :value="old('website', $websites[0]->link ?? '')"
                placeholder="{{ __('Please insert website') }}" 
                x-on:change=""
                />


</div>