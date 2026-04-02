<template>
    <div class="w-full h-full relative flex flex-col">
        <!-- Header -->
        <div class="px-6 py-4 bg-gray-900 border-b border-gray-800 text-white flex justify-between items-center z-10 shrink-0">
            <div>
                <h4 class="font-bold">Hotspots bearbeiten: {{ point.name }}</h4>
                <p class="text-xs text-gray-400">Doppelklick ins Bild, um einen neuen Hotspot zu setzen.</p>
            </div>
            <button @click="save" class="bg-[#ab715c] hover:bg-[#8e5c4a] text-white px-4 py-2 rounded font-bold text-sm transition">
                Speichern
            </button>
        </div>

        <div class="flex-1 relative flex">
            <!-- 360 Viewer Container -->
            <div ref="viewerContainer" class="flex-1 w-full h-full bg-black"></div>

            <!-- Hotspots Sidebar -->
            <div class="w-80 sm:w-full bg-gray-900 border-l border-gray-800 text-white flex flex-col absolute right-0 top-0 bottom-0 z-20 shadow-2xl transition-transform" 
                 v-if="hotspots.length > 0">
                <div class="px-4 py-3 border-b border-gray-800 flex justify-between items-center">
                    <h5 class="font-bold text-sm">Gesetzte Hotspots</h5>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-for="(hs, i) in hotspots" :key="hs.id" class="bg-gray-800 rounded-lg p-4 relative border border-gray-700">
                        <button @click="removeHotspot(i)" class="absolute top-2 right-2 text-gray-500 hover:text-red-500"><TrashIcon class="w-4 h-4"/></button>
                        
                        <div class="mb-3 pr-6">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Titel / Label</label>
                            <input v-model="hs.label" type="text" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white" @change="updateHotspotMarker(hs)"/>
                        </div>

                        <div class="mb-3">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Verlinken mit</label>
                            <select v-model="hs.link_type" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option value="none">-- Ohne --</option>
                                <option value="point">Anderer Punkt (Tour)</option>
                                <option value="apartment">Wohnung</option>
                                <option value="house">Haus</option>
                                <option value="slider">Slider / Popup</option>
                                <option value="tooltip">Info Tooltip (Content)</option>
                                <option value="video">Externe Video URL (Youtube/Streamable)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Darstellung (Style)</label>
                            <select v-model="hs.style_type" @change="updateHotspotMarker(hs)" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option value="text">Text-Label (Standard)</option>
                                <option value="icon_arrow">Pfeil nach Rechts</option>
                                <option value="icon_arrow_up">Pfeil nach Oben</option>
                                <option value="icon_info">Info Icon (i)</option>
                                <option value="icon_link">Link Icon</option>
                                <option value="custom_svg">Eigenes SVG einfügen</option>
                            </select>
                        </div>

                        <div class="mb-3" v-if="hs.style_type !== 'custom_svg'">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Farbe</label>
                            <input type="color" v-model="hs.color" @change="updateHotspotMarker(hs)" class="w-full bg-gray-900 border border-gray-700 rounded h-6" />
                        </div>
                        <div class="mb-3" v-if="hs.style_type === 'custom_svg'">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Eigenes SVG-Code</label>
                            <textarea v-model="hs.custom_svg" @change="updateHotspotMarker(hs)" rows="3" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white font-mono"></textarea>
                        </div>

                        <!-- Conditional fields based on link_type -->
                        <div v-if="hs.link_type === 'point'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Ziel-Punkt</label>
                            <select v-model="hs.target_point_id" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option :value="null">-- Wählen --</option>
                                <template v-for="t in project.virtual_tours" :key="t.id">
                                    <optgroup :label="t.name">
                                        <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </optgroup>
                                </template>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'apartment'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Wohnung wählen</label>
                            <select v-model="hs.apartment_id" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="a in project.apartments" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'house'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Haus wählen</label>
                            <select v-model="hs.house_id" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'slider'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Slider wählen</label>
                            <select v-model="hs.slider_id" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="s in project.sliders" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div v-if="hs.link_type === 'tooltip'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Info Tooltip Text</label>
                            <textarea v-model="hs.tooltip_text" rows="3" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white"></textarea>
                        </div>

                        <div v-if="hs.link_type === 'video' || hs.link_type === 'url'" class="mb-2">
                            <label class="text-[10px] uppercase font-bold text-gray-500 block mb-1">Video Link (Embed URL) / Web URL</label>
                            <input type="text" v-model="hs.target_url" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-xs text-white" placeholder="z.B. https://www.youtube.com/embed/..." />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import '@photo-sphere-viewer/core/index.css';
import '@photo-sphere-viewer/markers-plugin/index.css';
import { TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps(['point', 'project']);
const emit = defineEmits(['save']);

const viewerContainer = ref(null);
let viewer = null;
let markersPlugin = null;

const hotspots = ref(JSON.parse(JSON.stringify(props.point.hotspots || [])));

const save = () => {
    emit('save', hotspots.value);
};

onMounted(() => {
    initViewer();
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

    viewer = new Viewer({
        container: viewerContainer.value,
        panorama: panoramaUrl,
        navbar: ['zoom', 'fullscreen'],
        plugins: [
            [MarkersPlugin, {
                markers: buildMarkersFromHotspots()
            }]
        ]
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
