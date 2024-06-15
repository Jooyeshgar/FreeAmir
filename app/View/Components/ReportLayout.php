<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ReportLayout extends Component
{
    public $title;
    public $header;
    public $footer;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($title = null)
    {
        $this->title = $title;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.report-layout');
    }
}
