<template>
  <div class="h-full w-full relative bg-gray-100 flex items-center justify-center">
    <div v-if="loading" class="absolute inset-0 z-[1000] flex flex-col items-center justify-center bg-white/80 backdrop-blur-sm text-[#ab715c]">
      <svg class="animate-spin h-10 w-10 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span class="font-bold text-sm tracking-widest uppercase">Lade Umgebungskarte...</span>
    </div>
    <div v-if="error" class="text-sm font-bold text-red-500 bg-white p-4 rounded shadow z-50">
        {{ error }}
    </div>
    <!-- Leaflet container needs to be absolute or grow to bounds -->
    <div ref="mapContainer" class="absolute inset-0 z-10 border-0 outline-none"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    addressString: String,
    poiSettings: Object
});

const mapContainer = ref(null);
const loading = ref(true);
const error = ref(null);
let map = null;

const catColors = {
    supermarket: '#16a34a',
    school: '#2563eb',
    transit: '#ca8a04',
    restaurant: '#dc2626',
    park: '#059669',
    pharmacy: '#9333ea'
};
const catNames = {
    supermarket: 'Einkauf',
    school: 'Bildung / Kita',
    transit: 'ÖPNV',
    restaurant: 'Restaurant / Gastro',
    park: 'Natur / Park',
    pharmacy: 'Arzt / Apotheke'
};

const getOverpassTags = (cat) => {
    switch(cat) {
        case 'supermarket': return `["shop"~"supermarket|convenience"]`;
        case 'school': return `["amenity"~"school|kindergarten|college"]`;
        case 'transit': return `["public_transport"="stop_position"]`;
        case 'restaurant': return `["amenity"~"restaurant|cafe"]`;
        case 'park': return `["leisure"="park"]`;
        case 'pharmacy': return `["amenity"~"pharmacy|doctors|clinic"]`;
        default: return '';
    }
};

const createMarkerIcon = (color) => {
    return L.divIcon({
        html: `<div style="background-color:${color};width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.3);"></div>`,
        className: 'custom-poi-marker',
        iconSize: [20, 20],
        iconAnchor: [10, 10]
    });
};

onMounted(async () => {
    try {
        // 1. Geocode
        const nomRes = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(props.addressString)}`);
        const nomData = await nomRes.json();
        
        if (!nomData || nomData.length === 0) {
            throw new Error('Adresse nicht gefunden.');
        }
        
        const lat = parseFloat(nomData[0].lat);
        const lon = parseFloat(nomData[0].lon);
        
        // 2. Initialize leaflet
        map = L.map(mapContainer.value, {
            zoomControl: false // Move zoom control or disable default to reposition later if needed
        }).setView([lat, lon], 15);
        L.control.zoom({ position: 'bottomright' }).addTo(map);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO'
        }).addTo(map);
        
        // 3. Project Pin
        L.marker([lat, lon], {
            icon: L.divIcon({
                html: `<div style="background-color:#ab715c;width:28px;height:28px;border-radius:50%;border:4px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.4);position:relative;z-index:999;"><div style="position:absolute;inset:5px;background:white;border-radius:50%;"></div></div>`,
                className: '',
                iconSize: [28,28],
                iconAnchor: [14,14]
            }),
            zIndexOffset: 1000
        }).bindPopup(`<b>Standardstandort (<span style="color:#ab715c">Projekt</span>)</b><br>${props.addressString}`).addTo(map);

        // 4. Load POIs via overpass if active
        if (props.poiSettings?.active && props.poiSettings.categories?.length) {
            const rad = props.poiSettings.radius || 2000;
            
            let queryParts = props.poiSettings.categories.map(c => {
                const tag = getOverpassTags(c);
                if (!tag) return '';
                // Need to match node, way, relation ideally, but node is fastest. We'll use nwr to match all POI types
                return `nwr${tag}(around:${rad},${lat},${lon});`;
            }).join('\n');
            
            const overpassQuery = `[out:json][timeout:25];(${queryParts});out center;`;
            
            const opRes = await fetch('https://overpass-api.de/api/interpreter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: "data=" + encodeURIComponent(overpassQuery)
            });
            const opData = await opRes.json();
            
            if (opData && opData.elements) {
                opData.elements.forEach(el => {
                    let cat = null;
                    const tags = el.tags || {};
                    if (tags.shop?.match(/supermarket|convenience/)) cat = 'supermarket';
                    else if (tags.amenity?.match(/school|kindergarten|college/)) cat = 'school';
                    else if (tags.public_transport === 'stop_position') cat = 'transit';
                    else if (tags.amenity?.match(/restaurant|cafe/)) cat = 'restaurant';
                    else if (tags.leisure === 'park') cat = 'park';
                    else if (tags.amenity?.match(/pharmacy|doctors|clinic/)) cat = 'pharmacy';
                    
                    if (cat) {
                        const name = tags.name || catNames[cat] || 'POI';
                        const clat = el.lat || el.center?.lat;
                        const clon = el.lon || el.center?.lon;
                        if(clat && clon) {
                            L.marker([clat, clon], {
                                icon: createMarkerIcon(catColors[cat] || '#888')
                            })
                            .bindPopup(`<b>${name}</b><br><span style="color:gray;font-size:11px;">${catNames[cat]}</span>`)
                            .addTo(map);
                        }
                    }
                });
            }
        }
        
    } catch (e) {
        console.error(e);
        error.value = 'Karte konnte nicht geladen werden.';
    } finally {
        loading.value = false;
        if(map) {
            setTimeout(() => { map.invalidateSize(); }, 500);
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
/* Leaflet UI adjustments to not overlap with our application UI significantly */
.leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important;
    border-radius: 8px !important;
    overflow: hidden;
}
.leaflet-control-zoom a {
    color: #4a4a4a !important;
    background-color: #fff !important;
}
.leaflet-control-zoom a:hover {
    color: #ab715c !important;
}
</style>
