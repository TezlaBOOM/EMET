<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\ConfigTransfer;
use App\Services\Config\ConfigTransferManager;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConfigTransferController extends Controller
{
    public function __construct(
        protected ConfigTransferManager $transferManager
    ) {}

    public function index(Request $request): View
    {
        $transfers = ConfigTransfer::latest()->take(20)->get();
        $sections = $this->transferManager->getOrderedSections();

        return view('module-system::transfers', [
            'transfers' => $transfers,
            'sections' => $sections,
            'activeSubcategory' => 'transfers',
        ]);
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'include_secrets' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $includeSecrets = ! empty($validated['include_secrets']);
        $password = $validated['password'] ?? null;

        if ($includeSecrets && empty($password)) {
            return back()->with('error', 'Wymagane jest podanie hasła przy eksporcie sekretów.');
        }

        try {
            $result = $this->transferManager->export([
                'include_secrets' => $includeSecrets,
            ], $password, $request->user()?->id);

            return response()->download($result['zip_path'], basename($result['zip_path']));
        } catch (Exception $e) {
            return back()->with('error', 'Błąd generowania eksportu: '.$e->getMessage());
        }
    }

    public function dryRun(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:zip'],
            'mode' => ['nullable', 'string', 'in:merge,overwrite,new_only'],
            'password' => ['nullable', 'string'],
        ]);

        $zipPath = $request->file('file')->getRealPath();
        $mode = $validated['mode'] ?? 'merge';
        $password = $validated['password'] ?? null;

        try {
            $planResult = $this->transferManager->planImport($zipPath, ['mode' => $mode], $password);

            if (! $planResult['valid']) {
                return back()->with('error', 'Błąd walidacji archiwum: '.implode(', ', $planResult['errors']));
            }

            return view('module-system::dry_run', [
                'manifest' => $planResult['manifest'],
                'diffReport' => $planResult['diff_report'],
                'mode' => $mode,
            ]);
        } catch (Exception $e) {
            return back()->with('error', 'Błąd przygotowania dry-run: '.$e->getMessage());
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:zip'],
            'mode' => ['nullable', 'string', 'in:merge,overwrite,new_only'],
            'password' => ['nullable', 'string'],
        ]);

        $uploaded = $request->file('file');
        $storedPath = $uploaded->storeAs('transfers', 'import_'.time().'.zip');
        $fullPath = storage_path('app/'.$storedPath);

        $mode = $validated['mode'] ?? 'merge';
        $password = $validated['password'] ?? null;

        try {
            $transfer = $this->transferManager->applyImport($fullPath, ['mode' => $mode], $password, $request->user()?->id);
            $importedCount = $transfer->stats['total_imported'] ?? 0;
            $updatedCount = $transfer->stats['total_updated'] ?? 0;

            return redirect()->route('system.transfers.index')->with(
                'success',
                "Pomyślnie zaimportowano konfigurację! Dodano: {$importedCount}, zaktualizowano: {$updatedCount} rekordów."
            );
        } catch (Exception $e) {
            return back()->with('error', 'Błąd importu: '.$e->getMessage());
        }
    }
}
