<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:en,sq']]);
        $request->session()->put('locale', $data['locale']);
        $request->user()?->update(['preferred_language' => $data['locale']]);

        return back();
    }
}
