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

        function buildPopup(a) {
            var streetUrl = streetUrlTpl.replace('__STREET__', encodeURIComponent(a.street)) + '#address-' + a.id;
            var html = '<div style="min-width:180px;font-size:0.875rem">'
                + '<strong><a href="' + streetUrl + '" style="color:#6AB023">' + esc(a.label) + '</a></strong><br>'
                + '<span style="color:#6b7280">' + esc(a.address) + '</span>';

            if (a.response) {
                html += '<hr style="margin:6px 0;border-color:#e5e7eb">'
                    + '<strong>' + esc(RESPONSE_LABELS[a.response] || a.response) + '</strong>';
                if (a.likelihood) html += ' &nbsp;<span style="color:#6b7280">Likelihood: ' + a.likelihood + '/5</span>';
                if (a.turnout)    html += '<br><span style="color:#6b7280">' + esc(TURNOUT_LABELS[a.turnout] || a.turnout) + '</span>';
                if (a.notes)      html += '<br><em style="color:#374151">' + esc(a.notes) + '</em>';
                html += '<hr style="margin:6px 0;border-color:#e5e7eb">'
                    + '<span style="color:#6b7280;font-size:0.8em">';
                if (a.canvasser)  html += 'By ' + esc(a.canvasser);
                if (a.knocked_at) html += (a.canvasser ? ' · ' : '') + esc(a.knocked_at);
                html += '</span>';
            } else {
                html += '<br><span style="color:#9ca3af">Not yet knocked</span>';
            }

            if (a.dnk) html += '<br><span style="color:#b91c1c;font-size:0.8em">⚠ Do not knock</span>';
            html += '</div>';
            return html;
        }

        // ── Spiral layout: merge postcodes whose fans overlap, then place each
        //    group's addresses in a Vogel sunflower spiral around the centroid
        //    so dots never overlap regardless of postcode density. ───────────

        var SPIRAL_C = 0.0000226;                        // base spacing ≈ 2.5 m
        var GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
        var MERGE_THRESHOLD = 0.00018;                   // ~20 m between centroids

        var byCoord = {};
        addresses.forEach(function (a, i) {
            var key = a.lat.toFixed(7) + ',' + a.lng.toFixed(7);
            if (!byCoord[key]) byCoord[key] = { lat: a.lat, lng: a.lng, idxs: [] };
            byCoord[key].idxs.push(i);
        });
        var postcodes = Object.values(byCoord);

        var parent = postcodes.map(function (_, i) { return i; });
        function find(i) { while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; } return i; }

        function planarDist(a, b) {
            var latRad = a.lat * Math.PI / 180;
            var dlat = a.lat - b.lat;
            var dlng = (a.lng - b.lng) * Math.cos(latRad);
            return Math.sqrt(dlat * dlat + dlng * dlng);
        }

        for (var i = 0; i < postcodes.length; i++) {
            for (var j = i + 1; j < postcodes.length; j++) {
                var ri = find(i), rj = find(j);
                if (ri === rj) continue;
                if (planarDist(postcodes[i], postcodes[j]) < MERGE_THRESHOLD) {
                    parent[ri] = rj;
                }
            }
        }

        var groups = {};
        postcodes.forEach(function (_, i) {
            var root = find(i);
            (groups[root] = groups[root] || []).push(i);
        });

        Object.values(groups).forEach(function (pcIdxs) {
            var allIdxs = [], sumLat = 0, sumLng = 0;
            pcIdxs.forEach(function (pcIdx) {
                var pc = postcodes[pcIdx];
                sumLat += pc.lat;
                sumLng += pc.lng;
                allIdxs.push.apply(allIdxs, pc.idxs);
            });
            var meanLat = sumLat / pcIdxs.length;
            var meanLng = sumLng / pcIdxs.length;
            var latRad  = meanLat * Math.PI / 180;
            var n = allIdxs.length;

            if (n === 1) {
                addresses[allIdxs[0]].lat = meanLat;
                addresses[allIdxs[0]].lng = meanLng;
                return;
            }

            allIdxs.forEach(function (addrIdx, k) {
                var r     = SPIRAL_C * Math.sqrt(k + 0.5);
                var theta = k * GOLDEN_ANGLE;
                addresses[addrIdx].lat = meanLat + r * Math.cos(theta);
                addresses[addrIdx].lng = meanLng + r * Math.sin(theta) / Math.cos(latRad);
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
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        });
        tileLayer.addTo(map);

        var clusterGroup = L.markerClusterGroup({ maxClusterRadius: 40, disableClusteringAtZoom: 15 });

        var markers = addresses.map(function (a) {
            var marker = L.circleMarker([a.lat, a.lng], {
                radius: 7, fillColor: '#9ca3af',
                color: '#fff', weight: 1.5, opacity: 1, fillOpacity: 0.9,
            });
            marker.bindPopup(function() { return buildPopup(a); });
            clusterGroup.addLayer(marker);
            return marker;
        });

        map.addLayer(clusterGroup);

        if (markers.length > 0) {
            map.fitBounds(clusterGroup.getBounds().pad(0.1));
        } else {
            map.setView([53.7248, -1.8658], 13);
        }

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

        applyView(currentView);
    })();
    </script>

    <script>
    document.getElementById('wardSelect')?.addEventListener('change', function () {
        window.location.href = this.value;
    });
    </script>
</x-app-layout>
