<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocaleController extends Controller
{
    /**
     * Update the user's locale.
     */
    public function update(Request $request)
    {
        $request->validate([
            'locale' => ['required', 'string', 'max:10'],
        ]);

        $user = $request->user();

        if ($user) {
            $locale = $request->input('locale');

            \Log::info('Updating user locale', [
                'user_id' => $user->id,
                'old_locale' => $user->locale,
                'new_locale' => $locale,
            ]);

            $user->update([
                'locale' => $locale,
            ]);

            \Log::info('Locale updated successfully', [
                'user_id' => $user->id,
                'current_locale' => $user->fresh()->locale,
            ]);
        }

        return back();
    }
}
