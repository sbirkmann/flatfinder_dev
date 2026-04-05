<template>
    <div class="space-y-8 animate-in fade-in duration-500">
        
        <!-- List View -->
        <div v-if="!activeTour">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 border-b pb-6 border-gray-100">
                <div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                        <div class="bg-brand-50 p-2 rounded-xl">
                            <VideoCameraIcon class="w-6 h-6 text-brand-600"/>
                        </div>
                        Virtuelle Touren
                    </h3>
                    <p class="text-sm text-gray-500 mt-1 font-medium italic">Verwalte 360°-Rundgänge und interaktive Hotspots.</p>
                </div>
                <form @submit.prevent="createTour" class="flex gap-2">
                    <div class="relative min-w-[280px]">
                        <VideoCameraIcon class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input 
                            v-model="newTourName" 
                            placeholder="Tour-Titel eingeben..." 
                            class="w-full pl-10 pr-4 py-2.5 border-gray-200 rounded-xl text-sm focus:ring-brand-500 focus:border-brand-500 shadow-sm transition-all"
                        />
                    </div>
                    <button 
                        type="submit" 
                        :disabled="!newTourName.trim()"
                        class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl font-black text-xs uppercase tracking-widest shadow-lg shadow-brand-500/20 transition-all flex items-center gap-2 active:scale-95"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Tour anlegen
                    </button>
                </form>
            </div>

            <div v-if="project.virtual_tours?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Tour Cards -->
                <div v-for="tour in project.virtual_tours" :key="tour.id" class="group relative bg-white border border-gray-100 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-brand-50 rounded-full blur-3xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    
                    <div class="p-8 relative z-10">
                        <div class="flex items-start justify-between mb-4">
                            <div class="bg-gray-50 p-4 rounded-2xl group-hover:bg-brand-50 transition-colors">
                                <VideoCameraIcon class="w-8 h-8 text-gray-400 group-hover:text-brand-600" />
                            </div>
                            <button @click="deleteTour(tour.id)" class="text-gray-300 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-xl" title="Tour löschen">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </div>
                        
                        <h4 class="font-black text-gray-900 text-xl line-clamp-1 mb-1" :title="tour.name">{{ tour.name }}</h4>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">{{ tour.points?.length || 0 }} Szenen</p>
                        
                        <button 
                            @click="editTour(tour)" 
                            class="w-full bg-gray-900 hover:bg-black text-white py-3.5 rounded-2xl font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2 group-hover:shadow-lg active:scale-95"
                        >
                            Punkte verwalten
                            <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Sub-View -->
        <div v-else class="animate-in slide-in-from-right-4 duration-500">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10 pb-8 border-b border-gray-100">
                <div class="flex items-center gap-5">
                    <button @click="activeTourId = null" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:-translate-x-1 transition-all text-gray-600">
                        <ArrowLeftIcon class="w-6 h-6" />
                    </button>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] uppercase font-black tracking-[0.2em] text-brand-600 bg-brand-50 px-2 py-0.5 rounded">Tour-Editor</span>
                        </div>
                        <input 
                            v-model="activeTour.name"
                            @change="updateTourName(activeTour.id, activeTour.name)"
                            class="block w-full text-3xl font-black text-gray-900 tracking-tight border-none bg-transparent hover:bg-gray-50 focus:bg-white focus:ring-0 rounded-xl transition px-0"
                        />
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative min-w-[240px] group">
                        <PlusIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input 
                            v-model="newPointName" 
                            placeholder="Anderer Raum / Name..." 
                            class="w-full pl-9 pr-4 py-3 bg-white border-gray-200 rounded-2xl text-sm focus:ring-brand-500 focus:border-brand-500 shadow-sm shadow-brand-500/5 transition-all"
                            @keyup.enter="createPoint(activeTour.id)"
                        />
                    </div>
                    <button 
                        @click="createPoint(activeTour.id)"
                        :disabled="!newPointName.trim()"
                        class="bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white p-3.5 rounded-2xl shadow-lg transition-transform active:scale-95 flex items-center justify-center"
                    >
                        <PlusIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Minimap -->
            <div class="bg-white rounded-[2rem] border border-gray-100 p-8 shadow-sm mb-12 overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h4 class="font-black text-2xl text-gray-900 tracking-tight">Übersichtsplan</h4>
                        <p class="text-sm text-gray-500 font-medium italic">Platziere deine Tour-Punkte auf dem Grundriss.</p>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <!-- Quick Add Mode Toggle -->
                        <button 
                            @click="toggleQuickAddMode"
                            :class="[
                                quickAddMode ? 'bg-[#ab715c] text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                'px-5 py-3 rounded-2xl text-sm font-black flex items-center gap-2 transition-all active:scale-95'
                            ]"
                        >
                            <MapPinIcon class="w-5 h-5" :class="{'animate-bounce': quickAddMode}" />
                            {{ quickAddMode ? 'Ein Klick zum Platzieren...' : 'Schnell-Hinzufügen (Modus)' }}
                        </button>

                        <label class="cursor-pointer bg-gray-900 text-white px-5 py-3 rounded-2xl text-sm font-black transition-all flex items-center gap-2 active:scale-95 shadow-lg">
                            <ArrowUpTrayIcon class="w-5 h-5" />
                            Plan hochladen
                            <input type="file" class="hidden" @change="e => uploadMinimap(activeTour.id, e.target.files[0])" accept="image/*" />
                        </label>
                    </div>
                </div>
                
                <div v-if="activeTour.media?.find(m => m.collection_name === 'minimap')" class="relative w-full aspect-[21/9] min-h-[400px] bg-gray-50 border border-gray-200 rounded-3xl overflow-hidden cursor-crosshair shadow-inner" 
                     @click="handleMinimapContainerClick"
                     :class="{'ring-4 ring-brand-500/20': quickAddMode}">
                    
                    <img :src="activeTour.media.find(m => m.collection_name === 'minimap').original_url" class="absolute inset-0 w-full h-full object-contain pointer-events-none" />
                    
                    <!-- Markers -->
                    <div v-for="point in activeTour.points" :key="'marker_'+point.id">
                        <div v-if="point.minimap_x !== null" 
                             class="absolute w-7 h-7 rounded-full bg-white border-2 transform -translate-x-1/2 -translate-y-1/2 shadow-xl transition-all hover:scale-125 z-10"
                             :class="minimapActivePointId === point.id ? 'border-brand-500 scale-125 z-20 ring-4 ring-brand-500/10' : 'border-gray-200'"
                             :style="{ left: point.minimap_x + '%', top: point.minimap_y + '%' }"
                             @click.stop="minimapActivePointId = point.id">
                             <div class="w-full h-full rounded-full flex items-center justify-center p-1.5 bg-white">
                                 <div class="w-full h-full rounded-full" :class="minimapActivePointId === point.id ? 'bg-brand-500' : 'bg-gray-100'"></div>
                             </div>
                        </div>
                    </div>

                    <div v-if="quickAddMode" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-6 py-3 rounded-2xl shadow-2xl border border-brand-100 flex items-center gap-3 z-50 animate-bounce pointer-events-none">
                        <MapPinIcon class="w-5 h-5 text-brand-600" />
                        <span class="text-sm font-black text-gray-800 tracking-tight">Klicke zum Hinzufügen auf die Karte</span>
                    </div>
                </div>
            </div>

            <!-- Points Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div v-for="point in activeTour.points" :key="point.id" 
                     class="group bg-white border border-gray-100 rounded-[2rem] p-5 flex flex-col shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                     :class="{'ring-2 ring-brand-500': minimapActivePointId === point.id}">
                    
                    <div class="flex justify-between items-start mb-4">
                        <input v-model="point.name" @change="updatePointName(point.id, point.name)" class="font-black text-gray-800 border-none bg-transparent hover:bg-gray-50 focus:bg-white focus:ring-0 rounded-xl transition px-2 py-1 -ml-2 text-sm flex-1 mr-2" />
                        <button @click="deletePoint(point.id)" class="text-gray-300 hover:text-red-500 p-2 hover:bg-red-50 rounded-lg transition-colors"><TrashIcon class="w-4 h-4" /></button>
                    </div>
                    
                    <div class="flex-1 bg-gray-50 rounded-2xl flex items-center justify-center overflow-hidden mb-5 aspect-[4/3] relative group/thumb border border-gray-100">
                        <img v-if="point.media?.length" :src="point.media[0].original_url" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover/thumb:scale-110" />
                        <div v-else class="text-gray-300 flex flex-col items-center gap-2">
                            <PhotoIcon class="w-8 h-8" />
                            <span class="text-[10px] font-black uppercase tracking-widest">Kein Panorama</span>
                        </div>
                        
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover/thumb:opacity-100 transition-opacity backdrop-blur-[2px] flex flex-col items-center justify-center gap-3">
                            <label class="bg-white text-gray-900 px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest cursor-pointer hover:bg-brand-500 hover:text-white transition-all shadow-lg active:scale-95">
                                {{ point.media?.length ? 'Bild ändern' : 'Bild hochladen' }}
                                <input type="file" class="hidden" @change="e => uploadPointMedia(point.id, e.target.files[0])" accept="image/*" />
                            </label>
                            <button v-if="point.media?.length" @click="openHotspotEditor(point)" class="bg-white/10 text-white border border-white/20 px-4 py-2 rounded-xl text-[11px] font-black uppercase tracking-widest backdrop-blur-md hover:bg-brand-600 transition-all active:scale-95">
                                Editor öffnen
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="openHotspotEditor(point)" class="flex-1 bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest py-3 rounded-2xl hover:bg-black transition-all" :disabled="!point.media?.length">Hotspots</button>
                        <button @click="minimapActivePointId = point.id" 
                                :class="[minimapActivePointId === point.id ? 'bg-[#ab715c] text-white shadow-lg shadow-brand-500/30' : 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'py-3 rounded-2xl transition-all flex items-center justify-center']">
                            <MapPinIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hotspot Editor Modal -->
        <DialogModal :show="hotspotEditorModal" @close="hotspotEditorModal = false" maxWidth="7xl">
            <template #title>
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                         <div class="bg-brand-50 p-2 rounded-xl"><SparklesIcon class="w-5 h-5 text-brand-600" /></div>
                         <h4 class="font-black text-gray-900 tracking-tight">Hotspot Editor: {{ activePointForEditor?.name }}</h4>
                    </div>
                    <button @click="hotspotEditorModal = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="w-6 h-6" /></button>
                </div>
            </template>
            <template #content>
                 <div v-if="activePointForEditor" class="w-full h-[75vh]">
                      <HotspotViewer 
                          :point="activePointForEditor" 
                          :project="project" 
                          @save="savePointSettings" 
                      />
                 </div>
            </template>
        </DialogModal>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import HotspotViewer from './HotspotViewer.vue';
import { 
    VideoCameraIcon, TrashIcon, ArrowUpTrayIcon, ArrowLeftIcon, MapPinIcon, 
    PlusIcon, ArrowRightIcon, PhotoIcon, SparklesIcon, XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps(['project']);

const newTourName = ref('');
const newPointName = ref('');
const activeTourId = ref(null);
const quickAddMode = ref(false);
const minimapActivePointId = ref(null);
const hotspotEditorModal = ref(false);
const activePointForEditor = ref(null);

const activeTour = computed(() => {
    if (!activeTourId.value) return null;
    return props.project.virtual_tours?.find(t => t.id === activeTourId.value) || null;
});

const toggleQuickAddMode = () => {
    quickAddMode.value = !quickAddMode.value;
    if (quickAddMode.value) minimapActivePointId.value = null;
};

const editTour = (tour) => {
    activeTourId.value = tour.id;
    quickAddMode.value = false;
};

const createTour = () => {
    if (!newTourName.value.trim()) return;
    router.post(`/projects/${props.project.id}/virtual-tours`, { name: newTourName.value }, {
        onSuccess: () => newTourName.value = ''
    });
};

const deleteTour = (id) => {
    if (confirm('Tour wirklich löschen?')) {
        if (activeTourId.value === id) activeTourId.value = null;
        router.delete(`/virtual-tours/${id}`);
    }
};

const createPoint = (tourId, name = null, coords = null) => {
    const pointName = name || newPointName.value;
    if (!pointName.trim()) return;
    router.post(`/virtual-tours/${tourId}/points`, { name: pointName, minimap_x: coords?.x || null, minimap_y: coords?.y || null }, {
        onSuccess: () => { newPointName.value = ''; quickAddMode.value = false; }
    });
};

const updateTourName = (id, newName) => router.put(`/virtual-tours/${id}`, { name: newName });
const updatePointName = (id, newName) => router.put(`/virtual-tour-points/${id}`, { name: newName });

const deletePoint = (id) => {
    if (confirm('Punkt löschen?')) router.delete(`/virtual-tour-points/${id}`);
};

const uploadPointMedia = (id, file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('collection', 'panorama');
    window.axios.post(`/media/upload/VirtualTourPoint/${id}`, fd).then(() => router.reload());
};

const uploadMinimap = (tourId, file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('collection', 'minimap');
    window.axios.post(`/media/upload/VirtualTour/${tourId}`, fd).then(() => router.reload());
};

const handleMinimapContainerClick = (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    if (quickAddMode.value) {
        createPoint(activeTour.value.id, 'Neuer Punkt', { x, y });
    } else if (minimapActivePointId.value) {
        const pId = minimapActivePointId.value;
        window.axios.put(`/virtual-tour-points/${pId}`, { minimap_x: x, minimap_y: y }).then(() => {
            const p = activeTour.value.points.find(x => x.id === pId);
            if (p) { p.minimap_x = x; p.minimap_y = y; }
            minimapActivePointId.value = null;
        });
    }
};

const openHotspotEditor = (point) => {
    activePointForEditor.value = JSON.parse(JSON.stringify(point));
    hotspotEditorModal.value = true;
};

const savePointSettings = (data) => {
    window.axios.put(`/virtual-tour-points/${activePointForEditor.value.id}`, data).then(() => {
        router.reload();
        hotspotEditorModal.value = false;
    });
};
</script>

<style scoped>
.animate-in { animation-duration: 0.5s; animation-fill-mode: both; }
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes slide-in-from-right-4 { from { opacity: 0; transform: translateX(1rem); } to { opacity: 1; transform: translateX(0); } }
.fade-in { animation-name: fade-in; }
.slide-in-from-right-4 { animation-name: slide-in-from-right-4; }
</style>
