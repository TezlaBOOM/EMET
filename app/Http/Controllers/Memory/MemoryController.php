<?php

declare(strict_types=1);

namespace App\Http\Controllers\Memory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LlmProvider;
use App\Models\MemoryChunk;
use App\Models\MemoryCollection;
use App\Services\MemoryService\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemoryController extends Controller
{
    public function __construct(
        protected EmbeddingService $embeddingService
    ) {}

    public function index(Request $request): View
    {
        $collections = MemoryCollection::all();
        $selectedCollectionId = $request->query('collection_id') ?: $collections->first()?->id;

        $activeCollection = $selectedCollectionId ? MemoryCollection::find($selectedCollectionId) : null;
        $chunks = $activeCollection
            ? $activeCollection->chunks()->latest()->paginate(15)
            : collect();

        return view('module-memory::index', compact('collections', 'activeCollection', 'chunks'));
    }

    public function graph(Request $request): View
    {
        $collections = MemoryCollection::all();
        $activeCollection = $request->has('collection_id')
            ? MemoryCollection::find($request->query('collection_id'))
            : $collections->first();

        return view('module-memory::graph', compact('collections', 'activeCollection'));
    }

    public function graphData(MemoryCollection $collection): JsonResponse
    {
        $data = $this->embeddingService->getGraphData($collection);

        return response()->json($data);
    }

    public function search(Request $request): View
    {
        $collections = MemoryCollection::all();
        $selectedCollectionId = $request->query('collection_id') ?: $collections->first()?->id;
        $activeCollection = $selectedCollectionId ? MemoryCollection::find($selectedCollectionId) : null;

        $query = $request->query('q');
        $results = [];

        if ($activeCollection && ! empty($query)) {
            $results = $this->embeddingService->search($activeCollection, $query, 10);
        }

        return view('module-memory::search', compact('collections', 'activeCollection', 'query', 'results'));
    }

    public function collections(): View
    {
        $collections = MemoryCollection::withCount('chunks')->with('embeddingProvider')->get();
        $providers = LlmProvider::where('is_active', true)->get();

        return view('module-memory::collections', compact('collections', 'providers'));
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:memory_collections,slug'],
            'vector_store' => ['required', 'string', 'in:qdrant,pgvector'],
            'embedding_provider_id' => ['required', 'exists:llm_providers,id'],
            'embedding_model' => ['required', 'string', 'max:100'],
            'dimensions' => ['required', 'integer', 'min:64', 'max:4096'],
            'distance_metric' => ['required', 'string', 'in:cosine,euclidean,dot'],
        ]);

        $validated['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $collection = MemoryCollection::create($validated);

        // Utworzenie w silniku wektorowym
        $store = $this->embeddingService->getStore($collection);
        $store->createCollection($collection);

        AuditLog::record('memory_collection.created', 'MemoryCollection', (string) $collection->id, [
            'name' => $collection->name,
            'vector_store' => $collection->vector_store,
        ]);

        return back()->with('success', __('memory.collection_created'));
    }

    public function storeDocument(Request $request, MemoryCollection $collection): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $this->embeddingService->ingestDocument(
            collection: $collection,
            title: $validated['title'],
            content: $validated['content'],
            metadata: ['author_id' => auth()->id()]
        );

        AuditLog::record('memory_document.ingested', 'MemoryCollection', (string) $collection->id, [
            'title' => $validated['title'],
            'collection_name' => $collection->name,
        ]);

        return back()->with('success', __('memory.ingested_success'));
    }

    public function destroyChunk(MemoryChunk $chunk): RedirectResponse
    {
        $collection = $chunk->collection;
        $pointId = $chunk->vector_point_id;

        if ($collection && $pointId) {
            $store = $this->embeddingService->getStore($collection);
            $store->deletePoint($collection, $pointId);
        }

        $chunk->delete();

        return back()->with('success', __('memory.chunk_deleted'));
    }

    public function destroyCollection(MemoryCollection $collection): RedirectResponse
    {
        $store = $this->embeddingService->getStore($collection);
        $store->deleteCollection($collection);

        $name = $collection->name;
        $id = (string) $collection->id;
        $collection->delete();

        AuditLog::record('memory_collection.deleted', 'MemoryCollection', $id, ['name' => $name]);

        return back()->with('success', __('memory.collection_deleted'));
    }
}
