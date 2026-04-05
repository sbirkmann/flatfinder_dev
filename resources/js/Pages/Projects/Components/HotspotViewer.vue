<template>
    <div class="w-full h-full relative flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 bg-gray-900 border-b border-gray-800 text-white flex justify-between items-center z-10 shrink-0">
            <div>
                <h4 class="font-bold">Hotspots bearbeiten: {{ point.name }}</h4>
                <p class="text-xs text-gray-400">Doppelklick ins Bild, um einen neuen Hotspot zu setzen.</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="setStartView" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded font-bold text-sm transition flex items-center gap-2">
                    <MapPinIcon class="w-4 h-4" />
                    Startansicht festlegen
                </button>
                <button @click="save" class="bg-[#ab715c] hover:bg-[#8e5c4a] text-white px-4 py-2 rounded font-bold text-sm transition">
                    Speichern
                </button>
            </div>
        </div>

        <div class="flex-1 flex overflow-hidden relative">
            <!-- 360 Viewer Container -->
            <div ref="viewerContainer" class="flex-1 bg-black h-full transition-all"></div>

            <!-- Toggle Button (Sidebar) -->
            <button v-if="hotspots.length > 0" 
                    @click="showSidebar = !showSidebar" 
                    class="absolute top-4 right-4 z-30 bg-gray-900 border border-gray-700 text-white p-2 rounded-xl shadow-xl hover:bg-black transition-colors"
                    :class="{'translate-x-[calc(-100%-1rem)]': !showSidebar}">
                <ChevronRightIcon v-if="showSidebar" class="w-5 h-5" />
                <ChevronLeftIcon v-else class="w-5 h-5" />
            </button>

            <!-- Hotspots Sidebar -->
            <div 
                v-if="hotspots.length > 0 && showSidebar"
                class="w-96 flex-shrink-0 bg-gray-900 border-l border-gray-800 text-white flex flex-col z-20 shadow-2xl transition-all animate-in slide-in-from-right-full" 
            >
                <div class="px-6 py-5 border-b border-gray-800 flex justify-between items-center bg-gray-950/50 backdrop-blur-md">
                    <div>
                        <h5 class="font-black text-sm uppercase tracking-widest text-brand-500">Gesetzte Hotspots</h5>
                        <p class="text-[10px] font-bold text-gray-500 mt-0.5">{{ hotspots.length }} Elemente verknüpft</p>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div v-for="(hs, i) in hotspots" :key="hs.id" 
                         class="bg-gray-800/40 rounded-2xl p-5 relative border border-gray-700/50 hover:border-brand-500/50 transition-all hover:bg-gray-800 shadow-lg">
                        <button @click="removeHotspot(i)" class="absolute top-4 right-4 text-gray-500 hover:text-red-500 p-1.5 hover:bg-red-500/10 rounded-lg transition-colors"><TrashIcon class="w-4 h-4"/></button>
                        
                        <div class="mb-5 pr-8">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Titel / Label</label>
                            <input v-model="hs.label" type="text" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500" @change="updateHotspotMarker(hs)"/>
                        </div>

                        <div class="mb-5">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Verlinken mit</label>
                            <select v-model="hs.link_type" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <option value="none">-- Ohne --</option>
                                <option value="point">Anderer Punkt</option>
                                <option value="apartment">Wohnung</option>
                                <option value="house">Haus</option>
                                <option value="slider">Slider / Popup</option>
                                <option value="tooltip">Info Tooltip</option>
                                <option value="video">Externes Video</option>
                            </select>
                        </div>
                        
                        <div class="mb-5">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Darstellung</label>
                            <select v-model="hs.style_type" @change="updateHotspotMarker(hs)" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <option value="text">Text-Label</option>
                                <option value="icon_arrow">Pfeil Rechts</option>
                                <option value="icon_arrow_up">Pfeil Oben</option>
                                <option value="icon_info">Info Icon (i)</option>
                                <option value="icon_link">Link Icon</option>
                                <option value="custom_svg">SVG Code</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'point'" class="mb-2 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Ziel-Punkt</label>
                            <select v-model="hs.target_point_id" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <template v-for="t in project.virtual_tours" :key="t.id">
                                    <optgroup :label="t.name">
                                        <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </optgroup>
                                </template>
                            </select>
                        </div>
                        <div v-if="hs.link_type === 'apartment'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Wohnung wählen</label>
                            <select v-model="hs.apartment_id" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="a in project.apartments" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'house'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Haus wählen</label>
                            <select v-model="hs.house_id" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'slider'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Slider wählen</label>
                            <select v-model="hs.slider_id" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="s in project.sliders" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'tooltip'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Info Tooltip Text</label>
                            <textarea v-model="hs.tooltip_text" rows="3" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500"></textarea>
                        </div>

                        <div v-if="hs.link_type === 'video'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Externes Video (Embed URL)</label>
                            <input type="text" v-model="hs.target_url" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white focus:ring-brand-500" placeholder="https://..." />
                        </div>

                        <div v-if="hs.style_type === 'custom_svg'" class="mb-5 animate-in fade-in duration-300">
                            <label class="text-[10px] uppercase font-black tracking-widest text-gray-400 block mb-2 px-1">Eigenes SVG-Code</label>
                            <textarea v-model="hs.custom_svg" @change="updateHotspotMarker(hs)" rows="3" class="w-full bg-gray-950/50 border border-gray-700 rounded-xl px-4 py-2.5 text-xs text-white font-mono focus:ring-brand-500"></textarea>
                        </div>

                        <!-- Color for simple points -->
                        <div v-if="hs.style_type !== 'custom_svg'" class="mt-4 pt-4 border-t border-gray-700/50 flex items-center justify-between">
                            <span class="text-[10px] uppercase font-black tracking-widest text-gray-400 px-1">Markierfarbe</span>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="hs.color" @change="updateHotspotMarker(hs)" class="w-12 bg-transparent border-none p-0 h-6 cursor-pointer" />
                                <span class="text-[10px] font-mono text-gray-500">{{ hs.color }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import '@photo-sphere-viewer/core/index.css';
import '@photo-sphere-viewer/markers-plugin/index.css';
import { TrashIcon, ChevronRightIcon, ChevronLeftIcon, MapPinIcon } from '@heroicons/vue/24/outline';

const props = defineProps(['point', 'project']);
const emit = defineEmits(['save']);

const viewerContainer = ref(null);
let viewer = null;
let markersPlugin = null;
const showSidebar = ref(true);

const hotspots = ref(JSON.parse(JSON.stringify(props.point.hotspots || [])));
const initialYaw = ref(props.point.initial_yaw);
const initialPitch = ref(props.point.initial_pitch);
const initialFov = ref(props.point.initial_fov);

const save = () => {
    emit('save', {
        hotspots: hotspots.value,
        initial_yaw: initialYaw.value,
        initial_pitch: initialPitch.value,
        initial_fov: initialFov.value
    });
};

const setStartView = () => {
    if (!viewer) return;
    const pos = viewer.getPosition();
    const zoom = viewer.getZoomLevel(); // This is roughly FOV related in Photo Sphere Viewer
    
    initialYaw.value = pos.yaw;
    initialPitch.value = pos.pitch;
    initialFov.value = zoom;
    
    alert('Startansicht für diesen Punkt wurde temporär gesetzt. Bitte klicke auf "Speichern" um die Änderungen permanent zu übernehmen.');
};

// Auto-resize viewer when sidebar toggles
watch(showSidebar, () => {
    nextTick(() => {
        if (viewer) viewer.autoSize();
    });
});

// Also watch hotspots length to show sidebar if it was hidden
watch(() => hotspots.value.length, (newLen) => {
    if (newLen > 0) showSidebar.value = true;
    nextTick(() => {
        if (viewer) viewer.autoSize();
    });
});

onMounted(() => {
    // Wait for modal transitions to finish and DOM to stabilize
    setTimeout(() => {
        initViewer();
    }, 150);
});

onBeforeUnmount(() => {
    if (viewer) {
        viewer.destroy();
    }
});

const initViewer = () => {
    if (!props.point.media || props.point.media.length === 0) return;

    let panoramaUrl = props.point.media[0].original_url;
    try {
        panoramaUrl = new URL(panoramaUrl).pathname;
    } catch (e) {
        // Fallback
    }

    // Ensure container has dimensions
    if (viewerContainer.value.clientWidth === 0 || viewerContainer.value.clientHeight === 0) {
        setTimeout(initViewer, 100);
        return;
    }

    viewer = new Viewer({
        container: viewerContainer.value,
        panorama: panoramaUrl,
        navbar: ['zoom', 'fullscreen'],
        plugins: [
            [MarkersPlugin, {
                markers: buildMarkersFromHotspots()
            }]
        ],
        defaultYaw: props.point.initial_yaw || 0,
        defaultPitch: props.point.initial_pitch || 0,
        defaultZoomLvl: props.point.initial_fov || 0,
    });

    markersPlugin = viewer.getPlugin(MarkersPlugin);

    viewer.addEventListener('dblclick', ({ data }) => {
        const newHs = {
            id: 'hs_' + Date.now(),
            yaw: data.yaw,
            pitch: data.pitch,
            label: 'Neuer Hotspot',
            link_type: 'none',
            style_type: 'text',
            color: '#ab715c'
        };
        hotspots.value.push(newHs);
        markersPlugin.addMarker(hotspotToMarker(newHs));
    });
};

const buildMarkersFromHotspots = () => {
    return hotspots.value.map(hs => hotspotToMarker(hs));
};

const hotspotToMarker = (hs) => {
    const color = hs.color || '#ab715c';
    const styleType = hs.style_type || 'text';
    let htmlContent = '';

    if (styleType === 'text') {
        htmlContent = `<div style="background-color: ${color}" class="text-white text-xs font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap cursor-pointer border border-white/20 drop-shadow-md hover:scale-105 transition-transform">${hs.label || 'Hotspot'}</div>`;
    } else if (styleType === 'icon_arrow') {
        htmlContent = `<div class="cursor-pointer drop-shadow-md hover:scale-110 transition-transform" style="color: ${color}"><svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg></div>`;
    } else if (styleType === 'icon_arrow_up') {
        htmlContent = `<div class="cursor-pointer drop-shadow-md hover:scale-110 transition-transform" style="color: ${color}"><svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></div>`;
    } else if (styleType === 'icon_info') {
        htmlContent = `<div class="cursor-pointer drop-shadow-md hover:scale-110 transition-transform" style="color: ${color}"><svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></div>`;
    } else if (styleType === 'icon_link') {
        htmlContent = `<div class="cursor-pointer drop-shadow-md hover:scale-110 transition-transform" style="color: ${color}"><svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg></div>`;
    } else if (styleType === 'custom_svg') {
        htmlContent = `<div class="cursor-pointer drop-shadow-md hover:scale-110 transition-transform w-[50px] h-[50px] flex items-center justify-center">${hs.custom_svg || '<div class="text-white text-[10px]">SVG?</div>'}</div>`;
    }

    return {
        id: hs.id,
        position: { yaw: hs.yaw, pitch: hs.pitch },
        html: htmlContent,
        anchor: 'center center',
        size: { width: 0, height: 0 },
        tooltip: hs.label || ''
    };
};

const updateHotspotMarker = (hs) => {
    if (markersPlugin) {
        markersPlugin.updateMarker(hotspotToMarker(hs));
    }
};

const removeHotspot = (index) => {
    const hs = hotspots.value[index];
    if (markersPlugin) {
        markersPlugin.removeMarker(hs.id);
    }
    hotspots.value.splice(index, 1);
};
</script>
