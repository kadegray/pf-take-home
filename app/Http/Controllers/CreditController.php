<?php

namespace App\Http\Controllers;

use App\Services\CreditLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    /** Preset purchase amounts, in micro-dollars: $5 / $10 / $25. */
    public const PRESET_AMOUNTS_MICROS = [5_000_000, 10_000_000, 25_000_000];

    public function __construct(private readonly CreditLedger $credits) {}

    public function create(): Response
    {
        return Inertia::render('Credits', [
            'presetAmounts' => self::PRESET_AMOUNTS_MICROS,
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount_micros' => ['required', 'integer', Rule::in(self::PRESET_AMOUNTS_MICROS)],
        ]);

        $this->credits->deposit($request->user(), $data['amount_micros']);

        return back();
    }
}
