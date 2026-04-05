<template>
    <div class="h-full w-full flex flex-col bg-gray-50 relative overflow-hidden" style="min-height: 80vh;">
        
        <!-- Top Room Navigation (Horizontal scrollable) -->
        <div class="bg-white shadow-sm z-20 py-3 px-4 flex-shrink-0">
            <div class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide snap-x">
                <div v-for="room in configurator.rooms" :key="room.id" 
                     @click="activeRoom = room"
                     :class="[activeRoom?.id === room.id ? 'ring-2 ring-brand-500 bg-brand-50' : 'hover:bg-gray-100 ring-1 ring-gray-200']"
                     class="flex-shrink-0 w-32 md:w-40 rounded-xl overflow-hidden cursor-pointer transition-all snap-start flex flex-col bg-white">
                    <div class="h-20 w-full bg-gray-200 bg-cover bg-center" :style="roomPreviewImage(room) ? `background-image: url('${roomPreviewImage(room)}')` : ''"></div>
                    <div class="p-2 text-center text-xs font-semibold text-gray-800 break-words line-clamp-1">
                        {{ room.name }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">
            
            <!-- 3D Viewer Area -->
            <div class="flex-1 relative bg-gray-100 w-full h-full">
                <!-- Loading Overlay -->
                <div v-if="isLoadingModel" class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-sm">
                    <div class="px-6 py-3 bg-white rounded-full shadow-lg font-medium text-brand-600 flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Raum wird geladen...
                    </div>
                </div>

                <model-viewer 
                    v-if="activeRoomGlb"
                    ref="viewerRef"
                    :src="activeRoomGlb"
                    :environment-image="activeRoomHdri"
                    camera-controls
                    autoplay
                    shadow-intensity="1"
                    shadow-softness="1"
                    exposure="1"
                    interaction-prompt="none"
                    @load="onModelLoaded"
                    style="width: 100%; height: 100%;">
                </model-viewer>
                
                <div v-else class="absolute inset-0 flex items-center justify-center text-gray-400 p-8 text-center">
                    Für diesen Raum wurde noch kein 3D-Modell hinterlegt.
                </div>
                
                <!-- Floating Info (Surcharge Total) Desktop UI bottom left -->
                <div class="absolute bottom-6 left-6 z-10 hidden lg:block">
                    <div class="bg-white/90 backdrop-blur shadow-lg rounded-xl p-4 min-w-[200px]">
                        <div class="text-xs text-gray-500 uppercase tracking-wide font-bold mb-1">Aufpreis Gesamt</div>
                        <div class="text-2xl font-black text-gray-900">+ {{ formatPrice(totalSurcharge) }}</div>
                        <button @click="showSummary = true" class="mt-3 w-full bg-gray-900 hover:bg-black text-white text-sm font-semibold py-2 rounded-lg transition">
                            Zusammenfassung ansehen
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar: Configuration Options -->
            <div class="w-full lg:w-96 bg-white border-l h-full flex flex-col z-20 shadow-xl overflow-hidden shrink-0">
                <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-gray-800">{{ activeRoom?.name || 'Bitte Raum wählen' }}</h3>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-hide">
                    <div v-if="!activeRoom" class="text-sm text-gray-500">
                        Bitte wähle oben einen Raum aus, um die Optionen zu sehen.
                    </div>
                    
                    <div v-for="category in activeRoomCategories" :key="category.id" class="space-y-3">
                        <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ category.name }}</h4>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div v-for="option in category.options" :key="option.id"
                                 @click="selectOption(category, option)"
                                 :class="[isSelected(category.id, option.id) ? 'ring-2 ring-brand-500 bg-brand-50' : 'ring-1 ring-gray-200 hover:bg-gray-50']"
                                 class="relative rounded-lg cursor-pointer overflow-hidden transition group bg-white flex flex-col">
                                
                                <div class="h-20 w-full bg-cover bg-center bg-gray-100" :style="getOptionPreviewStyle(option)"></div>
                                
                                <div class="p-2 flex-1 flex flex-col">
                                    <div class="text-[11px] font-semibold text-gray-800 leading-tight flex-1">
                                        {{ option.label }}
                                    </div>
                                    <div class="mt-1 text-[10px] whitespace-nowrap">
                                        <span v-if="parseFloat(option.price_surcharge) > 0" class="font-bold text-gray-900">+ {{ formatPrice(option.price_surcharge) }}</span>
                                        <span v-else class="text-gray-500">inklusive</span>
                                    </div>
                                </div>
                                
                                <!-- Selection Checkmark -->
                                <div v-if="isSelected(category.id, option.id)" class="absolute top-1 right-1 bg-brand-500 text-white rounded-full p-0.5 shadow-sm">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Action Bar -->
                <div class="lg:hidden p-4 border-t bg-white flex items-center justify-between">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase font-bold">Aufpreis Gesamt</div>
                        <div class="text-lg font-black">+ {{ formatPrice(totalSurcharge) }}</div>
                    </div>
                    <button @click="showSummary = true" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-bold">
                        Zusammenfassung
                    </button>
                </div>
            </div>
            
        </div>
        
        <!-- Summary / Inquiry Modal Side-Over -->
        <div v-if="showSummary" class="fixed inset-0 z-50 overflow-hidden">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="showSummary = false"></div>
            
            <div class="absolute inset-y-0 right-0 max-w-xl w-full bg-white shadow-2xl flex flex-col transform transition-transform border-l">
                
                <div class="px-6 py-4 border-b flex items-center justify-between bg-gray-50 sticky top-0 z-10">
                    <h2 class="text-xl font-bold text-gray-900">Gewählte Konfiguration</h2>
                    <button @click="showSummary = false" class="p-2 hover:bg-gray-200 rounded-full transition">
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-white">
                    
                    <div v-for="room in configurator.rooms" :key="room.id" class="border rounded-xl shadow-sm overflow-hidden bg-white">
                        <div class="h-32 w-full bg-cover bg-center relative" :style="roomPreviewImage(room) ? `background-image: url('${roomPreviewImage(room)}')` : ''">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <h3 class="absolute bottom-3 left-4 text-white font-bold text-lg drop-shadow-md">{{ room.name }}</h3>
                        </div>
                        
                        <div class="divide-y">
                            <div v-for="category in room.categories" :key="category.id" class="p-4 grid grid-cols-12 items-center hover:bg-gray-50 transition">
                                <div class="col-span-8 pr-4">
                                    <div class="text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">{{ category.name }}</div>
                                    <div class="font-medium text-sm text-gray-900">{{ getSelectedOptionLabel(category.id) || 'Keine Auswahl' }}</div>
                                </div>
                                <div class="col-span-4 text-right">
                                    <span v-if="getSelectedOptionPrice(category.id) > 0" class="font-bold text-sm text-gray-900">+ {{ formatPrice(getSelectedOptionPrice(category.id)) }}</span>
                                    <span v-else class="text-xs text-gray-500">inklusive</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="p-6 border-t bg-gray-50 space-y-4 shadow-lg z-10 sticky bottom-0">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-gray-600">Aufpreis aus Konfiguration</span>
                        <span class="text-2xl font-black text-gray-900">+ {{ formatPrice(totalSurcharge) }}</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="generateSummaryPdf" class="w-full bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold py-3 rounded-lg flex justify-center items-center gap-2 transition shadow-sm">
                            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Als PDF öffnen
                        </button>
                        <button @click="submitInquiry" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-lg flex justify-center items-center gap-2 transition shadow-md">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            Anfrage senden
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    configurator: {
        type: Object,
        required: true
    },
    apartmentId: {
        type: [Number, String],
        required: false
    }
});

const emit = defineEmits(['open-inquiry', 'download-pdf']);

const viewerRef = ref(null);
const activeRoom = ref(null);
const isLoadingModel = ref(false);
const showSummary = ref(false);

// Stores category ID -> option ID mapping
const selections = ref({});

onMounted(() => {
    // Select first room
    if (props.configurator && props.configurator.rooms && props.configurator.rooms.length > 0) {
        activeRoom.value = props.configurator.rooms[0];
    }
    
    // Initialize default selections globally for all rooms
    props.configurator.rooms?.forEach(room => {
        room.categories?.forEach(cat => {
            if (cat.options?.length > 0) {
                // Find default
                const def = cat.options.find(o => o.is_default);
                selections.value[cat.id] = def ? def.id : cat.options[0].id;
            }
        });
    });
});

const getMedia = (obj, collection) => {
    if (!obj || !obj.media || obj.media.length === 0) return null;
    const m = obj.media.find(x => x.collection_name === collection);
    return m ? m.original_url : null;
};

const roomPreviewImage = (room) => {
    return getMedia(room, 'preview');
};

const activeRoomGlb = computed(() => getMedia(activeRoom.value, 'glb'));
const activeRoomHdri = computed(() => getMedia(activeRoom.value, 'hdri'));

const activeRoomCategories = computed(() => {
    return activeRoom.value?.categories || [];
});

const isSelected = (catId, optId) => selections.value[catId] === optId;

const selectOption = (category, option) => {
    selections.value[category.id] = option.id;
    applyLogic(category, option);
};

const getOptionPreviewStyle = (option) => {
    const previewUrl = getMedia(option, 'preview');
    if (previewUrl) return `background-image: url('${previewUrl}')`;
    
    const textureUrl = getMedia(option, 'texture');
    if (textureUrl) return `background-image: url('${textureUrl}')`;
    
    if (option.color_hex) return `background-color: ${option.color_hex}`;
    
    return '';
};

// Pricing and Summary logic
const formatPrice = (p) => {
    const num = parseFloat(p);
    return num.toLocaleString('de-CH', { style: 'currency', currency: 'CHF' });
};

const totalSurcharge = computed(() => {
    let total = 0;
    props.configurator.rooms?.forEach(room => {
        room.categories?.forEach(cat => {
            const sid = selections.value[cat.id];
            if (sid) {
                const opt = cat.options?.find(o => o.id === sid);
                if (opt && parseFloat(opt.price_surcharge) > 0) {
                    total += parseFloat(opt.price_surcharge);
                }
            }
        });
    });
    return total;
});

const getSelectedOptionLabel = (catId) => {
    const optId = selections.value[catId];
    if (!optId) return '';
    // Search entirely
    for (const r of props.configurator.rooms) {
        for (const c of r.categories) {
            if (c.id === catId) {
                const o = c.options.find(x => x.id === optId);
                return o ? o.label : '';
            }
        }
    }
    return '';
};

const getSelectedOptionPrice = (catId) => {
    const optId = selections.value[catId];
    if (!optId) return 0;
    for (const r of props.configurator.rooms) {
        for (const c of r.categories) {
            if (c.id === catId) {
                const o = c.options.find(x => x.id === optId);
                return o ? parseFloat(o.price_surcharge) : 0;
            }
        }
    }
    return 0;
};

// 3D Logic
watch(activeRoomGlb, () => {
    if (activeRoomGlb.value) {
        isLoadingModel.value = true;
    }
});

const onModelLoaded = () => {
    isLoadingModel.value = false;
    applyAllSelectionsForCurrentRoom();
};

const applyAllSelectionsForCurrentRoom = () => {
    if (!viewerRef.value || !viewerRef.value.model) return;
    
    // Apply defaults for active room
    activeRoomCategories.value.forEach(cat => {
        const optionId = selections.value[cat.id];
        if (optionId) {
            const option = cat.options.find(o => o.id === optionId);
            if (option) {
                applyLogic(cat, option);
            }
        }
    });
};

const applyLogic = async (category, option) => {
    if (!viewerRef.value || !viewerRef.value.model) return;
    
    try {
        if (category.type === 'visibility') {
            // Hide all meshes from ALL options in this category
            category.options.forEach(opt => {
                const meshes = opt.mesh_names || [];
                meshes.forEach(m => {
                    const material = viewerRef.value.model.getMaterialByName(m);
                     // If targeting a node/mesh directly, we might need model-viewer's scene graph nodes, 
                     // but model-viewer allows showing/hiding via material.setAlphaMode or opacity, 
                     // or we can adjust nodes if available. Let's try material first if mesh_name is material name
                     // Alternatively, if they are nodes:
                     
                     // We will try finding the node in the scene graph
                     const node = viewerRef.value.model.getNodeByName(m);
                     if (node && node.renderer) {
                         // Model viewer internal structure logic maybe different depending on version.
                     }
                });
            });
            // Unfortunately model-viewer doesn't have a reliable `hideObjectByName` natively exposed via HTML attributes.
            // It uses the material graph. Many workflows hide a node by scaling it to 0.
            // Or set baseColorFactor opacity to 0.
            category.options.forEach(opt => {
                const meshes = opt.mesh_names || [];
                // If it is NOT the selected option, make its opacity 0 or invisible
                const isSelectedOption = opt.id === option.id;
                meshes.forEach(m => {
                    let material = viewerRef.value.model.getMaterialByName(m);
                    if (material) {
                        material.setAlphaMode(isSelectedOption ? 'OPAQUE' : 'MASK');
                        material.pbrMetallicRoughness.setBaseColorFactor(isSelectedOption ? [1,1,1,1] : [1,1,1,0]);
                    }
                });
            });
        } 
        else if (category.type === 'material') {
            // Apply Texture or Color to specific meshes
            const targetColor = option.color_hex;
            const targetTexture = getMedia(option, 'texture');
            const targetScale = parseFloat(option.texture_scale) || 1;
            const meshes = option.mesh_names || [];

            meshes.forEach(async (meshName) => {
                let material = viewerRef.value.model.getMaterialByName(meshName);
                if (material) {
                    if (targetTexture) {
                        // Create and apply texture dynamically (using model-viewer API snippet)
                        const texture = await viewerRef.value.createTexture(targetTexture);
                        if (texture) {
                            material.pbrMetallicRoughness.baseColorTexture.setTexture(texture);
                            // Set scaling if required (model viewer has limited UV transform but sampler scaling is possible in GLTF)
                        }
                    } else if (targetColor) {
                        // Apply hex color
                        const colorArr = hexToRgba(targetColor);
                        material.pbrMetallicRoughness.setBaseColorFactor(colorArr);
                    }
                }
            });
        }
    } catch (e) {
        console.warn("Failed to apply 3D change. Ensure mesh names exist in the GLB.", e);
    }
};

// Utils
const hexToRgba = (hex) => {
    let c;
    if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
        c= hex.substring(1).split('');
        if(c.length== 3){
            c= [c[0], c[0], c[1], c[1], c[2], c[2]];
        }
        c= '0x'+c.join('');
        return [((c>>16)&255)/255, ((c>>8)&255)/255, (c&255)/255, 1];
    }
    return [1,1,1,1];
};

const generateSummaryPdf = () => {
    emit('download-pdf', {
        selections: selections.value,
        total_surcharge: totalSurcharge.value
    });
};

const submitInquiry = () => {
    emit('open-inquiry', {
        apartment_id: props.apartmentId,
        configurator_state: selections.value,
        total_surcharge: totalSurcharge.value,
        human_readable: generateHumanReadableConfig()
    });
    showSummary.value = false;
};

const generateHumanReadableConfig = () => {
    let result = "Gewählte Ausstattung:\n";
    props.configurator.rooms?.forEach(room => {
        let hasSelection = false;
        let cLog = "";
        room.categories?.forEach(cat => {
            const sid = selections.value[cat.id];
            if (sid) {
                const opt = cat.options?.find(o => o.id === sid);
                if (opt) {
                    hasSelection = true;
                    cLog += `  - ${cat.name}: ${opt.label} (${parseFloat(opt.price_surcharge) > 0 ? '+ CHF '+parseFloat(opt.price_surcharge) : 'inklusive'})\n`;
                }
            }
        });
        if (hasSelection) {
            result += `\nRaum: ${room.name}\n${cLog}`;
        }
    });
    
    if (totalSurcharge.value > 0) {
        result += `\nAufpreis Gesamt: CHF ${totalSurcharge.value}\n`;
    }
    return result;
};
</script>
