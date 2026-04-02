<template>
    <div class="space-y-6">
        <!-- Date Filter -->
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2">
                <label class="text-sm font-bold text-gray-600">Von:</label>
                <input type="date" v-model="dateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-bold text-gray-600">Bis:</label>
                <input type="date" v-model="dateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
            </div>
            <button @click="loadStats" class="px-4 py-2 bg-brand-500 text-white text-sm font-bold rounded-lg hover:bg-brand-600 transition">
                Laden
            </button>
            <div v-if="loading" class="text-sm text-gray-400 ml-2 animate-pulse">Laden...</div>
        </div>

        <!-- Sub Tabs -->
        <div class="flex gap-1 border-b border-gray-200">
            <button v-for="t in subTabs" :key="t.key" @click="subTab = t.key"
                    :class="[subTab === t.key ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700', 'px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap']">
                {{ t.label }}
            </button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading && !stats" class="grid grid-cols-4 gap-4">
            <div v-for="n in 4" :key="n" class="bg-gray-100 animate-pulse rounded-xl h-24"></div>
        </div>

        <template v-if="stats">

            <!-- Sub: Übersicht -->
            <div v-if="subTab === 'overview'" class="space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-2xl p-5">
                        <div class="text-3xl font-black text-blue-700">{{ stats.summary.total_visitors }}</div>
                        <div class="text-sm font-bold text-blue-500 mt-1">Besucher</div>
                    </div>
                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 rounded-2xl p-5">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.summary.returning_visitors }}</div>
                        <div class="text-sm font-bold text-emerald-500 mt-1">Wiederkehrend</div>
                    </div>
                    <div class="bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 rounded-2xl p-5">
                        <div class="text-3xl font-black text-amber-700">{{ stats.summary.total_events }}</div>
                        <div class="text-sm font-bold text-amber-500 mt-1">Events</div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-2xl p-5">
                        <div class="text-3xl font-black text-purple-700">{{ stats.summary.avg_events_per_visitor }}</div>
                        <div class="text-sm font-bold text-purple-500 mt-1">Ø Events / Besucher</div>
                    </div>
                </div>

                <!-- Events by Type -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-lg font-black text-gray-800 mb-4">Events nach Typ</h4>
                    <div class="space-y-3">
                        <div v-for="(count, type) in stats.events_by_type" :key="type" class="flex items-center gap-4">
                            <span class="text-sm font-bold text-gray-600 w-40 truncate">{{ eventLabel(type) }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                                <div class="bg-brand-500 h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                                     :style="{ width: Math.max(barPercent(count, maxEventCount), 3) + '%' }">
                                    <span class="text-[11px] font-black text-white">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visitors per Day (simple table) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Besucher pro Tag</h4>
                        <div class="space-y-1 max-h-[300px] overflow-y-auto">
                            <div v-for="(count, date) in stats.visitors_per_day" :key="date" class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                                <span class="text-sm font-medium text-gray-600">{{ date }}</span>
                                <span class="text-sm font-black text-gray-800 bg-gray-100 px-2 py-0.5 rounded-full">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.visitors_per_day).length" class="text-sm text-gray-400 py-4 text-center">Keine Daten</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Events pro Tag</h4>
                        <div class="space-y-1 max-h-[300px] overflow-y-auto">
                            <div v-for="(count, date) in stats.events_per_day" :key="date" class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                                <span class="text-sm font-medium text-gray-600">{{ date }}</span>
                                <span class="text-sm font-black text-gray-800 bg-gray-100 px-2 py-0.5 rounded-full">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.events_per_day).length" class="text-sm text-gray-400 py-4 text-center">Keine Daten</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub: Besucher -->
            <div v-if="subTab === 'visitors'" class="space-y-6">
                <!-- Breakdowns -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Browser</h4>
                        <div v-for="(count, name) in stats.browsers" :key="name" class="flex justify-between py-1.5 text-sm">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Geräte</h4>
                        <div v-for="(count, name) in stats.devices" :key="name" class="flex justify-between py-1.5 text-sm">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Sprachen</h4>
                        <div v-for="(count, name) in stats.languages" :key="name" class="flex justify-between py-1.5 text-sm">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Referrer</h4>
                        <div v-for="(count, name) in stats.referrers" :key="name" class="flex justify-between py-1.5 text-sm">
                            <span class="text-gray-600 truncate mr-2">{{ name }}</span>
                            <span class="font-bold text-gray-800 shrink-0">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Länder</h4>
                        <div v-for="(count, name) in stats.countries" :key="name" class="flex justify-between py-1.5 text-sm">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                        <div v-if="!Object.keys(stats.countries).length" class="text-sm text-gray-400 py-2">Kein Geo-Lookup konfiguriert</div>
                    </div>
                </div>

                <!-- Recent Visitors Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h4 class="font-black text-gray-800">Letzte Besucher</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">IP</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Browser</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">OS</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Gerät</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Sprache</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Besuche</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Erster Besuch</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Letzter Besuch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="v in stats.recent_visitors" :key="v.id" class="border-b border-gray-50 hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-500">#{{ v.id }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ v.ip }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ v.browser }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ v.os }}</td>
                                    <td class="px-4 py-3 text-sm"><span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="deviceClass(v.device)">{{ v.device }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ v.language }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-800">{{ v.visit_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(v.first_visit_at) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(v.last_visit_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sub: Wohnungen -->
            <div v-if="subTab === 'apartments'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-lg font-black text-gray-800 mb-4">Meistgesehene Wohnungen</h4>
                    <div class="space-y-3">
                        <div v-for="(count, apId) in stats.top_apartments" :key="apId" class="flex items-center gap-4">
                            <span class="text-sm font-bold text-gray-600 w-48 truncate">{{ apartmentName(apId) }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                                     :style="{ width: Math.max(barPercent(count, maxTopApCount), 3) + '%' }">
                                    <span class="text-[11px] font-black text-white">{{ count }}×</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!Object.keys(stats.top_apartments).length" class="text-sm text-gray-400 py-4 text-center">Noch keine Wohnungsaufrufe</div>
                    </div>
                </div>
            </div>

            <!-- Sub: Events Live -->
            <div v-if="subTab === 'events'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h4 class="font-black text-gray-800">Letzte Events</h4>
                        <button @click="loadStats" class="text-sm text-brand-600 font-bold hover:text-brand-800 transition">↻ Aktualisieren</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Zeit</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Event</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ziel</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Besucher</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Meta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ev in stats.recent_events" :key="ev.id" class="border-b border-gray-50 hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ formatDate(ev.created_at) }}</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="eventClass(ev.event_type)">{{ eventLabel(ev.event_type) }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <span v-if="ev.target_type">{{ ev.target_type }} #{{ ev.target_id }}</span>
                                        <span v-else class="text-gray-300">–</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ ev.visitor?.browser }} · {{ ev.visitor?.device }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-400 font-mono max-w-[200px] truncate">{{ ev.meta ? JSON.stringify(ev.meta) : '–' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </template>

        <!-- No data state -->
        <div v-if="!loading && !stats" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            <h3 class="text-xl font-black text-gray-500 mb-2">Statistik laden</h3>
            <p class="text-sm text-gray-400 mb-4">Wähle einen Zeitraum und klicke auf "Laden".</p>
            <button @click="loadStats" class="px-6 py-2.5 bg-brand-500 text-white font-bold rounded-xl hover:bg-brand-600 transition">Jetzt laden</button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    projectId: { type: Number, required: true },
    apartments: { type: Array, default: () => [] },
});

const subTabs = [
    { key: 'overview', label: 'Übersicht' },
    { key: 'visitors', label: 'Besucher' },
    { key: 'apartments', label: 'Wohnungen' },
    { key: 'events', label: 'Events' },
];

const subTab = ref('overview');
const loading = ref(false);
const stats = ref(null);

const dateFrom = ref(new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const dateTo = ref(new Date().toISOString().slice(0, 10));

const loadStats = async () => {
    loading.value = true;
    try {
        const res = await fetch(`/tracking/stats/${props.projectId}?from=${dateFrom.value}&to=${dateTo.value}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (res.ok) {
            stats.value = await res.json();
        }
    } catch (e) {
        console.error('Stats load failed', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => loadStats());

// --- Helpers ---
const maxEventCount = computed(() => {
    if (!stats.value?.events_by_type) return 1;
    return Math.max(...Object.values(stats.value.events_by_type), 1);
});

const maxTopApCount = computed(() => {
    if (!stats.value?.top_apartments) return 1;
    return Math.max(...Object.values(stats.value.top_apartments), 1);
});

const barPercent = (count, max) => (count / max) * 100;

const eventLabels = {
    page_view: 'Seitenaufruf',
    apartment_view: 'Wohnung angezeigt',
    favorite: 'Favorisiert',
    map_open: 'Karte geöffnet',
    slider_open: 'Slider geöffnet',
    tour_open: '3D-Rundgang',
    contact_click: 'Kontakt geklickt',
    filter_used: 'Filter genutzt',
    view_change: 'Ansicht gewechselt',
};
const eventLabel = (type) => eventLabels[type] || type;

const eventClass = (type) => {
    const classes = {
        page_view: 'bg-blue-100 text-blue-700',
        apartment_view: 'bg-emerald-100 text-emerald-700',
        favorite: 'bg-red-100 text-red-700',
        map_open: 'bg-amber-100 text-amber-700',
        slider_open: 'bg-purple-100 text-purple-700',
        tour_open: 'bg-indigo-100 text-indigo-700',
        contact_click: 'bg-pink-100 text-pink-700',
    };
    return classes[type] || 'bg-gray-100 text-gray-700';
};

const deviceClass = (device) => {
    if (device === 'desktop') return 'bg-blue-100 text-blue-700';
    if (device === 'mobile') return 'bg-emerald-100 text-emerald-700';
    if (device === 'tablet') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-700';
};

const apartmentName = (id) => {
    const ap = props.apartments.find(a => a.id === parseInt(id));
    return ap ? ap.name : `#${id}`;
};

const formatDate = (d) => {
    if (!d) return '–';
    return new Date(d).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
