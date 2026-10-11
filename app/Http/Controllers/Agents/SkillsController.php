<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Contracts\Skills\SkillLifecycleInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Skill;
use App\Models\SkillVersion;
use App\Services\Skills\SkillPackageExtractor;
use App\Services\Skills\SkillSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SkillsController extends Controller
{
    public function __construct(
        protected SkillPackageExtractor $extractor,
        protected SkillSandboxService $sandbox,
        protected SkillLifecycleInterface $lifecycle
    ) {}

    public function index(Request $request): View
    {
        $isTrash = $request->boolean('trash');
        $query = $isTrash
            ? Skill::onlyTrashed()->with(['currentVersion', 'versions'])
            : Skill::with(['currentVersion', 'versions']);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('slug', 'like', $search);
            });
        }

        $skills = $query->latest()->paginate(15);
        $trashedCount = Skill::onlyTrashed()->count();

        return view('module-agents::skills.index', compact('skills', 'isTrash', 'trashedCount'));
    }

    public function create(): View
    {
        return view('module-agents::skills.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $importSource = $request->input('source_type', 'manual');

        if ($importSource === 'zip') {
            $request->validate([
                'package' => [
                    'required',
                    'file',
                    'max:20480',
                    function ($attribute, $value, $fail) {
                        $ext = strtolower($value->getClientOriginalExtension());
                        $mime = (string) $value->getMimeType();
                        $allowedMimes = [
                            'application/zip',
                            'application/x-zip',
                            'application/x-zip-compressed',
                            'multipart/x-zip',
                            'application/octet-stream',
                        ];
                        if ($ext !== 'zip' && ! in_array($mime, $allowedMimes, true)) {
                            $fail('Przesłany plik musi być poprawnym archiwum ZIP (.zip).');
                        }
                    },
                ],
            ], [
                'package.required' => 'Wybierz plik paczki ZIP.',
                'package.file' => 'Wskazany obiekt nie jest prawidłowym plikiem.',
                'package.max' => 'Rozmiar pliku archiwum ZIP nie może przekraczać 20MB.',
            ]);

            $file = $request->file('package');
            $checksum = hash_file('sha256', $file->getRealPath());

            $storagePath = $file->storeAs('packages', Str::random(20).'.zip', 'skills');
            $destDir = storage_path('app/skills/unpacked/'.Str::random(16));

            try {
                $extracted = $this->extractor->extract($file->getRealPath(), $destDir);
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors([
                    'package' => 'Nie udało się przetworzyć lub rozpakować paczki ZIP: '.$e->getMessage(),
                ]);
            }

            $manifest = $extracted['manifest'] ?? [];

            $name = $manifest['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $baseSlug = Str::slug($manifest['slug'] ?? $name) ?: 'skill';
            $slug = $baseSlug;
            $counter = 1;
            while (Skill::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-".(++$counter);
            }

            $skill = Skill::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $manifest['description'] ?? null,
                'readme_md' => $manifest['readme'] ?? null,
                'type' => $manifest['type'] ?? 'package',
                'status' => 'active',
                'author' => $manifest['author'] ?? Auth::user()?->name,
                'tags' => $manifest['tags'] ?? [],
                'requires' => $manifest['requires'] ?? [],
            ]);

            $version = SkillVersion::create([
                'skill_id' => $skill->id,
                'version' => $manifest['version'] ?? '1.0.0',
                'definition' => $manifest,
                'package_path' => $storagePath,
                'checksum' => $checksum,
                'changelog' => 'Pierwszy import paczki ZIP',
                'created_by' => Auth::id(),
            ]);

            $skill->update(['current_version_id' => $version->id]);

            AuditLog::record('skill.create', Skill::class, (string) $skill->id, ['source' => 'zip']);

            return redirect()->route('agents.skills.show', $skill)
                ->with('success', 'Skill został pomyślnie zaimportowany z paczki ZIP.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:tool,mcp,prompt,workflow,package'],
            'description' => ['nullable', 'string'],
            'readme_md' => ['nullable', 'string'],
            'requires' => ['nullable', 'array'],
        ], [
            'name.required' => 'Nazwa skilla jest wymagana.',
            'name.max' => 'Nazwa skilla może mieć maksymalnie 150 znaków.',
            'type.required' => 'Typ skilla jest wymagany.',
            'type.in' => 'Wybrano nieprawidłowy typ skilla.',
        ]);

        $baseSlug = Str::slug($validated['name']) ?: 'skill';
        $slug = $baseSlug;
        $counter = 1;
        while (Skill::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-".(++$counter);
        }

        $skill = Skill::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'readme_md' => $validated['readme_md'] ?? null,
            'type' => $validated['type'],
            'status' => 'active',
            'author' => Auth::user()?->name,
            'requires' => $validated['requires'] ?? [],
        ]);

        $version = SkillVersion::create([
            'skill_id' => $skill->id,
            'version' => '1.0.0',
            'definition' => ['entry' => 'default'],
            'changelog' => 'Wersja początkowa',
            'created_by' => Auth::id(),
        ]);

        $skill->update(['current_version_id' => $version->id]);

        AuditLog::record('skill.create', Skill::class, (string) $skill->id, ['source' => 'manual']);

        return redirect()->route('agents.skills.show', $skill)
            ->with('success', 'Skill został pomyślnie utworzony.');
    }

    public function show(Skill $skill): View
    {
        $skill->load(['versions.creator', 'runs' => fn ($q) => $q->latest()->take(10)]);
        $isUnused = $this->lifecycle->isUnused($skill);
        $dependencies = $this->lifecycle->checkDependencies($skill);

        return view('module-agents::skills.show', compact('skill', 'isUnused', 'dependencies'));
    }

    public function edit(Skill $skill): View
    {
        $skill->load('currentVersion');

        return view('module-agents::skills.edit', compact('skill'));
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'readme_md' => ['nullable', 'string'],
            'type' => ['required', 'in:tool,mcp,prompt,workflow,package'],
            'status' => ['required', 'in:draft,pending_review,active,deprecated'],
            'tags' => ['nullable', 'array'],
            'definition' => ['nullable', 'string'],
            'changelog' => ['nullable', 'string'],
            'bump_type' => ['nullable', 'in:patch,minor,major'],
        ]);

        $skill->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'readme_md' => $validated['readme_md'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'tags' => $validated['tags'] ?? $skill->tags,
        ]);

        // Jeśli podano nową definicję, stwórz nową wersję SemVer
        if (! empty($validated['definition'])) {
            $parsedDefinition = json_decode($validated['definition'], true) ?? ['content' => $validated['definition']];
            $changelog = $validated['changelog'] ?? 'Aktualizacja definicji skilla';
            $bumpType = $validated['bump_type'] ?? 'patch';

            $this->lifecycle->updateDefinitionWithNewVersion($skill, $parsedDefinition, $changelog, $bumpType);
        }

        AuditLog::record('skill.update', Skill::class, (string) $skill->id, ['name' => $skill->name]);

        return redirect()->route('agents.skills.show', $skill)
            ->with('success', 'Konfiguracja skilla została pomyślnie zaktualizowana.');
    }

    public function destroy(Request $request, Skill $skill): RedirectResponse|JsonResponse
    {
        $deps = $this->lifecycle->checkDependencies($skill);
        $hasDeps = ! empty($deps['agents']) || ! empty($deps['scenarios']);

        if ($hasDeps && ! $request->boolean('force_detach')) {
            $agentNames = collect($deps['agents'])->pluck('name')->all();
            $scenarioNames = collect($deps['scenarios'])->pluck('name')->all();
            $msg = 'Nie można usunąć skilla, który jest używany przez: '
                .implode(', ', array_merge($agentNames, $scenarioNames));

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => $msg,
                    'dependencies' => $deps,
                ], 409);
            }

            return back()->with('error', $msg)->setStatusCode(409);
        }

        $this->lifecycle->safeDelete($skill, $request->boolean('force_detach'));

        AuditLog::record('skill.delete', Skill::class, (string) $skill->id, ['name' => $skill->name]);

        return redirect()->route('agents.skills.index')->with('success', 'Skill został przeniesiony do kosza.');
    }

    public function restore(int $id): RedirectResponse
    {
        $skill = $this->lifecycle->restoreFromTrash($id);
        AuditLog::record('skill.restore', Skill::class, (string) $skill->id, ['name' => $skill->name]);

        return redirect()->route('agents.skills.index')->with('success', 'Skill został pomyślnie przywrócony z kosza.');
    }

    public function purge(Request $request, int $id): RedirectResponse
    {
        if (! Auth::user()?->can('skills.purge') && ! Auth::user()?->hasRole('admin')) {
            abort(403, 'Brak uprawnień do trwałego usuwania skilli.');
        }

        $this->lifecycle->purgePermanently($id);
        AuditLog::record('skill.purge', Skill::class, (string) $id);

        return redirect()->route('agents.skills.index', ['trash' => 1])
            ->with('success', 'Skill został trwale usunięty wraz z plikami.');
    }

    public function testSandbox(Request $request, Skill $skill): JsonResponse
    {
        $input = $request->input('input', []);
        $run = $this->sandbox->execute($skill, $input);

        return response()->json([
            'success' => $run->status === 'success',
            'status' => $run->status,
            'duration_ms' => $run->duration_ms,
            'error' => $run->error,
        ]);
    }
}
