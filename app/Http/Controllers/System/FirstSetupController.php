<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\SetupProgress;
use App\Services\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FirstSetupController extends Controller
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    public function index(Request $request): View
    {
        $steps = SetupProgress::orderBy('id')->get();
        $totalSteps = $steps->count();
        $completedSteps = $steps->where('is_completed', true)->count();
        $percentage = $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0;

        return view('module-system::first_setup', [
            'steps' => $steps,
            'totalSteps' => $totalSteps,
            'completedSteps' => $completedSteps,
            'percentage' => $percentage,
            'activeSubcategory' => 'first_setup',
        ]);
    }

    public function toggleStep(Request $request, string $step): RedirectResponse
    {
        $setup = SetupProgress::where('step_key', $step)->first();
        if ($setup) {
            $setup->is_completed = ! $setup->is_completed;
            $setup->completed_at = $setup->is_completed ? now() : null;
            $setup->completed_by = $setup->is_completed ? $request->user()?->id : null;
            $setup->save();
        } else {
            SetupProgress::create([
                'step_key' => $step,
                'title' => ucfirst(str_replace('_', ' ', $step)),
                'module' => 'system',
                'is_completed' => true,
                'completed_at' => now(),
                'completed_by' => $request->user()?->id,
            ]);
        }

        return back()->with('success', 'Zaktualizowano status kroku konfiguracji.');
    }
}
