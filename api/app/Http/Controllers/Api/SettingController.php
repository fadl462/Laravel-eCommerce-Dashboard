<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Backs the Settings > Localization tab specifically: default language,
 * direction, currency, and date/time format are all stored here rather than
 * hard-coded, so a store owner can flip the default to Arabic without a
 * deploy. Individual admin users can still override the language client-side
 * (handled entirely in the dashboard's language toggle) — this is the
 * store-wide default only.
 */
class SettingController extends Controller
{
    public function index(Request $request)
    {
        $group = $request->query('group');
        $query = \App\Models\Setting::query();

        if ($group) {
            $query->where('group', $group);
        }

        return response()->json($query->get()->pluck('value', 'key'));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required'],
            'settings.*.group' => ['nullable', 'string'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value'], $item['group'] ?? 'general');
        }

        app(ActivityLogger::class)->log($request->user(), 'Settings modified', 'Settings', null, null, $data['settings']);

        return response()->json(['message' => 'Settings saved.']);
    }
}
