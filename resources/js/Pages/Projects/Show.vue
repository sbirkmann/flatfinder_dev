<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import ProjectStatistics from './Components/ProjectStatistics.vue';
import VirtualToursTab from './Components/VirtualToursTab.vue';
import draggable from 'vuedraggable';
import {
    MapPinIcon,
    HomeIcon,
    ArrowUpOnSquareStackIcon,
    BuildingOfficeIcon,
    Bars3CenterLeftIcon,
    Squares2X2Icon,
    CheckCircleIcon,
    PhotoIcon,
    MagnifyingGlassIcon,
    InformationCircleIcon,
    UserGroupIcon,
    TrashIcon,
    XMarkIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    BuildingStorefrontIcon,
    ChartBarIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    project: Object,
    teamContacts: { type: Array, default: () => [] },
});

const activeTab = ref('general');
const isEditingProject = ref(false);

// --- Contact Assignment ---
const contactAssignId = ref('');
const contactAssignNotify = ref(false);
const availableContacts = computed(() =>
    props.teamContacts.filter(c => !props.project.contacts?.some(pc => pc.id === c.id))
);
const attachContact = () => {
    if (!contactAssignId.value) return;
    router.post(route('contacts.attach', { project: props.project.id }), {
        contact_id: contactAssignId.value,
        notify_on_inquiry: contactAssignNotify.value,
    }, { preserveScroll: true, onSuccess: () => { contactAssignId.value = ''; contactAssignNotify.value = false; } });
};

// Edit Project Setup
const projectForm = useForm({
    name: props.project.name || '',
    address: props.project.address || '',
    zip: props.project.zip || '',
    city: props.project.city || '',
    description: props.project.description || '',
    has_google_map: props.project.has_google_map || false,
    initial_slider_id: props.project.initial_slider_id || '',
    color_settings: (() => {
        let cs = props.project.color_settings || {
            frei: { base: '#70cc52', hover: '#4da630', active: '#befc4b', border: '#ffffff', border_width: 2 },
            vermietet: { base: '#ff4d4d', hover: '#cc0000', active: '#ff8080', border: '#ffffff', border_width: 2 },
            verkauft: { base: '#e60000', hover: '#990000', active: '#ff3333', border: '#ffffff', border_width: 2 },
            reserviert: { base: '#ffcc00', hover: '#cc9900', active: '#ffee80', border: '#ffffff', border_width: 2 }
        };
        if (!cs.primary) cs.primary = { base: '#ab715c', hover: '#96624f', text: '#ffffff' };
        return cs;
    })(),
    floating_bar: props.project.floating_bar || {
        active: false,
        position: 'right',
        bg_color: '#2a2a2a',
        text_color: '#ffffff',
        active_color: '#ab715c',
        show_logo: true,
        logo_width: 80,
        buttons: []
    },
    comparison_settings: props.project.comparison_settings || {
        active: false,
        fields: ['name', 'price', 'size', 'rooms', 'status']
    },
    poi_settings: props.project.poi_settings || {
        active: false,
        radius: 2000,
        categories: ['supermarket', 'school', 'transit']
    },
    contact_form_config: props.project.contact_form_config || {
        title: 'Interesse wecken',
        subtitle: 'Hinterlassen Sie Ihre Kontaktdaten für weitere Informationen.',
        email_recipients: '', // Fallback or override
        fields: [
            { id: 'first_name', label: 'Vorname', type: 'text', required: true, width: 'half' },
            { id: 'last_name', label: 'Nachname', type: 'text', required: true, width: 'half' },
            { id: 'email', label: 'E-Mail Adresse', type: 'email', required: true, width: 'full' },
            { id: 'phone', label: 'Telefonnummer', type: 'tel', required: false, width: 'full' },
            { id: 'message', label: 'Ihre Nachricht', type: 'textarea', required: false, width: 'full' }
        ]
    }
});

const addFloatingBarButton = () => {
    projectForm.floating_bar.buttons.push({
        id: 'fbtn_' + Date.now(),
        label: 'Neuer Button',
        icon_type: 'icon_info',
        custom_svg: '',
        action_type: 'slider',
        action_target: null
    });
};

const addContactField = () => {
    projectForm.contact_form_config.fields.push({
        id: 'field_' + Date.now(),
        label: 'Neues Feld',
        type: 'text',
        required: false,
        width: 'full'
    });
};

const saveProjectDetails = () => {
    projectForm.put(`/projects/${props.project.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            alert('Projektdaten gespeichert!');
            isEditingProject.value = false;
        },
    });
};

// Upload forms
const imageForm = useForm({
    project_image: null,
    preview_image: null,
    project_pdf: null,
    logo: null,
});

const handleImageUpload = () => {
    imageForm.post(`/projects/${props.project.id}/images`, {
        preserveScroll: true,
        onSuccess: () => {
            alert('Dateien erfolgreich hochgeladen!');
            imageForm.reset();
        },
    });
};

// Generic Inline Flow
const editingEntity = ref({ type: null });
const modalData = ref({});
const relationsDataForm = useForm({
    id: null,
    model: '',
    payload: {},
});

const getGermanName = (type) => {
    const map = {
        'project': 'Projekt',
        'view': 'Ansicht',
        'house': 'Haus',
        'floor': 'Etage',
        'apartment': 'Wohnung',
        'layer': 'Layer',
        'frame': 'Frame',
        'feature': 'Ausstattung',
        'infoframe': 'Infoframe',
        'projectContact': 'Ansprechpartner'
    };
    return map[type] || type;
};

const editInline = (type, data = {}) => {
    // Only copy scalar/primitive fields – never eager-loaded relation arrays
    const RELATIONS = ['features', 'rooms', 'rooms_list', 'media', 'imageGroups', 'bestView', 'bestFrame', 'slides'];
    let mappedData = Object.fromEntries(
        Object.entries(data).filter(([k]) => !RELATIONS.includes(k))
    );

    if (type === 'apartment') {
        mappedData.features = data.features ? data.features.map(f => f.id) : [];
        // Ensure status has a default so the select renders properly
        if (!mappedData.status) mappedData.status = 'Frei';
    }
    modalData.value = mappedData;
    editingEntity.value.type = type;
};

const closeInline = () => {
    editingEntity.value.type = null;
    modalData.value = {};
    relationsDataForm.reset();
};

const addCustomButton = () => {
    if (!modalData.value.custom_buttons) {
        modalData.value.custom_buttons = [];
    }
    modalData.value.custom_buttons.push({
        id: 'btn_' + Date.now(),
        title: '',
        action_type: 'slider',
        action_target: null
    });
};

const saveEntity = (modelClass, additionalData = {}) => {
    relationsDataForm.model = modelClass;
    relationsDataForm.payload = { ...modalData.value, ...additionalData };
    relationsDataForm.id = additionalData.id || modalData.value.id || null;

    relationsDataForm.post(`/projects/${props.project.id}/relation/store`, {
        preserveScroll: true,
        onSuccess: () => closeInline(),
    });
};

const deleteEntity = (modelClass, id) => {
    if (confirm('Wirklich löschen?')) {
        router.post(`/projects/${props.project.id}/relation/delete/${id}`, { model: modelClass }, {
            preserveScroll: true,
        });
    }
};

// Layer toggle for views
const toggleViewLayer = (viewId, layerId) => {
    router.post(`/projects/${props.project.id}/views/${viewId}/toggle-layer`, { layer_id: layerId }, { preserveScroll: true });
};

// Rooms Logic
const newRoom = ref({ name: '', sqm: null });

const saveNewRoom = () => {
    if (!currentApartment.value?.id || !newRoom.value.name) return;
    router.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Room',
        payload: {
            apartment_id: currentApartment.value.id,
            name: newRoom.value.name,
            sqm: newRoom.value.sqm
        }
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newRoom.value = { name: '', sqm: null };
        }
    });
};

const roomTotalSqm = computed(() => {
    if (!currentApartment.value?.rooms_list) return 0;
    return currentApartment.value.rooms_list.reduce((sum, room) => sum + (parseFloat(room.sqm) || 0), 0).toFixed(2);
});

// Apartment Media & Image Groups Logic
const newImageGroupName = ref('');

const isUploadingMediaArray = ref(false);
const uploadMediaProgress = ref(0);

const uploadMedia = async (e, model, id, collection = 'default') => {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    isUploadingMediaArray.value = true;
    e.target.disabled = true;

    for (let i = 0; i < files.length; i++) {
        uploadMediaProgress.value = Math.round(((i) / files.length) * 100);
        
        const data = new FormData();
        data.append('file', files[i]);
        data.append('collection', collection);

        try {
            await window.axios.post(`/media/upload/${model}/${id}`, data, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        } catch (err) {
            console.error('File upload failed', files[i].name, err);
            let msg = 'Fehler beim Upload von ' + files[i].name;
            if (err.response && err.response.data) {
                if (err.response.data.message) msg += ': ' + err.response.data.message;
                if (err.response.data.errors) {
                    msg += '\n' + Object.values(err.response.data.errors).flat().join('\n');
                }
            }
            alert(msg);
        }
    }
    
    uploadMediaProgress.value = 100;

    // Reload entire UI after all sequential uploads are finished
    router.reload({ 
        preserveScroll: true, 
        onSuccess: () => {
            e.target.value = '';
            e.target.disabled = false;
            isUploadingMediaArray.value = false;
            uploadMediaProgress.value = 0;
        }
    });
};

const updateMediaCaption = (media) => {
    router.put(`/media/${media.id}`, { custom_properties: { caption: media.custom_properties?.caption || '' } }, { preserveScroll: true });
};

const deleteMedia = (mediaId) => {
    if (confirm('Bild löschen?')) {
        router.delete(`/media/${mediaId}`, { preserveScroll: true });
    }
};

const saveNewImageGroup = () => {
    if (!newImageGroupName.value.trim()) return;
    relationsDataForm.model = 'ApartmentImageGroup';
    relationsDataForm.payload = { apartment_id: modalData.value.id, name: newImageGroupName.value, is_active: true, sort_order: 1 };
    relationsDataForm.id = null;
    relationsDataForm.post(`/projects/${props.project.id}/relation/store`, { 
        preserveScroll: true,
        onSuccess: () => newImageGroupName.value = ''
    });
};

const saveImageGroup = (group) => {
    relationsDataForm.model = 'ApartmentImageGroup';
    relationsDataForm.payload = { ...group };
    relationsDataForm.id = group.id;
    relationsDataForm.post(`/projects/${props.project.id}/relation/store`, { preserveScroll: true });
};

// --- Slider Management ---
const newSliderName = ref('');
const newSlide = ref({}); // keyed by slider.id

// Initialize a blank slide form for each slider
const initNewSlide = (sliderId) => {
    if (!newSlide.value[sliderId]) {
        newSlide.value[sliderId] = { type: 'image', title: '', infoframe_id: null, iframe_url: '', imageFile: null, pdfFile: null };
    }
};
// Watch sliders to init forms
watch(() => props.project.sliders, (sliders) => {
    sliders?.forEach(s => initNewSlide(s.id));
}, { immediate: true });

const createSlider = () => {
    if (!newSliderName.value.trim()) return;
    router.post(`/projects/${props.project.id}/sliders`, { name: newSliderName.value }, {
        preserveScroll: true,
        onSuccess: () => { newSliderName.value = ''; },
    });
};

const deleteSlider = (id) => {
    if (confirm('Slider und alle Slides löschen?')) {
        router.delete(`/sliders/${id}`, { preserveScroll: true });
    }
};

const createSlide = (sliderId) => {
    const s = newSlide.value[sliderId];
    if (!s) return;
    const formData = new FormData();
    formData.append('type', s.type);
    if (s.title) formData.append('title', s.title);
    if (s.type === 'infoframe' && s.infoframe_id) formData.append('infoframe_id', s.infoframe_id);
    if (s.type === 'iframe' && s.iframe_url) formData.append('iframe_url', s.iframe_url);
    if (s.type === 'image' && s.imageFile) formData.append('image', s.imageFile);
    if (s.type === 'pdf' && s.pdfFile) formData.append('pdf', s.pdfFile);

    router.post(`/sliders/${sliderId}/slides`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { newSlide.value[sliderId] = { type: 'image', title: '', infoframe_id: null, iframe_url: '', imageFile: null, pdfFile: null }; },
    });
};

const deleteSlide = (id) => {
    if (confirm('Slide löschen?')) {
        router.delete(`/slides/${id}`, { preserveScroll: true });
    }
};


// Filters
const searchQueries = ref({
    views: '',
    houses: '',
    floors: '',
    apartments: '',
    layers: '',
});

const filteredViews = computed(() => {
    if (!props.project.views) return [];
    return props.project.views.filter(v => v.name.toLowerCase().includes(searchQueries.value.views.toLowerCase()));
});
const filteredHouses = computed(() => {
    if (!props.project.houses) return [];
    return props.project.houses.filter(h => h.name.toLowerCase().includes(searchQueries.value.houses.toLowerCase()));
});
const filteredFloors = computed(() => {
    if (!props.project.floors) return [];
    return props.project.floors.filter(f => f.name.toLowerCase().includes(searchQueries.value.floors.toLowerCase()));
});
const filteredApartments = computed(() => {
    if (!props.project.apartments) return [];
    return props.project.apartments.filter(a => a.name.toLowerCase().includes(searchQueries.value.apartments.toLowerCase()));
});
const filteredLayers = computed(() => {
    if (!props.project.layers) return [];
    return props.project.layers.filter(l => l.name.toLowerCase().includes(searchQueries.value.layers.toLowerCase()));
});

const currentApartment = computed(() => {
    if (editingEntity.value.type !== 'apartment' || !modalData.value.id) return null;
    return props.project.apartments?.find(a => a.id === modalData.value.id);
});

const currentApartmentExpose = computed(() => {
    if (!currentApartment.value || !currentApartment.value.media) return null;
    return currentApartment.value.media.find(m => m.collection_name === 'expose') || null;
});

// Frames logic
const viewFramesModal = ref(null);
const activeViewId = ref(null);

const activeView = computed(() => {
    if (!activeViewId.value || !props.project.views) return null;
    return props.project.views.find(v => v.id === activeViewId.value);
});

const openFrames = (view) => {
    activeViewId.value = view.id;
    viewFramesModal.value = true;
};

const swapFrames = (frame1, index1, frame2, index2) => {
    frame1.index = index2;
    frame2.index = index1;

    router.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Frame', id: frame1.id, payload: { index: frame1.index, view_id: activeView.value.id }
    }, {
        preserveScroll: true,
        onSuccess: () => {
            router.post(`/projects/${props.project.id}/relation/store`, {
                model: 'Frame', id: frame2.id, payload: { index: frame2.index, view_id: activeView.value.id }
            }, { preserveScroll: true });
        }
    });
};

const moveFrameUp = (frame, listIndex) => {
    if (listIndex > 0) swapFrames(frame, frame.index, activeView.value.frames[listIndex - 1], activeView.value.frames[listIndex - 1].index);
};
const moveFrameDown = (frame, listIndex) => {
    if (listIndex < (activeView.value.frames?.length || 0) - 1) swapFrames(frame, frame.index, activeView.value.frames[listIndex + 1], activeView.value.frames[listIndex + 1].index);
};

const bulkCreateCount = ref(10);
const bulkCreateFrames = async () => {
    if (bulkCreateCount.value < 1) return;
    let nextIndex = (activeView.value.frames?.length || 0) + 1;
    for(let i=0; i<bulkCreateCount.value; i++) {
        await window.axios.post(`/projects/${props.project.id}/relation/store`, {
            model: 'Frame', payload: { view_id: activeView.value.id, index: nextIndex + i, is_stop_frame: false }
        });
    }
    router.reload({ preserveScroll: true });
};

const isBulkUploading = ref(false);
const bulkUploadLayerId = ref(null);
const bulkFiles = ref([]);
const isUploadingBulk = ref(false);

const startBulkUpload = () => {
    isBulkUploading.value = true;
    bulkUploadLayerId.value = activeView.value.layers?.[0]?.id || null;
    bulkFiles.value = [];
};

const cancelBulkUpload = () => {
    isBulkUploading.value = false;
    bulkFiles.value = [];
};

const currentContactMedia = (data) => {
    if (!props.project.project_contacts) return null;
    const contact = props.project.project_contacts.find(c => c.id === data.id);
    if (!contact || !contact.media || contact.media.length === 0) return null;
    return contact.media.find(m => m.collection_name === 'avatar') || contact.media[0];
};

const handleBulkFileSelect = (e) => {
    Array.from(e.target.files).forEach(file => {
        const match = file.name.match(/\d+/);
        const suggestedIndex = match ? parseInt(match[0], 10) : null;
        
        let matchingFrame = null;
        if (suggestedIndex !== null && activeView.value.frames) {
            matchingFrame = activeView.value.frames.find(f => parseInt(f.index, 10) === suggestedIndex);
        }

        bulkFiles.value.push({ 
            file, 
            frameIndex: matchingFrame ? matchingFrame.index : null, 
            progress: 0, 
            status: 'pending', 
            tempUrl: URL.createObjectURL(file),
            suggestedIndex
        });
    });
    bulkFiles.value.sort((a,b) => (a.suggestedIndex || 0) - (b.suggestedIndex || 0));
    e.target.value = ''; // reset
};

const removeBulkFile = (idx) => {
    bulkFiles.value.splice(idx, 1);
};

const executeBulkUpload = async () => {
    if (!bulkUploadLayerId.value || bulkFiles.value.length === 0) return;
    
    // Check if any frame mapping is missing
    if (bulkFiles.value.some(i => i.frameIndex === null)) {
        alert("Bitte weise allen Bildern einen vorhandenen Ziel-Frame zu, oder entferne die Bilder ohne Zuordnung.");
        return;
    }
    
    isUploadingBulk.value = true;
    
    for (let item of bulkFiles.value) {
        if (item.status === 'success') continue;
        item.status = 'uploading';
        
        try {
            // Find EXACT matching frame
            let frame = activeView.value.frames?.find(f => parseInt(f.index, 10) === parseInt(item.frameIndex, 10));
            
            if (!frame) {
                // Should not happen with the select dropdown, but just in case
                item.status = 'error';
                continue;
            }
            
            // Now upload the media
            const formData = new FormData();
            formData.append('file', item.file);
            formData.append('collection', 'layer_' + bulkUploadLayerId.value);
            
            await window.axios.post(`/media/upload/Frame/${frame.id}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (progressEvent) => {
                    item.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            });
            
            item.status = 'success';
        } catch (e) {
            console.error("Upload failed for item", item, e);
            item.status = 'error';
        }
    }
    
    // Reload UI silently
    router.reload({ preserveScroll: true, onSuccess: () => {
        // Only if all success, close bulk wizard
        if (bulkFiles.value.every(i => i.status === 'success')) {
            isBulkUploading.value = false;
            bulkFiles.value = [];
        }
        isUploadingBulk.value = false;
    }});
};
</script>

<template>
    <AppLayout :title="`Projekt: ${project.name}`">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight flex items-center gap-2">
                    <BuildingOfficeIcon class="w-8 h-8 text-brand-500" />
                    {{ project.name }}
                </h2>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 mt-1 md:mt-0 flex items-center gap-1">
                        <MapPinIcon class="w-4 h-4" /> {{ project.address }}, {{ project.zip }} {{ project.city }}
                    </span>
                    <a :href="route('projects.public', project.id)" target="_blank" class="text-sm bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-md shadow-sm font-medium flex items-center gap-1">
                        Public URL öffnen
                    </a>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                
                <!-- Setup Tabs -->
                <div class="bg-white rounded-t-lg shadow-sm border-b border-gray-200 overflow-x-auto">
                    <nav class="flex space-x-1 p-2" aria-label="Tabs">
                        <button @click="activeTab = 'general'" :class="[activeTab === 'general' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <PhotoIcon class="w-5 h-5" /> Allgemein
                        </button>
                        <button @click="activeTab = 'views'" :class="[activeTab === 'views' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <ArrowUpOnSquareStackIcon class="w-5 h-5" /> Ansichten & Frames
                        </button>
                        <button @click="activeTab = 'houses'" :class="[activeTab === 'houses' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <HomeIcon class="w-5 h-5" /> Häuser
                        </button>
                        <button @click="activeTab = 'floors'" :class="[activeTab === 'floors' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <Bars3CenterLeftIcon class="w-5 h-5" /> Etagen
                        </button>
                        <button @click="activeTab = 'apartments'" :class="[activeTab === 'apartments' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <Squares2X2Icon class="w-5 h-5" /> Wohnungen
                        </button>
                        <button @click="activeTab = 'layers'" :class="[activeTab === 'layers' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <Squares2X2Icon class="w-5 h-5" /> Layer
                        </button>
                        <button @click="activeTab = 'features'" :class="[activeTab === 'features' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <StarIcon class="w-5 h-5" /> Ausstattung
                        </button>
                        <button @click="activeTab = 'infoframes'" :class="[activeTab === 'infoframes' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <InformationCircleIcon class="w-5 h-5" /> Infoframes
                        </button>
                        <button @click="activeTab = 'contacts'" :class="[activeTab === 'contacts' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <UserGroupIcon class="w-5 h-5" /> Ansprechpartner
                        </button>
                        <button @click="activeTab = 'sliders'" :class="[activeTab === 'sliders' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <BuildingStorefrontIcon class="w-5 h-5" /> Slider
                        </button>
                        <button @click="activeTab = 'statistics'" :class="[activeTab === 'statistics' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <ChartBarIcon class="w-5 h-5" /> Statistik
                        </button>
                        <button @click="activeTab = 'virtual-tours'" :class="[activeTab === 'virtual-tours' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <VideoCameraIcon class="w-5 h-5" /> Virtuelle Touren
                        </button>
                    </nav>
                </div>

                <!-- Tab: General Info & Uploads -->
                <div v-if="activeTab === 'general'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <div class="flex items-center justify-between border-b pb-2 mb-4">
                                <h3 class="text-lg font-bold flex items-center gap-2"><BuildingOfficeIcon class="w-6 h-6 text-brand-500"/> Projektdaten & Farben</h3>
                                <PrimaryButton v-if="!isEditingProject" @click="isEditingProject = true">Projekt bearbeiten</PrimaryButton>
                            </div>
                            
                            <div v-if="!isEditingProject" class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">Name</p>
                                    <p class="font-medium">{{ project.name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">Adresse</p>
                                    <p class="font-medium">{{ project.address || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">PLZ / Stadt</p>
                                    <p class="font-medium">{{ project.zip || '-' }} {{ project.city || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">Google Map</p>
                                    <p class="font-medium">
                                        <span v-if="project.has_google_map" class="text-green-600 font-bold">Aktiviert</span>
                                        <span v-else class="text-red-500 font-bold">Deaktiviert</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-bold uppercase">Start-Slider</p>
                                    <p class="font-medium">
                                        {{ project.sliders?.find(s => s.id === project.initial_slider_id)?.name || 'Keiner' }}
                                    </p>
                                </div>
                                <div class="col-span-1">
                                    <p class="text-xs text-gray-500 font-bold uppercase">Beschreibung</p>
                                    <p class="text-sm mt-1 text-gray-700 whitespace-pre-wrap">{{ project.description || 'Keine Beschreibung.' }}</p>
                                </div>
                            </div>

                            <div v-if="!isEditingProject" class="mb-6">
                                <h4 class="font-bold mb-3 text-gray-700">Übersicht: Farbeinstellungen</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="(settings, stateKey) in projectForm.color_settings" :key="'ov-'+stateKey" class="border rounded p-3 bg-gray-50 flex items-center justify-between">
                                        <span class="font-bold text-sm capitalize">{{ stateKey }}</span>
                                        <div class="flex gap-1" title="Grund | Hover | Aktiv | Rahmen">
                                            <div class="w-4 h-4 rounded-full border border-gray-300" :style="{ backgroundColor: settings.base }"></div>
                                            <div class="w-4 h-4 rounded-full border border-gray-300" :style="{ backgroundColor: settings.hover }"></div>
                                            <div class="w-4 h-4 rounded-full border border-gray-300" :style="{ backgroundColor: settings.active }"></div>
                                            <div class="w-4 h-4 rounded-full border" :style="{ borderColor: settings.border, borderWidth: '2px', backgroundColor: 'transparent' }"></div>
                                        </div>
                                    </div>
                                    <div v-if="!project.color_settings" class="col-span-2 text-sm text-gray-500">
                                        Keine benutzerdefinierten Farben gespeichert. Standardwerte aktiv.
                                    </div>
                                </div>
                            </div>

                            <!-- Inline Edit Form -->
                            <form v-if="isEditingProject" @submit.prevent="saveProjectDetails" class="bg-gray-50 p-5 rounded-lg border">
                                <div class="mb-4">
                                    <InputLabel value="Projektname" />
                                    <TextInput v-model="projectForm.name" class="mt-1 block w-full" />
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="col-span-2">
                                        <InputLabel value="Adresse" />
                                        <TextInput v-model="projectForm.address" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel value="PLZ" />
                                        <TextInput v-model="projectForm.zip" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel value="Stadt" />
                                        <TextInput v-model="projectForm.city" class="mt-1 block w-full" />
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <InputLabel value="Beschreibung" />
                                    <textarea v-model="projectForm.description" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full h-24"></textarea>
                                </div>
                                
                                <div class="mb-6 p-4 bg-white rounded border">
                                    <h4 class="font-bold mb-3">Globale Farbakzente (Buttons, Icons)</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <InputLabel value="Primärfarbe (Base)" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <input type="color" v-model="projectForm.color_settings.primary.base" class="w-10 h-10 p-0 border-0 rounded cursor-pointer shrink-0" />
                                                <TextInput v-model="projectForm.color_settings.primary.base" class="flex-1" />
                                            </div>
                                        </div>
                                        <div>
                                            <InputLabel value="Hover-Farbe" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <input type="color" v-model="projectForm.color_settings.primary.hover" class="w-10 h-10 p-0 border-0 rounded cursor-pointer shrink-0" />
                                                <TextInput v-model="projectForm.color_settings.primary.hover" class="flex-1" />
                                            </div>
                                        </div>
                                        <div>
                                            <InputLabel value="Textfarbe auf Primärfarbe" />
                                            <div class="flex items-center gap-2 mt-1">
                                                <input type="color" v-model="projectForm.color_settings.primary.text" class="w-10 h-10 p-0 border-0 rounded cursor-pointer shrink-0" />
                                                <TextInput v-model="projectForm.color_settings.primary.text" class="flex-1" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6 p-3 bg-white rounded border">
                                    <label class="flex items-center font-bold">
                                        <input type="checkbox" v-model="projectForm.has_google_map" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 w-5 h-5 mr-3">
                                        Google Map aktivieren
                                    </label>
                                </div>

                                <div class="mb-6 p-3 bg-white rounded border">
                                    <label class="block font-bold mb-2">Start-Slider bei Seitenaufruf</label>
                                    <select v-model="projectForm.initial_slider_id" class="w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                        <option value="">-- Keiner --</option>
                                        <option v-for="slider in project.sliders" :key="slider.id" :value="slider.id">{{ slider.name }}</option>
                                    </select>
                                </div>

                                <div class="mb-6 p-4 bg-white rounded border border-gray-200">
                                    <h4 class="font-bold text-lg mb-4 text-[#ab715c] flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                        Floating Button Bar (Sidebar Widget)
                                    </h4>
                                    
                                    <label class="flex items-center font-bold mb-4 bg-gray-50 p-2 rounded border cursor-pointer hover:bg-gray-100">
                                        <input type="checkbox" v-model="projectForm.floating_bar.active" class="rounded border-gray-300 text-[#ab715c] shadow-sm focus:ring-[#ab715c] w-5 h-5 mr-3">
                                        Floating Bar aktivieren
                                    </label>
                                    
                                    <div v-if="projectForm.floating_bar.active" class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel value="Position" />
                                                <select v-model="projectForm.floating_bar.position" class="w-full border-gray-300 focus:border-brand-500 rounded-md text-sm">
                                                    <option value="right">Rechts (mittig)</option>
                                                    <option value="right_bottom">Rechts (unten)</option>
                                                    <option value="left">Links (mittig)</option>
                                                    <option value="left_bottom">Links (unten)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <InputLabel value="Hintergrundfarbe (Pill)" />
                                                <input type="color" v-model="projectForm.floating_bar.bg_color" class="w-full h-9 p-0 border-0 rounded cursor-pointer" />
                                            </div>
                                            <div>
                                                <InputLabel value="Aktiv- / Hover-Farbe (Hintergrund)" />
                                                <input type="color" v-model="projectForm.floating_bar.active_color" class="w-full h-9 p-0 border-0 rounded cursor-pointer" />
                                            </div>
                                            <div>
                                                <InputLabel value="Icon- & Textfarbe" />
                                                <input type="color" v-model="projectForm.floating_bar.text_color" class="w-full h-9 p-0 border-0 rounded cursor-pointer" />
                                            </div>
                                            <div class="col-span-1 border-t pt-2 mt-2 md:col-span-2 lg:col-span-4">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <input type="checkbox" v-model="projectForm.floating_bar.show_logo" id="fbLogo" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                    <InputLabel for="fbLogo" value="Logo über Floating Bar ausspielen (falls vorhanden)" class="cursor-pointer" />
                                                </div>
                                                <div v-if="projectForm.floating_bar.show_logo" class="max-w-[200px]">
                                                    <InputLabel value="Maximale Logo-Breite (px)" />
                                                    <input type="number" v-model="projectForm.floating_bar.logo_width" class="w-full h-9 border border-gray-300 rounded text-sm px-2" min="30" max="300" />
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 border-t pt-4">
                                            <InputLabel value="Buttons in der Floating Bar" class="mb-2" />
                                            <draggable v-if="projectForm.floating_bar.buttons?.length" v-model="projectForm.floating_bar.buttons" item-key="id" handle=".cursor-move" class="space-y-2 mb-2">
                                                <template #item="{ element, index }">
                                                    <div class="flex flex-col gap-2 bg-gray-50 p-3 border border-gray-200 rounded relative">
                                                        <button @click.prevent="projectForm.floating_bar.buttons.splice(index, 1)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 p-1">
                                                            <XMarkIcon class="w-5 h-5" />
                                                        </button>
                                                        
                                                        <div class="flex gap-2 items-center pr-8 text-sm">
                                                            <div class="flex flex-col cursor-move text-gray-400 select-none px-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                                            </div>

                                                            <span class="w-16 font-bold uppercase text-[10px] text-gray-500">Label</span>
                                                            <TextInput v-model="element.label" placeholder="Hover Popup Text" class="flex-1 text-sm py-1.5" />
                                                            
                                                            <span class="w-10 font-bold uppercase text-[10px] text-gray-500 ml-2">Aktion</span>
                                                            <select v-model="element.action_type" class="border-gray-300 focus:border-brand-500 rounded text-sm w-36 py-1.5">
                                                                <option value="slider">Slider / Bilder</option>
                                                                <option value="tour_point">Virtual Tour</option>
                                                                <option value="url">Link (URL)</option>
                                                                <option value="video">Video (URL)</option>
                                                                <option value="iframe">Iframe (Popup)</option>
                                                                <option value="tooltip">Info Tooltip</option>
                                                                <option value="apartment">Wohnung öffnen</option>
                                                                <option value="ansicht">Ansicht (View)</option>
                                                                <option value="etage">Etage</option>
                                                                <option value="house">Haus öffnen</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="flex gap-2 items-center pl-8 text-sm">
                                                            <span class="w-16 text-gray-500 font-bold uppercase text-[10px]">Icon</span>
                                                            <select v-model="element.icon_type" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 py-1.5">
                                                                <option value="icon_info">Info (i)</option>
                                                                <option value="icon_mail">E-Mail (Brief)</option>
                                                                <option value="icon_map">Karte (Faltplan)</option>
                                                                <option value="icon_compass">Kompass</option>
                                                                <option value="icon_project">Projekt/Sitemap</option>
                                                                <option value="custom_svg">Eigenes SVG...</option>
                                                            </select>
                                                            
                                                            <span class="w-10 text-gray-500 font-bold uppercase text-[10px] text-right pr-2">Ziel</span>
                                                            <TextInput v-if="element.action_type === 'url' || element.action_type === 'iframe'" v-model="element.action_target" placeholder="https://" class="flex-1 text-sm py-1.5" />
                                                            <select v-else-if="element.action_type === 'slider'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <option v-for="s in project.sliders" :key="s.id" :value="s.id">{{ s.name }}</option>
                                                            </select>
                                                            <select v-else-if="element.action_type === 'tour_point'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <template v-for="t in project.virtual_tours" :key="t.id">
                                                                    <optgroup :label="t.name">
                                                                        <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                                                    </optgroup>
                                                                </template>
                                                            </select>
                                                            <select v-else-if="element.action_type === 'apartment'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <option v-for="a in project.apartments" :key="a.id" :value="a.id">{{ a.name }}</option>
                                                            </select>
                                                            <select v-else-if="element.action_type === 'house'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                                                            </select>
                                                            <select v-else-if="element.action_type === 'ansicht'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <option v-for="v in project.views" :key="v.id" :value="v.id">{{ v.name }}</option>
                                                            </select>
                                                            <select v-else-if="element.action_type === 'etage'" v-model="element.action_target" class="flex-1 border-gray-300 focus:border-brand-500 rounded text-sm py-1.5">
                                                                <option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option>
                                                            </select>
                                                            <TextInput v-else-if="element.action_type === 'tooltip'" v-model="element.action_target" placeholder="Tooltip Nachricht..." class="flex-1 text-sm py-1.5" />
                                                        </div>
                                                        
                                                        <div v-if="element.icon_type === 'custom_svg'" class="pl-8 pt-1">
                                                            <textarea v-model="element.custom_svg" rows="2" placeholder="<svg viewBox='...'>...</svg>" class="w-full text-xs font-mono border-gray-300 rounded"></textarea>
                                                        </div>
                                                    </div>
                                                </template>
                                            </draggable>
                                            
                                            <SecondaryButton @click="addFloatingBarButton" type="button" class="text-xs py-1.5 mt-2">Neu: Button / Icon in der Leiste</SecondaryButton>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 mb-6 p-4 bg-white rounded border border-gray-200">
                                    <div class="flex items-center gap-3 mb-4">
                                        <input type="checkbox" v-model="projectForm.comparison_settings.active" id="cmpActive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 w-5 h-5" />
                                        <label for="cmpActive" class="font-bold text-lg text-[#ab715c] cursor-pointer">
                                            Wohnungs-Vergleichs-Tool aktivieren (Split Screen)
                                        </label>
                                    </div>
                                    <div v-if="projectForm.comparison_settings.active" class="pl-8">
                                        <InputLabel value="Zum Vergleich freigegebene Felder (Mehrfachauswahl)" class="mb-2" />
                                        <div class="flex flex-wrap gap-4">
                                            <label class="flex items-center gap-1.5 text-sm cursor-pointer"><input type="checkbox" v-model="projectForm.comparison_settings.fields" value="name" class="rounded border-gray-300" /> Bezeichnung</label>
                                            <label class="flex items-center gap-1.5 text-sm cursor-pointer"><input type="checkbox" v-model="projectForm.comparison_settings.fields" value="price" class="rounded border-gray-300" /> Preis</label>
                                            <label class="flex items-center gap-1.5 text-sm cursor-pointer"><input type="checkbox" v-model="projectForm.comparison_settings.fields" value="size" class="rounded border-gray-300" /> Fläche (m²)</label>
                                            <label class="flex items-center gap-1.5 text-sm cursor-pointer"><input type="checkbox" v-model="projectForm.comparison_settings.fields" value="rooms" class="rounded border-gray-300" /> Zimmer</label>
                                            <label class="flex items-center gap-1.5 text-sm cursor-pointer"><input type="checkbox" v-model="projectForm.comparison_settings.fields" value="status" class="rounded border-gray-300" /> Status</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6 p-4 bg-white rounded border border-gray-200">
                                    <div class="flex items-center gap-3 mb-4">
                                        <input type="checkbox" v-model="projectForm.poi_settings.active" id="poiActive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 w-5 h-5" />
                                        <label for="poiActive" class="font-bold text-lg text-[#ab715c] cursor-pointer">
                                            Interaktive Umgebungs-Map (Points of Interest)
                                        </label>
                                    </div>
                                    <div v-if="projectForm.poi_settings.active" class="pl-8 space-y-4">
                                        <div>
                                            <InputLabel value="Such-Radius (in Metern) ausgehend vom Projekt-Standort" class="mb-1" />
                                            <input type="number" v-model="projectForm.poi_settings.radius" class="border border-gray-300 rounded px-3 py-1.5 text-sm w-32 focus:ring-brand-500" min="100" max="10000" step="100" />
                                        </div>
                                        <div>
                                            <InputLabel value="Welche Orte sollen gefunden / auf der Karte gezeigt werden?" class="mb-2" />
                                            <div class="flex flex-wrap gap-4">
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="supermarket" class="rounded border-gray-300 text-green-600" /> Supermarkt / Einkauf</label>
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="school" class="rounded border-gray-300 text-blue-600" /> Schule / Kita</label>
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="transit" class="rounded border-gray-300 text-yellow-600" /> ÖPNV / Haltestelle</label>
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="restaurant" class="rounded border-gray-300 text-red-600" /> Restaurant / Gastro</label>
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="park" class="rounded border-gray-300 text-emerald-600" /> Park / Natur</label>
                                                <label class="flex items-center gap-1.5 text-sm cursor-pointer bg-gray-50 px-3 py-1.5 rounded border border-gray-200"><input type="checkbox" v-model="projectForm.poi_settings.categories" value="pharmacy" class="rounded border-gray-300 text-purple-600" /> Arzt / Apotheke</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6 p-4 bg-white rounded border border-gray-200">
                                    <h4 class="font-bold text-lg mb-4 text-[#ab715c]">
                                        Kontaktformular Einstellungen (Jetzt bewerben)
                                    </h4>
                                    
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel value="Titel im Popup" />
                                                <TextInput v-model="projectForm.contact_form_config.title" class="w-full mt-1" />
                                            </div>
                                            <div>
                                                <InputLabel value="E-Mail Empfänger (optional, überschreibt Backend-Kontakte)" />
                                                <TextInput v-model="projectForm.contact_form_config.email_recipients" class="w-full mt-1" placeholder="kontakt@beispiel.de" />
                                            </div>
                                            <div class="col-span-2">
                                                <InputLabel value="Untertitel / Beschreibung" />
                                                <TextInput v-model="projectForm.contact_form_config.subtitle" class="w-full mt-1" />
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t pt-4">
                                            <InputLabel value="Kontaktfelder definieren" class="mb-2" />
                                            <draggable v-if="projectForm.contact_form_config.fields?.length" v-model="projectForm.contact_form_config.fields" item-key="id" handle=".cursor-move" class="space-y-2 mb-2">
                                                <template #item="{ element, index }">
                                                    <div class="flex gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded">
                                                        <div class="cursor-move text-gray-400 select-none px-1">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                                        </div>
                                                        <TextInput v-model="element.label" placeholder="Feld Label" class="w-1/3 text-sm py-1.5" />
                                                        <select v-model="element.type" class="border-gray-300 focus:border-brand-500 rounded text-sm w-32 py-1.5">
                                                            <option value="text">Text (Einzeilig)</option>
                                                            <option value="email">E-Mail</option>
                                                            <option value="tel">Telefon</option>
                                                            <option value="textarea">Textbereich</option>
                                                        </select>
                                                        <select v-model="element.width" class="border-gray-300 focus:border-brand-500 rounded text-sm w-32 py-1.5">
                                                            <option value="full">Volle Breite</option>
                                                            <option value="half">Halbe Breite</option>
                                                        </select>
                                                        <label class="flex items-center gap-1 text-sm text-gray-600">
                                                            <input type="checkbox" v-model="element.required" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                            Pflichtfeld
                                                        </label>
                                                        <button @click.prevent="projectForm.contact_form_config.fields.splice(index, 1)" class="ml-auto text-red-500 hover:text-red-700 p-1">
                                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </draggable>
                                            <SecondaryButton @click="addContactField" type="button" class="text-xs py-1.5">Neues Feld hinzufügen</SecondaryButton>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <h4 class="font-bold mb-3">Farbeinstellungen anpassen</h4>
                                    <div class="space-y-4">
                                        <div v-for="(settings, stateKey) in projectForm.color_settings" :key="'edit-'+stateKey" class="border rounded-md p-3 bg-white">
                                            <p class="font-bold capitalize mb-2 text-sm">{{ stateKey }}</p>
                                            <div class="grid grid-cols-5 gap-2 text-xs">
                                                <div>
                                                    <label class="block text-gray-500 mb-1">Grund</label>
                                                    <input type="color" v-model="settings.base" class="w-full h-8 cursor-pointer rounded">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500 mb-1">Hover</label>
                                                    <input type="color" v-model="settings.hover" class="w-full h-8 cursor-pointer rounded">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500 mb-1">Aktiv</label>
                                                    <input type="color" v-model="settings.active" class="w-full h-8 cursor-pointer rounded">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500 mb-1">Rahmen</label>
                                                    <input type="color" v-model="settings.border" class="w-full h-8 cursor-pointer rounded">
                                                </div>
                                                <div>
                                                    <label class="block text-gray-500 mb-1">Dicke</label>
                                                    <input type="number" v-model="settings.border_width" class="w-full h-8 border-gray-300 rounded shadow-sm text-center">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <SecondaryButton @click="isEditingProject = false">Abbrechen</SecondaryButton>
                                    <PrimaryButton type="submit" :class="{ 'opacity-50': projectForm.processing }" :disabled="projectForm.processing">Änderungen speichern</PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <!-- Media section -->
                        <div>
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2 border-b pb-2"><PhotoIcon class="w-6 h-6 text-brand-500"/> Projekt Medien</h3>
                            
                            <!-- Display existing Media -->
                            <div class="mb-6 grid grid-cols-2 gap-4">
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 md:col-span-1 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Aktuelles Projektbild</p>
                                    <div class="w-full aspect-video bg-gray-200 rounded relative overflow-hidden flex items-center justify-center">
                                        <img v-if="project.media && project.media.find(m => m.collection_name === 'project_image')" 
                                             :src="project.media.find(m => m.collection_name === 'project_image').original_url" 
                                             class="object-cover w-full h-full" />
                                        <span v-else class="text-gray-400 text-sm">Kein Bild</span>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 md:col-span-1 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Aktuelles Preview</p>
                                    <div class="w-full aspect-video bg-gray-200 rounded relative overflow-hidden flex items-center justify-center">
                                        <img v-if="project.media && project.media.find(m => m.collection_name === 'preview_image')" 
                                             :src="project.media.find(m => m.collection_name === 'preview_image').original_url" 
                                             class="object-cover w-full h-full" />
                                        <span v-else class="text-gray-400 text-sm">Kein Bild</span>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Aktuelles Projekt PDF</p>
                                    <div v-if="project.media && project.media.find(m => m.collection_name === 'project_pdf')" class="w-full py-2 bg-white border text-center rounded">
                                        <a target="_blank" :href="project.media.find(m => m.collection_name === 'project_pdf').original_url" class="text-brand-600 hover:underline font-bold">PDF ansehen / herunterladen</a>
                                    </div>
                                    <div v-else class="w-full py-2 bg-gray-200 text-center rounded text-gray-400 text-sm">Kein PDF hochgeladen</div>
                                </div>
                                <!-- Logo -->
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 md:col-span-1 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Logo (für PDF & Sidebar)</p>
                                    <div class="w-full h-24 bg-gray-200 rounded relative overflow-hidden flex items-center justify-center">
                                        <img v-if="project.media && project.media.find(m => m.collection_name === 'logo')"
                                             :src="project.media.find(m => m.collection_name === 'logo').original_url"
                                             class="max-h-full max-w-full object-contain p-2" />
                                        <span v-else class="text-gray-400 text-sm">Kein Logo</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-brand-50/50 p-5 rounded-lg border border-brand-100">
                                <h4 class="font-bold mb-4 text-gray-800">Dateien aktualisieren</h4>
                                <form @submit.prevent="handleImageUpload">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Projektbild (Main)</label>
                                        <input type="file" @change="e => imageForm.project_image = e.target.files[0]" class="w-full border p-2 rounded bg-white text-sm" accept="image/*">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Preview Image</label>
                                        <input type="file" @change="e => imageForm.preview_image = e.target.files[0]" class="w-full border p-2 rounded bg-white text-sm" accept="image/*">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Projekt PDF</label>
                                        <input type="file" @change="e => imageForm.project_pdf = e.target.files[0]" class="w-full border p-2 rounded bg-white text-sm" accept=".pdf">
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Logo (PNG/SVG empfohlen, transparenter Hintergrund)</label>
                                        <input type="file" @change="e => imageForm.logo = e.target.files[0]" class="w-full border p-2 rounded bg-white text-sm" accept="image/*">
                                    </div>
                                    <PrimaryButton type="submit" :class="{ 'opacity-50': imageForm.processing }" :disabled="imageForm.processing">
                                        Neu hochladen
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Views (Ansichten) -->
                <div v-if="activeTab === 'views'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><ArrowUpOnSquareStackIcon class="w-6 h-6 text-brand-500"/> Ansichten</h3>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                <input v-model="searchQueries.views" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'view'" @click="editInline('view')">Neue Ansicht</PrimaryButton>
                    </div>

                    <!-- Inline Form for Views -->
                    <div v-if="editingEntity.type === 'view'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Ansicht bearbeiten' : 'Neue Ansicht' }}</h4>
                        <div class="space-y-4">
                            <div>
                                <InputLabel value="Name / Bezeichnung" />
                                <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="modalData.is_start" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500">
                                    <span class="ml-2 text-sm text-gray-600">Startansicht</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('View')">Speichern</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'view'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="view in filteredViews" :key="view.id" class="border rounded-xl shadow-sm bg-white overflow-hidden transition hover:shadow-md relative">
                            <div v-if="view.is_start" class="absolute top-2 right-2 text-yellow-500" title="Startansicht">
                                <StarIcon class="w-6 h-6" />
                            </div>
                            <div class="p-5">
                                <h4 class="font-bold text-xl mb-1">{{ view.name }}</h4>
                                <p class="text-sm text-gray-500 mb-4">{{ view.frames ? view.frames.length : 0 }} Frames</p>
                                
                                <!-- Layers list briefly -->
                                <div class="mb-4">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Aktive Layer</p>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="l in view.layers" :key="l.id" class="px-2 py-1 bg-brand-50 text-brand-700 text-xs rounded-full border border-brand-100">{{ l.name }}</span>
                                        <span v-if="!view.layers || view.layers.length === 0" class="text-xs text-gray-400">Keine Layer aktiv</span>
                                    </div>
                                </div>

                                <div class="flex gap-2 mt-4 pt-4 border-t">
                                    <SecondaryButton @click="editInline('view', view)" class="text-xs py-1">Bearbeiten</SecondaryButton>
                                    <button @click="openFrames(view)" class="inline-flex items-center px-3 py-1 bg-gray-800 border-transparent rounded-md font-semibold text-xs text-white hover:bg-gray-700 transition">Frames verwalten</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredViews.length === 0" class="py-12 text-center text-gray-500">
                        Keine Ansichten gefunden.
                    </div>
                </div>

                <!-- Tab: Houses (Häuser) -->
                <div v-if="activeTab === 'houses'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><HomeIcon class="w-6 h-6 text-brand-500"/> Häuser</h3>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                <input v-model="searchQueries.houses" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'house'" @click="editInline('house')">Neues Haus</PrimaryButton>
                    </div>

                    <!-- Inline Form for House -->
                    <div v-if="editingEntity.type === 'house'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Haus bearbeiten' : 'Neues Haus' }}</h4>
                        <div class="space-y-4">
                            <div>
                                <InputLabel value="Name / Bezeichnung" />
                                <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('House')">Speichern</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'house'" class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / Bezeichnung</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="house in filteredHouses" :key="house.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">{{ house.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button @click="editInline('house', house)" class="text-brand-600 hover:text-brand-900 font-medium">Bearbeiten</button>
                                        <button @click="deleteEntity('House', house.id)" class="ml-4 text-red-600 hover:text-red-900">Löschen</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredHouses.length === 0">
                                    <td colspan="2" class="px-6 py-8 text-center text-gray-500">Keine Häuser gefunden.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Floors (Etagen) -->
                <div v-if="activeTab === 'floors'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><Bars3CenterLeftIcon class="w-6 h-6 text-brand-500"/> Etagen</h3>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                <input v-model="searchQueries.floors" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'floor'" @click="editInline('floor')">Neue Etage</PrimaryButton>
                    </div>

                    <!-- Inline Form for Floor -->
                    <div v-if="editingEntity.type === 'floor'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Etage bearbeiten' : 'Neue Etage' }}</h4>
                        <div class="space-y-4">
                            <div>
                                <InputLabel value="Name / Bezeichnung" />
                                <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Sortier-Index" />
                                <TextInput v-model="modalData.index" type="number" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Zugeordnetes Haus" />
                                <select v-model="modalData.house_id" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full">
                                    <option :value="null">Kein Haus</option>
                                    <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('Floor')">Speichern</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'floor'" class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sortierung</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Haus</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grundriss (2D)</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="floor in filteredFloors.sort((a,b) => a.index - b.index)" :key="floor.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span class="bg-gray-100 rounded px-2 py-1 font-mono text-xs">{{ floor.index || 0 }}</span></td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">{{ floor.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ project.houses.find(h => h.id === floor.house_id)?.name || 'Kein Haus zugewiesen' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2">
                                            <div v-if="floor.media?.find(m => m.collection_name === 'default')" class="relative w-12 h-12 bg-gray-100 border rounded">
                                                <img :src="floor.media.find(m => m.collection_name === 'default').original_url" class="object-cover w-full h-full rounded" />
                                                <button @click.prevent="deleteMedia(floor.media.find(m => m.collection_name === 'default').id)" class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-0.5 shadow hover:scale-110 z-10"><XMarkIcon class="w-3 h-3" /></button>
                                            </div>
                                            <div v-else>
                                                <input type="file" @change="e => uploadMedia(e, 'Floor', floor.id, 'default')" accept="image/*" class="text-[10px] w-48 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <button @click="editInline('floor', floor)" class="text-brand-600 hover:text-brand-900 font-medium">Bearbeiten</button>
                                        <button @click="deleteEntity('Floor', floor.id)" class="ml-4 text-red-600 hover:text-red-900">Löschen</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredFloors.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Keine Etagen gefunden.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Apartments (Wohnungen) -->
                <div v-if="activeTab === 'apartments'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><Squares2X2Icon class="w-6 h-6 text-brand-500"/> Wohnungen</h3>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                <input v-model="searchQueries.apartments" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'apartment'" @click="editInline('apartment')">Neue Wohnung</PrimaryButton>
                    </div>
                    
                    <!-- Inline Form for Apartment -->
                    <div v-if="editingEntity.type === 'apartment'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Wohnung bearbeiten' : 'Neue Wohnung' }}</h4>
                        <div class="space-y-4">
                            <div>
                                <InputLabel value="Name / Bezeichnung" />
                                <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Haus" />
                                    <select v-model="modalData.house_id" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full">
                                        <option :value="null">-</option>
                                        <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Etage" />
                                    <select v-model="modalData.floor_id" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full">
                                        <option :value="null">-</option>
                                        <option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Zimmer" />
                                    <TextInput v-model="modalData.rooms" type="number" step="0.5" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Bäder" />
                                    <TextInput v-model="modalData.bathrooms" type="number" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Quadratmeter (qm)" />
                                    <TextInput v-model="modalData.sqm" type="number" step="0.1" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Vermarktung" />
                                    <select v-model="modalData.marketing_type" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full">
                                        <option>Verkauf</option>
                                        <option>Miete</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Status" />
                                    <select v-model="modalData.status" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full">
                                        <option>Frei</option>
                                        <option>Reserviert</option>
                                        <option>Vermietet</option>
                                        <option>Verkauft</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Kaufpreis / Kaltmiete (€)" />
                                    <TextInput v-model="modalData.price" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Nebenkosten (€)" />
                                    <TextInput v-model="modalData.additional_costs" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Warmmiete (€)" />
                                    <TextInput v-model="modalData.warm_rent" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Bezug ab" />
                                    <TextInput v-model="modalData.available_from" class="mt-1 block w-full" placeholder="z.B. sofort / 01.12.2026" />
                                </div>
                                <div>
                                    <InputLabel value="Außenfläche (Balkon/Terrasse) qm" />
                                    <TextInput v-model="modalData.outdoor_area" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="3D-Rundgang (URL)" />
                                    <TextInput v-model="modalData.virtual_tour_url" class="mt-1 block w-full" placeholder="https://..." />
                                </div>
                                <div>
                                    <InputLabel value="Oft. Kontakt / Lead URL" />
                                    <TextInput v-model="modalData.external_contact_url" class="mt-1 block w-full" placeholder="https://..." />
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <InputLabel value="Kurzbeschreibung (Exposé-Text)" />
                                <textarea v-model="modalData.description" rows="3" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"></textarea>
                            </div>
                            
                            <!-- Features -->
                            <div class="mt-4 border-t pt-4">
                                <InputLabel value="Ausstattungsmerkmale (Features)" class="mb-2" />
                                <div class="flex flex-wrap gap-4">
                                    <label v-for="feature in project.features" :key="feature.id" class="flex items-center gap-2 text-sm bg-white border px-3 py-1 rounded shadow-sm cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" :value="feature.id" v-model="modalData.features" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4">
                                        {{ feature.name }}
                                    </label>
                                    <span v-if="!project.features?.length" class="text-xs text-gray-500">Keine Ausstattung im Projekt angelegt.</span>
                                </div>
                            </div>
                            
                            <!-- Eigene Buttons -->
                            <div class="mt-4 border-t pt-4">
                                <InputLabel value="Eigene Buttons (Aktions-Buttons in der Sidebar)" class="mb-2" />
                                <draggable v-if="modalData.custom_buttons?.length" v-model="modalData.custom_buttons" item-key="id" handle=".cursor-move" class="space-y-2 mb-2">
                                    <template #item="{ element, index }">
                                        <div class="flex gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded">
                                            <div class="flex flex-col cursor-move text-gray-400 select-none px-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                            </div>
                                            <TextInput v-model="element.title" placeholder="Titel (z.B. Zum virtuellen Rundgang)" class="flex-1 text-sm py-1.5" />
                                            <select v-model="element.action_type" class="border-gray-300 focus:border-brand-500 rounded text-sm w-36 py-1.5">
                                                <option value="slider">Slider / Bilder</option>
                                                <option value="tour_point">Virtual Tour</option>
                                                <option value="url">Link (URL)</option>
                                                <option value="video">Video (URL)</option>
                                                <option value="iframe">Iframe (Popup)</option>
                                                <option value="tooltip">Info Tooltip</option>
                                                <option value="apartment">Wohnung öffnen</option>
                                                <option value="ansicht">Ansicht (View)</option>
                                                <option value="etage">Etage</option>
                                                <option value="house">Haus öffnen</option>
                                            </select>
                                            <TextInput v-if="element.action_type === 'url' || element.action_type === 'iframe'" v-model="element.action_target" placeholder="https://" class="w-48 text-sm py-1.5" />
                                            <select v-else-if="element.action_type === 'slider'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <option v-for="s in project.sliders" :key="s.id" :value="s.id">{{ s.name }}</option>
                                            </select>
                                            <select v-else-if="element.action_type === 'tour_point'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <template v-for="t in project.virtual_tours" :key="t.id">
                                                    <optgroup :label="t.name">
                                                        <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                                    </optgroup>
                                                </template>
                                            </select>
                                            <select v-else-if="element.action_type === 'apartment'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <option v-for="a in project.apartments" :key="a.id" :value="a.id">{{ a.name }}</option>
                                            </select>
                                            <select v-else-if="element.action_type === 'house'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                                            </select>
                                            <select v-else-if="element.action_type === 'ansicht'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <option v-for="v in project.views" :key="v.id" :value="v.id">{{ v.name }}</option>
                                            </select>
                                            <select v-else-if="element.action_type === 'etage'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm w-48 py-1.5">
                                                <option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option>
                                            </select>
                                            <TextInput v-else-if="element.action_type === 'tooltip'" v-model="element.action_target" placeholder="Text..." class="flex-1 text-sm py-1.5" />
                                            <button @click.prevent="modalData.custom_buttons.splice(index, 1)" class="text-red-500 hover:text-red-700 ml-1 p-1">
                                                <XMarkIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </template>
                                </draggable>
                                <SecondaryButton @click="addCustomButton" class="text-xs py-1.5">Button hinzufügen</SecondaryButton>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('Apartment')">Speichern</PrimaryButton>
                        </div>
                        
                        <!-- Apartment Media Section (Only for existing Apartments) -->
                        <div v-if="currentApartment && currentApartment.id" class="mt-8 pt-8 border-t border-gray-200">
                            <h5 class="text-lg font-bold mb-4 flex items-center gap-2"><PhotoIcon class="w-5 h-5 text-brand-500" /> Wohnungs-Bilder & Gruppen</h5>
                            
                            <!-- Main Apartment Image -->
                            <div class="mb-8 bg-white p-4 rounded border">
                                <h6 class="font-bold mb-4 flex items-center justify-between">Raumliste (Übersicht)</h6>
                                <div class="flex gap-2 mb-4">
                                    <TextInput v-model="newRoom.name" placeholder="Raum (z.B. Wohnen/Essen)" class="flex-1" />
                                    <TextInput v-model="newRoom.sqm" placeholder="qm" type="number" step="0.01" class="w-24" />
                                    <SecondaryButton @click="saveNewRoom" :disabled="!newRoom.name.trim()">Hinzufügen</SecondaryButton>
                                </div>
                                <table v-if="currentApartment.rooms_list?.length" class="w-full text-sm text-left">
                                    <tr class="border-b bg-gray-50"><th class="px-2 py-1">Raum</th><th class="px-2 py-1 w-24">qm</th><th class="px-2 py-1 w-16"></th></tr>
                                    <tr v-for="room in currentApartment.rooms_list" :key="room.id" class="border-b">
                                        <td class="px-2 py-1">{{ room.name }}</td>
                                        <td class="px-2 py-1">{{ room.sqm || '-' }}</td>
                                        <td class="px-2 py-1"><button @click="deleteEntity('Room', room.id)" class="text-red-500 hover:text-red-700"><XMarkIcon class="w-4 h-4" /></button></td>
                                    </tr>
                                    <tr class="font-bold bg-gray-50">
                                        <td class="px-2 py-1 text-right">Gesamt (Räume):</td>
                                        <td class="px-2 py-1">{{ roomTotalSqm }} qm</td>
                                        <td class="px-2 py-1"></td>
                                    </tr>
                                </table>
                                <p v-else class="text-xs text-gray-500 italic">Noch keine Räume angelegt.</p>
                            </div>

                            <div class="mb-8 bg-white p-4 rounded border">
                                <h6 class="font-bold mb-2">Hauptbild der Wohnung</h6>
                                <div class="flex items-start gap-4">
                                    <div v-if="currentApartment.media?.length" class="w-32 h-32 rounded bg-gray-100 flex-shrink-0 relative overflow-hidden">
                                        <img :src="currentApartment.media[0].original_url" class="object-cover w-full h-full">
                                        <button @click="deleteMedia(currentApartment.media[0].id)" class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 opacity-75 hover:opacity-100"><XMarkIcon class="w-3 h-3" /></button>
                                    </div>
                                    <div v-else class="w-32 h-32 rounded bg-gray-100 flex items-center justify-center text-xs text-center text-gray-400 border-2 border-dashed flex-shrink-0">
                                        Kein Bild
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2">Wird z.B. in der Wohnungsliste oder als Vorschaubild verwendet.</p>
                                        <input type="file" accept="image/*" @change="e => uploadMedia(e, 'apartment', currentApartment.id, 'default')" class="text-sm block w-full text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Exposé PDF -->
                            <div class="mb-8 bg-white p-4 rounded border">
                                <h6 class="font-bold mb-2">Exposé (PDF)</h6>
                                <div class="flex items-start gap-4">
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2">Hier kannst du ein detailliertes Exposé für diese Wohnung als PDF-Datei hochladen.</p>
                                        <div v-if="currentApartmentExpose" class="flex gap-4 items-center bg-gray-50 p-2 border rounded max-w-lg mb-2">
                                            <a :href="currentApartmentExpose.original_url" target="_blank" class="text-brand-600 font-medium hover:underline text-sm truncate flex-1">
                                                {{ currentApartmentExpose.file_name }}
                                            </a>
                                            <button @click="deleteMedia(currentApartmentExpose.id)" class="text-red-600 text-xs hover:underline flex-shrink-0">Löschen</button>
                                        </div>
                                        <input type="file" accept="application/pdf" @change="e => uploadMedia(e, 'apartment', currentApartment.id, 'expose')" class="text-sm block w-full text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />
                                    </div>
                                </div>
                            </div>

                            <!-- Image Groups -->
                            <div class="bg-white p-4 rounded border">
                                <h6 class="font-bold mb-4">Bildgruppen (z.B. Galerie, Grundrisse, 360-Rundgang)</h6>
                                
                                <div class="flex gap-2 mb-6">
                                    <TextInput v-model="newImageGroupName" placeholder="Neue Gruppe (z.B. Wohnzimmer)" class="flex-1 max-w-sm" />
                                    <SecondaryButton @click="saveNewImageGroup" :disabled="!newImageGroupName.trim()">Hinzufügen</SecondaryButton>
                                </div>

                                <div v-if="currentApartment.image_groups?.length" class="space-y-4">
                                    <div v-for="group in currentApartment.image_groups" :key="group.id" class="border rounded bg-gray-50 p-4">
                                        <div class="flex justify-between items-start mb-4">
                                            <div>
                                                <h6 class="font-bold text-base">{{ group.name }}</h6>
                                                <div class="flex gap-4 mt-2 text-sm text-gray-500 items-center">
                                                    <label class="flex items-center gap-1 cursor-pointer">
                                                        <input type="checkbox" v-model="group.is_active" @change="saveImageGroup(group)" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4">
                                                        Aktiv
                                                    </label>
                                                    <label class="flex items-center gap-2">
                                                        Sortierung
                                                        <input type="number" v-model="group.sort_order" @blur="saveImageGroup(group)" class="w-16 h-7 text-xs border border-gray-300 rounded shadow-sm focus:ring-brand-500 focus:border-brand-500 text-center">
                                                    </label>
                                                </div>
                                            </div>
                                            <button @click="deleteEntity('ApartmentImageGroup', group.id)" class="text-red-500 text-sm hover:underline">Gruppe löschen</button>
                                        </div>
                                        
                                        <!-- Group Media Grid -->
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                                            <div v-for="media in group.media" :key="media.id" class="border rounded bg-white relative overflow-hidden group/media">
                                                <img :src="media.original_url" class="object-cover w-full h-24 bg-gray-100">
                                                <button @click.prevent="deleteMedia(media.id)" class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 opacity-0 group-hover/media:opacity-100 transition"><XMarkIcon class="w-3 h-3" /></button>
                                                <div class="p-2">
                                                    <InputLabel value="Beschriftung" class="text-[10px]" />
                                                    <input type="text" :value="media.custom_properties?.caption || ''" @input="e => { media.custom_properties = media.custom_properties || {}; media.custom_properties.caption = e.target.value; }" @blur="updateMediaCaption(media)" placeholder="..." class="w-full text-xs border-gray-300 rounded shadow-sm py-1 px-2 mt-1 focus:ring-brand-500 focus:border-brand-500">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Upload to Group -->
                                        <div class="flex items-center gap-4 mt-2">
                                            <input type="file" accept="image/*" multiple @change="e => uploadMedia(e, 'ApartmentImageGroup', group.id, 'default')" class="text-xs block text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300" :disabled="isUploadingMediaArray" />
                                            <span v-if="isUploadingMediaArray" class="text-xs font-bold text-brand-600 outline-none animate-pulse">Lade {{ uploadMediaProgress }}%...</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500 italic">
                                    Noch keine Bildgruppen angelegt.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'apartment'" class="overflow-x-auto rounded-lg border">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ort</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daten</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kauf/Miete</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status & Preis</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="apt in filteredApartments" :key="apt.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap font-medium text-brand-700">{{ apt.name }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center gap-1" title="Haus"><HomeIcon class="w-4 h-4"/> {{ project.houses.find(h=>h.id===apt.house_id)?.name || '-' }}</div>
                                        <div class="flex items-center gap-1 mt-1" title="Etage"><Bars3CenterLeftIcon class="w-4 h-4"/> {{ project.floors.find(f=>f.id===apt.floor_id)?.name || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ apt.rooms }} Zi. | {{ apt.bathrooms }} Bad<br>
                                        {{ apt.sqm }} m²
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ apt.marketing_type || '-' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-md" :class="{'bg-green-100 text-green-800': apt.status==='Frei', 'bg-red-100 text-red-800': apt.status==='Verkauft' || apt.status==='Vermietet', 'bg-yellow-100 text-yellow-800': apt.status==='Reserviert'}">
                                            {{ apt.status || 'Unbekannt' }}
                                        </span>
                                        <div class="mt-1 text-xs text-gray-500 font-mono">
                                            Kauf: {{ apt.price ? apt.price + ' €' : '-' }}<br>
                                            Warm: {{ apt.warm_rent ? apt.warm_rent + ' €' : '-' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="editInline('apartment', apt)" class="text-brand-600 hover:text-brand-900 mb-2">Bearbeiten</button><br>
                                        <button @click="deleteEntity('Apartment', apt.id)" class="text-red-600 hover:text-red-900">Löschen</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredApartments.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">Keine Wohnungen gefunden</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Layers -->
                <div v-if="activeTab === 'layers'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><Squares2X2Icon class="w-6 h-6 text-brand-500"/> Projekt Layer</h3>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                <input v-model="searchQueries.layers" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'layer'" @click="editInline('layer')">Neuer Layer</PrimaryButton>
                    </div>

                    <!-- Inline Form for Layer -->
                    <div v-if="editingEntity.type === 'layer'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Layer bearbeiten' : 'Neuer Layer' }}</h4>
                        <div class="space-y-4">
                            <div>
                                <InputLabel value="Name / Bezeichnung" />
                                <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('Layer')">Speichern</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'layer'" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div v-for="layer in filteredLayers" :key="layer.id" class="border rounded-lg p-4 text-center bg-gray-50 relative group">
                            <p class="font-bold text-gray-700 truncate">{{ layer.name }}</p>
                            <div class="mt-3 flex justify-center gap-3 opacity-0 group-hover:opacity-100 transition">
                                <button @click="editInline('layer', layer)" class="text-brand-600 hover:text-brand-900 text-xs font-medium">Edit</button>
                                <button @click="deleteEntity('Layer', layer.id)" class="text-red-600 hover:text-red-900 text-xs font-medium">Del</button>
                            </div>
                        </div>
                        <div v-if="filteredLayers.length === 0" class="col-span-full py-8 text-center text-gray-500">Keine Layer gefunden</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW TAB: Features -->
        <div v-if="activeTab === 'features'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold flex items-center gap-2"><StarIcon class="w-6 h-6 text-brand-500"/> Projekt Ausstattung (Features)</h3>
                <PrimaryButton v-if="editingEntity.type !== 'feature'" @click="editInline('feature')">Neues Feature</PrimaryButton>
            </div>
            <div v-if="editingEntity.type === 'feature'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                <h4 class="font-bold mb-4">{{ modalData.id ? 'Feature bearbeiten' : 'Neues Feature' }}</h4>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Name (z.B. Balkon, Aufzug)" />
                        <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Icon (Klassenname, optional)" />
                        <TextInput v-model="modalData.icon" class="mt-1 block w-full" />
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                    <PrimaryButton @click="saveEntity('Feature')">Speichern</PrimaryButton>
                </div>
            </div>
            <div v-if="editingEntity.type !== 'feature'" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div v-for="feature in project.features" :key="feature.id" class="border rounded-lg p-4 text-center bg-gray-50 relative group">
                    <p class="font-bold text-gray-700 truncate">{{ feature.name }}</p>
                    <div class="mt-3 flex justify-center gap-3 opacity-0 group-hover:opacity-100 transition">
                        <button @click="editInline('feature', feature)" class="text-brand-600 hover:text-brand-900 text-xs font-medium">Edit</button>
                        <button @click="deleteEntity('Feature', feature.id)" class="text-red-600 hover:text-red-900 text-xs font-medium">Del</button>
                    </div>
                </div>
                <div v-if="!project.features?.length" class="col-span-full py-8 text-center text-gray-500">Keine Ausstattung gefunden</div>
            </div>
        </div>

        <!-- NEW TAB: Infoframes -->
        <div v-if="activeTab === 'infoframes'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold flex items-center gap-2"><InformationCircleIcon class="w-6 h-6 text-brand-500"/> Infoframes</h3>
                <PrimaryButton v-if="editingEntity.type !== 'infoframe'" @click="editInline('infoframe')">Neuer Infoframe</PrimaryButton>
            </div>
            <div v-if="editingEntity.type === 'infoframe'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                <h4 class="font-bold mb-4">{{ modalData.id ? 'Infoframe bearbeiten' : 'Neuer Infoframe' }}</h4>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Inhalt (Word-ähnlicher Editor für Text, Tabellen und Bilder)" />
                        <div class="mt-1 bg-white border border-gray-300 rounded-md shadow-sm">
                            <QuillEditor v-model:content="modalData.content" contentType="html" toolbar="full" theme="snow" class="min-h-[300px]" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-6">
                    <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                    <PrimaryButton @click="saveEntity('Infoframe')">Speichern</PrimaryButton>
                </div>
            </div>
            <div v-if="editingEntity.type !== 'infoframe'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="info in project.infoframes" :key="info.id" class="border rounded-lg p-4 bg-gray-50 relative group">
                    <div class="flex justify-between items-center mb-2">
                        <p class="font-bold text-gray-700 truncate">{{ info.name }}</p>
                        <div class="flex items-center gap-3 opacity-0 group-hover:opacity-100 transition">
                            <button @click="editInline('infoframe', info)" class="text-brand-600 hover:text-brand-900 text-xs font-medium">Edit</button>
                            <button @click="deleteEntity('Infoframe', info.id)" class="text-red-600 hover:text-red-900 text-xs font-medium">Del</button>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 bg-white p-2 border rounded max-h-32 overflow-hidden relative">
                        <div v-html="info.content || '<em>Kein Inhalt</em>'"></div>
                    </div>
                </div>
                <div v-if="!project.infoframes?.length" class="col-span-full py-8 text-center text-gray-500">Keine Infoframes gefunden</div>
            </div>
        </div>

        <!-- NEW TAB: Contacts -->
        <div v-if="activeTab === 'contacts'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6 space-y-8">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold flex items-center gap-2"><UserGroupIcon class="w-6 h-6 text-brand-500"/> Ansprechpartner</h3>
                <a :href="route('contacts.index')" class="text-sm font-bold text-brand-600 hover:text-brand-800 transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Kontakte verwalten
                </a>
            </div>

            <!-- Already assigned -->
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Zugeordnet</h4>
                <div v-if="project.contacts?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="c in project.contacts" :key="c.id"
                         class="border border-gray-200 rounded-xl p-4 bg-gray-50 relative group flex gap-3 items-center">
                        <div class="w-12 h-12 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-black text-lg shrink-0">
                            {{ c.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-800 truncate text-sm">{{ c.name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ c.position || '' }}</p>
                            <p class="text-xs text-brand-600 truncate">{{ c.email }}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <!-- Notify toggle -->
                            <button @click="router.post(route('contacts.toggle-notify', { project: project.id, contact: c.id }))"
                                    :title="c.pivot?.notify_on_inquiry ? 'Benachrichtigung aktiv' : 'Keine Benachrichtigung'"
                                    :class="['w-7 h-7 flex items-center justify-center rounded-full transition', c.pivot?.notify_on_inquiry ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-400 hover:bg-amber-50 hover:text-amber-500']">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            </button>
                            <!-- Detach -->
                            <button @click="router.delete(route('contacts.detach', { project: project.id, contact: c.id }))"
                                    class="w-7 h-7 flex items-center justify-center rounded-full bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                                <XMarkIcon class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 py-4 text-center border border-dashed border-gray-200 rounded-xl">
                    Noch kein Ansprechpartner zugeordnet.
                </p>
            </div>

            <!-- Assign from team contacts -->
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Kontakt zuordnen</h4>
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
                    <div class="flex gap-3 items-center flex-wrap">
                        <select v-model="contactAssignId" class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500">
                            <option value="">-- Kontakt auswählen --</option>
                            <option v-for="c in availableContacts" :key="c.id" :value="c.id">{{ c.name }}{{ c.position ? ' – ' + c.position : '' }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 cursor-pointer">
                            <input type="checkbox" v-model="contactAssignNotify" class="rounded border-gray-300 text-brand-500" />
                            Bei Anfragen benachrichtigen
                        </label>
                        <PrimaryButton @click="attachContact" :disabled="!contactAssignId">Zuordnen</PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Sliders -->
        <div v-if="activeTab === 'sliders'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold flex items-center gap-2"><BuildingStorefrontIcon class="w-6 h-6 text-brand-500"/> Slider</h3>
            </div>

            <!-- New Slider Form -->
            <div class="mb-6 flex gap-3 items-end">
                <div class="flex-1">
                    <InputLabel value="Neuer Slider" />
                    <TextInput v-model="newSliderName" class="mt-1 block w-full" placeholder="Name des Sliders" />
                </div>
                <PrimaryButton @click="createSlider" :disabled="!newSliderName.trim()">Slider anlegen</PrimaryButton>
            </div>

            <!-- Slider List -->
            <div class="space-y-6">
                <div v-for="slider in project.sliders" :key="slider.id" class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-gray-800 flex items-center gap-2">
                            <BuildingStorefrontIcon class="w-4 h-4 text-brand-500"/>
                            {{ slider.name }}
                            <span class="text-xs font-normal text-gray-400">(ID: {{ slider.id }})</span>
                        </h4>
                        <button @click="deleteSlider(slider.id)" class="text-red-500 hover:text-red-700 text-xs font-bold">Löschen</button>
                    </div>

                    <!-- Slides -->
                    <div class="space-y-3 mb-4">
                        <div v-for="slide in slider.slides" :key="slide.id" class="bg-gray-50 border rounded p-3 flex items-start gap-4">
                            <!-- Thumbnail -->
                            <div class="w-16 h-16 shrink-0 bg-gray-200 rounded overflow-hidden">
                                <img v-if="slide.media?.[0]" :src="slide.media[0].original_url" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">–</div>
                            </div>
                            <div class="flex-1 text-sm">
                                <div class="font-bold text-gray-700 mb-0.5">{{ slide.title || '(Kein Titel)' }}</div>
                                <div class="text-xs text-gray-500">Typ: <span class="font-semibold">{{ slide.type }}</span></div>
                                <div v-if="slide.type === 'infoframe'" class="text-xs text-gray-500">Infoframe: {{ slide.infoframe?.name || '–' }}</div>
                                <div v-if="slide.type === 'iframe'" class="text-xs text-gray-500 truncate max-w-[200px]">URL: {{ slide.iframe_url }}</div>
                                <div v-if="slide.type === 'pdf'" class="text-xs text-gray-500">PDF: {{ slide.media?.find(m => m.collection_name === 'slide_pdf')?.file_name || '–' }}</div>
                            </div>
                            <button @click="deleteSlide(slide.id)" class="text-red-400 hover:text-red-600 ml-auto shrink-0"><XMarkIcon class="w-4 h-4"/></button>
                        </div>
                        <p v-if="!slider.slides?.length" class="text-xs text-gray-500 italic">Noch keine Slides.</p>
                    </div>

                    <!-- Add Slide Form -->
                    <div class="border-t pt-4">
                        <h5 class="text-sm font-bold text-gray-700 mb-3">Neue Slide hinzufügen</h5>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Typ" />
                                <select v-model="newSlide[slider.id].type" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full text-sm">
                                    <option value="image">Bild</option>
                                    <option value="infoframe">Infoframe</option>
                                    <option value="iframe">Iframe / URL</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Titel (optional)" />
                                <TextInput v-model="newSlide[slider.id].title" class="mt-1 block w-full text-sm" />
                            </div>
                            <div v-if="newSlide[slider.id].type === 'image'" class="col-span-2">
                                <InputLabel value="Bild" />
                                <input type="file" @change="e => newSlide[slider.id].imageFile = e.target.files[0]" accept="image/*" class="mt-1 text-sm block w-full" />
                            </div>
                            <div v-if="newSlide[slider.id].type === 'infoframe'" class="col-span-2">
                                <InputLabel value="Infoframe" />
                                <select v-model="newSlide[slider.id].infoframe_id" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm mt-1 block w-full text-sm">
                                    <option :value="null">– Auswählen –</option>
                                    <option v-for="inf in project.infoframes" :key="inf.id" :value="inf.id">{{ inf.name }}</option>
                                </select>
                            </div>
                            <div v-if="newSlide[slider.id].type === 'iframe'" class="col-span-2">
                                <InputLabel value="URL" />
                                <TextInput v-model="newSlide[slider.id].iframe_url" class="mt-1 block w-full text-sm" placeholder="https://..." />
                            </div>
                            <div v-if="newSlide[slider.id].type === 'pdf'" class="col-span-2">
                                <InputLabel value="PDF-Datei" />
                                <input type="file" @change="e => newSlide[slider.id].pdfFile = e.target.files[0]" accept=".pdf,application/pdf" class="mt-1 text-sm block w-full" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <PrimaryButton @click="createSlide(slider.id)" class="text-xs py-1.5">Slide hinzufügen</PrimaryButton>
                        </div>
                    </div>
                </div>
                <p v-if="!project.sliders?.length" class="text-gray-500 text-center py-8">Noch keine Slider. Lege oben einen an.</p>
            </div>
        </div>

        <!-- TAB: Statistics -->
        <div v-if="activeTab === 'statistics'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <ProjectStatistics :projectId="project.id" :apartments="project.apartments || []" />
        </div>

        <!-- TAB: Virtual Tours -->
        <div v-if="activeTab === 'virtual-tours'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <VirtualToursTab :project="project" />
        </div>

        <!-- View Frames Management Modal  -->
        <DialogModal :show="viewFramesModal" @close="viewFramesModal = false; activeViewId = null;" maxWidth="4xl">
            <template #title>
                Frames & Layer für Ansicht "{{ activeView?.name }}" verwalten
            </template>
            <template #content>
                <div v-if="activeView">
                    <!-- Layer Zuweisung -->
                    <div class="mb-8 border-b pb-6">
                        <h4 class="font-bold mb-3 flex items-center gap-2"><Squares2X2Icon class="w-5 h-5 text-gray-500"/> Layer für diese Ansicht aktivieren</h4>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="layer in project.layers" :key="layer.id" 
                                    @click="toggleViewLayer(activeView.id, layer.id)"
                                    :class="[activeView.layers?.some(l => l.id === layer.id) ? 'bg-brand-500 text-white border-brand-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200', 'px-3 py-1.5 rounded-full text-sm border font-medium transition']">
                                <CheckCircleIcon v-if="activeView.layers?.some(l => l.id === layer.id)" class="w-4 h-4 inline mr-1" />
                                {{ layer.name }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Klicke auf Layer, um sie für diese Ansicht zu aktivieren/deaktivieren.</p>
                    </div>

                    <!-- Bulk Upload Wizard -->
                    <div v-if="isBulkUploading">
                        <div class="mb-4">
                            <h4 class="font-bold mb-2 flex items-center gap-2 text-brand-600"><ArrowUpOnSquareStackIcon class="w-5 h-5"/> Sequenzieller Bulk-Upload</h4>
                            <p class="text-sm text-gray-600 mb-4">Wähle den Ziel-Layer und dann beliebig viele Bilder aus. Das System versucht anhand des Dateinamens (z.B. "render_05.png") den zugehörigen Frame (z.B. Index 5) automatisch zuzuordnen. Danach lädt es die Bilder einzeln, ohne Server-Abbruch hoch!</p>
                            
                            <div class="mb-6 p-4 bg-white border rounded">
                                <InputLabel value="Schritt 1: Ziel-Layer auswählen" class="mb-2 text-base font-bold text-gray-800" />
                                <div class="flex gap-2 mb-4">
                                    <select v-model="bulkUploadLayerId" class="border-gray-300 w-full focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" :disabled="isUploadingBulk">
                                        <option :value="null" disabled>Bitte wählen...</option>
                                        <option v-for="layer in activeView.layers" :key="layer.id" :value="layer.id">{{ layer.name }}</option>
                                    </select>
                                </div>
                                
                                <InputLabel v-if="bulkUploadLayerId" value="Schritt 2: Bilder auswählen" class="mb-2 text-base font-bold text-gray-800" />
                                <input v-if="bulkUploadLayerId" type="file" multiple accept="image/*" @change="handleBulkFileSelect" class="mb-4 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" :disabled="isUploadingBulk" />
                            </div>

                            <div v-if="bulkFiles.length > 0">
                                <h4 class="font-bold mb-3">Schritt 3: Zuordnungen prüfen & Hochladen</h4>
                                <div class="space-y-2 max-h-80 overflow-y-auto pr-2 border rounded p-2 bg-gray-50">
                                    <div v-for="(item, idx) in bulkFiles" :key="idx" class="flex items-center gap-4 bg-white p-2 rounded shadow-sm relative">
                                        <div class="w-12 h-12 bg-gray-200 rounded shrink-0 overflow-hidden">
                                            <img :src="item.tempUrl" class="w-full h-full object-cover" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-mono truncate text-gray-600 mb-1" :title="item.file.name">{{ item.file.name }}</p>
                                            <div v-if="item.status === 'uploading'" class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700 mt-1">
                                              <div class="bg-brand-600 h-1.5 rounded-full transition-all" :style="'width: ' + item.progress + '%'"></div>
                                            </div>
                                            <span v-if="item.status === 'success'" class="text-xs text-green-600 font-bold"><CheckCircleIcon class="w-4 h-4 inline" /> Erledigt</span>
                                            <span v-if="item.status === 'error'" class="text-xs text-red-600 font-bold"><XMarkIcon class="w-4 h-4 inline" /> Fehler</span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="text-xs text-gray-500 font-bold">Ziel-Frame:</span>
                                            <select v-model="item.frameIndex" class="w-24 h-8 text-sm text-center border-gray-300 rounded shadow-sm focus:ring-brand-500 py-0 pl-2 pr-6" :disabled="isUploadingBulk || item.status === 'success'">
                                                <option :value="null">-- ? --</option>
                                                <option v-for="frame in activeView.frames" :key="frame.id" :value="frame.index">
                                                    Index {{ frame.index }}
                                                </option>
                                            </select>
                                        </div>
                                        <button v-if="!isUploadingBulk && item.status !== 'success'" @click="removeBulkFile(idx)" class="text-red-500 hover:text-red-700 p-1"><XMarkIcon class="w-5 h-5" /></button>
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <PrimaryButton @click="executeBulkUpload" :disabled="isUploadingBulk || bulkUploadLayerId === null" class="w-full justify-center text-center">
                                        {{ isUploadingBulk ? 'Wird hochgeladen...' : 'Bestätigen & Upload starten' }}
                                    </PrimaryButton>
                                    <SecondaryButton v-if="!isUploadingBulk" @click="cancelBulkUpload" class="w-1/3 justify-center text-center">
                                        Abbrechen
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Frames Liste -->
                    <div v-else>
                        <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4 bg-white p-3 border rounded-lg shadow-sm">
                            <h4 class="font-bold flex items-center gap-2"><PhotoIcon class="w-5 h-5 text-gray-500"/> Frames (aus dieser Ansicht)</h4>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1 border border-gray-300 rounded overflow-hidden">
                                    <input type="number" v-model="bulkCreateCount" class="w-16 h-8 text-xs border-0 focus:ring-0 text-center" min="1" title="Anzahl Frames" />
                                    <button @click="bulkCreateFrames" class="bg-gray-100 px-3 h-8 text-xs font-bold text-gray-700 hover:bg-gray-200 border-l border-gray-300">Bulk anlegen</button>
                                </div>
                                <PrimaryButton @click="saveEntity('Frame', { view_id: activeView.id, index: (activeView.frames?.length || 0) + 1 })" class="text-xs py-1 h-8">Neuer Frame (+1)</PrimaryButton>
                                <SecondaryButton @click="startBulkUpload" class="text-xs py-1 h-8 text-brand-600 border-brand-200">Bilder Bulk-Upload</SecondaryButton>
                            </div>
                        </div>
                        
                        <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                            <div v-for="(frame, fIdx) in activeView.frames || []" :key="frame.id" class="border rounded-md p-4 bg-gray-50 flex flex-col md:flex-row gap-4 items-start relative">
                                <!-- Order arrows -->
                                <div class="flex flex-col gap-1 pr-2 border-r justify-center">
                                    <button @click="moveFrameUp(frame, fIdx)" :disabled="fIdx === 0" :class="[fIdx === 0 ? 'opacity-30' : 'hover:bg-gray-200 text-gray-600', 'p-1 rounded transition']">
                                        <ChevronUpIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="moveFrameDown(frame, fIdx)" :disabled="fIdx === (activeView.frames?.length || 0) - 1" :class="[fIdx === (activeView.frames?.length || 0) - 1 ? 'opacity-30' : 'hover:bg-gray-200 text-gray-600', 'p-1 rounded transition']">
                                        <ChevronDownIcon class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="w-20 flex flex-col items-center">
                                    <span class="text-xs font-bold text-gray-500 uppercase">Index</span>
                                    <input type="number" v-model="frame.index" class="w-full text-center mt-1 border-gray-300 rounded shadow-sm py-1 text-sm bg-gray-100" readonly />
                                    <label class="flex flex-col items-center gap-1 mt-3 cursor-pointer">
                                        <span class="text-[10px] font-bold text-gray-500 uppercase">Stop Frame</span>
                                        <input type="checkbox" v-model="frame.is_stop_frame" @change="saveEntity('Frame', { id: frame.id, is_stop_frame: frame.is_stop_frame, view_id: activeView.id })" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4 shadow-sm" />
                                    </label>
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="font-medium text-sm text-gray-700">Frame-Bilder (pro aktiven Layer)</p>
                                        <div v-if="activeView.layers?.length" class="text-xs font-bold" :class="frame.media?.length >= activeView.layers.length ? 'text-green-600' : 'text-orange-500'">
                                            {{ frame.media?.length || 0 }} / {{ activeView.layers.length }}
                                            <CheckCircleIcon v-if="frame.media?.length >= activeView.layers.length" class="w-4 h-4 inline ml-1" />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="layer in activeView.layers" :key="layer.id" class="flex items-center justify-between text-sm bg-white p-2 border rounded">
                                            <span class="truncate pr-2">Layer: <strong>{{ layer.name }}</strong></span>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <div v-if="frame.media?.find(m => m.collection_name === 'layer_'+layer.id)" class="w-10 h-10 bg-gray-100 rounded border relative flex-shrink-0">
                                                    <img :src="frame.media?.find(m => m.collection_name === 'layer_'+layer.id).original_url" class="object-cover w-full h-full rounded" />
                                                    <button @click.prevent="deleteMedia(frame.media?.find(m => m.collection_name === 'layer_'+layer.id).id)" class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-0.5 shadow hover:scale-110 transition z-10"><XMarkIcon class="w-3 h-3" /></button>
                                                </div>
                                                <span v-else class="text-[10px] text-red-500 font-bold bg-red-50 px-2 py-1 rounded">FEHLT</span>
                                                <input type="file" @change="e => uploadMedia(e, 'Frame', frame.id, 'layer_'+layer.id)" accept="image/*" class="text-[10px] w-48 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300">
                                            </div>
                                        </div>
                                        <div v-if="!activeView.layers || activeView.layers.length === 0" class="text-xs text-red-500">
                                            Bitte aktiviere zuerst Layer für diese Ansicht, um Bilder für den Frame hochzuladen.
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 flex justify-center">
                                    <button @click="deleteEntity('Frame', frame.id)" class="text-red-500 hover:text-red-700 text-xs mt-2 font-bold p-2 bg-red-50 rounded" title="Frame löschen">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="!activeView.frames || activeView.frames.length === 0" class="text-center py-6 border-2 border-dashed rounded-md text-gray-400">
                                Keine Frames vorhanden. Lege einen neuen Frame an.
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="viewFramesModal = false; activeView = null;">Fenster schließen</SecondaryButton>
            </template>
        </DialogModal>

    </AppLayout>
</template>
