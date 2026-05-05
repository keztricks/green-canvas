<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin=""/>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <a href="{{ route('canvassing.ward', $ward) }}" class="text-[#6AB023] hover:text-[#5a9620] text-sm">
                        ← Back to {{ $ward->name }}
                    </a>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ $ward->name }} — Map</h2>
                </div>

                @if($wards->count() > 1)
                <div class="flex items-center gap-2">
                    <label for="wardSelect" class="text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">Switch ward:</label>
                    <select id="wardSelect"
                            class="text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                        @foreach($wards as $w)
                            <option value="{{ route('canvassing.map', $w) }}" {{ $w->id === $ward->id ? 'selected' : '' }}>
                                {{ $w->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $totalCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total addresses</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-[#6AB023]">{{ $knockedCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Knocked</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-gray-400">{{ $totalCount - $knockedCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Still to do</p>
                </div>
            </div>

            @if($geocodedCount < $totalCount)
            <div class="mb-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-4 py-3 text-sm text-amber-700 dark:text-amber-300 flex items-center justify-between gap-4">
                <span>{{ $totalCount - $geocodedCount }} address{{ ($totalCount - $geocodedCount) === 1 ? '' : 'es' }} couldn't be placed on the map (missing postcode coordinates).</span>
                @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('canvassing.geocode') }}">
                    @csrf
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium px-3 py-1.5 rounded whitespace-nowrap">
                        Geocode now
                    </button>
                </form>
                @endif
            </div>
            @endif

            {{-- View tabs + Legend (combined bar) --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-4 py-3 mb-2 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex gap-1 shrink-0 flex-wrap">
                    <button data-view="supporter"  class="view-tab px-3 py-1.5 rounded text-sm font-medium transition">Supporter</button>
                    <button data-view="party"      class="view-tab px-3 py-1.5 rounded text-sm font-medium transition">Party</button>
                    <button data-view="likelihood" class="view-tab px-3 py-1.5 rounded text-sm font-medium transition">Likelihood</button>
                    <button data-view="coverage"   class="view-tab px-3 py-1.5 rounded text-sm font-medium transition">Coverage</button>
                    <button data-view="support"    class="view-tab px-3 py-1.5 rounded text-sm font-medium transition">Support</button>
                </div>
                <div id="legend" class="flex flex-wrap gap-x-3 gap-y-1.5 text-sm text-gray-600 dark:text-gray-300"></div>
            </div>

            {{-- Map --}}
            <div id="map" class="rounded-lg shadow" style="height: calc(100vh - 340px); min-height: 400px;"></div>

        </div>
    </div>

    {{-- Slide-up sheet (replaces the Leaflet popup) --}}
    <div id="recordSheet" class="hidden fixed inset-0 z-[2000]">
        <div class="absolute inset-0 bg-black/50" id="recordSheetBackdrop"></div>
        <div id="recordSheetPanel"
             class="absolute bottom-0 left-0 right-0 bg-white dark:bg-gray-800 rounded-t-2xl shadow-2xl max-h-[92vh] overflow-y-auto transform transition-transform duration-200 translate-y-full">
            <div class="p-4 sm:p-5 max-w-2xl mx-auto">

                {{-- Header --}}
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1 pr-2">
                        <h2 id="sheetTitle" class="text-lg font-semibold text-gray-800 dark:text-white"></h2>
                        <p id="sheetPostcode" class="text-sm text-gray-600 dark:text-gray-300"></p>
                    </div>
                    <button type="button" id="sheetClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-3xl leading-none px-2" aria-label="Close">×</button>
                </div>

                {{-- DNK warning (filled by JS) --}}
                <div id="sheetDnk" class="hidden mb-3 p-3 bg-red-100 border-l-4 border-red-500 rounded">
                    <p class="font-bold text-red-800 text-sm">⚠️ DO NOT KNOCK</p>
                </div>

                {{-- Latest result block (filled by JS) --}}
                <div id="sheetLatest" class="hidden"></div>

                {{-- Action row --}}
                <div class="mt-3 flex gap-2 flex-wrap">
                    <a id="sheetViewAddress" href="#"
                       class="block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-center text-sm">
                        Address details
                    </a>
                    <a id="sheetDirections" href="#" target="_blank" rel="noopener"
                       class="block bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded text-center text-sm">
                        Directions
                    </a>
                    @if($canEditPositions)
                        <button type="button" id="sheetPin"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded text-sm">
                            Pin dot
                        </button>
                    @endif
                </div>

                {{-- Record form --}}
                <form id="sheetForm" class="mt-4 border-t pt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="address_id" id="sheetAddressId" value="">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Record a result</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($responseOptions as $value => $label)
                                <label class="response-option flex items-center space-x-2 p-2 border rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                    <input type="radio" name="response" value="{{ $value }}" required class="text-green-600">
                                    <span class="text-sm">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Green Party Support (optional)</label>
                        <div class="flex gap-2 sm:gap-3">
                            @php $likelihoodColors = [1 => '#22c55e', 2 => '#84cc16', 3 => '#eab308', 4 => '#f97316', 5 => '#ef4444']; @endphp
                            @foreach($likelihoodColors as $n => $color)
                                <label class="likelihood-option flex items-center justify-center rounded-lg hover:opacity-80 cursor-pointer transition-all shadow-sm flex-1" style="max-width: 64px; height: 56px; border-width: 3px; border-color: transparent; background-color: {{ $color }};">
                                    <input type="radio" name="vote_likelihood" value="{{ $n }}" class="sr-only">
                                    <span class="text-xl sm:text-2xl font-semibold text-white">{{ $n }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-1">5 = Never voting Green, 1 = Definitely voting Green</p>
                    </div>

                    <div>
                        <label for="sheetNotes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (optional)</label>
                        <textarea id="sheetNotes" name="notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm"></textarea>
                    </div>

                    <div id="sheetError" class="hidden text-red-600 text-sm"></div>

                    <button type="submit" id="sheetSubmit"
                            class="w-full bg-[#6AB023] hover:bg-[#5a9620] disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-3 rounded font-bold">
                        Save Result
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>

    <style>
        .hex-label {
            background: transparent;
            border: none;
            box-shadow: none;
            color: #1f2937;
            font-size: 0.7rem;
            font-weight: 600;
            text-shadow: 0 0 3px #fff, 0 0 3px #fff;
            pointer-events: none;
        }
        .hex-label::before { display: none; }
    </style>
    <script>
    (function () {
        var addresses        = @json($addressData);
        var streetUrlTpl     = '{{ route('canvassing.street', ['ward' => $ward->id, 'streetName' => '__STREET__']) }}';
        var RESPONSE_LABELS  = @json($responseOptions);
        var TURNOUT_LABELS   = @json($turnoutOptions);
        var canEditPositions = @json($canEditPositions);

        // ── Colour schemes ──────────────────────────────────────────────────

        var SUPPORTER_PARTIES = ['labour', 'green', 'lib_dem', 'your_party'];
        var OPPOSITION_PARTIES = ['conservative', 'reform'];

        function colorSupporter(a) {
            if (a.dnk) return '#7f1d1d';
            var isSupporter = SUPPORTER_PARTIES.includes(a.response);
            if (isSupporter && (a.likelihood === 1 || a.likelihood === 2)) return '#16a34a'; // strong – bright green
            if (isSupporter) return '#86efac';                                                // weaker supporter – pale green
            if (OPPOSITION_PARTIES.includes(a.response)) return '#ef4444';
            switch (a.response) {
                case 'not_home':  return '#fb923c';
                case 'undecided': return '#facc15';
                case 'refused':
                case 'wont_vote': return '#64748b';
                case 'other':     return '#0d9488';
                default:          return '#9ca3af'; // not knocked
            }
        }

        function colorParty(a) {
            if (a.dnk) return '#7f1d1d';
            switch (a.response) {
                case 'labour':       return '#e4003b';
                case 'conservative': return '#0087dc';
                case 'lib_dem':      return '#faa61a';
                case 'green':        return '#02a95b';
                case 'reform':       return '#12b6cf';
                case 'your_party':   return '#6AB023';
                case 'not_home':     return '#fb923c';
                case 'undecided':    return '#facc15';
                case 'refused':
                case 'wont_vote':    return '#64748b';
                case 'other':        return '#0d9488';
                default:             return '#9ca3af';
            }
        }

        // vote_likelihood 1 = strongest, 5 = weakest
        var LIKELIHOOD_COLORS = ['#15803d', '#22c55e', '#fbbf24', '#fb923c', '#64748b'];
        function colorLikelihood(a) {
            if (a.dnk) return '#7f1d1d';
            if (!a.response) return '#9ca3af';       // not knocked
            if (!a.likelihood) return '#d1d5db';     // knocked, no score
            return LIKELIHOOD_COLORS[(a.likelihood - 1)] || '#9ca3af';
        }

        var COLOR_FNS = { supporter: colorSupporter, party: colorParty, likelihood: colorLikelihood };

        // ── Legends ─────────────────────────────────────────────────────────

        function dot(color) {
            return '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:' + color + ';vertical-align:middle;margin-right:4px"></span>';
        }

        var LEGENDS = {
            supporter: [
                ['Not knocked',          '#9ca3af'],
                ['Strong supporter (1–2)', '#16a34a'],
                ['Supporter',            '#86efac'],
                ['Opposition',           '#ef4444'],
                ['Not home',             '#fb923c'],
                ['Undecided',            '#facc15'],
                ["Refused / won't vote", '#64748b'],
                ['Do not knock',         '#7f1d1d'],
            ],
            party: [
                ['Not knocked',          '#9ca3af'],
                ['Labour',               '#e4003b'],
                ['Conservative',         '#0087dc'],
                ['Lib Dem',              '#faa61a'],
                ['Green',                '#02a95b'],
                ['Reform',               '#12b6cf'],
                ['Your Party',           '#6AB023'],
                ['Not home',             '#fb923c'],
                ['Undecided',            '#facc15'],
                ["Refused / won't vote", '#64748b'],
            ],
            likelihood: [
                ['Not knocked',   '#9ca3af'],
                ['No score',      '#d1d5db'],
                ['1 — Definite',  '#15803d'],
                ['2 — Likely',    '#22c55e'],
                ['3 — Possible',  '#fbbf24'],
                ['4 — Unlikely',  '#fb923c'],
                ["5 — Won't vote",'#64748b'],
                ['Do not knock',  '#7f1d1d'],
            ],
            coverage: [
                ['0%',     '#f3f4f6'],
                ['1–25%',  '#fde68a'],
                ['26–50%', '#bbf7d0'],
                ['51–75%', '#4ade80'],
                ['76–100%','#15803d'],
            ],
            support: [
                ['No data',  '#e5e7eb'],
                ['<20%',     '#dc2626'],
                ['20–40%',   '#f97316'],
                ['40–60%',   '#facc15'],
                ['60–80%',   '#86efac'],
                ['80–100%',  '#16a34a'],
            ],
        };

        function renderLegend(view) {
            return LEGENDS[view].map(function(item) {
                return '<span style="white-space:nowrap">' + dot(item[1]) + item[0] + '</span>';
            }).join('');
        }

        // ── Labels ──────────────────────────────────────────────────────────

        function esc(s) {
            return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
        }

        // ── Group addresses by exact lat/lng. Fan only those that share an
        //    identical coordinate (e.g. flats at one UPRN, or addresses that
        //    fell back to a postcode centroid). Everything else stays put.

        var SPIRAL_C = 0.0000226;                        // base spacing ≈ 2.5 m
        var GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));

        var byCoord = {};
        addresses.forEach(function (a, i) {
            var key = a.lat.toFixed(7) + ',' + a.lng.toFixed(7);
            if (!byCoord[key]) byCoord[key] = { lat: a.lat, lng: a.lng, idxs: [] };
            byCoord[key].idxs.push(i);
        });
        var postcodes = Object.values(byCoord);

        Object.values(byCoord).forEach(function (group) {
            if (group.idxs.length < 2) return;
            var latRad = group.lat * Math.PI / 180;
            group.idxs.forEach(function (addrIdx, k) {
                var r     = SPIRAL_C * Math.sqrt(k + 0.5);
                var theta = k * GOLDEN_ANGLE;
                addresses[addrIdx].lat = group.lat + r * Math.cos(theta);
                addresses[addrIdx].lng = group.lng + r * Math.sin(theta) / Math.cos(latRad);
            });
        });

        // ── Per-postcode aggregates (used to build sector-level data) ────────

        postcodes.forEach(function (pc) {
            pc.postcode   = addresses[pc.idxs[0]].postcode;
            pc.total      = pc.idxs.length;
            pc.knocked    = 0;
            pc.engaged    = 0;
            pc.supporters = 0;
            pc.idxs.forEach(function (i) {
                var r = addresses[i].response;
                if (!r) return;
                pc.knocked++;
                if (r !== 'not_home') pc.engaged++;
                if (SUPPORTER_PARTIES.indexOf(r) !== -1) pc.supporters++;
            });
        });

        // ── Per-sector aggregates for choropleth (e.g. HX1 1AA → HX1 1) ──────

        function postcodeSector(pc) {
            if (!pc) return null;
            var clean = pc.replace(/\s+/g, '').toUpperCase();
            if (clean.length < 5) return null;
            return clean.slice(0, -3) + ' ' + clean.slice(-3, -1);
        }

        var sectorMap = {};
        postcodes.forEach(function (pc) {
            var s = postcodeSector(pc.postcode);
            if (!s) return;
            if (!sectorMap[s]) sectorMap[s] = {
                sector: s,
                _sumLat: 0, _sumLng: 0, _weight: 0,
                total: 0, knocked: 0, engaged: 0, supporters: 0,
            };
            var sec = sectorMap[s];
            sec._sumLat    += pc.lat * pc.total;
            sec._sumLng    += pc.lng * pc.total;
            sec._weight    += pc.total;
            sec.total      += pc.total;
            sec.knocked    += pc.knocked;
            sec.engaged    += pc.engaged;
            sec.supporters += pc.supporters;
        });
        var sectors = Object.values(sectorMap).map(function (s) {
            s.lat      = s._sumLat / s._weight;
            s.lng      = s._sumLng / s._weight;
            s.coverage = s.total > 0 ? s.knocked / s.total : 0;
            s.support  = s.engaged > 0 ? s.supporters / s.engaged : null;
            return s;
        });

        // ── Hex grid layout: snap each sector centroid to nearest empty hex ──

        var HEX_RADIUS_M = 250;             // metres
        var hexAssignments = null;

        function buildHexGrid() {
            if (hexAssignments || sectors.length === 0) return hexAssignments;

            var refLat = sectors[0].lat, refLng = sectors[0].lng;
            var degToM_lat = 111000;
            var degToM_lng = 111000 * Math.cos(refLat * Math.PI / 180);

            var hexW = HEX_RADIUS_M * Math.sqrt(3);    // pointy-top: width = √3·r
            var hexH = HEX_RADIUS_M * 1.5;             //             vert spacing = 1.5·r

            function hexCenter(col, row) {
                return {
                    x: col * hexW + (row % 2 === 0 ? 0 : hexW / 2),
                    y: row * hexH,
                };
            }
            function nearestHex(x, y) {
                var row = Math.round(y / hexH);
                var x0  = (row % 2 === 0 ? 0 : hexW / 2);
                var col = Math.round((x - x0) / hexW);
                return { col: col, row: row };
            }

            // Sort by address count desc — bigger sectors get first dibs on their ideal hex
            var ordered = sectors.map(function (s, i) {
                return {
                    sectorIdx: i,
                    x: (s.lng - refLng) * degToM_lng,
                    y: (s.lat - refLat) * degToM_lat,
                    weight: s.total,
                };
            }).sort(function (a, b) { return b.weight - a.weight; });

            var taken = {};
            var result = new Array(sectors.length);

            ordered.forEach(function (p) {
                var ideal = nearestHex(p.x, p.y);
                var visited = {};
                var queue = [ideal];
                visited[ideal.col + ',' + ideal.row] = true;
                while (queue.length > 0) {
                    var hex = queue.shift();
                    var key = hex.col + ',' + hex.row;
                    if (!taken[key]) {
                        taken[key] = p.sectorIdx;
                        var c = hexCenter(hex.col, hex.row);
                        result[p.sectorIdx] = {
                            lat: c.y / degToM_lat + refLat,
                            lng: c.x / degToM_lng + refLng,
                        };
                        return;
                    }
                    var dirs = hex.row % 2 === 0
                        ? [[-1, 0], [1, 0], [-1, -1], [0, -1], [-1, 1], [0, 1]]
                        : [[-1, 0], [1, 0], [0, -1], [1, -1], [0, 1], [1, 1]];
                    for (var k = 0; k < 6; k++) {
                        var n = { col: hex.col + dirs[k][0], row: hex.row + dirs[k][1] };
                        var nKey = n.col + ',' + n.row;
                        if (!visited[nKey]) { visited[nKey] = true; queue.push(n); }
                    }
                }
            });

            hexAssignments = result;
            return hexAssignments;
        }

        function hexagonLatLngs(centerLat, centerLng, radiusM) {
            var latRad = centerLat * Math.PI / 180;
            var degToM_lat = 111000;
            var degToM_lng = 111000 * Math.cos(latRad);
            var pts = [];
            for (var k = 0; k < 6; k++) {
                var angle = Math.PI / 3 * k + Math.PI / 6;
                var dx = Math.cos(angle) * radiusM;
                var dy = Math.sin(angle) * radiusM;
                pts.push([centerLat + dy / degToM_lat, centerLng + dx / degToM_lng]);
            }
            return pts;
        }

        function coverageColor(s) {
            if (s.total === 0) return '#f3f4f6';
            var p = s.coverage;
            if (p === 0)     return '#f3f4f6';
            if (p < 0.25)    return '#fde68a';
            if (p < 0.50)    return '#bbf7d0';
            if (p < 0.75)    return '#4ade80';
            return '#15803d';
        }

        function supportColor(s) {
            if (s.engaged === 0) return '#e5e7eb';
            var p = s.support;
            if (p < 0.20)    return '#dc2626';
            if (p < 0.40)    return '#f97316';
            if (p < 0.60)    return '#facc15';
            if (p < 0.80)    return '#86efac';
            return '#16a34a';
        }

        function buildSectorPopup(s) {
            var supportPct = s.engaged > 0 ? Math.round(s.support * 100) + '%' : 'no data';
            return '<div style="min-width:170px;font-size:0.875rem">'
                + '<strong>' + esc(s.sector) + '</strong><br>'
                + '<span style="color:#6b7280">' + s.total + ' addresses</span><br><br>'
                + 'Knocked: <strong>' + s.knocked + '/' + s.total + '</strong>'
                + ' (' + Math.round(s.coverage * 100) + '%)<br>'
                + 'Supporters: <strong>' + s.supporters + '</strong>'
                + ' (' + supportPct + ' of engaged)'
                + '</div>';
        }

        var hexLayer = null;
        function showHexGrid(view) {
            var pos = buildHexGrid();
            if (!pos) return;
            hexLayer = L.layerGroup();
            sectors.forEach(function (sec, i) {
                var p = pos[i];
                if (!p) return;
                var fill = view === 'coverage' ? coverageColor(sec) : supportColor(sec);
                var hex = L.polygon(hexagonLatLngs(p.lat, p.lng, HEX_RADIUS_M * 0.95), {
                    color: '#374151', weight: 0.8,
                    fillColor: fill, fillOpacity: 0.85,
                });
                hex.bindPopup(function () { return buildSectorPopup(sec); });
                hex.bindTooltip(sec.sector, { direction: 'center', permanent: true, className: 'hex-label' });
                hexLayer.addLayer(hex);
            });
            hexLayer.addTo(map);
        }
        function hideHexGrid() {
            if (hexLayer) {
                map.removeLayer(hexLayer);
                hexLayer = null;
            }
        }

        // ── Map + markers ────────────────────────────────────────────────────

        var map = L.map('map');
        var tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · '
                + 'Contains OS data © Crown copyright and database right · '
                + 'Council data © Calderdale Council, OGL v3',
            maxZoom: 19,
        });
        tileLayer.addTo(map);

        var clusterGroup = L.markerClusterGroup({ maxClusterRadius: 40, disableClusteringAtZoom: 15 });

        var markers = addresses.map(function (a) {
            var marker = L.circleMarker([a.lat, a.lng], {
                radius: 7, fillColor: '#9ca3af',
                color: '#fff', weight: 1.5, opacity: 1, fillOpacity: 0.9,
            });
            marker.on('click', function () { openSheet(a, marker); });
            clusterGroup.addLayer(marker);
            return marker;
        });

        // ── Move-dot workflow ────────────────────────────────────────────────

        var moveMode = null;
        var moveBanner = null;
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function ensureBanner() {
            if (moveBanner) return moveBanner;
            moveBanner = document.createElement('div');
            moveBanner.style.cssText = 'position:fixed;top:1rem;left:50%;transform:translateX(-50%);'
                + 'background:#1d4ed8;color:#fff;padding:0.5rem 1rem;border-radius:0.375rem;'
                + 'box-shadow:0 4px 6px rgba(0,0,0,0.1);z-index:10000;font-size:0.875rem;';
            document.body.appendChild(moveBanner);
            return moveBanner;
        }

        function startMove(address, marker) {
            moveMode = { address: address, marker: marker };
            map.getContainer().style.cursor = 'crosshair';
            ensureBanner().textContent = 'Click on the map to pin "' + address.label + '" — Esc to cancel';
            moveBanner.style.display = 'block';
        }

        function endMove() {
            moveMode = null;
            map.getContainer().style.cursor = '';
            if (moveBanner) moveBanner.style.display = 'none';
        }

        map.on('click', function (e) {
            if (!moveMode) return;
            var lat = e.latlng.lat, lng = e.latlng.lng;
            var addr = moveMode.address, marker = moveMode.marker;

            fetch('{{ url('/address') }}/' + addr.id + '/position', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ lat: lat, lng: lng }),
            }).then(function (r) {
                if (r.ok) {
                    marker.setLatLng([lat, lng]);
                    addr.lat = lat;
                    addr.lng = lng;
                    addr.precise = true;
                } else {
                    alert('Could not save position (HTTP ' + r.status + ')');
                }
            }).catch(function () { alert('Network error saving position'); });

            endMove();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && moveMode) endMove();
        });

        map.addLayer(clusterGroup);

        if (markers.length > 0) {
            map.fitBounds(clusterGroup.getBounds().pad(0.1));
        } else {
            map.setView([53.7248, -1.8658], 13);
        }

        // ── Slide-up sheet (replaces map popup) ──────────────────────────────

        var sheetEl       = document.getElementById('recordSheet');
        var sheetPanel    = document.getElementById('recordSheetPanel');
        var sheetBackdrop = document.getElementById('recordSheetBackdrop');
        var sheetCloseBtn = document.getElementById('sheetClose');
        var sheetForm     = document.getElementById('sheetForm');
        var sheetSubmit   = document.getElementById('sheetSubmit');
        var sheetError    = document.getElementById('sheetError');
        var sheetTitle    = document.getElementById('sheetTitle');
        var sheetPostcode = document.getElementById('sheetPostcode');
        var sheetDnk      = document.getElementById('sheetDnk');
        var sheetLatest   = document.getElementById('sheetLatest');
        var sheetDirections    = document.getElementById('sheetDirections');
        var sheetViewAddress   = document.getElementById('sheetViewAddress');
        var sheetPin           = document.getElementById('sheetPin');
        var sheetCtx      = null; // { address, marker }

        function partyBorderClass(response) {
            switch (response) {
                case 'green':        return 'border-green-500';
                case 'labour':       return 'border-red-500';
                case 'conservative': return 'border-blue-500';
                case 'lib_dem':      return 'border-orange-400';
                case 'undecided':    return 'border-yellow-500';
                case 'reform':       return '';
                default:             return 'border-gray-400';
            }
        }

        function renderLatestBlock(a) {
            if (!a.response) {
                sheetLatest.classList.add('hidden');
                sheetLatest.innerHTML = '';
                return;
            }
            var borderCls   = partyBorderClass(a.response);
            var reformStyle = a.response === 'reform' ? ' style="border-left-color:#17B9D1;"' : '';
            var likelihoodNote = '';
            if (a.likelihood === 1) likelihoodNote = ' <span class="text-xs text-green-600">(Definitely)</span>';
            else if (a.likelihood === 5) likelihoodNote = ' <span class="text-xs text-red-600">(Never)</span>';

            var html = '<div class="mt-2 p-3 bg-white dark:bg-gray-700 rounded border-l-4 ' + borderCls + '"' + reformStyle + '>'
                + '<p class="font-medium text-sm">Latest: <span class="font-bold">' + esc(RESPONSE_LABELS[a.response] || a.response) + '</span></p>';
            if (a.likelihood) {
                html += '<p class="text-sm text-gray-700 dark:text-gray-300 mt-1">Green support: <span class="font-semibold">' + a.likelihood + '/5</span>' + likelihoodNote + '</p>';
            }
            if (a.turnout) {
                html += '<p class="text-sm text-gray-600 dark:text-gray-300 mt-1">' + esc(TURNOUT_LABELS[a.turnout] || a.turnout) + '</p>';
            }
            if (a.notes) {
                html += '<p class="text-sm text-gray-600 dark:text-gray-300 mt-1">' + esc(a.notes) + '</p>';
            }
            html += '<p class="text-xs text-gray-500 mt-1">'
                +     (a.knocked_at ? esc(a.knocked_at) : '')
                +     (a.canvasser ? ' by ' + esc(a.canvasser) : '')
                + '</p>'
                + '</div>';

            sheetLatest.innerHTML = html;
            sheetLatest.classList.remove('hidden');
        }

        function openSheet(address, marker) {
            sheetCtx = { address: address, marker: marker };

            sheetTitle.textContent = address.label;
            sheetPostcode.textContent = address.postcode || '';
            document.getElementById('sheetAddressId').value = address.id;

            sheetDnk.classList.toggle('hidden', !address.dnk);
            renderLatestBlock(address);

            sheetDirections.href  = 'https://www.google.com/maps/dir/?api=1&destination=' + address.lat + ',' + address.lng;
            sheetViewAddress.href = streetUrlTpl.replace('__STREET__', encodeURIComponent(address.street)) + '#address-' + address.id;
            if (sheetPin) sheetPin.textContent = address.precise ? 'Move dot' : 'Pin dot';

            // Reset form
            sheetForm.reset();
            document.getElementById('sheetAddressId').value = address.id;
            sheetError.classList.add('hidden');
            sheetError.textContent = '';
            sheetSubmit.disabled = false;
            sheetSubmit.textContent = 'Save Result';

            sheetEl.classList.remove('hidden');
            requestAnimationFrame(function () { sheetPanel.classList.remove('translate-y-full'); });
            document.body.style.overflow = 'hidden';
        }

        function closeSheet() {
            sheetPanel.classList.add('translate-y-full');
            setTimeout(function () {
                sheetEl.classList.add('hidden');
                document.body.style.overflow = '';
                sheetCtx = null;
            }, 200);
        }

        sheetCloseBtn.addEventListener('click', closeSheet);
        sheetBackdrop.addEventListener('click', closeSheet);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !sheetEl.classList.contains('hidden')) closeSheet();
        });

        if (sheetPin) {
            sheetPin.addEventListener('click', function () {
                if (!sheetCtx) return;
                var ctx = sheetCtx;
                closeSheet();
                setTimeout(function () { startMove(ctx.address, ctx.marker); }, 220);
            });
        }

        sheetForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!sheetCtx) return;
            sheetError.classList.add('hidden');
            sheetSubmit.disabled = true;
            sheetSubmit.textContent = 'Saving…';

            fetch('{{ route('knock-result.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(sheetForm),
            }).then(function (r) {
                if (!r.ok) {
                    return r.json().then(function (j) {
                        throw new Error(j.message || ('HTTP ' + r.status));
                    }).catch(function () { throw new Error('HTTP ' + r.status); });
                }
                return r.json();
            }).then(function (json) {
                var a = sheetCtx.address;
                a.response   = json.response;
                a.likelihood = json.likelihood;
                a.turnout    = json.turnout;
                a.notes      = json.notes;
                a.canvasser  = json.canvasser;
                a.knocked_at = json.knocked_at;

                var fn = COLOR_FNS[currentView];
                if (fn) sheetCtx.marker.setStyle({ fillColor: fn(a) });

                closeSheet();
            }).catch(function (err) {
                sheetError.textContent = 'Could not save (' + err.message + '). Try again.';
                sheetError.classList.remove('hidden');
                sheetSubmit.disabled = false;
                sheetSubmit.textContent = 'Save Result';
            });
        });

        // ── View switching ───────────────────────────────────────────────────

        var CHOROPLETH_VIEWS = ['coverage', 'support'];
        var currentView = localStorage.getItem('mapView') || 'supporter';

        function applyView(view) {
            currentView = view;
            localStorage.setItem('mapView', view);

            if (CHOROPLETH_VIEWS.indexOf(view) !== -1) {
                if (map.hasLayer(clusterGroup)) map.removeLayer(clusterGroup);
                if (map.hasLayer(tileLayer))   map.removeLayer(tileLayer);
                hideHexGrid();
                showHexGrid(view);
                if (hexLayer) map.fitBounds(hexLayer.getBounds().pad(0.05));
            } else {
                hideHexGrid();
                if (!map.hasLayer(tileLayer))   tileLayer.addTo(map);
                if (!map.hasLayer(clusterGroup)) map.addLayer(clusterGroup);
                var fn = COLOR_FNS[view];
                markers.forEach(function (m, i) { m.setStyle({ fillColor: fn(addresses[i]) }); });
            }

            document.getElementById('legend').innerHTML = renderLegend(view);
            document.querySelectorAll('.view-tab').forEach(function (btn) {
                var active = btn.dataset.view === view;
                btn.className = 'view-tab px-3 py-1.5 rounded text-sm font-medium transition '
                    + (active ? 'bg-[#6AB023] text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700');
            });
        }

        document.querySelectorAll('.view-tab').forEach(function (btn) {
            btn.addEventListener('click', function () { applyView(btn.dataset.view); });
        });

        // Honour ?focus=N — center on that address and open its sheet.
        var focusId = parseInt(new URLSearchParams(window.location.search).get('focus') || '0', 10);
        if (focusId) {
            currentView = 'supporter';
            localStorage.setItem('mapView', currentView);
        }

        applyView(currentView);

        if (focusId) {
            var idx = addresses.findIndex(function (a) { return a.id === focusId; });
            if (idx !== -1) {
                var focusMarker = markers[idx];
                var focusAddress = addresses[idx];
                map.setView(focusMarker.getLatLng(), 18);
                if (typeof clusterGroup.zoomToShowLayer === 'function') {
                    clusterGroup.zoomToShowLayer(focusMarker, function () { openSheet(focusAddress, focusMarker); });
                } else {
                    openSheet(focusAddress, focusMarker);
                }
            }
        }
    })();
    </script>

    <script>
    document.getElementById('wardSelect')?.addEventListener('change', function () {
        window.location.href = this.value;
    });
    </script>
</x-app-layout>
