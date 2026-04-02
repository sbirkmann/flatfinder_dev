<template>
    <div class="w-full h-full relative flex flex-col bg-black">
        <div ref="viewerContainer" class="flex-1 w-full h-full"></div>

        <!-- Minimap Overlay -->
        <div v-if="minimapTour && minimapImage" class="absolute bottom-6 left-6 w-56 md:w-72 aspect-video bg-white/90 backdrop-blur-md rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.5)] overflow-hidden border-[3px] border-white transition-all transform hover:scale-[1.03] group z-50">
            <img :src="minimapImage.original_url" class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-90 p-1" />
            
            <!-- Markers for all points -->
            <button v-for="pt in minimapTour.points" :key="'mm_'+pt.id"
                 class="absolute w-3.5 h-3.5 md:w-4 md:h-4 rounded-full border-2 transition-all shadow-md transform -translate-x-1/2 -translate-y-1/2"
                 :class="pt.id === point.id ? 'bg-[#ab715c] border-white scale-125 z-20 shadow-[0_0_15px_rgba(171,113,92,0.8)]' : 'bg-gray-800 border-white hover:scale-110 z-10'"
                 :style="{ left: pt.minimap_x + '%', top: pt.minimap_y + '%' }"
                 v-show="pt.minimap_x !== null && pt.minimap_y !== null"
                 @click="pt.id !== point.id ? $emit('action', { type: 'tour_point', target: pt.id }) : null"
                 :title="pt.name"
            >
                <div v-if="pt.id === point.id" class="absolute top-1/2 -translate-y-1/2 left-full ml-2 whitespace-nowrap bg-black/80 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg pointer-events-none transition drop-shadow-md">
                    {{ pt.name }}
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import { Viewer } from '@photo-sphere-viewer/core';
import { MarkersPlugin } from '@photo-sphere-viewer/markers-plugin';
import '@photo-sphere-viewer/core/index.css';
import '@photo-sphere-viewer/markers-plugin/index.css';

const props = defineProps(['point', 'project']);
const emit = defineEmits(['close', 'action', 'position-changed']);

const viewerContainer = ref(null);
let viewer = null;
let markersPlugin = null;

const minimapTour = computed(() => {
    if (!props.point || !props.project?.virtual_tours) return null;
    return props.project.virtual_tours.find(t => t.id === props.point.virtual_tour_id);
});

const minimapImage = computed(() => {
    return minimapTour.value?.media?.find(m => m.collection_name === 'minimap') || null;
});

const initViewer = () => {
    if (!props.point?.media || props.point.media.length === 0) return;

    let panoramaUrl = props.point.media[0].original_url;
    try {
        panoramaUrl = new URL(panoramaUrl).pathname;
    } catch (e) {
        // Fallback to original
    }

    if (viewer) {
        viewer.destroy();
    }

    viewer = new Viewer({
        container: viewerContainer.value,
        panorama: panoramaUrl,
        defaultYaw: props.point.initialYaw || 0,
        defaultPitch: props.point.initialPitch || 0,
        navbar: ['zoom', 'fullscreen'],
        plugins: [
            [MarkersPlugin, {
                markers: (props.point.hotspots || []).map(hs => hotspotToMarker(hs))
            }]
        ]
    });

    markersPlugin = viewer.getPlugin(MarkersPlugin);

    viewer.addEventListener('position-updated', ({ position }) => {
        emit('position-changed', { yaw: position.yaw, pitch: position.pitch });
    });

    markersPlugin.addEventListener('select-marker', ({ marker }) => {
        const hs = (props.point.hotspots || []).find(h => h.id === marker.id);
        if (hs && hs.link_type && hs.link_type !== 'none') {
            emit('action', {
                type: hs.link_type,
                target: hs.link_target || hs.target_url || hs.apartment_id || hs.house_id || hs.view_id || hs.slider_id || hs.tour_point_id || hs.tooltip_text // Provide fallback if multiple possibilities
            });
        }
    });
};

watch(() => props.point, () => {
    initViewer();
}, { deep: true });

onMounted(() => {
    initViewer();
});

onBeforeUnmount(() => {
    if (viewer) {
        viewer.destroy();
    }
});

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
</script>
