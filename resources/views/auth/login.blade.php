<x-login-layout>
    <header class="bg-gray-200 py-2 px-4 flex items-center justify-between">

        <div class="flex items-center">
            <img src="/images/logo.png" alt="Logo" width="50" class="mr-2">
            <h1 class="font-bold">نرم‌افزار آزاد حسابداری امیر</h1>
        </div>
        <div class="language-select">

            <div class="language-picker js-language-picker ml-10 bg-gray-100 p-2 rounded"
                 data-trigger-class="li4-btn li4-btn--subtle js-tab-focus">
                <form action="" class="language-picker__form">
                    <label for="language-picker-select">Select your language</label>

                    <select name="language-picker-select" id="language-picker-select">
                        <option lang="en" value="english" selected>انتخاب زبان</option>
                        <option lang="fr" value="francais">Français</option>
                        <option lang="it" value="italiano">Italiano</option>
                    </select>
                </form>
            </div>
        </div>

    </header>

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 p-0 border-opacity-85  overflow-hidden   ">
        <div class="flex items-center justify-center  rounded-3xl    ">
            <div class="w-96 p-7 mt-16	 h-373 bg-white rounded-lg ">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <h1 class="font-bold text-center">ورود به حساب</h1>
                    <x-form-input title="{{ __('نام کاربری / شماره موبایل') }}" name="email"
                                  place-holder="{{ __('Enter your email') }}" :message="$errors->first('email')"/>

                    <x-form-input title="{{ __('رمز عبور') }}" name="password"
                                  place-holder="{{ __('Enter your password') }}" :message="$errors->first('password')"
                                  type="password"/>
                    <div class="flex items-center justify-between mt-4 pl-2 ">

                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white  py-2 px-8 rounded">
                            {{ __('ورود به حساب') }}
                        </button>

                        <button type="submit" class="bg-gray-300 hover:bg-gray-400 text-black  py-2 px-8 rounded">
                            {{ __('بازیابی رمز عبور') }}
                        </button>
                    </div>
                    <div class="border border-gray-400 mt-3 p-0"></div>

                    <div class="flex justify-center mt-4 pl-2">
                        <button type="submit"
                                class="flex justify-center items-center bg-gray-300 w-full hover:bg-gray-400 text-black py-2 px-5 rounded">
                            <svg class="mx-2" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M18.1726 8.36794H17.5013V8.33335H10.0013V11.6667H14.7109C14.0238 13.6071 12.1776 15 10.0013 15C7.24005 15 5.0013 12.7613 5.0013 10C5.0013 7.23877 7.24005 5.00002 10.0013 5.00002C11.2759 5.00002 12.4355 5.48085 13.3184 6.26627L15.6755 3.90919C14.1871 2.5221 12.1963 1.66669 10.0013 1.66669C5.39922 1.66669 1.66797 5.39794 1.66797 10C1.66797 14.6021 5.39922 18.3334 10.0013 18.3334C14.6034 18.3334 18.3346 14.6021 18.3346 10C18.3346 9.44127 18.2771 8.89585 18.1726 8.36794Z"
                                        fill="#FFC107"/>
                                    <path
                                        d="M2.62891 6.12127L5.36682 8.12919C6.10766 6.29502 7.90182 5.00002 10.0014 5.00002C11.276 5.00002 12.4356 5.48085 13.3185 6.26627L15.6756 3.90919C14.1872 2.5221 12.1964 1.66669 10.0014 1.66669C6.80057 1.66669 4.02474 3.47377 2.62891 6.12127Z"
                                        fill="#FF3D00"/>
                                    <path
                                        d="M10.0008 18.3333C12.1533 18.3333 14.1091 17.5096 15.5879 16.17L13.0087 13.9875C12.1439 14.6451 11.0872 15.0008 10.0008 15C7.83328 15 5.99286 13.6179 5.29953 11.6891L2.58203 13.7829C3.9612 16.4816 6.76203 18.3333 10.0008 18.3333Z"
                                        fill="#4CAF50"/>
                                    <path
                                        d="M18.1713 8.3679H17.5V8.33331H10V11.6666H14.7096C14.3809 12.5902 13.7889 13.3971 13.0067 13.9879L13.0079 13.9871L15.5871 16.1696C15.4046 16.3354 18.3333 14.1666 18.3333 9.99998C18.3333 9.44123 18.2758 8.89581 18.1713 8.3679Z"
                                        fill="#1976D2"/>
                                </svg>

                            </svg>
                            <span>{{ __('ورود با حساب گوگل') }}</span>
                        </button>

                    </div>
                </form>

            </div>
        </div>

        <div class="flex justify-center mt-4 ">
            <div class="flex space-x-4">
                <a href="#" class="ml-4 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    شرایط استفاده از خدمات
                </a>
                <a href="#" class="mx-5 bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    سیاست‌های حفظ حریم خصوصی
                </a>
                <a href="#" class="bg-gray-300 hover:bg-gray-400 text-black py-2 px-5 rounded">
                    به راهنمایی نیاز دارید؟
                </a>
            </div>
        </div>
    </div>
    </div>
    <script>
        // utility functions
        if (!Util) function Util() {
        }
        ;

        Util.addClass = function (el, className) {
            var classList = className.split(' ');
            el.classList.add(classList[0]);
            if (classList.length > 1) Util.addClass(el, classList.slice(1).join(' '));
        };

        Util.removeClass = function (el, className) {
            var classList = className.split(' ');
            el.classList.remove(classList[0]);
            if (classList.length > 1) Util.removeClass(el, classList.slice(1).join(' '));
        };

        Util.toggleClass = function (el, className, bool) {
            if (bool) Util.addClass(el, className);
            else Util.removeClass(el, className);
        };

        Util.moveFocus = function (element) {
            if (!element) element = document.getElementsByTagName('body')[0];
            element.focus();
            if (document.activeElement !== element) {
                element.setAttribute('tabindex', '-1');
                element.focus();
            }
        };

        Util.getIndexInArray = function (array, el) {
            return Array.prototype.indexOf.call(array, el);
        };


        // File#: _1_language-picker
        // Usage: codyhouse.co/license
        (function () {
            var LanguagePicker = function (element) {
                this.element = element;
                this.select = this.element.getElementsByTagName('select')[0];
                this.options = this.select.getElementsByTagName('option');
                this.selectedOption = getSelectedOptionText(this);
                this.pickerId = this.select.getAttribute('id');
                this.trigger = false;
                this.dropdown = false;
                this.firstLanguage = false;
                // dropdown arrow inside the button element
                this.arrowSvgPath = '<svg viewBox="0 0 16 16"><polygon points="3,5 8,11 13,5 "></polygon></svg>';
                this.globeSvgPath = '<svg viewBox="0 0 16 16"><path d="M8,0C3.6,0,0,3.6,0,8s3.6,8,8,8s8-3.6,8-8S12.4,0,8,0z M13.9,7H12c-0.1-1.5-0.4-2.9-0.8-4.1 C12.6,3.8,13.6,5.3,13.9,7z M8,14c-0.6,0-1.8-1.9-2-5H10C9.8,12.1,8.6,14,8,14z M6,7c0.2-3.1,1.3-5,2-5s1.8,1.9,2,5H6z M4.9,2.9 C4.4,4.1,4.1,5.5,4,7H2.1C2.4,5.3,3.4,3.8,4.9,2.9z M2.1,9H4c0.1,1.5,0.4,2.9,0.8,4.1C3.4,12.2,2.4,10.7,2.1,9z M11.1,13.1 c0.5-1.2,0.7-2.6,0.8-4.1h1.9C13.6,10.7,12.6,12.2,11.1,13.1z"></path></svg>';

                initLanguagePicker(this);
                initLanguagePickerEvents(this);
            };

            function initLanguagePicker(picker) {
                // create the HTML for the custom dropdown element
                picker.element.insertAdjacentHTML('beforeend', initButtonPicker(picker) + initListPicker(picker));

                // save picker elements
                picker.dropdown = picker.element.getElementsByClassName('language-picker__dropdown')[0];
                picker.languages = picker.dropdown.getElementsByClassName('language-picker__item');
                picker.firstLanguage = picker.languages[0];
                picker.trigger = picker.element.getElementsByClassName('language-picker__button')[0];
            };

            function initLanguagePickerEvents(picker) {
                // make sure to add the icon class to the arrow dropdown inside the button element
                var svgs = picker.trigger.getElementsByTagName('svg');
                Util.addClass(svgs[0], 'li4-icon');
                Util.addClass(svgs[1], 'li4-icon');
                // language selection in dropdown
                // ⚠️ Important: you need to modify this function in production
                initLanguageSelection(picker);

                // click events
                picker.trigger.addEventListener('click', function () {
                    toggleLanguagePicker(picker, false);
                });
                // keyboard navigation
                picker.dropdown.addEventListener('keydown', function (event) {
                    if (event.keyCode && event.keyCode == 38 || event.key && event.key.toLowerCase() == 'arrowup') {
                        keyboardNavigatePicker(picker, 'prev');
                    } else if (event.keyCode && event.keyCode == 40 || event.key && event.key.toLowerCase() == 'arrowdown') {
                        keyboardNavigatePicker(picker, 'next');
                    }
                });
            };

            function toggleLanguagePicker(picker, bool) {
                var ariaExpanded;
                if (bool) {
                    ariaExpanded = bool;
                } else {
                    ariaExpanded = picker.trigger.getAttribute('aria-expanded') == 'true' ? 'false' : 'true';
                }
                picker.trigger.setAttribute('aria-expanded', ariaExpanded);
                if (ariaExpanded == 'true') {
                    picker.firstLanguage.focus(); // fallback if transition is not supported
                    picker.dropdown.addEventListener('transitionend', function cb() {
                        picker.firstLanguage.focus();
                        picker.dropdown.removeEventListener('transitionend', cb);
                    });
                    // place dropdown
                    placeDropdown(picker);
                }
            };

            function placeDropdown(picker) {
                var triggerBoundingRect = picker.trigger.getBoundingClientRect();
                Util.toggleClass(picker.dropdown, 'language-picker__dropdown--right', (window.innerWidth < triggerBoundingRect.left + picker.dropdown.offsetWidth));
                Util.toggleClass(picker.dropdown, 'language-picker__dropdown--up', (window.innerHeight < triggerBoundingRect.bottom + picker.dropdown.offsetHeight));
            };

            function checkLanguagePickerClick(picker, target) { // if user clicks outside the language picker -> close it
                if (!picker.element.contains(target)) toggleLanguagePicker(picker, 'false');
            };

            function moveFocusToPickerTrigger(picker) {
                if (picker.trigger.getAttribute('aria-expanded') == 'false') return;
                if (document.activeElement.closest('.language-picker__dropdown') == picker.dropdown) picker.trigger.focus();
            };

            function initButtonPicker(picker) { // create the button element -> picker trigger
                // check if we need to add custom classes to the button trigger
                var customClasses = picker.element.getAttribute('data-trigger-class') ? ' ' + picker.element.getAttribute('data-trigger-class') : '';

                var button = '<button class="language-picker__button' + customClasses + '" aria-label="' + picker.select.value + ' ' + picker.element.getElementsByTagName('label')[0].textContent + '" aria-expanded="false" aria-controls="' + picker.pickerId + '-dropdown">';
                button = button + '<span aria-hidden="true" class="language-picker__label language-picker__flag language-picker__flag--' + picker.select.value + '">' + picker.globeSvgPath + '<em>' + picker.selectedOption + '</em>';
                button = button + picker.arrowSvgPath + '</span>';
                return button + '</button>';
            };

            function initListPicker(picker) { // create language picker dropdown
                var list = '<div class="language-picker__dropdown" aria-describedby="' + picker.pickerId + '-description" id="' + picker.pickerId + '-dropdown">';
                list = list + '<p class="li4-sr-only" id="' + picker.pickerId + '-description">' + picker.element.getElementsByTagName('label')[0].textContent + '</p>';
                list = list + '<ul class="language-picker__list" role="listbox">';
                for (var i = 0; i < picker.options.length; i++) {
                    var selected = picker.options[i].selected ? ' aria-selected="true"' : '',
                        language = picker.options[i].getAttribute('lang');
                    list = list + '<li><a lang="' + language + '" hreflang="' + language + '" href="' + getLanguageUrl(picker.options[i]) + '"' + selected + ' role="option" data-value="' + picker.options[i].value + '" class="language-picker__item language-picker__flag language-picker__flag--' + picker.options[i].value + '"><span>' + picker.options[i].text + '</span></a></li>';
                }
                ;
                return list;
            };

            function getSelectedOptionText(picker) { // used to initialize the label of the picker trigger button
                var label = '';
                if ('selectedIndex' in picker.select) {
                    label = picker.options[picker.select.selectedIndex].text;
                } else {
                    label = picker.select.querySelector('option[selected]').text;
                }
                return label;
            };

            function getLanguageUrl(option) {
                // ⚠️ Important: You should replace this return value with the real link to your website in the selected language
                // option.value gives you the value of the language that you can use to create your real url (e.g, 'english' or 'italiano')
                return '#';
            };

            function initLanguageSelection(picker) {
                picker.element.getElementsByClassName('language-picker__list')[0].addEventListener('click', function (event) {
                    var language = event.target.closest('.language-picker__item');
                    if (!language) return;

                    if (language.hasAttribute('aria-selected') && language.getAttribute('aria-selected') == 'true') {
                        // selecting the same language
                        event.preventDefault();
                        picker.trigger.setAttribute('aria-expanded', 'false'); // hide dropdown
                    } else {
                        // ⚠️ Important: this 'else' code needs to be removed in production.
                        // The user has to be redirected to the new url -> nothing to do here
                        event.preventDefault();
                        picker.element.getElementsByClassName('language-picker__list')[0].querySelector('[aria-selected="true"]').removeAttribute('aria-selected');
                        language.setAttribute('aria-selected', 'true');
                        picker.trigger.getElementsByClassName('language-picker__label')[0].setAttribute('class', 'language-picker__label language-picker__flag language-picker__flag--' + language.getAttribute('data-value'));
                        picker.trigger.getElementsByClassName('language-picker__label')[0].getElementsByTagName('em')[0].textContent = language.textContent;
                        picker.trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            };

            function keyboardNavigatePicker(picker, direction) {
                var index = Util.getIndexInArray(picker.languages, document.activeElement);
                index = (direction == 'next') ? index + 1 : index - 1;
                if (index < 0) index = picker.languages.length - 1;
                if (index >= picker.languages.length) index = 0;
                Util.moveFocus(picker.languages[index]);
            };

            //initialize the LanguagePicker objects
            var languagePicker = document.getElementsByClassName('js-language-picker');
            if (languagePicker.length > 0) {
                var pickerArray = [];
                for (var i = 0; i < languagePicker.length; i++) {
                    (function (i) {
                        pickerArray.push(new LanguagePicker(languagePicker[i]));
                    })(i);
                }

                // listen for key events
                window.addEventListener('keyup', function (event) {
                    if (event.keyCode && event.keyCode == 27 || event.key && event.key.toLowerCase() == 'escape') {
                        // close language picker on 'Esc'
                        pickerArray.forEach(function (element) {
                            moveFocusToPickerTrigger(element); // if focus is within dropdown, move it to dropdown trigger
                            toggleLanguagePicker(element, 'false'); // close dropdown
                        });
                    }
                });
                // close language picker when clicking outside it
                window.addEventListener('click', function (event) {
                    pickerArray.forEach(function (element) {
                        checkLanguagePickerClick(element, event.target);
                    });
                });
            }
        }());
    </script>
</x-login-layout>
