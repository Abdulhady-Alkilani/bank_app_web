<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeRedirect extends Controller
{
    /**
     * Send the authenticated user to the dashboard of their role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect($request->user()->homePath());
    }
}
