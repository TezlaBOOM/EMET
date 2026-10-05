<x-layouts.app active-module="memory" active-subcategory="graph" :title="__('memory.sub_graph')">
    <div class="h-[calc(100vh-140px)] flex flex-col space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('memory.sub_graph') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Interaktywna mapa relacji semantycznych i połączeń między dokumentami (styl Obsidian).
                </p>
            </div>

            @if($collections->isNotEmpty())
                <form method="GET" action="{{ route('memory.graph') }}" class="flex items-center gap-2">
                    <select name="collection_id" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                        @foreach($collections as $col)
                            <option value="{{ $col->id }}" @selected($activeCollection?->id === $col->id)>
                                {{ $col->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        <!-- Canvas Container -->
        <div class="flex-1 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden relative shadow-xs">
            @if($activeCollection)
                <canvas id="graphCanvas" class="w-full h-full cursor-grab active:cursor-grabbing block"></canvas>

                <div class="absolute bottom-4 left-4 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 inline-block"></span> Kolekcja wiedzy
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> Fragmenty dokumentów (Chunks)
                    </div>
                    <span class="text-[10px] text-slate-400 block pt-1">Przeciągaj węzły myszką.</span>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const canvas = document.getElementById('graphCanvas');
                        const ctx = canvas.getContext('2d');
                        let width = canvas.width = canvas.offsetWidth;
                        let height = canvas.height = canvas.offsetHeight;

                        window.addEventListener('resize', () => {
                            width = canvas.width = canvas.offsetWidth;
                            height = canvas.height = canvas.offsetHeight;
                        });

                        fetch('{{ route('memory.graph.data', $activeCollection) }}')
                            .then(res => res.json())
                            .then(data => {
                                initGraph(data.nodes, data.links);
                            });

                        function initGraph(nodes, links) {
                            // Inicjalizacja pozycji wokół środka
                            const centerX = width / 2;
                            const centerY = height / 2;

                            nodes.forEach((node, idx) => {
                                const angle = (idx / nodes.length) * 2 * Math.PI;
                                const dist = node.group === 'collection' ? 0 : (120 + Math.random() * 80);
                                node.x = centerX + Math.cos(angle) * dist;
                                node.y = centerY + Math.sin(angle) * dist;
                                node.vx = (Math.random() - 0.5) * 0.5;
                                node.vy = (Math.random() - 0.5) * 0.5;
                            });

                            let draggedNode = null;

                            canvas.addEventListener('mousedown', (e) => {
                                const rect = canvas.getBoundingClientRect();
                                const mouseX = e.clientX - rect.left;
                                const mouseY = e.clientY - rect.top;

                                for (const n of nodes) {
                                    const dist = Math.hypot(n.x - mouseX, n.y - mouseY);
                                    if (dist < 15) {
                                        draggedNode = n;
                                        break;
                                    }
                                }
                            });

                            window.addEventListener('mousemove', (e) => {
                                if (draggedNode) {
                                    const rect = canvas.getBoundingClientRect();
                                    draggedNode.x = e.clientX - rect.left;
                                    draggedNode.y = e.clientY - rect.top;
                                    draggedNode.vx = 0;
                                    draggedNode.vy = 0;
                                }
                            });

                            window.addEventListener('mouseup', () => {
                                draggedNode = null;
                            });

                            function step() {
                                // Fizyka odpychania i sprężyn
                                for (let i = 0; i < nodes.length; i++) {
                                    for (let j = i + 1; j < nodes.length; j++) {
                                        const n1 = nodes[i];
                                        const n2 = nodes[j];
                                        const dx = n2.x - n1.x;
                                        const dy = n2.y - n1.y;
                                        const dist = Math.hypot(dx, dy) || 1;
                                        if (dist < 200) {
                                            const force = (200 - dist) / 200 * 0.05;
                                            n1.vx -= (dx / dist) * force;
                                            n1.vy -= (dy / dist) * force;
                                            n2.vx += (dx / dist) * force;
                                            n2.vy += (dy / dist) * force;
                                        }
                                    }
                                }

                                // Siła przyciągania do środka
                                nodes.forEach(n => {
                                    if (n === draggedNode) return;
                                    n.vx += (centerX - n.x) * 0.001;
                                    n.vy += (centerY - n.y) * 0.001;
                                    n.x += n.vx;
                                    n.y += n.vy;
                                    n.vx *= 0.92; // tarcie
                                    n.vy *= 0.92;
                                });

                                // Rysowanie
                                ctx.clearRect(0, 0, width, height);

                                // Krawędzie
                                ctx.strokeStyle = document.documentElement.classList.contains('dark') ? 'rgba(79, 70, 229, 0.2)' : 'rgba(99, 102, 241, 0.25)';
                                ctx.lineWidth = 1;
                                links.forEach(link => {
                                    const source = nodes.find(n => n.id === link.source);
                                    const target = nodes.find(n => n.id === link.target);
                                    if (source && target) {
                                        ctx.beginPath();
                                        ctx.moveTo(source.x, source.y);
                                        ctx.lineTo(target.x, target.y);
                                        ctx.stroke();
                                    }
                                });

                                // Węzły
                                nodes.forEach(node => {
                                    const isCenter = node.group === 'collection';
                                    const radius = isCenter ? 14 : 7;

                                    ctx.beginPath();
                                    ctx.arc(node.x, node.y, radius, 0, 2 * Math.PI);
                                    ctx.fillStyle = isCenter ? '#6366f1' : '#10b981';
                                    ctx.fill();

                                    ctx.strokeStyle = '#ffffff';
                                    ctx.lineWidth = 2;
                                    ctx.stroke();

                                    // Etykieta
                                    ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#334155';
                                    ctx.font = isCenter ? 'bold 11px sans-serif' : '10px sans-serif';
                                    ctx.fillText(node.name, node.x + radius + 4, node.y + 3);
                                });

                                requestAnimationFrame(step);
                            }

                            requestAnimationFrame(step);
                        }
                    });
                </script>
            @else
                <div class="h-full flex items-center justify-center text-center p-8 text-slate-400 text-sm">
                    Brak aktywnej kolekcji do wyrenderowania grafu.
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
