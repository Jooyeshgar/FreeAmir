<?php

namespace App\View\Components;

use Illuminate\View\Component;

class FormInput extends Component
{
    public $name;
    public $value;
    public $title;
    public $placeHolder;
    public $message;
    public $type;

    public function __construct($name, $title, $placeHolder, $message = null, $type = 'text', $value = '')
    {
        $this->name = $name;
        $this->title = $title;
        $this->placeHolder = $placeHolder;
        $this->message = $message;
        $this->type = $type;
        $this->value = $value;
    }

    public function render()
    {
        return view('components.form-input');
    }
}
