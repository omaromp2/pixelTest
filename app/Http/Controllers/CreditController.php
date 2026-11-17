<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditController extends Controller
{
    /** $10.00 in nanodollars. */
    private const PACK_SIZE = 10_000_000_000;

    /**
     * Fake purchase: add a credit pack to the user's balance.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->user()->increment('balance', self::PACK_SIZE);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added $10.00 in credits.')]);

        return back();
    }
}
