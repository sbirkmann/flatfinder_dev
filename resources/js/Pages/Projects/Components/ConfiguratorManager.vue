<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between border-b pb-4">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <SparklesIcon class="w-6 h-6 text-brand-500" />
                3D Wohnungskonfiguratoren
            </h3>
            <PrimaryButton @click="createNewConfigurator">Neuen Konfigurator erstellen</PrimaryButton>
        </div>

        <div v-if="loading" class="text-center py-8">Lade...</div>
        
        <div v-else class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Left col: Lists -->
            <div class="col-span-1 border-r pr-4 space-y-4">
                <h4 class="font-semibold text-gray-700">Angelegte Konfiguratoren</h4>
                <div v-if="configurators.length === 0" class="text-sm text-gray-500">
                    Noch keine angelegt.
                </div>
                <div v-for="conf in configurators" :key="conf.id" 
                    @click="selectConfigurator(conf)"
                    :class="[activeConfigurator?.id === conf.id ? 'bg-brand-50 border-brand-500' : 'hover:bg-gray-50 border-transparent']"
                    class="p-3 border rounded cursor-pointer transition">
                    <div class="font-medium text-gray-800">{{ conf.name }}</div>
                    <div class="text-xs text-gray-500">{{ conf.rooms?.length || 0 }} Räume</div>
                    <div class="flex gap-2 mt-2">
                        <button @click.stop="deleteConfigurator(conf)" class="text-red-500 text-xs hover:underline">Löschen</button>
                    </div>
                </div>
            </div>

            <!-- Right col: Management -->
            <div class="col-span-3" v-if="activeConfigurator">
                <div class="flex items-center justify-between mb-4 bg-gray-100 p-3 rounded">
                    <input v-model="activeConfigurator.name" @blur="updateConfigurator(activeConfigurator)" class="font-bold text-lg border-none bg-transparent focus:ring-0">
                    <PrimaryButton @click="createNewRoom" size="sm">Raum hinzufügen</PrimaryButton>
                </div>

                <!-- Rooms Accordion -->
                <div class="space-y-4">
                    <div v-for="room in activeConfigurator.rooms" :key="room.id" class="border rounded shadow-sm">
                        <div class="bg-gray-50 p-3 flex items-center justify-between cursor-pointer" @click="activeRoomId = activeRoomId === room.id ? null : room.id">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-700">{{ room.name }}</span>
                                <span v-if="room.media && room.media.length" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">GLB vorhanden</span>
                                <span v-else class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Kein GLB</span>
                            </div>
                            <button @click.stop="deleteRoom(room)" class="text-red-500 hover:text-red-700"><TrashIcon class="w-4 h-4"/></button>
                        </div>
                        
                        <div v-if="activeRoomId === room.id" class="p-4 bg-white border-t space-y-6">
                            
                            <!-- Media Uploads (GLB & HDRI & Preview) -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border rounded bg-gray-50 p-4">
                                <div>
                                    <InputLabel value="Raum-Vorschaubild" />
                                    <input type="file" @change="e => uploadRoomMedia(e, room, 'preview')" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300" />
                                    <div v-if="getMediaUrl(room, 'preview')" class="mt-2 h-16 w-24 bg-cover bg-center rounded border" :style="`background-image: url('${getMediaUrl(room, 'preview')}')`"></div>
                                </div>
                                <div class="border-l pl-4">
                                    <InputLabel value="3D Modell (GLB/GLTF)" />
                                    <input type="file" @change="e => uploadRoomMedia(e, room, 'glb')" accept=".glb,.gltf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                                    <div v-if="getMediaUrl(room, 'glb')" class="text-xs text-gray-600 mt-1">
                                        GLB vorhanden
                                    </div>
                                </div>
                                <div class="border-l pl-4">
                                    <InputLabel value="HDRI Umgebung" />
                                    <input type="file" @change="e => uploadRoomMedia(e, room, 'hdri')" accept=".hdr" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <div v-if="getMediaUrl(room, 'hdri')" class="text-xs text-gray-600 mt-1">
                                        HDRI vorhanden
                                    </div>
                                </div>
                            </div>

                            <!-- 3D Viewer & Categories split -->
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                <!-- 3D Viewer for selecting meshes -->
                                <div class="border rounded bg-black relative min-h-[400px]">
                                    <div class="absolute top-2 left-2 bg-white/90 text-xs px-2 py-1 rounded shadow pointer-events-none z-10">
                                        Klicke auf Objekte, um Mesh-Namen zu kopieren
                                    </div>
                                    <model-viewer v-if="getMediaUrl(room, 'glb')" 
                                        :src="getMediaUrl(room, 'glb')"
                                        :environment-image="getMediaUrl(room, 'hdri')"
                                        camera-controls 
                                        autoplay 
                                        shadow-intensity="1"
                                        style="width:100%; height:100%; min-height:400px;"
                                        @click="handleModelClick">
                                    </model-viewer>
                                    <div v-else class="flex flex-col items-center justify-center h-full text-white">
                                        <ArrowUpOnSquareStackIcon class="w-12 h-12 mb-2 text-gray-500" />
                                        <span>Bitte erst ein GLB hochladen</span>
                                    </div>
                                </div>

                                <!-- Categories Management -->
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h5 class="font-bold">Kategorien</h5>
                                        <SecondaryButton @click="createNewCategory(room)" size="sm">Neu</SecondaryButton>
                                    </div>

                                    <div class="space-y-4">
                                        <div v-for="cat in room.categories" :key="cat.id" class="border rounded p-3">
                                            <div class="flex items-center gap-2 mb-3">
                                                <input v-model="cat.name" @blur="updateCategory(cat)" class="flex-1 text-sm border-gray-300 rounded font-bold" placeholder="Kategoriename">
                                                <select v-model="cat.type" @change="updateCategory(cat)" class="text-sm border-gray-300 rounded block w-32">
                                                    <option value="material">Material (Textur/Farbe)</option>
                                                    <option value="visibility">Sichtbarkeit (Ein/Aus)</option>
                                                </select>
                                                <button @click="deleteCategory(cat)" title="Kategorie löschen"><TrashIcon class="w-4 h-4 text-red-500 hover:text-red-700"/></button>
                                            </div>

                                            <!-- Options inside Category -->
                                            <div class="bg-gray-50 p-2 rounded space-y-2">
                                                <div class="flex justify-between items-center mb-1">
                                                    <span class="text-xs font-semibold text-gray-500 uppercase">Optionen</span>
                                                    <button @click="createNewOption(cat)" class="text-xs text-brand-600 hover:underline">+ Option</button>
                                                </div>
                                                <div v-for="opt in cat.options" :key="opt.id" class="bg-white border rounded p-2 text-sm grid grid-cols-12 gap-2 relative group">
                                                    <!-- Row 1 -->
                                                    <div class="col-span-6">
                                                        <input v-model="opt.label" @blur="updateOption(opt)" placeholder="Name" class="w-full text-sm border-gray-300 py-1 rounded">
                                                    </div>
                                                    <div class="col-span-6 flex items-center justify-between">
                                                        <input v-model="opt.price_surcharge" type="number" @blur="updateOption(opt)" placeholder="Aufpreis" class="w-20 text-sm border-gray-300 py-1 rounded h-7">
                                                        <label class="flex items-center gap-1 text-xs">
                                                            <input type="checkbox" v-model="opt.is_default" @change="updateOption(opt)"> Standard
                                                        </label>
                                                        <button @click="deleteOption(opt)" class="text-red-500 hover:text-red-700"><TrashIcon class="w-3 h-3"/></button>
                                                    </div>

                                                    <!-- Row 2 (Meshes) -->
                                                    <div class="col-span-12">
                                                        <input :value="opt.mesh_names?.join(', ')" @change="e => { opt.mesh_names = e.target.value.split(',').map(s=>s.trim()).filter(Boolean); updateOption(opt); }" placeholder="z.B. Floor_Mesh, Wall_01 (Klick in 3D zur Auswahl)" class="w-full text-sm border-gray-300 py-1 rounded bg-yellow-50 text-xs">
                                                    </div>

                                                    <!-- Row 3 (Material data ONLY if type == material) -->
                                                    <div v-if="cat.type !== 'visibility'" class="col-span-12 grid grid-cols-3 gap-2 items-center mt-1">
                                                        <div class="col-span-1 flex flex-col gap-1">
                                                            <span class="text-[10px] text-gray-500 font-medium">Farbe (Hex)</span>
                                                            <input type="color" v-model="opt.color_hex" @change="updateOption(opt)" class="h-6 w-full cursor-pointer">
                                                        </div>
                                                        <div class="col-span-1 flex flex-col gap-1">
                                                            <span class="text-[10px] text-gray-500 font-medium">Textur (JPG)</span>
                                                            <input type="file" @change="e => uploadOptionImage(e, opt, 'texture')" class="text-[10px] max-w-full overflow-hidden" accept="image/*">
                                                        </div>
                                                        <div class="col-span-1 flex flex-col gap-1">
                                                            <span class="text-[10px] text-gray-500 font-medium">Scale (Tiling)</span>
                                                            <input type="number" step="0.1" v-model="opt.texture_scale" @blur="updateOption(opt)" class="text-sm py-1 border-gray-300 rounded px-1">
                                                        </div>
                                                    </div>

                                                    <!-- Row 4 (Preview UI) -->
                                                    <div class="col-span-12 mt-1">
                                                        <span class="text-[10px] text-gray-500 font-medium -mt-1 block">Vorschaubild (Thumbnail in Liste)</span>
                                                        <input type="file" @change="e => uploadOptionImage(e, opt, 'preview')" class="text-[10px]" accept="image/*">
                                                        <div v-if="getMediaUrl(opt, 'preview')" class="mt-1 h-8 w-8 bg-cover bg-center rounded border" :style="`background-image: url('${getMediaUrl(opt, 'preview')}')`"></div>
                                                        <div v-else-if="opt.color_hex" class="mt-1 h-8 w-8 rounded border" :style="`background-color: ${opt.color_hex}`"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { SparklesIcon, TrashIcon, ArrowUpOnSquareStackIcon } from '@heroicons/vue/24/outline';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    project: Object,
});

const configurators = ref(props.project.configurators || []);
const activeConfigurator = ref(null);
const activeRoomId = ref(null);
const loading = ref(false);

onMounted(() => {
    // If not eagerly loaded, we might need to fetch them. For now we assume they are passed via props, 
    // but the backend controller might not pass them if we didn't update the inertia render call. Let's fetch to be safe.
    fetchConfigurators();
});

const fetchConfigurators = async () => {
    // Actually we don't have a GET endpoint in the routes yet, so we will rely on inertia props if updated.
    // If not, we use what we have.
    if(props.project.configurators) {
        configurators.value = props.project.configurators;
    }
};

const selectConfigurator = (conf) => {
    activeConfigurator.value = conf;
};

const createNewConfigurator = async () => {
    const name = prompt('Name des Konfigurators (z.B. Typ A, Penthouse):');
    if (!name) return;
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators`, { name });
        configurators.value.push(res.data);
        activeConfigurator.value = res.data;
    } catch (e) {
        alert('Fehler beim Erstellen');
    }
};

const updateConfigurator = async (conf) => {
    await window.axios.put(`/projects/${props.project.id}/configurators/${conf.id}`, { name: conf.name });
};

const deleteConfigurator = async (conf) => {
    if(!confirm("Wirklich löschen?")) return;
    await window.axios.delete(`/projects/${props.project.id}/configurators/${conf.id}`);
    configurators.value = configurators.value.filter(c => c.id !== conf.id);
    if(activeConfigurator.value?.id === conf.id) activeConfigurator.value = null;
};

// Rooms
const createNewRoom = async () => {
    const name = prompt('Name des Raums (z.B. Wohnzimmer):');
    if (!name) return;
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators/${activeConfigurator.value.id}/rooms`, { name });
        if(!activeConfigurator.value.rooms) activeConfigurator.value.rooms = [];
        activeConfigurator.value.rooms.push(res.data);
        activeRoomId.value = res.data.id;
    } catch (e) {
        alert('Fehler');
    }
};

const deleteRoom = async (room) => {
    if(!confirm("Raum löschen?")) return;
    await window.axios.delete(`/projects/${props.project.id}/configurators/rooms/${room.id}`);
    activeConfigurator.value.rooms = activeConfigurator.value.rooms.filter(r => r.id !== room.id);
};

const uploadRoomMedia = async (e, room, type) => {
    const file = e.target.files[0];
    if(!file) return;
    const formData = new FormData();
    formData.append(type, file);
    formData.append('name', room.name);
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators/rooms/${room.id}`, formData, {
            headers: {'Content-Type': 'multipart/form-data'}
        });
        const idx = activeConfigurator.value.rooms.findIndex(r => r.id === room.id);
        if(idx > -1) activeConfigurator.value.rooms[idx] = res.data;
    } catch(err) {
        alert("Upload fehlgeschlagen");
    }
};

const getMediaUrl = (obj, collection) => {
    if(!obj || !obj.media || obj.media.length === 0) return null;
    const m = obj.media.find(x => x.collection_name === collection);
    return m ? m.original_url : null;
};

// Categories
const createNewCategory = async (room) => {
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators/rooms/${room.id}/categories`, { 
            name: 'Neue Kategorie',
            type: 'material' 
        });
        if(!room.categories) room.categories = [];
        room.categories.push(res.data);
    } catch (e) {
        alert('Fehler');
    }
};

const updateCategory = async (cat) => {
    await window.axios.put(`/projects/${props.project.id}/configurators/categories/${cat.id}`, { name: cat.name, type: cat.type });
};

const deleteCategory = async (cat) => {
    if(!confirm("Kategorie löschen?")) return;
    await window.axios.delete(`/projects/${props.project.id}/configurators/categories/${cat.id}`);
    const room = activeConfigurator.value.rooms.find(r => r.id === cat.apartment_configurator_room_id);
    if(room) room.categories = room.categories.filter(c => c.id !== cat.id);
};

// Options
const createNewOption = async (cat) => {
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators/categories/${cat.id}/options`, { 
            label: 'Neue Option'
        });
        if(!cat.options) cat.options = [];
        cat.options.push(res.data);
    } catch (e) {
        alert('Fehler');
    }
};

const updateOption = async (opt) => {
    await window.axios.post(`/projects/${props.project.id}/configurators/options/${opt.id}`, { 
        label: opt.label,
        price_surcharge: opt.price_surcharge,
        color_hex: opt.color_hex,
        texture_scale: opt.texture_scale,
        is_default: opt.is_default,
        mesh_names: JSON.stringify(opt.mesh_names || [])
    });
};

const deleteOption = async (opt) => {
    if(!confirm("Option löschen?")) return;
    await window.axios.delete(`/projects/${props.project.id}/configurators/options/${opt.id}`);
    const room = activeConfigurator.value.rooms.find(r => r.categories?.some(c => c.id === opt.apartment_configurator_category_id));
    if(room) {
        const cat = room.categories.find(c => c.id === opt.apartment_configurator_category_id);
        if(cat) cat.options = cat.options.filter(o => o.id !== opt.id);
    }
};

const uploadOptionImage = async (e, opt, collectionName) => {
    const file = e.target.files[0];
    if(!file) return;
    const formData = new FormData();
    formData.append(collectionName, file);
    formData.append('label', opt.label); // required by validation
    
    try {
        const res = await window.axios.post(`/projects/${props.project.id}/configurators/options/${opt.id}`, formData, {
            headers: {'Content-Type': 'multipart/form-data'}
        });
        // Replace opt with returning data
        Object.assign(opt, res.data);
    } catch(err) {
        alert("Upload fehlgeschlagen");
    }
};

// 3D Click Handling to get Mesh Names
const handleModelClick = (e) => {
    // Model-viewer specific API to get mesh under cursor
    const modelViewer = e.target;
    if (modelViewer && typeof modelViewer.positionAndNormalFromPoint === 'function') {
        const hit = modelViewer.positionAndNormalFromPoint(e.clientX, e.clientY);
        if (hit) {
            // Unfortunately model-viewer doesn't return the raw Three.js mesh name easily from a click event natively in v3.
            // But we can try to guess it or use a trick. Actually, model-viewer v3 has scene graph access.
            const material = modelViewer.materialFromPoint(e.clientX, e.clientY);
            if (material) {
                // We copy the material name to clipboard as a fallback
                navigator.clipboard.writeText(material.name);
                alert(`Material-Name kopiert: ${material.name}\n\nTipp: Wenn du Meshes steuern willst, benutze den Materialnamen in der model-viewer API oder füge es ins Feld ein.`);
            }
        }
    }
};
</script>
