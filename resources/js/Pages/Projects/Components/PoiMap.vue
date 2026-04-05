<template>
  <div class="h-full w-full relative bg-gray-100 flex items-center justify-center font-sans">
    <div v-if="loading" class="absolute inset-0 z-[1000] flex flex-col items-center justify-center bg-white/80 backdrop-blur-sm text-[#ab715c]">
      <svg class="animate-spin h-10 w-10 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span class="font-bold text-sm tracking-widest uppercase">Lade Umgebungskarte...</span>
    </div>
    
    <div v-if="error" class="absolute top-4 left-4 z-[900] text-sm font-bold text-red-500 bg-white p-4 rounded shadow">
        {{ error }}
    </div>

    <!-- Right Sidebar Legend & Filters -->
    <div v-if="!loading && (allPois.length || allIsochrones.length)" class="absolute top-4 right-4 z-[500] bg-white rounded-xl shadow-[0_4px_20px_rgba(0,0,0,0.15)] flex flex-col w-[240px] max-h-[calc(100%-32px)] border border-gray-100 overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 shrink-0">
            <h4 class="font-black text-gray-800 text-[14px]">Umgebung & Distanzen</h4>
            <p class="text-[11px] text-gray-500 font-medium leading-tight mt-0.5">Filter für Orte und Erreichbarkeit</p>
        </div>
        
        <div class="p-4 overflow-y-auto flex-1 space-y-5 custom-scrollbar">
            <!-- Isochrones Filter -->
            <div v-if="allIsochrones.length">
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Erreichbarkeit</div>
                <div class="space-y-2.5">
                    <label v-for="iso in allIsochrones" :key="iso.id" class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" v-model="activeIsochrones" :value="iso.id" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 w-4 h-4 transition" />
                        <div class="flex items-center gap-2 flex-1">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: isoColors[iso.type] || '#ccc', opacity: 0.8 }"></span>
                            <span class="text-[13px] font-semibold text-gray-700 group-hover:text-black transition">
                                {{ isoNames[iso.type] }} ({{ iso.minutes }} Min)
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- POI Categories Filter -->
            <div v-if="allPois.length">
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Orte & Kategorien</div>
                <div class="space-y-2.5">
                    <label v-for="cat in availableCategories" :key="cat" class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" v-model="activeCategories" :value="cat" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 w-4 h-4 transition" />
                        <div class="flex items-center gap-2 flex-1">
                            <span class="w-3.5 h-3.5 rounded-full border border-white shadow-sm shrink-0" :style="{ backgroundColor: catColors[cat] || '#888' }"></span>
                            <span class="text-[13px] font-semibold text-gray-700 group-hover:text-black transition truncate">
                                {{ catNames[cat] || cat }}
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Sun Settings (New) -->
            <div v-if="poiSettings?.show_sun" class="pt-4 border-t border-gray-100">
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Extra</div>
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" v-model="showSunControls" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400 w-4 h-4 transition" />
                    <div class="flex items-center gap-2 flex-1">
                        <span class="w-3.5 h-3.5 rounded-full bg-yellow-400 shrink-0 shadow-sm border border-white"></span>
                        <span class="text-[13px] font-semibold text-gray-700 group-hover:text-black transition">Sonnenstand</span>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- Leaflet container needs to be absolute or grow to bounds -->
    <div ref="mapContainer" class="absolute inset-0 z-10 border-0 outline-none"></div>

    <!-- Sun Controls Overlay -->
    <div v-if="showSunControls" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-[500] w-[90%] max-w-[400px]">
        <div class="bg-white/90 backdrop-blur shadow-2xl rounded-2xl p-4 border border-brand-100 animate-in fade-in slide-in-from-bottom-4 duration-300">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center shadow-inner">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7a5 5 0 100 10 5 5 0 000-10zM2 13h2a1 1 0 100-2H2a1 1 0 100 2zm18 0h2a1 1 0 100-2h-2a1 1 0 100 2zM11 2v2a1 1 0 102 0V2a1 1 0 10-2 0zm0 18v2a1 1 0 102 0v-2a1 1 0 10-2 0zM5.99 4.58a1 1 0 111.41 1.41L6.05 7.34a1 1 0 01-1.41-1.41l1.35-1.35zm12.02 12.02a1 1 0 111.41 1.41l-1.35 1.35a1 1 0 11-1.41-1.41l1.35-1.35zM5.99 19.42l-1.35-1.35a1 1 0 111.41-1.41l1.35 1.35a1 1 0 11-1.41 1.41zm12.02-12.02l1.35-1.35a1 1 0 011.41 1.41l-1.35 1.35a1 1 0 11-1.41-1.41z"/></svg>
                    </div>
                    <div>
                        <div class="text-[14px] font-black text-gray-800 leading-tight">Sonnenstand</div>
                        <div class="text-[11px] text-gray-400 font-bold tracking-wider">{{ currentTimeLabel }}</div>
                    </div>
                </div>
                <button @click="showSunControls = false" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <input type="range" v-model="sunHour" min="0" max="23" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-yellow-400" />
            
            <div class="flex justify-between mt-2 px-1 text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                <span>00:00</span>
                <span>06:00</span>
                <span>12:00</span>
                <span>18:00</span>
                <span>24:00</span>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    addressString: String,
    poiSettings: Object,
    projectId: [String, Number]
});

// Sun Logic State
const showSunControls = ref(false);
const sunHour = ref(new Date().getHours() + new Date().getMinutes() / 60);
const currentTimeLabel = computed(() => {
    const h = Math.floor(sunHour.value);
    const m = Math.floor((sunHour.value % 1) * 60);
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')} Uhr`;
});

// Sun Position Calculation (Utility)
const getSunPosition = (hour, lat, lng) => {
    const rad = Math.PI / 180;
    const now = new Date();
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(hour), (hour % 1) * 60);
    
    const dayMs = 1000 * 60 * 60 * 24;
    const J1970 = 2440588, J2000 = 2451545;
    const toJulian = (d) => d.valueOf() / dayMs - 0.5 + J1970;
    const toDays = (d) => toJulian(d) - J2000;

    const d = toDays(date);
    const lw = rad * -lng;
    const phi = rad * lat;

    const e = rad * 23.4397; // obliquity of the Earth
    const M = rad * (357.5291 + 0.98560028 * d);
    const C = rad * (1.9148 * Math.sin(M) + 0.02 * Math.sin(2 * M) + 0.0003 * Math.sin(3 * M));
    const lambda = M + C + rad * 102.9372 + Math.PI;

    const dec = Math.asin(Math.sin(e) * Math.sin(lambda));
    const ra = Math.atan2(Math.cos(e) * Math.sin(lambda), Math.cos(lambda));
    const siderealTime = rad * (280.16 + 360.9856235 * d) - lw;
    const H = siderealTime - ra;

    const alt = Math.asin(Math.sin(phi) * Math.sin(dec) + Math.cos(phi) * Math.cos(dec) * Math.cos(H));
    const az = Math.atan2(Math.sin(H), Math.cos(H) * Math.sin(phi) - Math.tan(dec) * Math.cos(phi));

    return {
        azimuth: (az * 180 / Math.PI), // Azimuth in degrees
        altitude: alt * 180 / Math.PI
    };
};

const mapContainer = ref(null);
const loading = ref(true);
const error = ref(null);
let map = null;

// Backend Data Source
const allPois = ref([]);
const allIsochrones = ref([]);

// Filter State
const activeIsochrones = ref([]);
const activeCategories = ref([]);
const availableCategories = ref([]);

// Layers reference to add/remove without recreating map
let poiLayerGroup = null;
let isoLayerGroup = null;
let sunLayerGroup = null;

let projectLat = 51.165691;
let projectLon = 10.451526;

const catColors = {
    supermarket: '#16a34a', // green
    school: '#2563eb', // blue
    transit: '#ca8a04', // yellow
    restaurant: '#dc2626', // red
    park: '#059669', // emerald
    pharmacy: '#9333ea', // purple
    bank: '#0ea5e9', // light blue
    fitness: '#f97316', // orange
    culture: '#be185d', // pink
    gas: '#475569', // slate
    bakery: '#c2410c', // rust
    parking: '#334155', // dark slate
    playground: '#14b8a6', // teal
    hospital: '#e11d48', // rose
    clothing: '#fbbf24', // amber
    hotel: '#8b5cf6', // violet
    hairdresser: '#f472b6', // pink soft
};
const catNames = {
    supermarket: 'Einkauf',
    school: 'Bildung / Kita',
    transit: 'ÖPNV',
    restaurant: 'Restaurant / Gastro',
    park: 'Natur / Park',
    pharmacy: 'Arzt / Apotheke',
    bank: 'Bank / Geldautomat',
    fitness: 'Fitness / Sport',
    culture: 'Kultur / Kino',
    gas: 'Tankstelle',
    bakery: 'Bäckerei',
    parking: 'Parken',
    playground: 'Spielplatz',
    hospital: 'Krankenhaus',
    clothing: 'Bekleidung / Mode',
    hotel: 'Hotel / Unterkunft',
    hairdresser: 'Friseur / Kosmetik',
};

const isoColors = {
    walking: '#22c55e', // green
    cycling: '#3b82f6', // blue
    driving: '#f43f5e', // rose
};
const isoNames = {
    walking: 'Zu Fuß',
    cycling: 'Fahrrad',
    driving: 'Auto'
};

const createMarkerIcon = (color) => {
    return L.divIcon({
        html: `<div style="background-color:${color};width:16px;height:16px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.4);"></div>`,
        className: 'custom-poi-marker',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });
};

// Ray-casting algorithm for Point in Polygon checking
// coords is an array of [lon, lat] pairs defining the ring
const pointInPolygon = (point, vs) => {
    const x = point[0], y = point[1]; // lon, lat
    let inside = false;
    for (let i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        const xi = vs[i][0], yi = vs[i][1];
        const xj = vs[j][0], yj = vs[j][1];
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
};

// Evaluate if a POI [lat, lon] is inside ANY of the currently active isochrones
const isPoiInActiveIsochrones = (lat, lon) => {
    if (activeIsochrones.value.length === 0) return false; // If no isochrone is checked, show none!

    const point = [lon, lat]; // GeoJSON uses lon, lat
    
    for (const isoId of activeIsochrones.value) {
        const isoData = allIsochrones.value.find(i => i.id === isoId);
        if (!isoData || !isoData.geojson) continue;
        
        const features = isoData.geojson.features || [];
        for (const feature of features) {
            if (feature.geometry && feature.geometry.coordinates) {
                // GeoJSON coordinates structure for Polygon: [[[lon, lat], [lon, lat], ...]]
                const polygonRings = feature.geometry.coordinates;
                for (const ring of polygonRings) {
                    if (pointInPolygon(point, ring)) {
                        return true; // Found in at least one active isochrone
                    }
                }
            }
        }
    }
    return false;
};

const renderLayers = () => {
    if (!map) return;
    
    // Clear old layers
    if (poiLayerGroup) {
        poiLayerGroup.clearLayers();
    } else {
        poiLayerGroup = L.layerGroup().addTo(map);
    }
    
    if (isoLayerGroup) {
        isoLayerGroup.clearLayers();
    } else {
        isoLayerGroup = L.layerGroup().addTo(map);
    }

    // 1. Draw Isochrones
    const activeIsoData = allIsochrones.value.filter(i => activeIsochrones.value.includes(i.id));
    activeIsoData.forEach(iso => {
        if (iso.geojson) {
            L.geoJSON(iso.geojson, {
                style: {
                    color: isoColors[iso.type] || '#3388ff',
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.15
                }
            }).addTo(isoLayerGroup);
        }
    });

    // 2. Adjust Map Bounds if we have isochrones
    if (activeIsoData.length > 0) {
        const bounds = L.featureGroup(isoLayerGroup.getLayers()).getBounds();
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
        }
    }

    // 3. Draw POIs that match category AND spatial filter
    allPois.value.forEach(poi => {
        // Category Filter
        if (!activeCategories.value.includes(poi.category)) return;
        
        // Spatial Filter (nur pois in aktivierten Isochronen, unless none active)
        if (!isPoiInActiveIsochrones(poi.lat, poi.lon)) return;

        L.marker([poi.lat, poi.lon], {
            icon: createMarkerIcon(catColors[poi.category] || '#888')
        })
        .bindPopup(`<b>${poi.name}</b><br><span style="color:gray;font-size:11px;">${poi.label}</span>`)
        .addTo(poiLayerGroup);
    });

    // 4. Draw Sun Visualization
    if (sunLayerGroup) {
        sunLayerGroup.clearLayers();
    } else {
        sunLayerGroup = L.layerGroup().addTo(map);
    }

    if (showSunControls.value) {
        const sunPos = getSunPosition(sunHour.value, projectLat, projectLon);
        const radius = 1000; // Visualization distance
        const rad = Math.PI / 180;
        
        // Standard Map Projection: Azimuth 0 is South, -90 is East, +90 is West
        // y (Latitude) = center_lat - R * cos(azimuth)
        // x (Longitude) = center_lon - R * sin(azimuth)
        const sunPoint = [
            projectLat - (radius / 111320) * Math.cos(sunPos.azimuth * rad),
            projectLon - (radius / (111320 * Math.cos(projectLat * rad))) * Math.sin(sunPos.azimuth * rad)
        ];

        const isNight = sunPos.altitude < 0;
        const beamColor = isNight ? '#1e293b' : '#fbbf24';
        const beamOpacity = Math.max(0, Math.min(0.5, (sunPos.altitude + 10) / 40));

        // Light Beam / Direction Indicator
        if (!isNight) {
            // Draw a cone or line indicating direction
            const beamWidth = 20; // degrees
            const leftAngle = (sunPos.azimuth - 180 - beamWidth) * rad;
            const rightAngle = (sunPos.azimuth - 180 + beamWidth) * rad;
            
            const p1 = [
                projectLat + (radius / 111320) * Math.cos(leftAngle),
                projectLon + (radius / (111320 * Math.cos(projectLat * rad))) * Math.sin(leftAngle)
            ];
            const p2 = [
                projectLat + (radius / 111320) * Math.cos(rightAngle),
                projectLon + (radius / (111320 * Math.cos(projectLat * rad))) * Math.sin(rightAngle)
            ];

            L.polygon([[projectLat, projectLon], p1, p2], {
                color: 'transparent',
                fillColor: beamColor,
                fillOpacity: beamOpacity,
                interactive: false
            }).addTo(sunLayerGroup);

            // Sun Icon Marker
            const sunIcon = L.divIcon({
                html: `
                    <div class="relative">
                        <div class="absolute inset-[-12px] bg-yellow-400 rounded-full animate-pulse blur-md opacity-40"></div>
                        <div class="w-6 h-6 bg-gradient-to-br from-yellow-300 to-orange-400 rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                             <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg>
                        </div>
                    </div>`,
                className: '',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            L.marker(sunPoint, { icon: sunIcon, interactive: false, zIndexOffset: 2000 }).addTo(sunLayerGroup);
        } else {
            // Night visualization
            L.circle([projectLat, projectLon], {
                radius: radius,
                color: 'transparent',
                fillColor: '#0f172a',
                fillOpacity: Math.min(0.3, Math.abs(sunPos.altitude) / 60),
                interactive: false
            }).addTo(sunLayerGroup);
        }
    }
};

watch([activeIsochrones, activeCategories, showSunControls, sunHour], () => {
    // Re-render when checkboxes or sun controls change
    renderLayers();
}, { deep: true });

onMounted(async () => {
    let lat = 51.165691;
    let lon = 10.451526;
    let addressFound = false;

    try {

        // Fetch aggregated, cached Data from our Backend API instead of querying Overpass directly!
        if (props.projectId) {
            const res = await fetch(`/p/${props.projectId}/map-data`);
            if (res.ok) {
                const data = await res.json();
                
                if (data.lat && data.lon) {
                    projectLat = lat = data.lat;
                    projectLon = lon = data.lon;
                    addressFound = data.addressFound;
                }
                
                allPois.value = data.pois || [];
                allIsochrones.value = data.isochrones || [];
                
                // Initialize Filters
                if (allIsochrones.value.length > 0) {
                    // Activate shortest driving or walking by default
                    activeIsochrones.value = [allIsochrones.value[0].id];
                }
                
                // Determine which categories exist in the POIs to build the legend
                const cats = new Set(allPois.value.map(p => p.category));
                availableCategories.value = Array.from(cats).sort();
                
                // Activate all categories by default
                activeCategories.value = [...availableCategories.value];
            }
        }

        // Initialize leaflet
        if (!mapContainer.value) return;

        // Cinematic Start Location (Far away)
        map = L.map(mapContainer.value, {
            zoomControl: false,
            attributionControl: false
        }).setView([48.0, 8.0], 5);
        
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.control.attribution({ position: 'bottomleft' }).addTo(map);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
        }).addTo(map);
        
        // Project Pin Center
        if (addressFound) {
            L.marker([lat, lon], {
                icon: L.divIcon({
                    html: `<div style="background-color:#ab715c;width:28px;height:28px;border-radius:50%;border:4px solid white;box-shadow:0 3px 12px rgba(0,0,0,0.5);position:relative;z-index:999;"><div style="position:absolute;inset:5px;background:white;border-radius:50%;"></div></div>`,
                    className: '',
                    iconSize: [28,28],
                    iconAnchor: [14,14]
                }),
                zIndexOffset: 1000
            }).bindPopup(`<b>Projektstandort</b><br>${props.addressString || ''}`).addTo(map);
        } else {
            error.value = 'Achtung: Exakte Adresse konnte nicht lokalisiert werden.';
        }

        // Render initial view
        renderLayers();
        
    } catch (e) {
        console.error('Kritischer Fehler bei Leaflet Setup:', e);
        error.value = 'Fehler beim Laden der Kartendaten.';
    } finally {
        loading.value = false;
        if(map) {
            setTimeout(() => { 
                map.invalidateSize(); 
                // Cinematic Fly-In
                map.flyTo([lat, lon], typeof addressFound !== 'undefined' && addressFound ? 15 : 12, {
                    animate: true,
                    duration: 3.5,
                    easeLinearity: 0.2
                });
            }, 600);
        }
    }
});

onUnmounted(() => {
    if (map) {
        map.remove();
    }
});
</script>

<style>
.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important;
    border-radius: 8px !important;
    overflow: hidden;
}
.leaflet-control-zoom a {
    color: #4a4a4a !important;
    background-color: #fff !important;
    width: 32px !important;
    height: 32px !important;
    line-height: 32px !important;
}
.leaflet-control-zoom a:hover {
    color: #ab715c !important;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
/* Tailwind class fixes if not compiled into standard layout */
.text-brand-500 { color: #ab715c; }
.focus\:ring-brand-500:focus { --tw-ring-color: #ab715c; }
</style>
