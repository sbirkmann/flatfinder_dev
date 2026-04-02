<template>
    <div class="space-y-6">
        
        <!-- List View -->
        <div v-if="!activeTour">
            <div class="flex justify-between items-center mb-6 border-b pb-4 border-gray-100">
                <h3 class="text-xl font-black text-gray-800 tracking-tight flex items-center gap-2">
                    <VideoCameraIcon class="w-6 h-6 text-brand-500"/>
                    Virtuelle Touren
                </h3>
                <form @submit.prevent="createTour" class="flex gap-2">
                    <TextInput v-model="newTourName" placeholder="Neue Tour Name..." class="w-64" />
                    <PrimaryButton type="submit" :disabled="!newTourName.trim()">Tour anlegen</PrimaryButton>
                </form>
            </div>

            <div v-if="project.virtual_tours?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Tour Cards -->
                <div v-for="tour in project.virtual_tours" :key="tour.id" class="border border-gray-200 rounded-xl overflow-hidden shadow-sm bg-white hover:shadow-md transition">
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-2">
                            <VideoCameraIcon class="w-8 h-8 text-gray-300" />
                            <h4 class="font-bold text-gray-900 text-lg line-clamp-1" :title="tour.name">{{ tour.name }}</h4>
                        </div>
                        <p class="text-sm text-gray-500 mb-6">{{ tour.points?.length || 0 }} Punkte / Szenen</p>
                        
                        <div class="flex gap-2">
                            <PrimaryButton @click="editTour(tour)" class="flex-1 justify-center py-2 text-xs">Punkte bearbeiten</PrimaryButton>
                            <button @click="deleteTour(tour.id)" class="px-3 py-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-md transition" title="Tour löschen">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div v-else class="text-center py-12 bg-gray-50 border border-dashed border-gray-300 rounded-2xl flex flex-col items-center justify-center">
                <VideoCameraIcon class="w-12 h-12 text-brand-300 mb-3" />
                <h4 class="font-bold text-gray-700 text-lg">Keine Virtuellen Touren</h4>
                <p class="text-gray-500 mt-1 max-w-sm text-sm">Lege oben deine erste Virtuelle Tour an, um 360° Punkte und Hotspots zu verlinken.</p>
            </div>
        </div>

        <!-- Detail Sub-View -->
        <div v-else>
            <!-- Back & Header -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-4">
                    <button @click="activeTour = null" class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </button>
                    <div class="flex-1 ml-2">
                        <p class="text-[10px] text-[#ab715c] font-black uppercase tracking-widest mb-1 bg-[#ab715c]/10 inline-block px-1.5 py-0.5 rounded">Tour bearbeiten</p>
                        <input 
                            v-model="activeTour.name"
                            @change="updateTourName(activeTour.id, activeTour.name)"
                            class="block w-full text-2xl font-black text-gray-800 tracking-tight border-none bg-transparent hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#ab715c] rounded-lg transition px-2 py-1 -ml-2"
                        />
                    </div>
                </div>
                <form @submit.prevent="createPoint(activeTour.id)" class="flex gap-2">
                    <TextInput v-model="newPointName" placeholder="Neuer Punkt Name..." class="w-48 text-sm"/>
                    <SecondaryButton type="submit">Punkt hinzufügen</SecondaryButton>
                </form>
            </div>
            <!-- Minimap Upload & Editor -->
            <div class="mb-8 border border-gray-200 rounded-xl bg-white p-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-lg text-gray-800">Minimap (Übersichtsplan)</h4>
                    <label class="cursor-pointer bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2">
                        <ArrowUpTrayIcon class="w-4 h-4" />
                        {{ activeTour.media?.find(m => m.collection_name === 'minimap') ? 'Bild austauschen' : 'Minimap hochladen' }}
                        <input type="file" class="hidden" @change="e => uploadMinimap(activeTour.id, e.target.files[0])" accept="image/*" />
                    </label>
                </div>
                
                <div v-if="activeTour.media?.find(m => m.collection_name === 'minimap')">
                    <p class="text-sm text-gray-500 mb-4">Um Punkte auf der Minimap zu platzieren, wähle unten den "Platzieren"-Modus für einen Punkt und klicke auf die Karte.</p>
                    <div class="relative w-full aspect-video bg-gray-50 border border-gray-200 rounded-lg overflow-hidden cursor-crosshair shadow-inner" @click="handleMinimapClick">
                        <img :src="activeTour.media.find(m => m.collection_name === 'minimap').original_url" class="absolute inset-0 w-full h-full object-contain pointer-events-none" />
                        
                        <!-- Existing Markers -->
                        <template v-for="point in activeTour.points" :key="'mm_'+point.id">
                            <div v-if="point.minimap_x !== null && point.minimap_y !== null" 
                                 class="absolute w-5 h-5 rounded-full bg-brand-500 border-[3px] border-white shadow-md transform -translate-x-1/2 -translate-y-1/2 group transition-all"
                                 :class="{'ring-2 ring-brand-300 ring-offset-1 scale-110': minimapActivePointId === point.id}"
                                 :style="{ left: point.minimap_x + '%', top: point.minimap_y + '%' }">
                                 <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-black/90 text-white text-[11px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition z-10 pointer-events-none">
                                     {{ point.name }}
                                 </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Points Grid -->
            <div v-if="activeTour.points?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="point in activeTour.points" :key="point.id" class="border border-gray-200 rounded-xl p-4 flex flex-col bg-white shadow-sm hover:shadow transition">
                    <div class="flex justify-between items-start mb-3">
                        <input
                            v-model="point.name" 
                            @change="updatePointName(point.id, point.name)"
                            class="font-bold text-gray-800 border-none bg-transparent hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#ab715c] rounded-lg transition px-2 py-1 -ml-2 text-sm flex-1 mr-2"
                        />
                        <button @click="deletePoint(point.id)" class="text-gray-400 hover:text-red-500 p-1" title="Punkt löschen">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                    
                    <div class="flex-1 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden mb-3 aspect-video relative group border border-gray-200">
                        <img v-if="point.media?.length" :src="point.media[0].original_url" class="absolute inset-0 w-full h-full object-cover" />
                        <div v-else class="text-gray-400 text-xs text-center p-4">360° Bild hochladen</div>
                        <!-- Hover Upload overlay -->
                        <label class="absolute inset-0 bg-black/50 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer backdrop-blur-[2px]">
                            <ArrowUpTrayIcon class="w-6 h-6 mb-1"/>
                            <span class="text-xs font-bold">{{ point.media?.length ? 'Bild austauschen' : 'Panorama hochladen' }}</span>
                            <input type="file" class="hidden" @change="e => uploadPointMedia(point.id, e.target.files[0])" accept="image/*" />
                        </label>
                    </div>
                    
                    <div class="flex gap-2">
                        <PrimaryButton @click="openHotspotEditor(point)" class="flex-1 justify-center text-[11px] py-2 bg-gray-800 hover:bg-black" :disabled="!point.media?.length">
                            Hotspots bearbeiten
                        </PrimaryButton>
                        <SecondaryButton @click="minimapActivePointId = point.id" class="px-2 py-2 flex-shrink-0" :class="{'bg-[#ab715c] text-white border-[#ab715c]': minimapActivePointId === point.id}" title="Auf Minimap platzieren">
                            <MapPinIcon class="w-4 h-4" />
                        </SecondaryButton>
                    </div>
                </div>
            </div>
            
            <div v-else class="text-center py-12 bg-gray-50 border border-dashed border-gray-300 rounded-2xl flex flex-col items-center justify-center">
                <MapPinIcon class="w-10 h-10 text-gray-300 mb-3" />
                <h4 class="font-bold text-gray-700 text-lg">Keine Punkte vorhanden</h4>
                <p class="text-gray-500 mt-1 max-w-sm text-sm">Lege oben rechts deinen ersten Punkt an und lade ein 360° Panorama hoch.</p>
            </div>
        </div>

        <!-- Editor Modal -->
        <DialogModal :show="hotspotEditorModal" @close="hotspotEditorModal = false; activePointForEditor = null" maxWidth="7xl">
            <template #title>
                <div class="flex items-center gap-2">
                    <VideoCameraIcon class="w-5 h-5 text-brand-500" /> Hotspots für "{{ activePointForEditor?.name }}" bearbeiten
                </div>
            </template>
           <template #content>
               <div v-if="activePointForEditor" class="w-full flex flex-col bg-gray-900 border border-gray-700 rounded-lg overflow-hidden h-[70vh] md:h-[650px] relative">
                   <div class="flex-1 bg-black relative flex items-center justify-center overflow-hidden w-full h-full select-none">
                        <HotspotViewer 
                            :point="activePointForEditor" 
                            :project="project" 
                            @save="saveHotspots" 
                        />
                   </div>
               </div>
           </template>
        </DialogModal>

    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import HotspotViewer from './HotspotViewer.vue';
import { VideoCameraIcon, TrashIcon, ArrowUpTrayIcon, ArrowLeftIcon, MapPinIcon } from '@heroicons/vue/24/outline';

const props = defineProps(['project']);

// Master forms
const newTourName = ref('');
const newPointName = ref('');

// State
const activeTourId = ref(null);

// Computed view of the active tour
const activeTour = computed(() => {
    if (!activeTourId.value) return null;
    return props.project.virtual_tours?.find(t => t.id === activeTourId.value) || null;
});

// Switch to detail view
const editTour = (tour) => {
    activeTourId.value = tour.id;
    newPointName.value = ''; // reset point form
};

const createTour = () => {
    if (!newTourName.value.trim()) return;
    router.post(`/projects/${props.project.id}/virtual-tours`, { name: newTourName.value }, {
        preserveScroll: true,
        onSuccess: () => newTourName.value = ''
    });
};

const deleteTour = (id) => {
    if (confirm('Tour wirklich löschen? Alle Punkte und Hotspots gehen verloren.')) {
        if (activeTourId.value === id) activeTourId.value = null; // Exit if active
        router.delete(`/virtual-tours/${id}`, { preserveScroll: true });
    }
};

const createPoint = (tourId) => {
    if (!newPointName.value.trim()) return;
    
    router.post(`/virtual-tours/${tourId}/points`, { name: newPointName.value }, {
        preserveScroll: true,
        onSuccess: () => newPointName.value = ''
    });
};

const updateTourName = (id, newName) => {
    if (!newName.trim()) return;
    router.put(`/virtual-tours/${id}`, { name: newName }, { preserveScroll: true });
};

const updatePointName = (id, newName) => {
    if (!newName.trim()) return;
    router.put(`/virtual-tour-points/${id}`, { name: newName }, { preserveScroll: true });
};

const deletePoint = (id) => {
    if (confirm('Punkt wirklich löschen?')) {
        router.delete(`/virtual-tour-points/${id}`, { preserveScroll: true });
    }
};

const uploadPointMedia = (id, file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('collection', 'panorama');
    
    window.axios.post(`/media/upload/VirtualTourPoint/${id}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).then(() => {
        router.reload({ preserveScroll: true });
    }).catch(err => {
        alert("Upload fehlgeschlagen. Datei evtl. zu groß.");
    });
};

// --- Hotspot Editor ---
const hotspotEditorModal = ref(false);
const activePointForEditor = ref(null);
const minimapActivePointId = ref(null);

const uploadMinimap = (tourId, file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('collection', 'minimap');
    
    // VirtualTour is our model for the minimap
    window.axios.post(`/media/upload/VirtualTour/${tourId}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
    }).then(() => {
        router.reload({ preserveScroll: true });
    }).catch(err => {
        alert("Upload fehlgeschlagen.");
    });
};

const handleMinimapClick = (e) => {
    if (!minimapActivePointId.value) {
        alert("Bitte zuerst bei einem Punkt auf das Stecknadel-Symbol klicken, um ihn zu platzieren!");
        return;
    }
    
    // Calculate percentage coords based on click event relative to container bounds
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    
    const pId = minimapActivePointId.value;
    
    window.axios.put(`/virtual-tour-points/${pId}`, { minimap_x: x, minimap_y: y })
        .then(() => {
            // Update local object immediately
            const p = activeTour.value.points.find(x => x.id === pId);
            if (p) {
                p.minimap_x = x;
                p.minimap_y = y;
            }
            minimapActivePointId.value = null; // deselect
        })
        .catch(err => alert("Speichern der Position fehlgeschlagen"));
};

const openHotspotEditor = (point) => {
    activePointForEditor.value = JSON.parse(JSON.stringify(point));
    hotspotEditorModal.value = true;
};

const saveHotspots = (hotspots) => {
    if (!activePointForEditor.value) return;
    window.axios.put(`/virtual-tour-points/${activePointForEditor.value.id}`, { hotspots })
        .then(() => {
            // Update local object state so UI reflects it immediately
            const t = props.project.virtual_tours?.find(x => x.id === activePointForEditor.value.virtual_tour_id);
            if (t) {
                const p = t.points.find(x => x.id === activePointForEditor.value.id);
                if (p) p.hotspots = hotspots;
            }
            hotspotEditorModal.value = false;
        })
        .catch(err => alert("Speichern fehlgeschlagen"));
};

</script>
