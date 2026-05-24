<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AboutController extends Controller
{
    public function index()
    {
        $dbConnected = false;
        $dbDriver = config('database.default');
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
        }

        return view('about', [
            'version' => config('app.version'),
            'appEnv' => config('app.env'),
            'debugMode' => config('app.debug'),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'dbDriver' => $dbDriver,
            'dbConnected' => $dbConnected,
            'locale' => app()->getLocale(),
            'timezone' => config('app.timezone'),
            'serverOs' => PHP_OS,
        ]);
    }
}
