<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
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
        protected SkillSandboxService $sandbox
    ) {}

    public function index(Request $request): View
    {
        $query = Skill::with(['currentVersion', 'versions']);

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

        return view('module-agents::skills.index', compact('skills'));
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
                'package' => ['required', 'file', 'mimes:zip', 'max:20480'],
            ]);

            $file = $request->file('package');
            $checksum = hash_file('sha256', $file->getRealPath());

            $storagePath = $file->storeAs('packages', Str::random(20).'.zip', 'skills');
            $destDir = storage_path('app/skills/unpacked/'.Str::random(16));

            $extracted = $this->extractor->extract($file->getRealPath(), $destDir);
            $manifest = $extracted['manifest'] ?? [];

            $name = $manifest['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $slug = Str::slug($manifest['slug'] ?? $name);

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

            return redirect()->route('agents.skills.show', $skill)
                ->with('success', 'Skill został pomyślnie zaimportowany z paczki ZIP.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:tool,mcp,prompt,workflow,package'],
            'description' => ['nullable', 'string'],
            'readme_md' => ['nullable', 'string'],
            'requires' => ['nullable', 'array'],
        ]);

        $skill = Skill::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
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

        return redirect()->route('agents.skills.show', $skill)
            ->with('success', 'Skill został pomyślnie utworzony.');
    }

    public function show(Skill $skill): View
    {
        $skill->load(['versions.creator', 'runs' => fn ($q) => $q->latest()->take(10)]);

        return view('module-agents::skills.show', compact('skill'));
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
