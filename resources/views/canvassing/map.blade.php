<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

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

                {{-- Ward switcher --}}
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

            {{-- Stats bar --}}
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
            <div class="mb-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                {{ $totalCount - $geocodedCount }} address{{ ($totalCount - $geocodedCount) === 1 ? '' : 'es' }} couldn't be placed on the map (missing postcode coordinates).
                @if(auth()->user()->isAdmin())
                    Run <code class="font-mono bg-amber-100 dark:bg-amber-900 px-1 rounded">php artisan addresses:geocode</code> to retry.
                @endif
            </div>
            @endif

            {{-- Legend --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-4 py-3 mb-4 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <span class="font-medium text-gray-600 dark:text-gray-300 mr-1">Legend:</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span>Not knocked</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>Supporter</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>Opposition</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-orange-400"></span>Not home</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-yellow-400"></span>Undecided</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-slate-500"></span>Refused / won't vote</span>
                <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-red-900"></span>Do not knock</span>
            </div>

            {{-- Map --}}
            <div id="map" class="rounded-lg shadow" style="height: calc(100vh - 320px); min-height: 400px;"></div>

        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPcM=" crossorigin=""></script>
    <script>
    (function () {
        var addresses = @json($addressData);

        var map = L.map('map');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        function dotColor(address) {
            if (address.dnk) return '#7f1d1d';
            switch (address.response) {
                case 'labour':
                case 'green':
                case 'lib_dem':
                case 'your_party':  return '#22c55e';
                case 'conservative':
                case 'reform':      return '#ef4444';
                case 'not_home':    return '#fb923c';
                case 'undecided':   return '#facc15';
                case 'refused':
                case 'wont_vote':   return '#64748b';
                case 'other':       return '#0d9488';
                default:            return '#9ca3af'; // not knocked
            }
        }

        function responseLabel(response) {
            var labels = {
                not_home:     'Not Home',
                conservative: 'Conservative',
                labour:       'Labour',
                lib_dem:      'Liberal Democrat',
                green:        'Green Party',
                reform:       'Reform UK',
                your_party:   'Your Party',
                undecided:    'Undecided',
                refused:      'Refused to Say',
                wont_vote:    "Won't Vote",
                other:        'Other Party',
            };
            return response ? (labels[response] || response) : 'Not yet knocked';
        }

        function turnoutLabel(turnout) {
            return turnout ? ({ wont: "Won't vote", might: 'Might vote', will: 'Will vote' }[turnout] || turnout) : null;
        }

        var markers = [];

        addresses.forEach(function (a) {
            var color = dotColor(a);
            var marker = L.circleMarker([a.lat, a.lng], {
                radius: 7,
                fillColor: color,
                color: '#fff',
                weight: 1.5,
                opacity: 1,
                fillOpacity: 0.9,
            });

            var turnout = turnoutLabel(a.turnout);
            var popup = '<div style="min-width:160px">'
                + '<strong>' + a.label + '</strong><br>'
                + '<span style="color:#6b7280;font-size:0.85em">' + a.address + '</span><br><br>'
                + '<span style="font-weight:600">' + responseLabel(a.response) + '</span>'
                + (turnout ? '<br><span style="font-size:0.85em">' + turnout + '</span>' : '')
                + (a.dnk ? '<br><span style="color:#b91c1c;font-size:0.85em">⚠ Do not knock</span>' : '')
                + '</div>';

            marker.bindPopup(popup);
            marker.addTo(map);
            markers.push(marker);
        });

        if (markers.length > 0) {
            var group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        } else {
            // Default to Halifax if no geocoded addresses
            map.setView([53.7248, -1.8658], 13);
        }
    })();
    </script>

    <script>
    document.getElementById('wardSelect')?.addEventListener('change', function () {
        window.location.href = this.value;
    });
    </script>
</x-app-layout>
