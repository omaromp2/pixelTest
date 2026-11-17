<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Events\GenerationRequested;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge;
use App\Services\PixelforgeCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    /**
     * Show the user's generation history.
     *
     * Pending generations are also refreshed from Pixelforge here, so the page the user watches reaches the right state
     * even when webhooks are late or can't reach us (e.g. local development). Each one is checked at most every
     * 10 seconds to stay well inside Pixelforge's rate limit.
     */
    public function index(Request $request, Pixelforge $pixelforge): Response
    {
        $request->user()->generations()->pending()->whereNotNull('pixelforge_id')->get()
            ->filter(fn (Generation $generation) => Cache::add("pixelforge:refresh:{$generation->id}", true, 10))
            ->each(fn (Generation $generation) => rescue(
                fn () => $generation->syncWith($pixelforge->generation($generation->pixelforge_id)),
            ));

        return Inertia::render('generations/Index', [
            // shortcut: newest 50 only, add pagination once users have more history than that
            'generations' => $request->user()->generations()->latest()->limit(50)->get(),
        ]);
    }

    /**
     * Show the generate form.
     */
    public function create(Request $request, Pixelforge $pixelforge): Response
    {
        return Inertia::render('generations/Create', [
            'pricing' => $pixelforge->pricing(),
            'markup' => PixelforgeCost::MARKUP,
            'sizes' => StoreGenerationRequest::SIZES,
            'qualities' => StoreGenerationRequest::QUALITIES,
            'balance' => $request->user()->balance,
        ]);
    }

    /**
     * Reserve the estimated price from the user's balance, then submit the generation to Pixelforge.
     */
    public function store(StoreGenerationRequest $request, Pixelforge $pixelforge): RedirectResponse
    {
        $estimatedCost = PixelforgeCost::withMarkup(PixelforgeCost::estimate(
            $pixelforge->pricing(),
            $request->validated('prompt'),
            $request->validated('size'),
            $request->validated('quality'),
            count($request->validated('reference_images', [])),
        ));

        $generation = DB::transaction(function () use ($request, $estimatedCost) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if ($user->balance < $estimatedCost) {
                throw ValidationException::withMessages(['prompt' => __('Not enough credits. Buy more credits to generate this image.')]);
            }

            $user->decrement('balance', $estimatedCost);

            return $user->generations()->create([
                ...$request->validated(),
                'reference_images' => $request->validated('reference_images', []),
                'status' => GenerationStatus::Queued,
                'estimated_cost' => $estimatedCost,
            ]);
        });

        GenerationRequested::dispatch($generation);

        return to_route('generations.index');
    }
}
