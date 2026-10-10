<x-layouts.editor :scenario="$scenario">
    <div class="flex-1 flex overflow-hidden w-full h-full" x-data="scenarioEditor()">
        <!-- Lewy panel: Paleta węzłów (12 typów) -->
        <aside class="w-64 bg-slate-900 border-r border-slate-800 p-4 flex flex-col shrink-0 z-10 overflow-y-auto">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Węzły Scenariusza</h2>
            <div class="space-y-2">
                <template x-for="node in availableNodes" :key="node.type">
                    <div class="p-2.5 rounded-xl bg-slate-800/80 border border-slate-700/60 hover:border-indigo-500/80 hover:bg-slate-800 cursor-grab transition-all select-none flex items-center justify-between"
                         draggable="true"
                         @dragstart="drag($event, node.type)">
                        <div class="flex items-center gap-2.5">
                            <span class="w-2.5 h-2.5 rounded-full" :class="node.color"></span>
                            <span class="text-xs font-semibold text-slate-200" x-text="node.label"></span>
                        </div>
                        <span class="text-[10px] font-mono text-slate-500" x-text="node.type"></span>
                    </div>
                </template>
            </div>
        </aside>

        <!-- Środkowy obszar: Kanwa Drawflow -->
        <div class="flex-1 relative overflow-hidden h-full">
            <div id="drawflow" @drop="drop($event)" @dragover.prevent></div>

            <!-- Panel powiadomień telemetrii na żywo -->
            <div class="absolute bottom-4 left-4 z-10 bg-slate-900/90 backdrop-blur-md border border-slate-800 p-3 rounded-xl shadow-lg max-w-sm"
                 x-show="lastEvent">
                <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Telemetria na żywo</div>
                <div class="text-xs text-slate-300 font-mono mt-1" x-text="lastEvent"></div>
            </div>
        </div>

        <!-- Prawy panel: Parametry zaznaczonego węzła -->
        <aside class="w-80 bg-slate-900 border-l border-slate-800 p-4 shrink-0 z-10 overflow-y-auto" x-show="selectedNode">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Właściwości Węzła</h2>
            <template x-if="selectedNode">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">ID węzła</label>
                        <input type="text" readonly :value="selectedNode.id"
                               class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 font-mono text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Typ</label>
                        <input type="text" readonly :value="selectedNode.name"
                               class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 font-mono text-xs" />
                    </div>

                    <!-- Parametr dla agenta -->
                    <template x-if="selectedNode.name === 'agent'">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Wybór agenta</label>
                            <select x-model="selectedNode.data.agent_id" @change="updateNodeData()"
                                    class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-xs">
                                <option value="">Wybierz agenta...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->primary_model }})</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <!-- Parametr dla warunku condition -->
                    <template x-if="selectedNode.name === 'condition'">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Wyrażenie warunkowe (Symfony Expression)</label>
                            <textarea x-model="selectedNode.data.expression" @input="updateNodeData()" rows="3"
                                      placeholder="input.status == 'ok' and score >= 80"
                                      class="w-full px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 font-mono text-xs"></textarea>
                        </div>
                    </template>
                </div>
            </template>
        </aside>
    </div>

    <script>
        function scenarioEditor() {
            return {
                editor: null,
                selectedNode: null,
                lastEvent: null,
                availableNodes: [
                    { type: 'start', label: 'Start (Trigger)', color: 'bg-emerald-400' },
                    { type: 'agent', label: 'Agent AI', color: 'bg-indigo-400' },
                    { type: 'skill', label: 'Skill (Narzędzie)', color: 'bg-blue-400' },
                    { type: 'memory', label: 'Pamięć wektorowa', color: 'bg-purple-400' },
                    { type: 'condition', label: 'Warunek (If/Else)', color: 'bg-amber-400' },
                    { type: 'parallel', label: 'Równoległość (Fork)', color: 'bg-teal-400' },
                    { type: 'join', label: 'Scalenie (Join)', color: 'bg-cyan-400' },
                    { type: 'loop', label: 'Pętla (Loop)', color: 'bg-orange-400' },
                    { type: 'human', label: 'Akceptacja człowieka', color: 'bg-pink-400' },
                    { type: 'transform', label: 'Transformacja danych', color: 'bg-lime-400' },
                    { type: 'delay', label: 'Opóźnienie (Sleep)', color: 'bg-gray-400' },
                    { type: 'end', label: 'Koniec (End)', color: 'bg-rose-400' }
                ],
                init() {
                    const container = document.getElementById('drawflow');
                    this.editor = new Drawflow(container);
                    this.editor.reroute = true;
                    this.editor.start();

                    // Załadowanie istniejącego grafu
                    const existingGraph = @json($scenario->currentVersion?->graph ?? null);
                    if (existingGraph && existingGraph.drawflow) {
                        this.editor.import(existingGraph);
                    } else if (existingGraph && existingGraph.nodes) {
                        // Inicjalizacja domyślna
                        this.editor.addNode('start', 0, 1, 150, 200, 'start', { label: 'Start' }, '<div><b>Start</b></div>');
                        this.editor.addNode('end', 1, 0, 550, 200, 'end', { label: 'Koniec' }, '<div><b>Koniec</b></div>');
                    }

                    this.editor.on('nodeSelected', (id) => {
                        this.selectedNode = this.editor.getNodeFromId(id);
                    });

                    this.editor.on('nodeUnselected', () => {
                        this.selectedNode = null;
                    });

                    document.getElementById('btn-save-draft').addEventListener('click', () => this.saveDraft());
                    document.getElementById('btn-publish').addEventListener('click', () => this.publishVersion());
                    document.getElementById('btn-run-test').addEventListener('click', () => this.runTest());
                },
                drag(ev, type) {
                    ev.dataTransfer.setData("node", type);
                },
                drop(ev) {
                    ev.preventDefault();
                    const type = ev.dataTransfer.getData("node");
                    if (!type) return;

                    const pos_x = ev.clientX * (this.editor.precanvas.clientWidth / (this.editor.precanvas.clientWidth * this.editor.zoom)) - (this.editor.precanvas.getBoundingClientRect().x * (this.editor.precanvas.clientWidth / (this.editor.precanvas.clientWidth * this.editor.zoom)));
                    const pos_y = ev.clientY * (this.editor.precanvas.clientHeight / (this.editor.precanvas.clientHeight * this.editor.zoom)) - (this.editor.precanvas.getBoundingClientRect().y * (this.editor.precanvas.clientHeight / (this.editor.precanvas.clientHeight * this.editor.zoom)));

                    const inputs = type === 'start' ? 0 : 1;
                    const outputs = type === 'end' ? 0 : (type === 'condition' ? 2 : 1);

                    this.editor.addNode(type, inputs, outputs, pos_x, pos_y, type, { label: type, expression: '' }, `<div><b>${type.toUpperCase()}</b></div>`);
                },
                updateNodeData() {
                    if (this.selectedNode) {
                        this.editor.updateNodeDataFromId(this.selectedNode.id, this.selectedNode.data);
                    }
                },
                saveDraft() {
                    const exportData = this.editor.export();
                    fetch('{{ route('scenarios.save', $scenario) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ graph: exportData })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Szkic został pomyślnie zapisany.');
                        } else {
                            alert('Błąd zapisu: ' + (data.errors ? data.errors.join("\n") : 'Nieznany błąd'));
                        }
                    });
                },
                publishVersion() {
                    this.saveDraft();
                    fetch('{{ route('scenarios.publish', $scenario) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Błąd publikacji: ' + (data.errors ? data.errors.join("\n") : data.error));
                        }
                    });
                },
                runTest() {
                    fetch('{{ route('scenarios.run', $scenario) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ input: { test: true } })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.lastEvent = 'Uruchomiono run #' + data.run_id;
                            alert('Rozpoczęto uruchomienie testowe #' + data.run_id);
                        } else {
                            alert('Błąd startu: ' + data.error);
                        }
                    });
                }
            };
        }
    </script>
</x-layouts.editor>
