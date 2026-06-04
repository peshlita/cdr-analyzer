@extends('layouts.app')

@section('title', 'Red de Llamadas')
@section('page-title', 'Red de Llamadas')
@section('page-subtitle', 'Grafo interactivo de comunicaciones de voz')

@push('styles')
<style>
#cy { width: 100%; height: 100%; }
.node-panel { min-width: 260px; }
.num-item:hover { background-color: rgba(51,65,85,0.5); }
</style>
@endpush

@section('content')
<div class="flex gap-3 h-full" style="height: calc(100vh - 120px);" x-data="networkApp()">

    <!-- ── Left sidebar ──────────────────────────────────────────────── -->
    <div class="w-64 shrink-0 rounded-xl border border-slate-700 flex flex-col overflow-hidden" style="background-color:#1e293b;">

        <!-- Date filters -->
        <div class="px-4 pt-4 pb-3 space-y-3 border-b border-slate-700 shrink-0">
            <h3 class="text-white font-semibold text-sm">Filtros</h3>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Desde</label>
                <input type="date" x-model="filters.date_from" @change="loadGraph()"
                       class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                       style="background-color:#0f172a;">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Hasta</label>
                <input type="date" x-model="filters.date_to" @change="loadGraph()"
                       class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                       style="background-color:#0f172a;">
            </div>
        </div>

        <!-- Layout & color buttons -->
        <div class="px-4 py-3 space-y-2 border-b border-slate-700 shrink-0">

            <!-- Color by frequency toggle -->
            <button @click="toggleColorByFreq()"
                    :class="colorByFreq
                        ? 'border-violet-500 text-violet-300 bg-violet-900/20'
                        : 'border-slate-600 text-slate-300 hover:border-slate-500'"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-all"
                    style="">
                <span class="text-base leading-none">🌡️</span>
                <span x-text="colorByFreq ? 'Mapa de calor activo' : 'Colorear por frecuencia'"></span>
                <span x-show="colorByFreq" class="ml-auto w-2 h-2 rounded-full bg-violet-400 shrink-0"></span>
            </button>

            <div class="flex gap-2">
                <button @click="applyDirectionalLayout()"
                        class="flex-1 text-xs text-white py-1.5 rounded-lg transition-colors hover:opacity-90"
                        style="background-color:#3b82f6;">
                    <i class="fas fa-redo mr-1"></i> Reorganizar
                </button>
                <button @click="fitGraph()"
                        class="flex-1 text-xs text-slate-300 py-1.5 rounded-lg border border-slate-600 hover:bg-slate-700 transition-colors">
                    <i class="fas fa-compress-arrows-alt mr-1"></i> Ajustar
                </button>
            </div>

            <!-- Save snapshot for report -->
            <button @click="saveSnapshot()"
                    :disabled="snapshotSaving"
                    :class="snapshotSaved
                        ? 'border-green-600 text-green-300 bg-green-900/20'
                        : 'border-slate-600 text-slate-300 hover:border-slate-500'"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-all disabled:opacity-50">
                <template x-if="snapshotSaving">
                    <span><i class="fas fa-spinner fa-spin mr-1"></i> Guardando...</span>
                </template>
                <template x-if="snapshotSaved && !snapshotSaving">
                    <span><i class="fas fa-check mr-1"></i> Guardado para reporte</span>
                </template>
                <template x-if="!snapshotSaving && !snapshotSaved">
                    <span><i class="fas fa-camera mr-1"></i> Guardar para reporte</span>
                </template>
            </button>
        </div>

        <!-- Number filter — header + search -->
        <div class="px-4 pt-3 pb-2 space-y-2 shrink-0 border-b border-slate-700/50">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-300 uppercase tracking-wide">
                    Números
                    <span class="text-slate-500 font-normal normal-case ml-1"
                          x-text="'(' + selectedCount + '/' + numberList.length + ')'"></span>
                </span>
                <div class="flex gap-1">
                    <button @click="selectAll()"
                            class="text-xs px-1.5 py-0.5 rounded text-blue-400 hover:bg-blue-900/30 transition-colors">
                        Todos
                    </button>
                    <span class="text-slate-600 text-xs">|</span>
                    <button @click="selectNone()"
                            class="text-xs px-1.5 py-0.5 rounded text-slate-400 hover:bg-slate-700 transition-colors">
                        Ninguno
                    </button>
                </div>
            </div>

            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
                <input type="text"
                       x-model="numSearch"
                       placeholder="Buscar número o nombre..."
                       class="w-full text-xs rounded-lg pl-7 pr-3 py-1.5 text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                       style="background-color:#0f172a;">
            </div>
        </div>

        <!-- Scrollable number list -->
        <div class="flex-1 overflow-y-auto py-1 px-2">
            <p x-show="numberList.length === 0"
               class="text-xs text-slate-500 text-center py-6">
               Carga el grafo primero
            </p>

            <template x-for="item in filteredNums" :key="item.phone">
                <label class="num-item flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer transition-colors select-none"
                       :class="item.isTarget  ? 'bg-amber-900/10'  :
                               item.crossRef  ? 'bg-purple-900/10' : ''">
                    <input type="checkbox"
                           :checked="isSelected(item.phone)"
                           @change="toggleNum(item.phone)"
                           class="rounded shrink-0"
                           style="accent-color:#3b82f6; background-color:#0f172a;">

                    {{-- Icono de objetivo o cruce (solo para esos dos casos) --}}
                    <template x-if="item.isTarget">
                        <span title="Número objetivo" class="shrink-0 text-amber-400" style="font-size:11px;">
                            <i class="fas fa-crosshairs"></i>
                        </span>
                    </template>
                    <template x-if="!item.isTarget && item.crossRef">
                        <span title="Cruce entre sábanas" class="shrink-0 text-purple-400" style="font-size:11px;">
                            <i class="fas fa-link"></i>
                        </span>
                    </template>

                    <div class="flex-1 min-w-0">
                        <p class="text-xs truncate font-mono leading-tight"
                           :class="item.isTarget ? 'text-amber-300 font-semibold' :
                                   item.crossRef ? 'text-purple-300'              : 'text-slate-200'"
                           x-text="item.label !== item.phone ? item.label : item.phone"></p>
                        <p x-show="item.label !== item.phone"
                           class="text-xs text-slate-500 font-mono truncate leading-tight"
                           x-text="item.phone"></p>
                    </div>

                    <span class="text-xs font-bold shrink-0 px-1.5 py-0.5 rounded min-w-6.5 text-center"
                          :class="item.calls >= highFreqThreshold
                              ? 'text-red-300 bg-red-900/40'
                              : item.calls >= medFreqThreshold
                                  ? 'text-yellow-300 bg-yellow-900/30'
                                  : 'text-slate-400 bg-slate-700/40'"
                          x-text="item.calls"></span>
                </label>
            </template>

            <p x-show="filteredNums.length === 0 && numSearch.length > 0"
               class="text-xs text-slate-500 text-center py-4">
                Sin resultados
            </p>
        </div>

        <!-- Legend -->
        <div class="px-4 py-3 border-t border-slate-700 space-y-1.5 text-xs text-slate-400 shrink-0">
            <p class="font-medium text-slate-300 mb-1.5">Leyenda</p>
            <div class="flex items-center gap-2">
                <span class="text-green-400 font-mono text-sm">⟶</span> Saliente
            </div>
            <div class="flex items-center gap-2">
                <span class="text-red-400 font-mono text-sm">⟵</span> Entrante
            </div>
            <div class="flex items-center gap-2">
                <span class="text-green-400 font-mono text-sm">⟶</span><span class="text-red-400 font-mono text-sm">⟵</span> Bidireccional
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full inline-block shrink-0" style="background:#f59e0b;"></span>
                <i class="fas fa-crosshairs text-amber-400" style="font-size:10px;"></i>
                Objetivo
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full inline-block shrink-0" style="background:#a855f7; outline:2px dashed #c084fc; outline-offset:2px;"></span>
                <i class="fas fa-link text-purple-400" style="font-size:10px;"></i>
                Cruce entre sábanas
            </div>
        </div>
    </div>

    <!-- ── Cytoscape graph ────────────────────────────────────────────── -->
    <div class="flex-1 rounded-xl border border-slate-700 relative overflow-hidden" style="background-color:#0f172a;">

        <!-- Loading overlay -->
        <div x-show="loading" class="absolute inset-0 flex items-center justify-center z-10" style="background-color:rgba(15,23,42,0.85);">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin text-blue-400 text-3xl mb-2 block"></i>
                <p class="text-slate-400 text-sm">Construyendo grafo...</p>
            </div>
        </div>

        <!-- Heatmap scale (shown when active) -->
        <div x-show="colorByFreq && !loading" x-cloak
             class="absolute bottom-4 left-4 z-10 rounded-lg px-3 py-2 text-xs"
             style="background-color:#1e293b; border:1px solid #334155;">
            <p class="text-slate-400 font-medium mb-1.5">Mapa de calor — frecuencia</p>
            <div class="w-32 h-3 rounded mb-1"
                 style="background: linear-gradient(to right, #3b82f6, #10b981, #f59e0b, #ef4444);"></div>
            <div class="flex justify-between text-slate-500" style="width:128px;">
                <span>Baja</span><span>Alta</span>
            </div>
        </div>

        <!-- Zone labels (visible when directional layout is active) -->
        <div x-show="directionalLayout" x-cloak
             class="absolute inset-0 pointer-events-none z-10 flex" style="padding:12px;">
            <div class="flex-1 flex flex-col items-start justify-start">
                <span class="text-xs font-semibold px-2 py-1 rounded"
                      style="background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);">
                    ⟶ Salientes
                </span>
                <span class="text-xs text-slate-500 mt-1 ml-1">objetivo llamó</span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-start">
                <span class="text-xs font-semibold px-2 py-1 rounded"
                      style="background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);">
                    ⟶ ⟵ Bidireccionales
                </span>
            </div>
            <div class="flex-1 flex flex-col items-end justify-start">
                <span class="text-xs font-semibold px-2 py-1 rounded"
                      style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);">
                    ⟵ Entrantes
                </span>
                <span class="text-xs text-slate-500 mt-1 mr-1">le llamaron</span>
            </div>
        </div>

        <div id="cy" class="absolute inset-0"></div>
    </div>

    <!-- ── Node detail panel ─────────────────────────────────────────── -->
    <div x-show="selectedNode" x-cloak
         class="node-panel rounded-xl border border-slate-700 p-4 flex flex-col gap-3 overflow-y-auto shrink-0"
         style="background-color:#1e293b;">
        <div class="flex items-center justify-between">
            <h3 class="text-white font-semibold text-sm">Detalle del Nodo</h3>
            <button @click="selectedNode = null" class="text-slate-500 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <template x-if="selectedNode">
            <div class="space-y-3">
                <div class="flex justify-center">
                    <template x-if="selectedNode.image">
                        <img :src="selectedNode.image" class="w-20 h-20 rounded-full object-cover border-2 border-blue-500">
                    </template>
                    <template x-if="!selectedNode.image">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl" style="background-color:#334155;">
                            <i class="fas fa-user text-slate-400"></i>
                        </div>
                    </template>
                </div>

                <div class="rounded-lg p-3 space-y-2 text-sm" style="background-color:#0f172a;">
                    <div>
                        <p class="text-xs text-slate-500">Número</p>
                        <p class="text-white font-mono text-xs" x-text="selectedNode.phone"></p>
                    </div>
                    <template x-if="selectedNode.name">
                        <div>
                            <p class="text-xs text-slate-500">Nombre</p>
                            <p class="text-white" x-text="selectedNode.name"></p>
                        </div>
                    </template>
                    <template x-if="selectedNode.alias">
                        <div>
                            <p class="text-xs text-slate-500">Alias</p>
                            <p class="text-yellow-400" x-text="selectedNode.alias"></p>
                        </div>
                    </template>
                    <div>
                        <p class="text-xs text-slate-500">Interacciones</p>
                        <p class="text-blue-400 font-bold text-xl" x-text="selectedNode.calls"></p>
                    </div>
                    <template x-if="selectedNode.crossRef">
                        <div class="rounded-lg px-3 py-2" style="background:rgba(168,85,247,0.12);border:1px solid rgba(168,85,247,0.4);">
                            <p class="text-xs text-purple-400 font-semibold mb-1"><i class="fas fa-link mr-1"></i>Cruce entre sábanas</p>
                            <template x-for="src in selectedNode.sources" :key="src">
                                <p class="text-xs text-purple-300 font-mono" x-text="src"></p>
                            </template>
                        </div>
                    </template>
                    <template x-if="selectedNode.notes">
                        <div>
                            <p class="text-xs text-slate-500">Notas</p>
                            <p class="text-slate-300 text-xs" x-text="selectedNode.notes"></p>
                        </div>
                    </template>
                </div>

                <a href="/contacts"
                   class="block text-center text-xs py-2 rounded-lg text-white hover:opacity-90 transition-opacity"
                   style="background-color:#3b82f6;">
                    <i class="fas fa-edit mr-1"></i> Editar contacto
                </a>
            </div>
        </template>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.28.1/cytoscape.min.js"></script>
<script>
(function () {

    window.networkApp = function () {
        return {
            loading:           true,
            selectedNode:      null,
            cy:                null,
            colorByFreq:       false,
            directionalLayout: false,
            snapshotSaving:    false,
            snapshotSaved:     false,
            filters:           { date_from: '', date_to: '' },

            // Number filter
            numberList: [],   // [{phone, label, calls}] sorted desc by calls
            deselected: [],   // phones unchecked by user
            numSearch:  '',

            // ── Computed ──────────────────────────────────────────────
            get filteredNums() {
                const q = this.numSearch.trim().toLowerCase();
                if (!q) return this.numberList;
                return this.numberList.filter(n =>
                    n.phone.toLowerCase().includes(q) ||
                    n.label.toLowerCase().includes(q)
                );
            },

            get selectedCount() {
                return this.numberList.length - this.deselected.length;
            },

            // Top 10% threshold → red badge
            get highFreqThreshold() {
                if (!this.numberList.length) return Infinity;
                const idx = Math.floor(this.numberList.length * 0.10);
                return this.numberList[idx]?.calls ?? Infinity;
            },

            // Top 40% threshold → yellow badge
            get medFreqThreshold() {
                if (!this.numberList.length) return Infinity;
                const idx = Math.floor(this.numberList.length * 0.40);
                return this.numberList[idx]?.calls ?? Infinity;
            },

            isSelected(phone) {
                return !this.deselected.includes(phone);
            },

            // ── Init ──────────────────────────────────────────────────
            init() { this.loadGraph(); },

            // ── Load / build ──────────────────────────────────────────
            async loadGraph() {
                this.loading           = true;
                this.selectedNode      = null;
                this.directionalLayout = false;

                const params = new URLSearchParams();
                if (this.filters.date_from) params.set('date_from', this.filters.date_from);
                if (this.filters.date_to)   params.set('date_to',   this.filters.date_to);
                // Never send numbers[] — filtering is done client-side

                const res  = await fetch('/api/network-data?' + params.toString());
                const data = await res.json();

                // Update number list; preserve deselected for phones that still exist
                if (data.numberList?.length) {
                    const prevDeselected = new Set(this.deselected);
                    this.numberList = data.numberList;
                    this.deselected = data.numberList
                        .filter(n => prevDeselected.has(n.phone))
                        .map(n => n.phone);
                }

                this.buildCy(data);
                // Apply visibility filter after graph is built
                this.applyNumFilter();
            },

            buildCy(data) {
                if (this.cy) this.cy.destroy();

                this.cy = cytoscape({
                    container: document.getElementById('cy'),
                    elements:  [...data.nodes, ...data.edges],
                    style: [
                        {
                            selector: 'node',
                            style: {
                                'background-color':   '#3b82f6',
                                'label':                    'data(label)',
                                'color':                    '#e2e8f0',
                                'font-size':                '9px',
                                'text-valign':              'bottom',
                                'text-halign':              'center',
                                'text-margin-y':            '5px',
                                'text-outline-color':       '#0f172a',
                                'text-outline-width':       '3px',
                                'text-background-color':    '#0f172a',
                                'text-background-opacity':  0.75,
                                'text-background-padding':  '2px',
                                'text-background-shape':    'roundrectangle',
                                'width':              'data(size)',
                                'height':             'data(size)',
                                'border-width':       2,
                                'border-color':       '#1e40af',
                            }
                        },
                        {
                            selector: 'node[?isTarget]',
                            style: {
                                'background-color': '#f59e0b',
                                'border-color':     '#d97706',
                                'border-width':     4,
                                'font-size':        '11px',
                            }
                        },
                        {
                            selector: 'node[?crossRef]',
                            style: {
                                'background-color': '#a855f7',
                                'border-color':     '#c084fc',
                                'border-width':     3,
                                'border-style':     'dashed',
                            }
                        },
                        {
                            selector: 'edge',
                            style: {
                                'width':                   'data(width)',
                                'line-color':              'data(color)',
                                // bezier con offset automático para pares paralelos
                                'curve-style':             'bezier',
                                'control-point-step-size': 60,
                                'opacity':                 0.85,
                                'label':                   'data(label)',
                                'font-size':               '9px',
                                'color':                   '#94a3b8',
                                'text-rotation':           'autorotate',
                                'text-margin-y':           '-7px',
                                'text-outline-color':      '#0f172a',
                                'text-outline-width':      '2px',
                                'mid-target-arrow-shape':  'none',
                                'mid-source-arrow-shape':  'none',
                                // Todas las aristas usan target-arrow; la dirección
                                // la da el source/target del edge, no el tipo de flecha
                                'target-arrow-shape':      'triangle',
                                'target-arrow-color':      'data(color)',
                                'source-arrow-shape':      'none',
                            }
                        },
                        {
                            selector: "edge[dirType='outgoing']",
                            style: {
                                // Hereda target-arrow del selector base; sin cambios extra
                            }
                        },
                        {
                            selector: "edge[dirType='incoming']",
                            style: {
                                // source/target ya están invertidos desde el servidor;
                                // el target-arrow apunta al callee correcto (lo)
                            }
                        },
                        {
                            selector: ':selected',
                            style: {
                                'border-color': '#fff',
                                'border-width': 3,
                                'opacity':      1,
                            }
                        }
                    ],
                    layout: {
                        name:            'cose',
                        animate:         false,
                        randomize:       true,
                        nodeRepulsion:   8000,
                        idealEdgeLength: 100,
                        padding:         40,
                    },
                });

                // Apply photos programmatically (CSS selector doesn't work with data URLs)
                this.cy.nodes().forEach(node => {
                    const img = node.data('image');
                    if (img) {
                        node.style({
                            'background-image':             img,
                            'background-fit':               'cover',
                            'background-clip':              'node',
                            'background-image-crossorigin': 'anonymous',
                        });
                    }
                });

                this.cy.on('tap', 'node', evt => {
                    this.selectedNode = evt.target.data();
                });
                this.cy.on('tap', evt => {
                    if (evt.target === this.cy) this.selectedNode = null;
                });

                if (this.colorByFreq) this.applyFreqColors();

                this.loading = false;
            },

            // ── Color by frequency ────────────────────────────────────
            toggleColorByFreq() {
                this.colorByFreq = !this.colorByFreq;
                if (this.colorByFreq) {
                    this.applyFreqColors();
                    this.applyConcentricLayout();
                    this.directionalLayout = false;
                } else {
                    this.resetNodeColors();
                    this.resetLayout();
                }
            },

            applyFreqColors() {
                if (!this.cy) return;
                const nodes = this.cy.nodes();
                const maxC  = Math.max(...nodes.map(n => n.data('calls') || 1));
                nodes.forEach(node => {
                    if (node.data('isTarget') || node.data('crossRef')) return;
                    const t     = (node.data('calls') || 1) / maxC;
                    const color = this.freqToColor(t);
                    const s     = { 'border-color': color, 'border-width': 4 };
                    // Don't overwrite background when the node has a photo
                    if (!node.data('image')) s['background-color'] = color;
                    node.style(s);
                });
            },

            resetNodeColors() {
                if (!this.cy) return;
                this.cy.nodes().forEach(node => {
                    if (node.data('isTarget')) {
                        node.style({ 'background-color': '#f59e0b', 'border-color': '#d97706', 'border-width': 4, 'border-style': 'solid' });
                    } else if (node.data('crossRef')) {
                        node.style({ 'background-color': '#a855f7', 'border-color': '#c084fc', 'border-width': 3, 'border-style': 'dashed' });
                    } else {
                        const s = { 'border-color': '#1e40af', 'border-width': 2, 'border-style': 'solid' };
                        if (!node.data('image')) s['background-color'] = '#3b82f6';
                        node.style(s);
                    }
                });
            },

            applyConcentricLayout() {
                if (!this.cy) return;
                this.cy.layout({
                    name:              'concentric',
                    concentric:        node => node.data('calls') || 1,
                    levelWidth:        () => 3,
                    padding:           60,
                    animate:           true,
                    animationDuration: 700,
                    animationEasing:   'ease-in-out',
                }).run();
            },

            // Blue → Emerald → Amber → Red heatmap
            freqToColor(t) {
                const stops = [
                    { t: 0.00, r: 59,  g: 130, b: 246 },
                    { t: 0.33, r: 16,  g: 185, b: 129 },
                    { t: 0.66, r: 245, g: 158, b: 11  },
                    { t: 1.00, r: 239, g: 68,  b: 68  },
                ];
                for (let i = 0; i < stops.length - 1; i++) {
                    if (t >= stops[i].t && t <= stops[i + 1].t) {
                        const s = (t - stops[i].t) / (stops[i + 1].t - stops[i].t);
                        const r = Math.round(stops[i].r + s * (stops[i + 1].r - stops[i].r));
                        const g = Math.round(stops[i].g + s * (stops[i + 1].g - stops[i].g));
                        const b = Math.round(stops[i].b + s * (stops[i + 1].b - stops[i].b));
                        return `rgb(${r},${g},${b})`;
                    }
                }
                return '#ef4444';
            },

            // ── Number filter — client-side show/hide ─────────────────
            applyNumFilter() {
                if (!this.cy) return;
                // Show/hide nodes based on deselected list
                this.cy.nodes().forEach(node => {
                    if (this.deselected.includes(node.data('phone'))) {
                        node.hide();
                    } else {
                        node.show();
                    }
                });
                // Hide edges where at least one endpoint is hidden
                this.cy.edges().forEach(edge => {
                    if (edge.source().hidden() || edge.target().hidden()) {
                        edge.hide();
                    } else {
                        edge.show();
                    }
                });
            },

            toggleNum(phone) {
                if (this.deselected.includes(phone)) {
                    this.deselected = this.deselected.filter(p => p !== phone);
                } else {
                    this.deselected = [...this.deselected, phone];
                }
                this.applyNumFilter(); // instant, no API call
            },

            selectAll() {
                this.deselected = [];
                this.applyNumFilter();
            },

            selectNone() {
                this.deselected = this.numberList.map(n => n.phone);
                this.applyNumFilter();
            },

            // ── Snapshot for report ───────────────────────────────────
            async saveSnapshot() {
                if (!this.cy || this.snapshotSaving) return;
                this.snapshotSaving = true;
                this.snapshotSaved  = false;

                // Fit first so the full visible graph is captured
                this.cy.fit(undefined, 30);

                await new Promise(r => setTimeout(r, 300)); // wait for layout settle

                const png = this.cy.png({
                    output:     'base64uri',
                    full:       false,
                    scale:      2,
                    bg:         '#0f172a',
                    maxWidth:   2400,
                    maxHeight:  1600,
                });

                try {
                    const res = await fetch('/api/network-snapshot', {
                        method:  'POST',
                        headers: {
                            'Content-Type':  'application/json',
                            'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ image: png }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.snapshotSaved = true;
                        setTimeout(() => { this.snapshotSaved = false; }, 4000);
                    }
                } catch (e) {
                    console.error('Error saving snapshot', e);
                } finally {
                    this.snapshotSaving = false;
                }
            },

            // ── Layout helpers ────────────────────────────────────────
            applyDirectionalLayout() {
                if (!this.cy) return;
                this.directionalLayout = true;

                const targetNodes = this.cy.nodes('[?isTarget]').filter(':visible');
                const W   = this.cy.width();
                const H   = this.cy.height();
                const pad = 60;

                if (!targetNodes.length) { this.resetLayout(); return; }

                const positions = {};
                const byFreq    = (a, b) => (b.data('calls') || 0) - (a.data('calls') || 0);

                // ── Columna vertical centrada ──────────────────────────
                const column = (nodes, x) => {
                    if (!nodes.length) return;
                    const step   = Math.max(50, (H - pad * 2) / (nodes.length + 1));
                    const startY = (H - step * (nodes.length - 1)) / 2;
                    nodes.forEach((n, i) => {
                        positions[n.data('id')] = { x, y: startY + step * i };
                    });
                };

                if (targetNodes.length === 1) {
                    // ── Caso: 1 número objetivo ────────────────────────────────────
                    const tNode   = targetNodes.first();
                    const tId     = tNode.data('id');
                    positions[tId] = { x: W / 2, y: H / 2 };

                    const outOnly = [], inOnly = [], bidir = [], solo = [];

                    this.cy.nodes(':visible').forEach(node => {
                        if (node.data('isTarget')) return;
                        const edges = node.edgesWith(tNode).filter(':visible');
                        if (!edges.length) { solo.push(node); return; }

                        let hasOut = false, hasIn = false;
                        edges.forEach(edge => {
                            // Cada arista tiene su dirección real en source→target:
                            //   outgoing: source=lo → target=hi  (lo llamó)
                            //   incoming: source=hi → target=lo  (hi llamó, invertido desde servidor)
                            // El "caller" es siempre source y el "callee" siempre target.
                            const caller = edge.data('source');
                            const callee = edge.data('target');
                            if (caller === tId) hasOut = true; // objetivo llamó al nodo
                            if (callee === tId) hasIn  = true; // alguien llamó al objetivo
                        });

                        if (hasOut && hasIn) bidir.push(node);
                        else if (hasOut)     outOnly.push(node);
                        else if (hasIn)      inOnly.push(node);
                        else                 bidir.push(node);
                    });

                    outOnly.sort(byFreq); inOnly.sort(byFreq);
                    bidir.sort(byFreq);   solo.sort(byFreq);

                    column(outOnly, pad + 70);
                    column(inOnly,  W - pad - 70);

                    const radius = Math.min(W, H) * 0.22;
                    bidir.forEach((node, i) => {
                        const angle = (2 * Math.PI * i) / Math.max(bidir.length, 1) - Math.PI / 2;
                        positions[node.data('id')] = {
                            x: W / 2 + radius * Math.cos(angle),
                            y: H / 2 + radius * Math.sin(angle),
                        };
                    });

                    solo.forEach((node, i) => {
                        const cols = 5, cellW = (W - pad * 2) / cols;
                        positions[node.data('id')] = {
                            x: pad + cellW / 2 + (i % cols) * cellW,
                            y: H - pad - Math.floor(i / cols) * 55,
                        };
                    });

                } else {
                    // ── Caso: N números objetivo (una por sábana) ──────────────────
                    //
                    // Distribución horizontal:
                    //   [ excl. obj-1 ] [ obj-1 ] [ CRUCES ] [ obj-2 ] [ excl. obj-2 ]
                    //
                    // Los cruces entre sábanas quedan al centro, entre los objetivos.

                    const targets  = targetNodes.toArray();
                    const nT       = targets.length;
                    // Zonas: nT objetivos + nT+1 bloques exteriores + 1 centro para cruces
                    // Dividimos el ancho en 2*nT+1 secciones
                    const sections = 2 * nT + 1;
                    const secW     = (W - pad * 2) / sections;

                    // Objetivos: secciones 1, 3, 5… (índice par en base-0 → 1-based odd)
                    targets.forEach((t, i) => {
                        const section = 1 + i * 2; // 1, 3, 5...
                        positions[t.data('id')] = {
                            x: pad + secW * section + secW / 2,
                            y: H / 2,
                        };
                    });

                    // Centro de cruces: sección del medio (nT para 2 targets → sección 1-based = nT)
                    const crossX = pad + secW * nT + secW / 2;

                    // Clasificar nodos no-objetivo
                    const crossRefs  = [];
                    const perTarget  = targets.map(() => []);
                    const noLink     = [];

                    this.cy.nodes(':visible').forEach(node => {
                        if (node.data('isTarget')) return;

                        if (node.data('crossRef')) {
                            crossRefs.push(node); return;
                        }

                        // Asignar al objetivo con más llamadas conectadas
                        let bestIdx = -1, bestCalls = 0;
                        targets.forEach((t, i) => {
                            const cnt = node.edgesWith(t).filter(':visible')
                                .reduce((s, e) => s + (e.data('calls') || 0), 0);
                            if (cnt > bestCalls) { bestCalls = cnt; bestIdx = i; }
                        });

                        if (bestIdx >= 0) perTarget[bestIdx].push(node);
                        else              noLink.push(node);
                    });

                    crossRefs.sort(byFreq);
                    perTarget.forEach(g => g.sort(byFreq));
                    noLink.sort(byFreq);

                    // Cruces al centro
                    column(crossRefs, crossX);

                    // Contactos exclusivos de cada objetivo: en la columna exterior de su target
                    targets.forEach((t, i) => {
                        // Para objetivo i (sección 2i+1), la columna exterior es
                        // sección 2i (izquierda del primero) o 2i+2 (derecha del último)
                        const outerSection = i < nT / 2
                            ? 1 + i * 2 - 1        // a la izquierda del objetivo
                            : 1 + i * 2 + 1;        // a la derecha del objetivo
                        const colX = pad + secW * outerSection + secW / 2;
                        column(perTarget[i], colX);
                    });

                    // Nodos sin enlace: fila inferior
                    noLink.forEach((n, i) => {
                        const cols  = Math.min(noLink.length, 6);
                        const cellW = (W - pad * 2) / Math.max(cols, 1);
                        positions[n.data('id')] = {
                            x: pad + cellW / 2 + (i % cols) * cellW,
                            y: H - pad - Math.floor(i / cols) * 50,
                        };
                    });
                }

                this.cy.layout({
                    name:              'preset',
                    positions:         node => positions[node.data('id')] || { x: W / 2, y: H / 2 },
                    animate:           true,
                    animationDuration: 800,
                    animationEasing:   'ease-in-out',
                    padding:           pad,
                    fit:               true,
                }).run();
            },

            resetLayout() {
                if (!this.cy) return;
                this.directionalLayout = false;
                this.cy.layout({
                    name:          'cose',
                    animate:       true,
                    randomize:     true,
                    nodeRepulsion: 8000,
                }).run();
            },

            fitGraph() {
                if (this.cy) this.cy.fit(undefined, 40);
            },
        };
    };
})();
</script>
@endpush
