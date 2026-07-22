<?php

namespace App\Http\Controllers;

use App\Services\GlobalConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AboutController extends Controller
{
    public function __construct(private readonly GlobalConfigService $globalConfigService) {}

    public function index(Request $request)
    {
        $dbConnected = false;
        $dbDriver = config('database.default');
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
        }

        $current = [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'app_locale' => app()->getLocale(),
            'app_registration' => config('app.registration') ? 'true' : 'false',
            'app_email_verification' => config('app.email_verification') ? 'true' : 'false',
        ];
        $options = [
            'app_env' => ['local' => __('local'), 'production' => __('production')],
            'app_debug' => ['true' => __('Enabled'), 'false' => __('Disabled')],
            'app_locale' => ['fa' => __('Persian'), 'en' => __('English')],
            'app_registration' => ['true' => __('Enabled'), 'false' => __('Disabled')],
            'app_email_verification' => ['true' => __('Enabled'), 'false' => __('Disabled')],
        ];
        $gcSettings = [];
        foreach ($this->settingTitles() as $key => $title) {
            $gcSettings[$key] = [
                'title' => $title,
                'current' => $current[$key],
                'options' => $options[$key],
            ];
        }

        $isManagementSettings = $request->routeIs('management.settings');

        if ($isManagementSettings) {
            $request->session()->put('interface_mode', 'management');
        }

        $view = $isManagementSettings ? 'super-admin.settings' : 'about';

        return view($view, [
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
            'gcSettings' => $gcSettings,
        ]);
    }

    public function updateGlobalConfigs(Request $request)
    {
        $validated = $request->validate([
            'app_env' => ['nullable', Rule::in($this->globalConfigService::SETTINGS['app_env'])],
            'app_locale' => ['nullable', Rule::in($this->globalConfigService::SETTINGS['app_locale'])],
            'app_debug' => ['nullable', Rule::in($this->globalConfigService::SETTINGS['app_debug'])],
            'app_registration' => ['nullable', Rule::in($this->globalConfigService::SETTINGS['app_registration'])],
            'app_email_verification' => ['nullable', Rule::in($this->globalConfigService::SETTINGS['app_email_verification'])],
        ]);

        $this->globalConfigService->update($validated);

        $updated = implode(', ', array_intersect_key($this->settingTitles(), $validated));

        return redirect()->route('management.settings')->with('success', __(':setting updated successfully.', ['setting' => $updated]));
    }

    private function settingTitles(): array
    {
        return [
            'app_env' => __('about.environment'),
            'app_debug' => __('about.debug_mode'),
            'app_locale' => __('about.locale'),
            'app_registration' => __('about.registration'),
            'app_email_verification' => __('about.email_verification'),
        ];
    }
}
