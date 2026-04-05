<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import ProjectStatistics from './Components/ProjectStatistics.vue';
import VirtualToursTab from './Components/VirtualToursTab.vue';
import ConfiguratorManager from './Components/ConfiguratorManager.vue';
import draggable from 'vuedraggable';
import { pipeline, env } from '@huggingface/transformers';
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
    Cog6ToothIcon,
    ArrowDownTrayIcon,
    SparklesIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    project: Object,
    teamContacts: { type: Array, default: () => [] },
    externalProperties: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
});

const poiCategoryDefinitions = [
    { value: 'supermarket', label: 'Supermarkt / Einkauf', color: 'text-green-600' },
    { value: 'school', label: 'Schule / Kita', color: 'text-blue-600' },
    { value: 'transit', label: 'ÖPNV / Haltestelle', color: 'text-yellow-600' },
    { value: 'restaurant', label: 'Restaurant / Gastro', color: 'text-red-600' },
    { value: 'park', label: 'Park / Natur', color: 'text-emerald-600' },
    { value: 'pharmacy', label: 'Arzt / Apotheke', color: 'text-purple-600' },
    { value: 'bank', label: 'Bank / Geldautomat', color: 'text-sky-500' },
    { value: 'fitness', label: 'Fitness / Sport', color: 'text-orange-500' },
    { value: 'culture', label: 'Kultur / Kino', color: 'text-pink-600' },
    { value: 'gas', label: 'Tankstelle', color: 'text-slate-500' },
    { value: 'bakery', label: 'Bäckerei', color: 'text-orange-700' },
    { value: 'parking', label: 'Parken', color: 'text-slate-700' },
    { value: 'playground', label: 'Spielplatz', color: 'text-teal-500' },
    { value: 'hospital', label: 'Krankenhaus', color: 'text-rose-600' },
    { value: 'clothing', label: 'Bekleidung / Mode', color: 'text-amber-500' },
    { value: 'hotel', label: 'Hotel / Unterkunft', color: 'text-violet-500' },
    { value: 'hairdresser', label: 'Friseur / Kosmetik', color: 'text-pink-400' }
];

// --- AI Depth Map Generation ---
const isGeneratingDepths = ref(false);
const generationProgress = ref(0);

const generateDepthMaps = async () => {
    if (!confirm('Dies lädt das hochauflösende KI-Modell (ca. 450MB) herunter und generiert für alle fehlenden Frames direkt im Browser Tiefenkarten. Fortfahren?')) return;
    
    isGeneratingDepths.value = true;
    generationProgress.value = 1;
    env.allowLocalModels = false; // from remote cdn
    
    try {
        let estimator;
        try {
            // Transformers v3 supports WebGPU and loads the new IR_9 V2 models cleanly
            estimator = await pipeline('depth-estimation', 'onnx-community/depth-anything-v2-base', {
                device: 'webgpu'
            });
        } catch (innerE) {
            console.warn("WebGPU/Base model failed. Falling back to quantized small model...", innerE);
            estimator = await pipeline('depth-estimation', 'onnx-community/depth-anything-v2-small', {
                quantized: true,
                device: 'wasm'
            });
        }
        
        let allProjectFrames = [];
        if (props.project && props.project.views) {
            props.project.views.forEach(v => {
                if (v.frames) {
                    allProjectFrames.push(...v.frames);
                }
            });
        }
        
        let itemsToProcess = [];
        
        for (const frame of allProjectFrames) {
            if (!frame.media) continue;
            
            const targetMedias = frame.media.filter(m => m.collection_name === 'default' || m.collection_name.startsWith('layer_'));
            
            for (const media of targetMedias) {
                const hasDepth = frame.media.some(m => m.collection_name === 'depth_map' && m.custom_properties?.target_collection === media.collection_name);
                
                if (!hasDepth && media.original_url) {
                    itemsToProcess.push({
                        frameId: frame.id,
                        imgUrl: media.original_url,
                        targetCollection: media.collection_name
                    });
                }
            }
        }
        
        let processed = 0;
        for (const item of itemsToProcess) {
            // Load original image to get Native Aspect Ratio & Resolution
            const originalImg = new Image();
            originalImg.crossOrigin = 'Anonymous';
            originalImg.src = item.imgUrl;
            await new Promise((resolve, reject) => {
                originalImg.onload = resolve;
                originalImg.onerror = reject;
            });
            const finalW = originalImg.naturalWidth;
            const finalH = originalImg.naturalHeight;
            
            // Run Depth Estimation
            const result = await estimator(item.imgUrl);
            const rawImage = result.depth;
            
            // Draw Raw model output to small canvas
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = rawImage.width;
            tempCanvas.height = rawImage.height;
            const tempCtx = tempCanvas.getContext('2d');
            
            const rgbaData = new Uint8ClampedArray(rawImage.width * rawImage.height * 4);
            for (let i = 0; i < rawImage.data.length; i++) {
                const val = rawImage.data[i];
                rgbaData[i * 4] = val;
                rgbaData[i * 4 + 1] = val;
                rgbaData[i * 4 + 2] = val;
                rgbaData[i * 4 + 3] = 255;
            }
            const imgData = new ImageData(rgbaData, rawImage.width, rawImage.height);
            tempCtx.putImageData(imgData, 0, 0);
            
            // Upscale to HD target canvas matching original image
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = finalW;
            finalCanvas.height = finalH;
            const finalCtx = finalCanvas.getContext('2d');
            
            // Smooth resize
            finalCtx.imageSmoothingEnabled = true;
            finalCtx.imageSmoothingQuality = 'high';
            finalCtx.drawImage(tempCanvas, 0, 0, finalW, finalH);
            
            const base64Depth = finalCanvas.toDataURL('image/jpeg', 0.95);
            
            await window.axios.post(`/projects/${props.project.id}/frames/${item.frameId}/maps`, {
                image: base64Depth,
                type: 'depth_map',
                target_collection: item.targetCollection
            });
            
            processed++;
            generationProgress.value = Math.round((processed / itemsToProcess.length) * 100);
        }
        
        alert(`Fertig! ${processed} HD-Tiefenkarten erstellt. Die Seite wird aktualisiert.`);
        window.location.reload();
    } catch (e) {
        console.error(e);
        alert('Fehler bei der Generierung der KI Tiefenkarten.');
    } finally {
        isGeneratingDepths.value = false;
        generationProgress.value = 0;
    }
};

const deleteDepthMaps = async () => {
    if (!confirm('Achtung: Dies löscht alle Tiefenkarten für dieses Projekt. Du musst sie danach neu berechnen lassen. Fortfahren?')) return;
    try {
        await window.axios.delete(`/projects/${props.project.id}/maps`);
        alert('Alle Tiefenkarten wurden erfolgreich gelöscht.');
        window.location.reload();
    } catch (e) {
        alert('Fehler beim Löschen der Tiefenkarten.');
    }
};

const activeTab = ref('general');
const isEditingProject = ref(false);

// Deep-link: ?apartment=ID opens the apartment editor
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const aptId = params.get('apartment');
    if (aptId) {
        activeTab.value = 'apartments';
        nextTick(() => {
            const apt = props.project.apartments?.find(a => a.id === parseInt(aptId));
            if (apt) editInline('apartment', apt);
        });
    }
    const tabParam = params.get('tab');
    if (tabParam) activeTab.value = tabParam;
});

// --- Color Palette Generator ---
const showColorPaletteModal = ref(false);
const paletteBaseColor = ref('#ab715c');
const isAiPaletteLoading = ref(false);
const suggestedPalettes = ref([]);

const hexToHsl = (hex) => {
    let r = parseInt(hex.slice(1, 3), 16) / 255;
    let g = parseInt(hex.slice(3, 5), 16) / 255;
    let b = parseInt(hex.slice(5, 7), 16) / 255;
    let max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    if (max === min) { h = s = 0; } else {
        let d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }
    return [h * 360, s * 100, l * 100];
};

const hslToHex = (h, s, l) => {
    l /= 100;
    const a = s * Math.min(l, 1 - l) / 100;
    const f = n => {
        const k = (n + h / 30) % 12;
        const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
        return Math.round(255 * color).toString(16).padStart(2, '0');
    };
    return `#${f(0)}${f(8)}${f(4)}`;
};

const getThemePalettes = (base) => {
    const [h, s, l] = hexToHsl(base);
    const getContrastYIQ = (hex) => {
        const r = parseInt(hex.substr(1,2),16);
        const g = parseInt(hex.substr(3,2),16);
        const b = parseInt(hex.substr(5,2),16);
        return ((r*299)+(g*587)+(b*114))/1000 >= 128 ? '#111827' : '#ffffff';
    };

    const make = (name, h1, s1, l1) => {
        const primary = hslToHex(h1 % 360, s1, l1);
        return {
            name,
            colors: [
                primary,
                hslToHex(h1 % 360, Math.min(100, s1 + 10), Math.max(0, l1 - 10)), // Hover
                getContrastYIQ(primary), // Text
                hslToHex(h1 % 360, s1 * 0.5, Math.min(100, l1 + 20)) // Secondary/Border
            ]
        };
    };

    return [
        make('Basis', h, s, l),
        make('Analog (Links)', h - 30, s, l),
        make('Analog (Rechts)', h + 30, s, l),
        make('Komplementär', h + 180, s, l),
        make('Triadisch A', h + 120, s, l),
        make('Triadisch B', h + 240, s, l),
        make('Soft & Elegant', h, s * 0.5, l + 10),
        make('Modern Dark', h, s * 0.8, 25),
    ];
};

const generateAiPalettes = async () => {
    isAiPaletteLoading.value = true;
    try {
        const response = await axios.post(route('ai.generate'), {
            prompt: `Generiere Farbschemen für ein Immobilienprojekt namens "${props.project.name}". Beschreibung: ${props.project.description || 'Modernes Wohnen'}`,
            mode: 'color_palette',
            model: 'llama3.1'
        });
        
        const data = typeof response.data.content === 'string' ? JSON.parse(response.data.content) : response.data.content;
        if (Array.isArray(data)) {
            // Append AI results
            suggestedPalettes.value = [...getThemePalettes(paletteBaseColor.value), ...data];
        }
    } catch (e) {
        console.error('AI Palette error:', e);
    } finally {
        isAiPaletteLoading.value = false;
    }
};

const updatePaletteSuggestions = () => {
    suggestedPalettes.value = getThemePalettes(paletteBaseColor.value);
};

const applyPalette = (colors) => {
    projectForm.color_settings.primary.base = colors[0];
    projectForm.color_settings.primary.hover = colors[1];
    projectForm.color_settings.primary.text = colors[2];
    showColorPaletteModal.value = false;
};

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

// --- CRM / Integrations Tab Logic ---
const newIntegrationPlatform = ref('');
const createIntegration = () => {
    router.post(`/projects/${props.project.id}/integrations`, {
        platform_name: newIntegrationPlatform.value,
        is_active: false
    }, { preserveScroll: true, onSuccess: () => newIntegrationPlatform.value = '' });
};
const updateIntegrationStatus = (integration, active) => {
    router.put(`/integrations/${integration.id}`, {
        is_active: active
    }, { preserveScroll: true });
};
const dispatchPropertySync = () => {
    router.post(`/projects/${props.project.id}/external-properties/dispatch-sync`, {}, { preserveScroll: true });
};

// Confirm Popup
const confirmPopup = ref({ show: false, title: '', message: '', action: null });
const showConfirm = (title, message, action) => {
    confirmPopup.value = { show: true, title, message, action };
};
const executeConfirm = () => {
    if (confirmPopup.value.action) confirmPopup.value.action();
    confirmPopup.value.show = false;
};

const deleteIntegration = (integration) => {
    showConfirm('Anbindung löschen', `"${integration.platform_name}" wirklich löschen? Alle zugehörigen Daten gehen verloren.`, () => {
        router.delete(`/integrations/${integration.id}`, { preserveScroll: true });
    });
};

const integrationForms = ref({});
const initIntegrationForms = () => {
    props.project.integrations?.forEach(integration => {
        let creds = integration.credentials || {};
        if (integration.platform_name === 'onoffice' && !creds.token) creds = { token: '', secret: '' };
        if (integration.platform_name === 'flowfact' && !creds.client_id) creds = { client_id: '', api_key: '' };
        if (integration.platform_name === 'propstack' && !creds.api_key) creds = { api_key: '' };
        integrationForms.value[integration.id] = { ...creds };
    });
};
initIntegrationForms();

watch(() => props.project.integrations, () => {
    initIntegrationForms();
}, { deep: true });

const saveIntegrationCredentials = (integrationId) => {
    router.put(`/integrations/${integrationId}`, {
        credentials: integrationForms.value[integrationId]
    }, { preserveScroll: true });
};

// --- Team Tab Logic ---
const syncUsersData = ref(props.project.users ? JSON.parse(JSON.stringify(props.project.users)) : []);
const selectedTeamUser = ref(null);
const selectedTeamUserRole = ref('member');

const addTeamUserToProject = () => {
    if (!selectedTeamUser.value) return;
    const u = props.teamUsers.find(x => x.id === selectedTeamUser.value);
    if (u && !syncUsersData.value.find(x => x.id === u.id)) {
        syncUsersData.value.push({ ...u, pivot: { role: selectedTeamUserRole.value } });
        selectedTeamUser.value = null;
        selectedTeamUserRole.value = 'member';
    }
};
const removeTeamUserFromProject = (id) => {
    syncUsersData.value = syncUsersData.value.filter(u => u.id !== id);
};
const saveProjectTeam = () => {
    router.post(`/projects/${props.project.id}/sync-users`, {
        users: syncUsersData.value.map(u => ({ id: u.id, role: u.pivot.role }))
    }, { preserveScroll: true });
};

// Edit Project Setup
const activeApartmentAccordion = ref('basis');
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
        position: 'right_bottom',
        position_mobile: 'bottom_bar',
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
    poi_settings: (() => {
        let ps = props.project.poi_settings || {
            active: false,
            radius: 2000,
            categories: ['supermarket', 'school', 'transit'],
            default_categories: ['supermarket', 'school', 'transit'],
            show_sun: false
        };
        if (!ps.isochrones) {
            ps.isochrones = {
                walking: { active: false, minutes: 15 },
                cycling: { active: false, minutes: 15 },
                driving: { active: false, minutes: 10 }
            };
        }
        if (!ps.default_categories) {
            ps.default_categories = [...(ps.categories || [])];
        }
        if (ps.show_sun === undefined) ps.show_sun = false;
        return ps;
    })(),
    analytics_settings: props.project.analytics_settings || {
        active: false,
        ga_id: '',
        events: []
    },
    calculator_settings: props.project.calculator_settings || {
        active: true,
        interest_rate: 3.5,
        repayment: 2.0
    },
    auto_tour_settings: props.project.auto_tour_settings || {
        active: false,
        storyboard: []
    },
    contact_form_config: props.project.contact_form_config || {
        title: 'Wohnung anfragen',
        subtitle: 'Hinterlassen Sie Ihre Kontaktdaten für weitere Informationen.',
        email_recipients: '', // Fallback or override
        success_message: 'Ihre Anfrage wurde erfolgreich an uns übermittelt. Wir melden uns in Kürze.',
        fields: [
            { id: 'first_name', label: 'Vorname', type: 'text', required: true, width: 'half' },
            { id: 'last_name', label: 'Nachname', type: 'text', required: true, width: 'half' },
            { id: 'email', label: 'E-Mail Adresse', type: 'email', required: true, width: 'full' },
            { id: 'phone', label: 'Telefonnummer', type: 'tel', required: false, width: 'full' },
            { id: 'message', label: 'Ihre Nachricht', type: 'textarea', required: false, width: 'full' }
        ]
    },
    openimmo_settings: props.project.openimmo_settings || {
        firma: '',
        openimmo_anid: '',
        baujahr: '',
        waehrung: 'EUR',
        zustand_art: 'ERSTBEZUG',
        objektnr_prefix: 'APT-',
        impressum_firmenname: '',
        impressum_strasse: '',
        impressum_plz: '',
        impressum_ort: '',
        impressum_telefon: '',
        impressum_email: '',
        impressum_website: '',
        kontakt_anrede: 'HERR',
        kontakt_vorname: '',
        kontakt_nachname: '',
        kontakt_telefon: '',
        kontakt_email: '',
        kontakt_strasse: '',
        kontakt_plz: '',
        kontakt_ort: ''
    },
    legal_settings: props.project.legal_settings || {
        impressum: '',
        datenschutz: ''
    },
    pdf_settings: props.project.pdf_settings || {
        template: 'modern',
        show_features: true,
        show_rooms: true,
        show_unit_table: false,
        footer_text: ''
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

const duplicateProject = () => {
    if (!confirm('Projekt wirklich duplizieren? Es wird eine Kopie ohne Mediendateien erstellt.')) return;
    router.post(`/projects/${props.project.id}/duplicate`, {}, {
        preserveScroll: true,
        onSuccess: () => alert('Projekt erfolgreich dupliziert!'),
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
        mappedData.contact_ids = data.contacts ? data.contacts.map(c => c.id) : [];
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

// External Property Sync
const syncExternalProperty = (apartmentId, externalPropertyId) => {
    if (!confirm('Möchtest du wirklich die lokalen Daten der Wohnung mit den Daten aus dem CRM überschreiben?')) return;
    
    router.post(route('apartments.sync-external', apartmentId), {
        external_property_id: externalPropertyId
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Re-map the newly refreshed apartment into modalData so the UI updates
            const updated = props.project.apartments.find(a => a.id === apartmentId);
            if (updated) {
                editInline('apartment', updated);
            }
        }
    });
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

const uploadingMediaContext = ref(null);
const uploadMediaProgress = ref(0);

const uploadMedia = async (e, model, id, collection = 'default') => {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    uploadingMediaContext.value = `${model}-${id}-${collection}`;
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
            uploadingMediaContext.value = null;
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
const expandedSlider = ref(null);
const newSlide = ref({}); // keyed by slider.id

const sortedSlides = (slider) => {
    return [...(slider.slides || [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
};

const moveSlide = (slider, currentIdx, direction) => {
    const slides = sortedSlides(slider);
    const targetIdx = currentIdx + direction;
    if (targetIdx < 0 || targetIdx >= slides.length) return;

    // Swap sort_order values
    const ids = slides.map(s => s.id);
    [ids[currentIdx], ids[targetIdx]] = [ids[targetIdx], ids[currentIdx]];

    router.post(`/projects/${props.project.id}/sliders/${slider.id}/reorder-slides`, { order: ids }, { preserveScroll: true });
};

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
    return [...props.project.floors]
        .filter(f => f.name.toLowerCase().includes(searchQueries.value.floors.toLowerCase()))
        .sort((a, b) => a.index - b.index);
});

const updateFloorOrder = (event) => {
    // Collect the new order and POST to backend
    const updatedFloors = filteredFloors.value.map((f, i) => ({ id: f.id, index: i }));
    router.post(route('projects.floors.reorder', { project: props.project.id }), { floors: updatedFloors }, {
        preserveScroll: true
    });
};

const updateLayerOrder = (event) => {
    const updatedLayers = filteredLayers.value.map((l, i) => ({ id: l.id, index: i }));
    router.post(route('projects.layers.reorder', { project: props.project.id }), { layers: updatedLayers }, {
        preserveScroll: true
    });
};

const updateRoomOrder = (event) => {
    if (!currentApartment.value?.rooms_list) return;
    const updatedRooms = currentApartment.value.rooms_list.map((r, i) => ({ id: r.id, index: i }));
    router.post(route('projects.rooms.reorder', { project: props.project.id }), { rooms: updatedRooms }, {
        preserveScroll: true
    });
};

const filterApartmentHouse = ref('');
const filterApartmentFloor = ref('');

const filteredApartments = computed(() => {
    if (!props.project.apartments) return [];
    return [...props.project.apartments]
        .filter(a => a.name.toLowerCase().includes(searchQueries.value.apartments.toLowerCase()))
        .filter(a => filterApartmentHouse.value === '' || a.house_id === filterApartmentHouse.value);
});

// --- Infoframes Enhancements ---
const infoframeTab = ref('visual');
const aiChatMessages = ref([]);
const aiCurrentPrompt = ref('');
const isAiLoading = ref(false);
const isAiPitchLoading = ref(false);

const showDescAiModal = ref(false);
const descAiChatMessages = ref([]);
const descAiCurrentPrompt = ref('');
const isDescAiLoading = ref(false);
const descAiTarget = ref('project'); // 'project' or 'apartment'

const sendDescAiChat = async () => {
    if (!descAiCurrentPrompt.value.trim() || isDescAiLoading.value) return;
    const userMessage = descAiCurrentPrompt.value;
    descAiChatMessages.value.push({ role: 'user', content: userMessage });
    descAiCurrentPrompt.value = '';
    isDescAiLoading.value = true;
    
    // Choose context text
    const currentContextText = descAiTarget.value === 'project' 
        ? (projectForm.description || '') 
        : (modalData.value.description || '');

    try {
        const response = await axios.post(route('ai.generate'), {
            prompt: userMessage,
            context: currentContextText,
            history: descAiChatMessages.value.slice(-6),
            model: 'llama3.1',
            mode: 'text_description'
        });
        if (response.data.success) {
            descAiChatMessages.value.push({ role: 'assistant', content: response.data.content });
        } else {
            descAiChatMessages.value.push({ role: 'assistant', content: 'Fehler: ' + response.data.error });
        }
    } catch (e) {
        descAiChatMessages.value.push({ role: 'assistant', content: 'Netzwerkfehler (' + e.message + ')' });
    } finally {
        isDescAiLoading.value = false;
        setTimeout(() => {
            const el = document.getElementById('descAiChatWindow');
            if(el) el.scrollTop = el.scrollHeight;
        }, 100);
    }
};

const applyDescAiCode = (messageContent) => {
    let code = messageContent.match(/```([\s\S]*?)```/);
    if (code) {
        if (descAiTarget.value === 'project') {
            projectForm.description = code[1].trim();
        } else {
            modalData.value.description = code[1].trim();
        }
    } else {
         alert("Kein Code-Block in der Antwort gefunden!");
    }
};
const sendAiChat = async () => {
    if (!aiCurrentPrompt.value.trim() || isAiLoading.value) return;

    const userMessage = aiCurrentPrompt.value;
    aiChatMessages.value.push({ role: 'user', content: userMessage });
    aiCurrentPrompt.value = '';
    isAiLoading.value = true;

    try {
        const safeContext = prepareContextForAi(modalData.value.content);

        const response = await axios.post(route('ai.generate'), {
            prompt: userMessage,
            context: safeContext,
            history: aiChatMessages.value.slice(-6), // Send last 3 pairs
            model: 'llama3.1'
        });

        if (response.data.success) {
            aiChatMessages.value.push({ role: 'assistant', content: response.data.content });
        } else {
            aiChatMessages.value.push({ role: 'assistant', content: 'Entschuldigung, es gab einen Fehler: ' + response.data.error });
        }
    } catch (e) {
        aiChatMessages.value.push({ role: 'assistant', content: 'Verbindungsfehler zur KI. Ist Ollama aktiv?' });
    } finally {
        isAiLoading.value = false;
        // Scroll to bottom of chat? (handeld via watch probably)
    }
};

// Base64 AI Proxy Logic to avoid blowing up context
let base64AiMap = {};

const prepareContextForAi = (html) => {
    base64AiMap = {};
    if (!html) return html;
    let idx = 1;
    // Proxies heavy embedded base64 images
    return html.replace(/src="(data:image\/[^;]+;base64,[^"]+)"/g, (match, dataUri) => {
        const placeholder = `[BASE64_IMAGE_${idx}]`;
        base64AiMap[placeholder] = dataUri;
        idx++;
        return `src="${placeholder}"`;
    });
};

const restoreBase64Images = (html) => {
    if (!html) return html;
    let restored = html;
    for (const [placeholder, base64] of Object.entries(base64AiMap)) {
        // Escape [ and ] to safely parse inside RegExp
        const safePlaceholder = placeholder.replace(/\[/g, '\\[').replace(/\]/g, '\\]');
        restored = restored.replace(new RegExp(`src=["']?${safePlaceholder}["']?`, 'g'), `src="${base64}"`);
    }
    // Clean up random language names left by markdown parsers
    if (restored.startsWith('html')) restored = restored.substring(4).trim();
    return restored;
};

const extractCodeFromMessage = (content) => {
    const match = content.match(/```([\s\S]*?)```/);
    if (!match) return null;
    let code = match[1].trim();
    return restoreBase64Images(code);
};

const applyAiCode = (messageContent) => {
    const code = extractCodeFromMessage(messageContent);
    if (code) {
        // Apply code and force reactivity by updating reference
        modalData.value = { ...modalData.value, content: code };
        infoframeTab.value = 'preview'; // Switch to preview to avoid Quill tearing down tables
    }
};

const generateAiContent = async () => {
    // Legacy support or quick prompt
    aiCurrentPrompt.value = aiPrompt.value;
    aiPrompt.value = '';
    sendAiChat();
};

const generateAiPitch = async () => {
    isAiPitchLoading.value = true;
    try {
        const features = modalData.value.features?.map(fid => props.project.features.find(f => f.id === fid)?.name).filter(Boolean).join(', ') || 'Standard';
        
        const prompt = `Erstelle einen professionellen, emotional ansprechenden Immobilien-Verkaufstext (Pitch) für folgende Wohnung:
        Name: ${modalData.value.name}
        Zimmer: ${modalData.value.rooms}
        Bäder: ${modalData.value.bathrooms}
        qm: ${modalData.value.sqm}
        Balkon/Terrasse: ${modalData.value.outdoor_area} qm
        Features: ${features}
        Marketing-Typ: ${modalData.value.marketing_type}
        
        Der Text soll die Vorzüge hervorheben und das Interesse wecken. Antworte NUR mit dem generierten Text.`;

        const response = await axios.post(route('ai.generate'), { prompt });
        const data = response.data;
        if (data.success) {
            modalData.value.description = data.content;
        } else {
            alert(data.error || 'Fehler bei der Textgenerierung.');
        }
    } catch (e) {
        console.error('AI Pitch Error:', e);
    } finally {
        isAiPitchLoading.value = false;
    }
};


const filteredLayers = computed(() => {
    if (!props.project.layers) return [];
    return [...props.project.layers]
        .filter(l => l.name.toLowerCase().includes(searchQueries.value.layers.toLowerCase()))
        .sort((a, b) => a.sort_order - b.sort_order);
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
const bulkUploadStartsAtZero = ref(true);

const updateBulkFileMapping = () => {
    bulkFiles.value.forEach(item => {
        if (item.suggestedIndex !== null && activeView.value?.frames) {
            const offset = bulkUploadStartsAtZero.value ? 1 : 0;
            const targetIndex = item.suggestedIndex + offset;
            const matchingFrame = activeView.value.frames.find(f => parseInt(f.index, 10) === targetIndex);
            item.frameIndex = matchingFrame ? matchingFrame.index : null;
        }
    });
};

watch(bulkUploadStartsAtZero, () => {
    updateBulkFileMapping();
});

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
        const matches = file.name.match(/\d+/g);
        const suggestedIndex = matches && matches.length > 0 ? parseInt(matches[matches.length - 1], 10) : null;

        bulkFiles.value.push({ 
            file, 
            frameIndex: null, 
            progress: 0, 
            status: 'pending', 
            tempUrl: URL.createObjectURL(file),
            suggestedIndex
        });
    });
    bulkFiles.value.sort((a,b) => (a.suggestedIndex || 0) - (b.suggestedIndex || 0));
    updateBulkFileMapping();
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

const isOptimizing = ref(false);
const optmizationProgress = ref('');

const startOptimization = async () => {
    if (!activeView.value?.frames || activeView.value.frames.length === 0) return;
    
    if (!confirm('Möchten Sie alle Bilder in dieser Ansicht wirklich auf max. 2500px verkleinern und in AVIF konvertieren? (Das Frontend spielt danach automatisch diese Versionen aus)')) return;

    isOptimizing.value = true;
    let totalOptimized = 0;
    const framesToProcess = activeView.value.frames;
    
    for (let i = 0; i < framesToProcess.length; i++) {
        optmizationProgress.value = `Frame ${i + 1}/${framesToProcess.length}`;
        try {
            const r = await window.axios.post(`/projects/${props.project.id}/frames/${framesToProcess[i].id}/optimize`);
            if (r.data.success && r.data.optimized > 0) {
                totalOptimized += r.data.optimized;
            }
        } catch (e) {
            console.error("Optimization err:", e);
        }
    }
    
    isOptimizing.value = false;
    optmizationProgress.value = '';
    alert(`Optimierung abgeschlossen! ${totalOptimized} Bilder wurden erfolgreich konvertiert/verkleinert.`);
    router.reload({ preserveScroll: true });
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
                        <button @click="activeTab = 'autotour'" :class="[activeTab === 'autotour' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg> Auto-Tour
                        </button>
                        <button @click="activeTab = 'configurator'" :class="[activeTab === 'configurator' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <SparklesIcon class="w-5 h-5" /> 3D Konfigurator
                        </button>
                        <button @click="activeTab = 'team'" :class="[activeTab === 'team' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <UserGroupIcon class="w-5 h-5" /> Team-Zugriff
                        </button>
                        <button @click="activeTab = 'crm'" :class="[activeTab === 'crm' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <BuildingOfficeIcon class="w-5 h-5" /> Anbindungen
                        </button>
                        <button @click="activeTab = 'settings'" :class="[activeTab === 'settings' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <Cog6ToothIcon class="w-5 h-5" /> Einstellungen
                        </button>
                        <button @click="activeTab = 'pdf'" :class="[activeTab === 'pdf' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50', 'px-4 py-3 rounded-md text-sm transition font-medium flex items-center gap-2 whitespace-nowrap']">
                            <ArrowDownTrayIcon class="w-5 h-5" /> Exposé-Generator
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'configurator'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <ConfiguratorManager :project="project" />
                </div>

                <div v-if="activeTab === 'pdf'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <h3 class="text-lg font-bold flex items-center gap-2 mb-4">
                        <ArrowDownTrayIcon class="w-6 h-6 text-brand-500" /> Exposé-Generator
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Template-Design" />
                            <select v-model="projectForm.pdf_settings.template" class="w-full mt-1 border-gray-300 rounded-md shadow-sm">
                                <option value="modern">Modern (Hell & Großflächig)</option>
                                <option value="classic">Klassisch (Detailliert)</option>
                                <option value="minimal">Minimalistisch</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Footer Text" />
                            <TextInput v-model="projectForm.pdf_settings.footer_text" type="text" class="mt-1 block w-full" placeholder="z.B. Alle Angaben ohne Gewähr." />
                        </div>
                        <div class="md:col-span-2 flex flex-col md:flex-row md:items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="projectForm.pdf_settings.show_features" class="border-gray-300 rounded text-brand-600"> Ausstattung pro Wohnung
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="projectForm.pdf_settings.show_rooms" class="border-gray-300 rounded text-brand-600"> Raumliste (qm) anzeigen
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="projectForm.pdf_settings.show_unit_table" class="border-gray-300 rounded text-brand-600"> Liste aller freien Wohnungen anhängen
                            </label>
                        </div>
                        <div class="md:col-span-2 pt-4 border-t border-gray-200 text-sm text-gray-500">
                            <strong>Hinweis:</strong> Die PDF-Generierung für einzelne Wohnungen erfolgt im Projekt-Frontend per Knopfdruck ("PDF Download"). Die Master-Einstellungen dafür können hier festgelegt werden.
                        </div>
                    </div>
                </div>

                <!-- Tab: General Info & Uploads -->
                <div v-if="activeTab === 'general'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <div class="flex items-center justify-between border-b pb-2 mb-4">
                                <h3 class="text-lg font-bold flex items-center gap-2"><BuildingOfficeIcon class="w-6 h-6 text-brand-500"/> Projektdaten & Farben</h3>
                                <div class="flex items-center gap-2">
                                    <PrimaryButton v-if="!isEditingProject" @click="isEditingProject = true">Projekt bearbeiten</PrimaryButton>
                                </div>
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
                                    <div class="flex items-center justify-between mb-1">
                                        <InputLabel value="Beschreibung" />
                                        <button @click.prevent="descAiTarget = 'project'; showDescAiModal = true" class="text-xs bg-brand-50 text-brand-600 px-2 py-1 rounded font-bold hover:bg-brand-100 flex items-center gap-1 transition">
                                            <SparklesIcon class="w-3 h-3"/> KI Text Generator
                                        </button>
                                    </div>
                                    <textarea v-model="projectForm.description" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm block w-full h-24"></textarea>
                                </div>
                                
                                <div class="mb-6 p-4 bg-white rounded border">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-bold">Globale Farbakzente (Buttons, Icons)</h4>
                                        <button @click.prevent="paletteBaseColor = projectForm.color_settings.primary.base; updatePaletteSuggestions(); showColorPaletteModal = true" class="text-xs bg-brand-50 text-brand-600 px-3 py-1.5 rounded-full font-black border border-brand-100 hover:bg-brand-100 transition flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                                            Farbschema generieren
                                        </button>
                                    </div>
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
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2a3.001 3.001 0 01-3-2M12 6v2m0 8v2M7 10h.01M17 14h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Finanzierungsrechner
                                    </h4>
                                    
                                    <label class="flex items-center font-bold mb-4 bg-gray-50 p-2 rounded border cursor-pointer hover:bg-gray-100">
                                        <input type="checkbox" v-model="projectForm.calculator_settings.active" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 w-5 h-5 mr-3">
                                        Rechner im Frontend aktivieren
                                    </label>
                                    
                                    <div v-if="projectForm.calculator_settings.active" class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel value="Standard Zinssatz (%)" />
                                                <TextInput type="number" step="0.1" v-model="projectForm.calculator_settings.interest_rate" class="mt-1 block w-full" />
                                            </div>
                                            <div>
                                                <InputLabel value="Standard Tilgung (%)" />
                                                <TextInput type="number" step="0.1" v-model="projectForm.calculator_settings.repayment" class="mt-1 block w-full" />
                                            </div>
                                        </div>
                                    </div>
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
                                                <InputLabel value="Position (Desktop)" />
                                                <select v-model="projectForm.floating_bar.position" class="w-full border-gray-300 focus:border-brand-500 rounded-md text-sm">
                                                    <option value="right">Rechts (mittig)</option>
                                                    <option value="right_bottom">Rechts (unten)</option>
                                                    <option value="left">Links (mittig)</option>
                                                    <option value="left_bottom">Links (unten)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <InputLabel value="Position (Mobile < 768px)" />
                                                <select v-model="projectForm.floating_bar.position_mobile" class="w-full border-gray-300 focus:border-brand-500 rounded-md text-sm">
                                                    <option value="bottom_bar">Als Sticky Bottom-Bar (Volle Breite, unten)</option>
                                                    <option value="right_bottom">Rechts (unten schwebend)</option>
                                                    <option value="left_bottom">Links (unten schwebend)</option>
                                                    <option value="hidden">Auf Mobile verbergen</option>
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
                                            <InputLabel value="Welche Orte sollen gefunden / auf der Karte gezeigt werden?" class="mb-2" />
                                            <div class="flex flex-wrap gap-3">
                                                <div v-for="cat in poiCategoryDefinitions" :key="cat.value" class="bg-gray-50 p-3 rounded border border-gray-200 flex flex-col min-w-[170px] flex-1">
                                                    <div class="text-[13px] font-bold mb-2 flex items-center gap-1.5" :class="cat.color">
                                                        {{ cat.label }}
                                                    </div>
                                                    <div class="flex flex-col gap-1.5">
                                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                                            <input type="checkbox" v-model="projectForm.poi_settings.categories" :value="cat.value" class="rounded border-gray-300 w-3.5 h-3.5 text-brand-600 focus:ring-brand-500" />
                                                            Daten abrufen
                                                        </label>
                                                        <label class="flex items-center gap-2 text-xs text-brand-800 cursor-pointer" :class="{'opacity-50': !projectForm.poi_settings.categories.includes(cat.value)}">
                                                            <input type="checkbox" v-model="projectForm.poi_settings.default_categories" :value="cat.value" :disabled="!projectForm.poi_settings.categories.includes(cat.value)" class="rounded border-gray-300 w-3.5 h-3.5 text-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:bg-gray-100" />
                                                            Im Frontend aktiv
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="pt-4 border-t border-gray-200">
                                            <InputLabel value="Erreichbarkeitspolygone (Isochronen) generieren" class="mb-2" />
                                            <p class="text-[12px] text-gray-500 mb-4 font-normal">Achtung: Dies definiert die maximale Entfernung. Sind diese aktiv, werden POIs außerhalb der Entfernung für den User automatisch versteckt.</p>
                                            <div class="flex items-center gap-4 mb-4">
                                                <label class="flex items-center gap-2 cursor-pointer font-bold text-brand-700">
                                                    <input type="checkbox" v-model="projectForm.poi_settings.show_sun" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 w-5 h-5" />
                                                    Sonnenstands-Simulation (Sonnenverlauf) aktivieren
                                                </label>
                                            </div>
                                            <div class="space-y-3">
                                                <div class="flex items-center gap-4">
                                                    <label class="flex items-center gap-2 cursor-pointer w-32"><input type="checkbox" v-model="projectForm.poi_settings.isochrones.walking.active" class="rounded border-gray-300" /> <b>Zu Fuß</b></label>
                                                    <input v-if="projectForm.poi_settings.isochrones.walking.active" type="number" v-model="projectForm.poi_settings.isochrones.walking.minutes" class="w-20 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-brand-500" min="1" max="45" /> <span v-if="projectForm.poi_settings.isochrones.walking.active" class="text-sm">Minuten</span>
                                                </div>
                                                <div class="flex items-center gap-4">
                                                    <label class="flex items-center gap-2 cursor-pointer w-32"><input type="checkbox" v-model="projectForm.poi_settings.isochrones.cycling.active" class="rounded border-gray-300" /> <b>Fahrrad</b></label>
                                                    <input v-if="projectForm.poi_settings.isochrones.cycling.active" type="number" v-model="projectForm.poi_settings.isochrones.cycling.minutes" class="w-20 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-brand-500" min="1" max="45" /> <span v-if="projectForm.poi_settings.isochrones.cycling.active" class="text-sm">Minuten</span>
                                                </div>
                                                <div class="flex items-center gap-4">
                                                    <label class="flex items-center gap-2 cursor-pointer w-32"><input type="checkbox" v-model="projectForm.poi_settings.isochrones.driving.active" class="rounded border-gray-300" /> <b>Auto</b></label>
                                                    <input v-if="projectForm.poi_settings.isochrones.driving.active" type="number" v-model="projectForm.poi_settings.isochrones.driving.minutes" class="w-20 border border-gray-300 rounded px-2 py-1 text-sm focus:ring-brand-500" min="1" max="45" /> <span v-if="projectForm.poi_settings.isochrones.driving.active" class="text-sm">Minuten</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6 p-4 bg-white rounded border border-gray-200">
                                    <div class="flex items-center gap-3 mb-4">
                                        <input type="checkbox" v-model="projectForm.analytics_settings.active" id="analyticsActive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 w-5 h-5" />
                                        <label for="analyticsActive" class="font-bold text-lg text-[#ab715c] cursor-pointer flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                                            Google Analytics (GA4) / Tracking Optionen
                                        </label>
                                    </div>
                                    <div v-if="projectForm.analytics_settings.active" class="pl-8 space-y-4">
                                        <div>
                                            <InputLabel value="Measurement ID (z.B. G-XXXXXXXXXX)" class="mb-1" />
                                            <TextInput v-model="projectForm.analytics_settings.ga_id" class="w-full md:w-1/2" placeholder="G-" />
                                        </div>
                                        
                                        <div class="mt-4 border-t pt-4">
                                            <InputLabel value="Tracking Events konfigurieren" class="mb-2 font-bold" />
                                            <div v-if="projectForm.analytics_settings.events?.length" class="space-y-2 mb-2">
                                                <div v-for="(event, index) in projectForm.analytics_settings.events" :key="index" class="flex flex-wrap gap-x-2 gap-y-1 items-start bg-gray-50 p-3 border border-gray-200 rounded">
                                                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                                                        <span class="text-[10px] uppercase font-bold text-gray-500">Trigger (Auslöser)</span>
                                                        <select v-model="event.trigger" class="border-gray-300 focus:border-brand-500 rounded text-sm w-full py-1.5">
                                                            <option value="view_apartment">Wohnung geöffnet</option>
                                                            <option value="contact_form_open">Kontakt-Formular geöffnet</option>
                                                            <option value="contact_form_submit">Kontakt-Anfrage gesendet</option>
                                                            <option value="tour_open">Virtuelle Tour geöffnet</option>
                                                            <option value="pdf_download">Exposé / PDF Download</option>
                                                        </select>
                                                    </div>
                                                    <div class="flex flex-col gap-1 w-1/3">
                                                        <span class="text-[10px] uppercase font-bold text-gray-500">GA Event Name</span>
                                                        <TextInput v-model="event.event_name" placeholder="z.B. generate_lead" class="w-full text-sm py-1.5" />
                                                    </div>
                                                    <div class="flex flex-col gap-1 w-1/3">
                                                        <span class="text-[10px] uppercase font-bold text-gray-500">Wert / Parameter (optional)</span>
                                                        <TextInput v-model="event.event_value" placeholder="10 / label" class="w-full text-sm py-1.5" />
                                                    </div>
                                                    <div class="col-span-full border-t border-gray-100 pt-2 mt-2 w-full flex justify-between items-center">
                                                        <label class="flex items-center gap-1.5 text-xs cursor-pointer text-gray-600">
                                                            <input type="checkbox" v-model="event.pass_apartment_data" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                                            Ausgewählte Wohnung (Name, ID, Preis) als GA4 Parameter übergeben
                                                        </label>
                                                        <button @click.prevent="projectForm.analytics_settings.events.splice(index, 1)" class="text-red-500 hover:text-red-700 p-1">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <SecondaryButton @click="() => { if(!projectForm.analytics_settings.events) projectForm.analytics_settings.events = []; projectForm.analytics_settings.events.push({ trigger: 'view_apartment', event_name: '', event_value: '', pass_apartment_data: false }); }" type="button" class="text-xs py-1.5">
                                                Neues Event hinzufügen
                                            </SecondaryButton>
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
                                            <div class="col-span-2">
                                                <InputLabel value="Erfolgsmeldung nach Versenden" />
                                                <TextInput v-model="projectForm.contact_form_config.success_message" class="w-full mt-1" placeholder="Ihre Anfrage wurde erfolgreich übermittelt. Wir melden uns in Kürze." />
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t pt-4">
                                            <InputLabel value="Kontaktfelder definieren" class="mb-2" />
                                            <draggable v-if="projectForm.contact_form_config.fields?.length" v-model="projectForm.contact_form_config.fields" item-key="id" handle=".cursor-move" class="space-y-2 mb-2">
                                                <template #item="{ element, index }">
                                                    <div class="flex flex-col gap-2 bg-gray-50 p-3 border border-gray-200 rounded relative">
                                                        <button @click.prevent="projectForm.contact_form_config.fields.splice(index, 1)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 p-1">
                                                            <XMarkIcon class="w-5 h-5" />
                                                        </button>
                                                        <div class="flex gap-2 items-center pr-8">
                                                            <div class="cursor-move text-gray-400 select-none px-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                                            </div>
                                                            <TextInput v-model="element.label" placeholder="Feld Label" class="flex-1 text-sm py-1.5" />
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
                                                        </div>
                                                        <div class="flex items-center gap-2 pl-8">
                                                            <span class="text-xs font-bold text-gray-500 w-24">Validierung:</span>
                                                            <TextInput v-model="element.validation_rule" placeholder="Laravel Validation (z.B. required|min:5|numeric)" class="flex-1 text-xs py-1 text-gray-500 bg-white" />
                                                        </div>
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
                                        <template v-if="project.media && project.media.find(m => m.collection_name === 'project_image')">
                                            <img :src="project.media.find(m => m.collection_name === 'project_image').original_url" class="object-cover w-full h-full" />
                                            <button @click.prevent="deleteMedia(project.media.find(m => m.collection_name === 'project_image').id)" class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 shadow hover:scale-110 z-10"><XMarkIcon class="w-4 h-4" /></button>
                                        </template>
                                        <span v-else class="text-gray-400 text-sm">Kein Bild</span>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 md:col-span-1 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Aktuelles Preview</p>
                                    <div class="w-full aspect-video bg-gray-200 rounded relative overflow-hidden flex items-center justify-center">
                                        <template v-if="project.media && project.media.find(m => m.collection_name === 'preview_image')">
                                            <img :src="project.media.find(m => m.collection_name === 'preview_image').original_url" class="object-cover w-full h-full" />
                                            <button @click.prevent="deleteMedia(project.media.find(m => m.collection_name === 'preview_image').id)" class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 shadow hover:scale-110 z-10"><XMarkIcon class="w-4 h-4" /></button>
                                        </template>
                                        <span v-else class="text-gray-400 text-sm">Kein Bild</span>
                                    </div>
                                </div>
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 flex-col items-center relative group">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Aktuelles Projekt PDF</p>
                                    <div v-if="project.media && project.media.find(m => m.collection_name === 'project_pdf')" class="w-full h-full relative">
                                        <div class="w-full py-2 bg-white border text-center rounded relative">
                                            <a target="_blank" :href="project.media.find(m => m.collection_name === 'project_pdf').original_url" class="text-brand-600 hover:underline font-bold inline-block mr-8">PDF ansehen / herunterladen</a>
                                            <button @click.prevent="deleteMedia(project.media.find(m => m.collection_name === 'project_pdf').id)" class="absolute right-2 top-1/2 -translate-y-1/2 bg-red-600 text-white rounded-full p-0.5 shadow hover:scale-110"><XMarkIcon class="w-3 h-3" /></button>
                                        </div>
                                    </div>
                                    <div v-else class="w-full py-2 bg-gray-200 text-center rounded text-gray-400 text-sm">Kein PDF hochgeladen</div>
                                </div>
                                <!-- Logo -->
                                <div class="border rounded-lg p-3 bg-gray-50 flex col-span-2 md:col-span-1 flex-col items-center">
                                    <p class="font-bold text-sm mb-2 text-gray-700">Logo (für PDF & Sidebar)</p>
                                    <div class="w-full h-24 bg-gray-200 rounded relative flex items-center justify-center">
                                        <template v-if="project.media && project.media.find(m => m.collection_name === 'logo')">
                                            <img :src="project.media.find(m => m.collection_name === 'logo').original_url" class="max-h-full max-w-full object-contain p-2" />
                                            <button @click.prevent="deleteMedia(project.media.find(m => m.collection_name === 'logo').id)" class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 shadow hover:scale-110 z-10"><XMarkIcon class="w-3 h-3" /></button>
                                        </template>
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
                        <div class="flex items-center gap-2">
                            <PrimaryButton v-if="editingEntity.type !== 'view'" @click="editInline('view')">Neue Ansicht</PrimaryButton>
                            <div v-if="isGeneratingDepths" class="flex-1 mr-4">
                                <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: generationProgress + '%' }"></div>
                                </div>
                                <p class="text-xs text-indigo-600 mt-1 font-bold text-right">{{ generationProgress }}%</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="deleteDepthMaps" :disabled="isGeneratingDepths" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-sm">
                                    <Cog6ToothIcon class="w-4 h-4 mr-2" />
                                    Tiefenkarten löschen
                                </button>
                                <button @click="generateDepthMaps" :disabled="isGeneratingDepths" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-sm">
                                    <Cog6ToothIcon v-if="isGeneratingDepths" class="w-4 h-4 mr-2 animate-spin" />
                                    <SparklesIcon v-else class="w-4 h-4 mr-2" />
                                    {{ isGeneratingDepths ? 'Generiere...' : 'KI Tiefenkarten erzeugen' }}
                                </button>
                            </div>
                        </div>
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
                                    <th class="w-10 px-6 py-3"></th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sortierung</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Haus</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grundriss (2D)</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                </tr>
                            </thead>
                            <draggable tag="tbody" :list="filteredFloors" item-key="id" @end="updateFloorOrder" class="bg-white divide-y divide-gray-200 cursor-move" handle=".drag-handle">
                                <template #item="{ element: floor }">
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4 text-gray-400 drag-handle text-center">
                                            <svg class="w-5 h-5 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                                        </td>
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
                                </template>
                            </draggable>
                            <tbody class="bg-white" v-if="filteredFloors.length === 0">
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">Keine Etagen gefunden.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Apartments (Wohnungen) -->
                <div v-if="activeTab === 'apartments'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex flex-col gap-4">
                            <h3 class="text-lg font-bold flex items-center gap-2"><Squares2X2Icon class="w-6 h-6 text-brand-500"/> Wohnungen</h3>
                            <div class="flex items-center gap-4 flex-wrap">
                                <div class="relative">
                                    <MagnifyingGlassIcon class="w-5 h-5 absolute left-2 top-2 text-gray-400" />
                                    <input v-model="searchQueries.apartments" type="text" placeholder="Suchen..." class="pl-8 border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                                </div>
                                <select v-model="filterApartmentHouse" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500 w-40">
                                    <option value="">Alle Häuser</option>
                                    <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                                <select v-model="filterApartmentFloor" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500 w-40">
                                    <option value="">Alle Etagen</option>
                                    <option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>
                        </div>
                        <PrimaryButton v-if="editingEntity.type !== 'apartment'" @click="editInline('apartment')" class="self-start">Neue Wohnung</PrimaryButton>
                    </div>
                    
                    <!-- Inline Form for Apartment -->
                    <div v-if="editingEntity.type === 'apartment'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                        <h4 class="font-bold mb-4">{{ modalData.id ? 'Wohnung bearbeiten' : 'Neue Wohnung' }}</h4>
                        <div class="space-y-3">
                            <!-- Accordion: Basisdaten -->
                            <div class="border rounded-md bg-white overflow-hidden shadow-sm">
                                <button type="button" @click.prevent="activeApartmentAccordion = activeApartmentAccordion === 'basis' ? '' : 'basis'" class="w-full text-left font-bold bg-gray-50 hover:bg-gray-100 px-4 py-3 border-b flex justify-between items-center text-gray-700 transition">
                                    <span class="flex items-center gap-2">Basisdaten, Flächen & Preise</span>
                                    <svg :class="{'rotate-180': activeApartmentAccordion === 'basis'}" class="w-5 h-5 transition-transform text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div v-show="activeApartmentAccordion === 'basis'" class="p-4 bg-white">
                                    <div class="mb-4">
                                        <InputLabel value="Name / Bezeichnung" />
                                        <TextInput v-model="modalData.name" class="mt-1 block w-full" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><InputLabel value="Haus" /><select v-model="modalData.house_id" class="border-gray-300 focus:border-brand-500 rounded-md shadow-sm mt-1 block w-full"><option :value="null">-</option><option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option></select></div>
                                        <div><InputLabel value="Etage" /><select v-model="modalData.floor_id" class="border-gray-300 focus:border-brand-500 rounded-md shadow-sm mt-1 block w-full"><option :value="null">-</option><option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option></select></div>
                                        <div><InputLabel value="Zimmer" /><TextInput v-model="modalData.rooms" type="number" step="0.5" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Bäder" /><TextInput v-model="modalData.bathrooms" type="number" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Quadratmeter (qm)" /><TextInput v-model="modalData.sqm" type="number" step="0.1" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Außenfläche (Balkon/Terrasse) qm" /><TextInput v-model="modalData.outdoor_area" type="number" step="0.01" class="mt-1 block w-full" /></div>
                                        <div>
                                            <InputLabel value="Vermarktung" />
                                            <select v-model="modalData.marketing_type" class="border-gray-300 focus:border-brand-500 rounded-md shadow-sm mt-1 block w-full"><option>Verkauf</option><option>Miete</option></select>
                                        </div>
                                        <div>
                                            <InputLabel value="Status" />
                                            <select v-model="modalData.status" class="border-gray-300 focus:border-brand-500 rounded-md shadow-sm mt-1 block w-full"><option>Frei</option><option>Reserviert</option><option>Vermietet</option><option>Verkauft</option></select>
                                        </div>
                                        <div><InputLabel value="Kaufpreis / Kaltmiete (€)" /><TextInput v-model="modalData.price" type="number" step="0.01" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Nebenkosten (€)" /><TextInput v-model="modalData.additional_costs" type="number" step="0.01" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Warmmiete (€)" /><TextInput v-model="modalData.warm_rent" type="number" step="0.01" class="mt-1 block w-full" /></div>
                                        <div><InputLabel value="Bezug ab" /><TextInput v-model="modalData.available_from" class="mt-1 block w-full" placeholder="z.B. sofort / 01.12.2026" /></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Accordion: Objektdetails & Ausstattung -->
                            <div class="border rounded-md bg-white overflow-hidden shadow-sm">
                                <button type="button" @click.prevent="activeApartmentAccordion = activeApartmentAccordion === 'details' ? '' : 'details'" class="w-full text-left font-bold bg-gray-50 hover:bg-gray-100 px-4 py-3 border-b flex justify-between items-center text-gray-700 transition">
                                    <span class="flex items-center gap-2">Objektdetails & Ausstattung</span>
                                    <svg :class="{'rotate-180': activeApartmentAccordion === 'details'}" class="w-5 h-5 transition-transform text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div v-show="activeApartmentAccordion === 'details'" class="p-4 bg-white space-y-4">
                                    <div>
                                    <div class="flex justify-between items-end mb-1">
                                        <InputLabel value="Kurzbeschreibung (Exposé-Text)" />
                                        <div class="flex items-center gap-2">
                                            <button @click="generateAiPitch" :disabled="isAiPitchLoading" class="text-brand-600 hover:text-brand-800 text-[10px] font-bold flex items-center gap-1 bg-brand-50 px-2 py-0.5 rounded border border-brand-200 transition">
                                                <SparklesIcon v-if="!isAiPitchLoading" class="w-3 h-3" />
                                                <svg v-else class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                {{ isAiPitchLoading ? 'KI schreibt...' : 'Auto-Text aus Daten' }}
                                            </button>
                                            <button @click.prevent="descAiTarget = 'apartment'; showDescAiModal = true" class="text-[10px] bg-brand-50 text-brand-600 px-2 py-0.5 rounded border border-brand-200 font-bold hover:bg-brand-100 flex items-center gap-1 transition">
                                                <SparklesIcon class="w-3 h-3"/> KI Text Editor
                                            </button>
                                        </div>
                                    </div>
                                    <textarea v-model="modalData.description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-500 rounded-md shadow-sm text-sm" placeholder="Professioneller Verkaufstext..."></textarea>
                                    </div>
                                    <div>
                                        <InputLabel value="Ausstattungsmerkmale (Features)" class="mb-2" />
                                        <div class="flex flex-wrap gap-4">
                                            <label v-for="feature in project.features" :key="feature.id" class="flex items-center gap-2 text-sm bg-gray-50 border px-3 py-1 rounded shadow-sm cursor-pointer hover:bg-gray-100">
                                                <input type="checkbox" :value="feature.id" v-model="modalData.features" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4">
                                                {{ feature.name }}
                                            </label>
                                            <span v-if="!project.features?.length" class="text-xs text-gray-500">Keine Ausstattung im Projekt angelegt.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Accordion: Verknüpfungen & Aktionen -->
                            <div class="border rounded-md bg-white overflow-hidden shadow-sm">
                                <button type="button" @click.prevent="activeApartmentAccordion = activeApartmentAccordion === 'links' ? '' : 'links'" class="w-full text-left font-bold bg-gray-50 hover:bg-gray-100 px-4 py-3 border-b flex justify-between items-center text-gray-700 transition">
                                    <span class="flex items-center gap-2">Links, Routings & Eigene Buttons</span>
                                    <svg :class="{'rotate-180': activeApartmentAccordion === 'links'}" class="w-5 h-5 transition-transform text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div v-show="activeApartmentAccordion === 'links'" class="p-4 bg-white space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><InputLabel value="3D-Rundgang (URL)" /><TextInput v-model="modalData.virtual_tour_url" class="mt-1 block w-full" placeholder="https://..." /></div>
                                        <div><InputLabel value="Oft. Kontakt / Lead URL" /><TextInput v-model="modalData.external_contact_url" class="mt-1 block w-full" placeholder="https://..." /></div>
                                    </div>
                                    <div class="border-t pt-4">
                                        <InputLabel value="Eigene Buttons (Aktions-Buttons in der Sidebar)" class="mb-2" />
                                        <draggable v-if="modalData.custom_buttons?.length" v-model="modalData.custom_buttons" item-key="id" handle=".cursor-move" class="space-y-2 mb-2">
                                            <template #item="{ element, index }">
                                                <div class="flex flex-wrap gap-2 items-center bg-gray-50 p-2 border border-gray-200 rounded">
                                                    <div class="flex flex-col cursor-move text-gray-400 select-none px-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg></div>
                                                    <TextInput v-model="element.title" placeholder="Titel (z.B. Zum virtuellen Rundgang)" class="flex-1 min-w-[150px] text-sm py-1.5" />
                                                    <select v-model="element.action_type" class="border-gray-300 focus:border-brand-500 rounded text-sm w-36 py-1.5"><option value="slider">Slider / Bilder</option><option value="tour_point">Virtual Tour</option><option value="url">Link (URL)</option><option value="video">Video (URL)</option><option value="iframe">Iframe (Popup)</option><option value="tooltip">Info Tooltip</option><option value="apartment">Wohnung öffnen</option><option value="ansicht">Ansicht (View)</option><option value="etage">Etage</option><option value="house">Haus öffnen</option></select>
                                                    <TextInput v-if="element.action_type === 'url' || element.action_type === 'iframe'" v-model="element.action_target" placeholder="https://" class="flex-1 min-w-[150px] text-sm py-1.5" />
                                                    <select v-else-if="element.action_type === 'slider'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><option v-for="s in project.sliders" :key="s.id" :value="s.id">{{ s.name }}</option></select>
                                                    <select v-else-if="element.action_type === 'tour_point'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><template v-for="t in project.virtual_tours" :key="t.id"><optgroup :label="t.name"><option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option></optgroup></template></select>
                                                    <select v-else-if="element.action_type === 'apartment'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><option v-for="a in project.apartments" :key="a.id" :value="a.id">{{ a.name }}</option></select>
                                                    <select v-else-if="element.action_type === 'house'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option></select>
                                                    <select v-else-if="element.action_type === 'ansicht'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><option v-for="v in project.views" :key="v.id" :value="v.id">{{ v.name }}</option></select>
                                                    <select v-else-if="element.action_type === 'etage'" v-model="element.action_target" class="border-gray-300 focus:border-brand-500 rounded text-sm flex-1 min-w-[150px] py-1.5"><option v-for="f in project.floors" :key="f.id" :value="f.id">{{ f.name }}</option></select>
                                                    <TextInput v-else-if="element.action_type === 'tooltip'" v-model="element.action_target" placeholder="Text..." class="flex-1 min-w-[150px] text-sm py-1.5" />
                                                    <button @click.prevent="modalData.custom_buttons.splice(index, 1)" class="text-red-500 hover:text-red-700 ml-1 p-1"><XMarkIcon class="w-5 h-5" /></button>
                                                </div>
                                            </template>
                                        </draggable>
                                        <SecondaryButton @click="addCustomButton" class="text-xs py-1.5">Button hinzufügen</SecondaryButton>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Accordion: CRM & Ansprechpartner -->
                            <div class="border rounded-md bg-white overflow-hidden shadow-sm">
                                <button type="button" @click.prevent="activeApartmentAccordion = activeApartmentAccordion === 'crm' ? '' : 'crm'" class="w-full text-left font-bold bg-gray-50 hover:bg-gray-100 px-4 py-3 border-b flex justify-between items-center text-gray-700 transition">
                                    <span class="flex items-center gap-2">CRM, Portal-Verknüpfung & Ansprechpartner</span>
                                    <svg :class="{'rotate-180': activeApartmentAccordion === 'crm'}" class="w-5 h-5 transition-transform text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                                <div v-show="activeApartmentAccordion === 'crm'" class="p-4 bg-white space-y-6">
                                    <div>
                                        <h5 class="text-sm font-bold text-gray-800 mb-2 flex items-center gap-2"><ArrowUpOnSquareStackIcon class="w-4 h-4 text-brand-500" />CRM / Externe Verknüpfung</h5>
                                        <div class="flex items-end gap-2">
                                            <div class="flex-1">
                                                <select v-model="modalData.external_property_id" class="border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm block w-full text-sm">
                                                    <option :value="null">-- Keine Verknüpfung (Nur lokal) --</option>
                                                    <option v-for="ext in externalProperties" :key="ext.id" :value="ext.id">
                                                        {{ ext.integration?.platform_name }}: {{ ext.name || ext.external_id }}
                                                    </option>
                                                </select>
                                            </div>
                                            <SecondaryButton v-if="modalData.id && modalData.external_property_id" @click="syncExternalProperty(modalData.id, modalData.external_property_id)" class="shrink-0 text-xs py-1.5">
                                                <ArrowPathIcon class="w-4 h-4 mr-1" /> Importiere aktuelle Daten
                                            </SecondaryButton>
                                        </div>
                                    </div>
                                    
                                    <div class="border-t pt-4">
                                        <h5 class="text-sm font-bold text-gray-800 mb-2 flex items-center gap-2"><UserGroupIcon class="w-4 h-4 text-brand-500" /> Spezifische Ansprechpartner für diese Wohnung</h5>
                                        <p class="text-xs text-gray-500 mb-3">Werden keine ausgewählt, greift das Kontaktformular auf die Projekt-Ansprechpartner zurück.</p>
                                        <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                                            <label v-for="contact in teamContacts" :key="contact.id" class="flex items-center gap-3 text-sm text-gray-700 bg-gray-50 p-2 rounded border cursor-pointer hover:bg-gray-100 transition">
                                                <input type="checkbox" :value="contact.id" v-model="modalData.contact_ids" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                                <img v-if="contact.media?.length" :src="contact.media.find(m => m.collection_name === 'avatar' || m.collection_name === 'default')?.original_url" class="w-8 h-8 rounded-full object-cover bg-white border" />
                                                <div v-else class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 font-bold text-xs">{{ contact.name.charAt(0) }}</div>
                                                <div class="flex-1">
                                                    <div class="font-bold">{{ contact.name }}</div>
                                                    <div class="text-xs text-gray-400">{{ contact.position || 'Ansprechpartner' }}</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
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
                                    <thead class="bg-gray-50 border-b">
                                        <tr><th class="px-2 py-1">Raum</th><th class="px-2 py-1 w-24">qm</th><th class="px-2 py-1 w-16"></th></tr>
                                    </thead>
                                    <draggable tag="tbody" :list="currentApartment.rooms_list" item-key="id" @end="updateRoomOrder" class="divide-y divide-gray-200 cursor-move" handle=".drag-handle">
                                        <template #item="{ element: room }">
                                            <tr class="border-b">
                                                <td class="px-2 py-1"><Bars3CenterLeftIcon class="w-4 h-4 drag-handle inline-block mr-2 cursor-move text-gray-400 hover:text-gray-600" />{{ room.name }}</td>
                                                <td class="px-2 py-1">{{ room.sqm || '-' }}</td>
                                                <td class="px-2 py-1"><button @click="deleteEntity('Room', room.id)" class="text-red-500 hover:text-red-700"><XMarkIcon class="w-4 h-4" /></button></td>
                                            </tr>
                                        </template>
                                    </draggable>
                                    <tfoot class="font-bold bg-gray-50">
                                        <tr>
                                            <td class="px-2 py-1 text-right border-t">Gesamt (Räume):</td>
                                            <td class="px-2 py-1 border-t">{{ roomTotalSqm }} qm</td>
                                            <td class="px-2 py-1 border-t"></td>
                                        </tr>
                                    </tfoot>
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
                                            <input type="file" accept="image/*" multiple @change="e => uploadMedia(e, 'ApartmentImageGroup', group.id, 'default')" class="text-xs block text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300" :disabled="uploadingMediaContext === 'ApartmentImageGroup-' + group.id + '-default'" />
                                            <span v-if="uploadingMediaContext === 'ApartmentImageGroup-' + group.id + '-default'" class="text-xs font-bold text-brand-600 outline-none animate-pulse">Lade {{ uploadMediaProgress }}%...</span>
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
                            <div class="flex items-center gap-2 mt-4">
                                <input type="checkbox" v-model="modalData.sun_simulation" id="sun_sim" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                                <label for="sun_sim" class="text-sm text-gray-700">Licht & Sonnenstandsimulation (3D) für diesen Layer aktivieren</label>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-6">
                            <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                            <PrimaryButton @click="saveEntity('Layer')">Speichern</PrimaryButton>
                        </div>
                    </div>

                    <div v-if="editingEntity.type !== 'layer'">
                        <draggable 
                            :list="filteredLayers" 
                            item-key="id" 
                            @end="updateLayerOrder" 
                            class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4"
                        >
                            <template #item="{ element: layer }">
                                <div class="border rounded-lg p-4 text-center bg-gray-50 relative group cursor-move hover:shadow-md transition">
                                    <p class="font-bold text-gray-700 truncate">{{ layer.name }}</p>
                                    <div class="mt-3 flex justify-center gap-3 opacity-0 group-hover:opacity-100 transition">
                                        <button @click="editInline('layer', layer)" class="text-brand-600 hover:text-brand-900 text-xs font-medium">Edit</button>
                                        <button @click="deleteEntity('Layer', layer.id)" class="text-red-600 hover:text-red-900 text-xs font-medium">Del</button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                        <div v-if="filteredLayers.length === 0" class="col-span-full py-8 text-center text-gray-500">Keine Layer gefunden</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW TAB: Features -->
        <div v-if="activeTab === 'features'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold flex items-center gap-2"><StarIcon class="w-6 h-6 text-brand-500"/> Projekt Ausstattung</h3>
                <PrimaryButton v-if="editingEntity.type !== 'feature'" @click="editInline('feature')">Neue Ausstattung</PrimaryButton>
            </div>
            <div v-if="editingEntity.type === 'feature'" class="bg-gray-50 p-6 rounded-lg border mb-6">
                <h4 class="font-bold mb-4">{{ modalData.id ? 'Ausstattung bearbeiten' : 'Neue Ausstattung' }}</h4>
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
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-gray-800">{{ modalData.id ? 'Infoframe bearbeiten' : 'Neuer Infoframe' }}</h4>
                    <span class="text-xs bg-brand-100 text-brand-700 font-bold px-2 py-0.5 rounded uppercase tracking-wider">AI Powered Editor</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[700px]">
                    <!-- LEFT: AI Chat Sidebar -->
                    <div class="lg:col-span-4 flex flex-col bg-white border rounded-xl overflow-hidden shadow-sm">
                        <div class="p-3 border-b bg-gray-50 flex items-center gap-2">
                             <SparklesIcon class="w-4 h-4 text-brand-600" />
                             <span class="text-xs font-black text-gray-700 uppercase tracking-widest">KI Assistent (Llama3)</span>
                        </div>
                        
                        <!-- Chat History -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50/50 custom-scrollbar" id="aiChatWindow">
                            <div v-if="!aiChatMessages.length" class="h-full flex flex-col items-center justify-center text-center opacity-40 px-6">
                                <SparklesIcon class="w-10 h-10 mb-2" />
                                <p class="text-sm font-medium italic">Frage die KI nach Änderungen oder Inhalten für diesen Frame.</p>
                            </div>
                            
                            <div v-for="(msg, idx) in aiChatMessages" :key="idx" :class="['flex flex-col', msg.role === 'user' ? 'items-end' : 'items-start']">
                                <div :class="['max-w-[90%] px-3 py-2 rounded-2xl text-sm shadow-sm', msg.role === 'user' ? 'bg-brand-600 text-white rounded-tr-none' : 'bg-white text-gray-800 border rounded-tl-none']">
                                    <div class="whitespace-pre-wrap font-medium leading-relaxed">{{ msg.content }}</div>
                                    
                                    <!-- Apply code button if found -->
                                    <div v-if="msg.role === 'assistant' && extractCodeFromMessage(msg.content)" class="mt-3 pt-3 border-t">
                                        <button @click="applyAiCode(msg.content)" class="w-full py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 text-xs font-black rounded-lg transition-all flex items-center justify-center gap-2">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                            Code übernehmen
                                        </button>
                                    </div>
                                </div>
                                <span class="text-[9px] font-bold text-gray-400 mt-1 uppercase px-1">{{ msg.role }}</span>
                            </div>
                            
                            <div v-if="isAiLoading" class="flex gap-1.5 p-3">
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                            </div>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="p-3 border-t bg-white">
                            <div class="relative">
                                <textarea 
                                    v-model="aiCurrentPrompt" 
                                    @keydown.enter.prevent="sendAiChat"
                                    placeholder="Änderungswunsch..." 
                                    class="w-full pl-3 pr-10 py-2 border-gray-300 rounded-xl text-sm focus:ring-brand-500 focus:border-brand-500 resize-none min-h-[45px] max-h-[100px]"
                                ></textarea>
                                <button @click="sendAiChat" :disabled="!aiCurrentPrompt.trim() || isAiLoading" class="absolute right-2 bottom-2 p-1.5 text-brand-600 hover:text-brand-800 disabled:opacity-30 transition">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Tabbed Editor -->
                    <div class="lg:col-span-8 flex flex-col bg-white border rounded-xl overflow-hidden shadow-sm">
                        <!-- Toolbar/Tabs -->
                        <div class="px-4 py-2 border-b bg-gray-50 flex items-center justify-between gap-4">
                            <div class="flex p-0.5 bg-gray-200/60 rounded-lg">
                                <button v-for="t in ['visual', 'code', 'preview']" :key="t" @click="infoframeTab = t"
                                        :class="['px-3 py-1 rounded-md text-xs font-black uppercase tracking-wider transition-all', infoframeTab === t ? 'bg-white text-brand-600 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                                    {{ t === 'visual' ? 'Editor' : (t === 'code' ? 'HTML Code' : 'Vorschau') }}
                                </button>
                            </div>
                            <div class="flex-1">
                                <input v-model="modalData.name" placeholder="Name des Frames..." class="w-full bg-transparent border-none focus:ring-0 text-sm font-bold text-gray-700" />
                            </div>
                        </div>
                        
                        <!-- Editor Body -->
                        <div class="flex-1 min-h-0 bg-white">
                            <!-- Visual Editor -->
                            <div v-show="infoframeTab === 'visual'" class="h-full flex flex-col pt-1">
                                <QuillEditor 
                                    :content="modalData.content" 
                                    @update:content="(val) => { if (infoframeTab === 'visual') modalData.content = val; }"
                                    contentType="html" 
                                    toolbar="full" 
                                    theme="snow" 
                                    class="flex-1" 
                                />
                            </div>
                            
                            <!-- Code Editor -->
                            <div v-show="infoframeTab === 'code'" class="h-full flex flex-col p-2">
                                <textarea 
                                    v-model="modalData.content" 
                                    class="w-full flex-1 font-mono text-sm p-4 bg-gray-900 text-gray-200 border-none rounded-lg focus:ring-0 resize-none selection:bg-brand-500/30"
                                    spellcheck="false"
                                ></textarea>
                            </div>
                            
                            <!-- Preview View -->
                            <div v-show="infoframeTab === 'preview'" class="h-full overflow-y-auto p-8 bg-white border-l border-r border-gray-100 flex justify-center">
                                <div class="w-full max-w-4xl bg-white shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] rounded-xl border p-12 prose prose-zinc max-w-none prose-headings:text-gray-900" v-html="modalData.content">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 mt-6 justify-end">
                    <SecondaryButton @click="closeInline">Abbrechen</SecondaryButton>
                    <PrimaryButton @click="saveEntity('Infoframe')" :disabled="isAiLoading">Änderungen speichern</PrimaryButton>
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

            <!-- Slider List (compact) -->
            <div class="space-y-3">
                <div v-for="slider in project.sliders" :key="slider.id" class="border rounded-lg overflow-hidden">
                    <!-- Slider Header (always visible) -->
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition" @click="expandedSlider = expandedSlider === slider.id ? null : slider.id">
                        <div class="flex items-center gap-3">
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="expandedSlider === slider.id ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            <BuildingStorefrontIcon class="w-4 h-4 text-brand-500"/>
                            <span class="font-bold text-gray-800">{{ slider.name }}</span>
                            <span class="text-xs text-gray-400 font-mono">ID: {{ slider.id }}</span>
                            <span class="bg-brand-100 text-brand-700 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ slider.slides?.length || 0 }} Slides</span>
                        </div>
                        <button @click.stop="deleteSlider(slider.id)" class="text-red-400 hover:text-red-600 p-1" title="Slider löschen">
                            <TrashIcon class="w-4 h-4"/>
                        </button>
                    </div>

                    <!-- Expanded: Slides + Add Form -->
                    <div v-show="expandedSlider === slider.id" class="p-4 border-t">
                        <!-- Slides (sortable) -->
                        <div class="space-y-2 mb-4">
                            <div v-for="(slide, idx) in sortedSlides(slider)" :key="slide.id" class="bg-gray-50 border rounded p-3 flex items-center gap-3">
                                <!-- Sort Arrows -->
                                <div class="flex flex-col gap-0.5 shrink-0">
                                    <button @click="moveSlide(slider, idx, -1)" :disabled="idx === 0" class="text-gray-400 hover:text-gray-700 disabled:opacity-30 p-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </button>
                                    <button @click="moveSlide(slider, idx, 1)" :disabled="idx === (slider.slides?.length || 0) - 1" class="text-gray-400 hover:text-gray-700 disabled:opacity-30 p-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </div>
                                <!-- Thumbnail -->
                                <div class="w-12 h-12 shrink-0 bg-gray-200 rounded overflow-hidden">
                                    <img v-if="slide.media?.[0]" :src="slide.media[0].original_url" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-[10px]">–</div>
                                </div>
                                <div class="flex-1 text-sm min-w-0">
                                    <div class="font-bold text-gray-700 truncate">{{ slide.title || '(Kein Titel)' }}</div>
                                    <div class="text-xs text-gray-500">Typ: <span class="font-semibold">{{ slide.type }}</span></div>
                                </div>
                                <button @click="deleteSlide(slide.id)" class="text-red-400 hover:text-red-600 shrink-0 p-1"><XMarkIcon class="w-4 h-4"/></button>
                            </div>
                            <p v-if="!slider.slides?.length" class="text-xs text-gray-500 italic py-2 text-center">Noch keine Slides.</p>
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
                </div>
                <p v-if="!project.sliders?.length" class="text-gray-500 text-center py-8">Noch keine Slider. Lege oben einen an.</p>
            </div>
        </div>

        <!-- TAB: Statistics -->
        <div v-if="activeTab === 'statistics'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <ProjectStatistics :projectId="project.id" :apartments="project.apartments || []" />
        </div>

        <!-- TAB: Auto-Tour -->
        <div v-if="activeTab === 'autotour'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <form @submit.prevent="updateProject">
                <div class="mb-6 bg-[#f8f9fa] p-4 border border-gray-200 rounded-md">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block font-bold text-gray-700 text-base">🎬 Auto-Tour Settings (Story-Builder)</label>
                        <div class="flex items-center gap-2">
                             <span class="text-sm text-gray-500">Aktivieren:</span>
                             <input type="checkbox" v-model="projectForm.auto_tour_settings.active" class="w-5 h-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500 cursor-pointer">
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-4 pb-4 border-b border-gray-200">
                        <div>
                           <span class="text-xs text-gray-500 block mb-1">Beschränken auf Ansicht (optional):</span>
                           <select v-model="projectForm.auto_tour_settings.view_id" class="text-sm border-gray-300 rounded w-48 py-1">
                               <option :value="null">Alle Ansichten</option>
                               <option v-for="v in project.views" :key="v.id" :value="v.id">{{ v.name }}</option>
                           </select>
                        </div>
                        <div>
                           <span class="text-xs text-gray-500 block mb-1">Beschränken auf Layer (optional):</span>
                           <select v-model="projectForm.auto_tour_settings.layer_id" class="text-sm border-gray-300 rounded w-48 py-1">
                               <option :value="null">Alle Layer</option>
                               <option v-for="l in project.layers" :key="l.id" :value="l.id">{{ l.name }}</option>
                           </select>
                        </div>
                        <div class="ml-auto text-xs text-gray-400 max-w-xs text-right">
                            Wenn Ansicht & Layer leer sind, erscheint die Auto-Tour auf der Start-Ebene.
                        </div>
                    </div>
                    
                    <div v-if="projectForm.auto_tour_settings.active" class="mt-4">
                        <draggable v-model="projectForm.auto_tour_settings.storyboard" item-key="uuid" handle=".cursor-move" class="space-y-2 mb-4">
                            <template #item="{ element, index }">
                                <div class="flex items-start gap-3 bg-white p-3 border border-gray-200 rounded-lg shadow-[0_2px_4px_rgba(0,0,0,0.02)] relative group hover:border-brand-300 transition-colors">
                                    <span class="cursor-move text-gray-400 p-2 hover:text-brand-500 flex items-center shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                    </span>
                                    
                                    <select v-model="element.type" class="text-sm border-gray-300 rounded-md py-2 px-3 w-40 shrink-0 shadow-sm">
                                        <option value="spin_to">Kamerafahrt</option>
                                        <option value="tooltip">Tooltip Text</option>
                                        <option value="video">Pop-up Video</option>
                                        <option value="audio">Audio (mp3)</option>
                                        <option value="highlight">Wohnung Highlight</option>
                                        <option value="virtual_tour">Virtuelle Tour öffnen</option>
                                    </select>

                                    <!-- Type Specific Inputs -->
                                    <div class="flex-1 flex gap-3 min-w-0">
                                        <input v-if="element.type === 'spin_to'" v-model.number="element.targetFrameIndex" type="number" placeholder="Ziel Frame (z.B. 45)" class="text-sm border-gray-300 rounded-md w-full py-2 px-3 shadow-sm">
                                        <input v-if="element.type === 'tooltip'" v-model="element.text" type="text" placeholder="Beispiel-Text..." class="text-sm border-gray-300 rounded-md w-full py-2 px-3 shadow-sm">
                                        <input v-if="element.type === 'video' || element.type === 'audio'" v-model="element.url" type="text" placeholder="https://... (.mp4 oder .mp3)" class="text-sm border-gray-300 rounded-md w-full py-2 px-3 shadow-sm">
                                        <select v-if="element.type === 'highlight'" v-model="element.apartment_id" class="text-sm border-gray-300 rounded-md w-full py-2 px-3 shadow-sm">
                                            <option value="">Wohnung wählen...</option>
                                            <option v-for="ap in project.apartments" :key="ap.id" :value="ap.id">{{ ap.name || 'Wohnung ' + ap.id }}</option>
                                        </select>
                                        
                                        <select v-if="element.type === 'virtual_tour'" v-model="element.virtual_tour_id" class="text-sm border-gray-300 rounded-md w-full py-2 px-3 shadow-sm">
                                            <option value="">Tour wählen...</option>
                                            <option v-for="vt in project.virtual_tours" :key="vt.id" :value="vt.id">{{ vt.name }}</option>
                                        </select>
                                        <input v-if="element.type === 'virtual_tour'" v-model="element.yaw" type="number" step="0.01" placeholder="Yaw (optional)" class="text-sm border-gray-300 rounded-md w-24 py-2 px-3 shadow-sm">
                                        <input v-if="element.type === 'virtual_tour'" v-model="element.pitch" type="number" step="0.01" placeholder="Pitch (optional)" class="text-sm border-gray-300 rounded-md w-24 py-2 px-3 shadow-sm">
                                        
                                        <!-- Duration -->
                                        <div class="flex items-center gap-2 w-32 shrink-0 relative">
                                            <input v-model.number="element.duration" type="number" step="100" class="text-sm border-gray-300 rounded-md w-full pl-3 pr-8 py-2 shadow-sm" placeholder="Dauer">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium pointer-events-none">ms</span>
                                        </div>
                                    </div>

                                    <button @click.prevent="projectForm.auto_tour_settings.storyboard.splice(index, 1)" class="text-gray-400 hover:text-red-600 p-2 shrink-0 transition-colors bg-gray-50 rounded hover:bg-red-50">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </template>
                        </draggable>
                        
                        <div class="flex gap-2">
                            <SecondaryButton @click="() => { if(!projectForm.auto_tour_settings.storyboard) projectForm.auto_tour_settings.storyboard = []; projectForm.auto_tour_settings.storyboard.push({ type: 'spin_to', duration: 2000, uuid: Date.now() + Math.random().toString() }); }" type="button" class="text-sm py-2 px-4 shadow-sm bg-white hover:bg-gray-50 border-gray-300 text-gray-700">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Leere Aktion hinzufügen
                            </SecondaryButton>
                            <SecondaryButton type="submit" :class="{ 'opacity-50': projectForm.processing }" :disabled="projectForm.processing" class="ml-auto bg-green-50 hover:bg-green-100 text-green-700 border-green-300 py-2 px-4 shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                Einstellungen Speichern
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB: Virtual Tours -->
        <div v-if="activeTab === 'virtual-tours'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <VirtualToursTab :project="project" />
        </div>

        <!-- TAB: CRM / Anbindungen -->
        <div v-if="activeTab === 'crm'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6 inline-block w-full">
            <h3 class="text-lg font-bold flex items-center gap-2 mb-6"><BuildingOfficeIcon class="w-6 h-6 text-brand-500"/> CRM Anbindungen</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                 <div v-for="integration in project.integrations" :key="integration.id" class="border p-4 rounded-lg bg-gray-50">
                     <div class="flex justify-between items-start mb-4">
                         <h4 class="font-bold text-gray-800 capitalize">{{ integration.platform_name }}</h4>
                         <span :class="integration.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500'" class="px-2 py-1 rounded text-xs font-bold uppercase">
                             {{ integration.is_active ? 'Aktiv' : 'Inaktiv' }}
                         </span>
                     </div>
                     <div class="space-y-4">
                         
                         <!-- Details -->
                         <div v-if="integrationForms[integration.id]">
                             <template v-if="integration.platform_name === 'onoffice'">
                                 <div class="mb-2">
                                     <InputLabel value="API Token" />
                                     <TextInput v-model="integrationForms[integration.id].token" class="w-full text-sm mt-1" type="text" />
                                 </div>
                                 <div class="mb-2">
                                     <InputLabel value="API Secret" />
                                     <TextInput v-model="integrationForms[integration.id].secret" class="w-full text-sm mt-1" type="password" />
                                 </div>
                             </template>
                             <template v-if="integration.platform_name === 'flowfact'">
                                 <div class="mb-2">
                                     <InputLabel value="Client ID" />
                                     <TextInput v-model="integrationForms[integration.id].client_id" class="w-full text-sm mt-1" type="text" />
                                 </div>
                                 <div class="mb-2">
                                     <InputLabel value="API Key" />
                                     <TextInput v-model="integrationForms[integration.id].api_key" class="w-full text-sm mt-1" type="password" />
                                 </div>
                             </template>
                             <template v-if="integration.platform_name === 'propstack'">
                                 <div class="mb-2">
                                     <InputLabel value="API Key" />
                                     <TextInput v-model="integrationForms[integration.id].api_key" class="w-full text-sm mt-1" type="password" />
                                 </div>
                             </template>

                             <PrimaryButton @click="saveIntegrationCredentials(integration.id)" class="w-full justify-center text-xs py-2 mt-2">
                                Zugangsdaten speichern
                             </PrimaryButton>
                         </div>

                         <button @click="updateIntegrationStatus(integration, !integration.is_active)" class="w-full text-center text-sm font-bold bg-white border border-gray-300 py-1.5 rounded shadow-sm hover:bg-gray-100 mt-2">
                             {{ integration.is_active ? 'Integration deaktivieren' : 'Integration aktivieren' }}
                         </button>
                         <button @click="deleteIntegration(integration)" class="w-full text-center text-sm font-bold text-red-600 bg-red-50 border border-red-200 py-1.5 rounded shadow-sm hover:bg-red-100 mt-2">
                             Löschen
                         </button>
                     </div>
                 </div>

                 <!-- Add new integration -->
                 <div class="border border-dashed p-4 rounded-lg flex flex-col items-center justify-center gap-3">
                     <select v-model="newIntegrationPlatform" class="border-gray-300 w-full rounded focus:ring-brand-500 text-sm">
                         <option value="">-- Plattform wählen --</option>
                         <option value="onoffice">onOffice</option>
                         <option value="flowfact">FlowFact</option>
                         <option value="propstack">Propstack</option>
                     </select>
                     <PrimaryButton @click="createIntegration" :disabled="!newIntegrationPlatform" class="w-full justify-center">Hinzufügen</PrimaryButton>
                 </div>
            </div>

            <div class="mt-8 border-t pt-8 w-full block">
                 <div class="flex justify-between items-center mb-6">
                     <h3 class="text-lg font-bold">Importierte Externe Immobilien</h3>
                     <PrimaryButton @click="dispatchPropertySync">Import / Sync starten</PrimaryButton>
                 </div>
                 <table class="min-w-full divide-y divide-gray-200 text-sm border rounded">
                      <thead class="bg-gray-50">
                          <tr>
                              <th class="px-4 py-2 text-left">Plattform</th>
                              <th class="px-4 py-2 text-left">Ext. ID</th>
                              <th class="px-4 py-2 text-left">Name</th>
                              <th class="px-4 py-2 text-left">Aktionen</th>
                          </tr>
                      </thead>
                      <tbody>
                           <tr v-for="prop in externalProperties" :key="prop.id" class="border-b bg-white">
                               <td class="px-4 py-2 capitalize font-bold">{{ prop.integration?.platform_name }}</td>
                               <td class="px-4 py-2 text-xs font-mono">{{ prop.external_id }}</td>
                               <td class="px-4 py-2">{{ prop.name }}</td>
                               <td class="px-4 py-2">
                               </td>
                           </tr>
                           <tr v-if="!externalProperties?.length">
                               <td colspan="4" class="px-4 py-6 text-center text-gray-500 italic">Keine Immobilien gefunden</td>
                           </tr>
                      </tbody>
                 </table>
            </div>
        </div>

        <!-- TAB: Team -->
        <div v-if="activeTab === 'team'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6 inline-block w-full">
            <h3 class="text-lg font-bold flex items-center gap-2 mb-6"><UserGroupIcon class="w-6 h-6 text-brand-500"/> Projekt-Team Manger</h3>
            <div class="mb-6 p-4 border rounded bg-gray-50">
                 <h4 class="font-bold text-sm mb-2">Nutzer aus dem Team hinzufügen</h4>
                 <div class="flex flex-wrap gap-2">
                     <select v-model="selectedTeamUser" class="border-gray-300 w-full md:w-64 rounded text-sm focus:ring-brand-500 shadow-sm">
                         <option :value="null">-- Nutzer wählen --</option>
                         <option v-for="tu in teamUsers.filter(u => !syncUsersData.find(su => su.id === u.id))" :key="tu.id" :value="tu.id">
                             {{ tu.name }} ({{ tu.email }})
                         </option>
                     </select>
                     <select v-model="selectedTeamUserRole" class="border-gray-300 rounded text-sm focus:ring-brand-500 shadow-sm">
                         <option value="member">Mitglied (Lesen)</option>
                         <option value="admin">Admin (Schreiben & Berechtigungen)</option>
                     </select>
                     <SecondaryButton @click="addTeamUserToProject" :disabled="!selectedTeamUser">Hinzufügen</SecondaryButton>
                 </div>
            </div>

            <div class="overflow-x-auto w-full">
                <table class="min-w-full divide-y divide-gray-200 border rounded text-sm w-full">
                     <thead class="bg-gray-100">
                         <tr>
                             <th class="px-4 py-2 text-left">Nutzer</th>
                             <th class="px-4 py-2 text-left">Rolle</th>
                             <th class="px-4 py-2 text-left">Aktion</th>
                         </tr>
                     </thead>
                     <tbody class="bg-white text-gray-700">
                         <tr v-for="user in syncUsersData" :key="user.id" class="border-b">
                             <td class="px-4 py-2"><strong>{{ user.name }}</strong> <span class="text-xs text-gray-500 ml-2 hidden md:inline">{{ user.email }}</span></td>
                             <td class="px-4 py-2">
                                  <select v-model="user.pivot.role" class="border-gray-300 rounded-md text-xs p-1.5 focus:ring-brand-500">
                                      <option value="admin">Admin</option>
                                      <option value="member">Mitglied</option>
                                  </select>
                             </td>
                             <td class="px-4 py-2">
                                  <button @click="removeTeamUserFromProject(user.id)" class="text-red-500 hover:text-red-700 text-xs font-bold p-2 bg-red-50 rounded"><XMarkIcon class="w-4 h-4" /></button>
                             </td>
                         </tr>
                         <tr v-if="!syncUsersData?.length">
                             <td colspan="3" class="px-4 py-4 text-center text-gray-500">Keine Nutzer zugewiesen</td>
                         </tr>
                     </tbody>
                </table>
            </div>
            <div class="mt-6 flex justify-end">
                 <PrimaryButton @click="saveProjectTeam">Berechtigungen Speichern</PrimaryButton>
            </div>
        </div>

        <!-- TAB: Settings (Einstellungen) -->
        <div v-if="activeTab === 'settings'" class="bg-white shadow-sm sm:rounded-b-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="text-lg font-bold flex items-center gap-2"><Cog6ToothIcon class="w-6 h-6 text-brand-500"/> Projekt & OpenImmo Einstellungen</h3>
                <div class="flex items-center gap-3">
                    <a :href="`/projects/${project.id}/openimmo-export`" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-600 text-white text-sm font-bold rounded-lg hover:bg-brand-700 transition shadow-sm">
                        <ArrowDownTrayIcon class="w-5 h-5" />
                        OpenImmo XML Export (Alle Wohnungen)
                    </a>
                </div>
            </div>

            <form @submit.prevent="saveProjectDetails" class="space-y-8">
                <!-- OpenImmo Basic -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b">
                    <div class="space-y-4">
                        <h4 class="font-bold text-gray-700 flex items-center gap-2"><BuildingOfficeIcon class="w-5 h-5 text-brand-500" /> OpenImmo Basisdaten</h4>
                        <div>
                            <InputLabel value="Firma (Anbieter Name)" />
                            <TextInput v-model="projectForm.openimmo_settings.firma" class="mt-1 block w-full" placeholder="z.B. Immobilien GmbH" />
                        </div>
                        <div>
                            <InputLabel value="Anbieter ID (AnID)" />
                            <TextInput v-model="projectForm.openimmo_settings.openimmo_anid" class="mt-1 block w-full" placeholder="z.B. anid-12345" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Baujahr" />
                                <TextInput v-model="projectForm.openimmo_settings.baujahr" class="mt-1 block w-full" placeholder="2024" />
                            </div>
                            <div>
                                <InputLabel value="Währung" />
                                <TextInput v-model="projectForm.openimmo_settings.waehrung" class="mt-1 block w-full" placeholder="EUR" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Zustand (Art)" />
                                <select v-model="projectForm.openimmo_settings.zustand_art" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    <option value="ERSTBEZUG">Erstbezug</option>
                                    <option value="NEUBAU">Neubau</option>
                                    <option value="GEPFLEGT">Gepflegt</option>
                                    <option value="MODERNISIERT">Modernisiert</option>
                                    <option value="SANIERUNGSBEDUERFTIG">Sanierungsbedürftig</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="ObjektNr Präfix" />
                                <TextInput v-model="projectForm.openimmo_settings.objektnr_prefix" class="mt-1 block w-full" placeholder="APT-" />
                                <p class="text-[10px] text-gray-400 mt-1">Wird vor die Wohnungs-ID gehängt</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="font-bold text-gray-700 flex items-center gap-2"><InformationCircleIcon class="w-5 h-5 text-brand-500" /> Anbieter Impressum</h4>
                        <div>
                            <InputLabel value="Firmenname (Impressum)" />
                            <TextInput v-model="projectForm.openimmo_settings.impressum_firmenname" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Straße & Hausnummer" />
                            <TextInput v-model="projectForm.openimmo_settings.impressum_strasse" class="mt-1 block w-full" />
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="PLZ" />
                                <TextInput v-model="projectForm.openimmo_settings.impressum_plz" class="mt-1 block w-full" />
                            </div>
                            <div class="col-span-2">
                                <InputLabel value="Ort" />
                                <TextInput v-model="projectForm.openimmo_settings.impressum_ort" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Telefon" />
                                <TextInput v-model="projectForm.openimmo_settings.impressum_telefon" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="E-Mail" />
                                <TextInput v-model="projectForm.openimmo_settings.impressum_email" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Website" />
                            <TextInput v-model="projectForm.openimmo_settings.impressum_website" class="mt-1 block w-full" placeholder="https://..." />
                        </div>
                    </div>
                </div>

                <!-- Kontaktperson -->
                <div class="space-y-4">
                    <h4 class="font-bold text-gray-700 flex items-center gap-2"><UserGroupIcon class="w-5 h-5 text-brand-500" /> Standard Kontaktperson (XML)</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <InputLabel value="Anrede" />
                            <select v-model="projectForm.openimmo_settings.kontakt_anrede" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                <option value="HERR">Herr</option>
                                <option value="FRAU">Frau</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Vorname" />
                            <TextInput v-model="projectForm.openimmo_settings.kontakt_vorname" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Nachname" />
                            <TextInput v-model="projectForm.openimmo_settings.kontakt_nachname" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="E-Mail (Direkt)" />
                            <TextInput v-model="projectForm.openimmo_settings.kontakt_email" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Telefon (Direkt)" />
                            <TextInput v-model="projectForm.openimmo_settings.kontakt_telefon" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <InputLabel value="Straße" />
                            <TextInput v-model="projectForm.openimmo_settings.kontakt_strasse" class="mt-1 block w-full" />
                        </div>
                        <div class="flex gap-4">
                            <div class="w-1/3">
                                <InputLabel value="PLZ" />
                                <TextInput v-model="projectForm.openimmo_settings.kontakt_plz" class="mt-1 block w-full" />
                            </div>
                            <div class="flex-1">
                                <InputLabel value="Ort" />
                                <TextInput v-model="projectForm.openimmo_settings.kontakt_ort" class="mt-1 block w-full" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legal / Datenschutz -->
                <div class="space-y-4 pb-6 border-b">
                    <h4 class="font-bold text-gray-700 flex items-center gap-2">📜 Impressum & Datenschutz (Public)</h4>
                    <p class="text-xs text-gray-500">Diese Texte werden als klickbare Links im öffentlichen Projektfinder angezeigt.</p>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Impressum (HTML)" />
                            <QuillEditor v-model:content="projectForm.legal_settings.impressum" contentType="html" theme="snow" class="mt-1 bg-white min-h-[150px]" />
                        </div>
                        <div>
                            <InputLabel value="Datenschutzerklärung (HTML)" />
                            <QuillEditor v-model:content="projectForm.legal_settings.datenschutz" contentType="html" theme="snow" class="mt-1 bg-white min-h-[150px]" />
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="space-y-4">
                    <h4 class="font-bold text-red-600 flex items-center gap-2">⚠️ Gefahrenzone</h4>
                    <div class="flex items-center gap-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex-1">
                            <p class="font-bold text-sm text-red-800">Projekt duplizieren</p>
                            <p class="text-xs text-red-600">Erstellt eine Kopie dieses Projekts inkl. aller Ansichten, Wohnungen, Etagen etc. (ohne Medien).</p>
                        </div>
                        <button @click="duplicateProject" type="button" class="px-4 py-2 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700 transition shadow-sm">
                            Duplizieren
                        </button>
                    </div>
                </div>

                <div class="pt-6 flex justify-end">
                    <PrimaryButton type="submit" :class="{ 'opacity-50': projectForm.processing }" :disabled="projectForm.processing">
                        Einstellungen permanent speichern
                    </PrimaryButton>
                </div>
            </form>
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
                                <div v-if="bulkUploadLayerId" class="mb-4 space-y-3">
                                    <label class="flex items-center text-sm font-bold text-gray-700 cursor-pointer">
                                        <input type="checkbox" v-model="bulkUploadStartsAtZero" class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 w-4 h-4 mr-2" :disabled="isUploadingBulk">
                                        Bild-Nummerierung beginnt bei 0 (Dateiname "0" = Frame "1")
                                    </label>
                                    <input type="file" multiple accept="image/*" @change="handleBulkFileSelect" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" :disabled="isUploadingBulk" />
                                </div>
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
                                <SecondaryButton @click="startOptimization" :disabled="isOptimizing" class="text-xs py-1 h-8 text-green-600 border-green-200">
                                    <span v-if="!isOptimizing">Bilder optimieren</span>
                                    <span v-else>{{ optmizationProgress }}</span>
                                </SecondaryButton>
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
                                        <div v-if="activeView.layers?.length" class="text-xs font-bold" :class="(frame.media?.filter(m => !['depth_map', 'normal_map'].includes(m.collection_name)).length || 0) >= activeView.layers.length ? 'text-green-600' : 'text-orange-500'">
                                            {{ frame.media?.filter(m => !['depth_map', 'normal_map'].includes(m.collection_name)).length || 0 }} / {{ activeView.layers.length }}
                                            <CheckCircleIcon v-if="(frame.media?.filter(m => !['depth_map', 'normal_map'].includes(m.collection_name)).length || 0) >= activeView.layers.length" class="w-4 h-4 inline ml-1" />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div v-for="layer in activeView.layers" :key="layer.id" class="flex items-center justify-between text-sm bg-white p-2 border rounded">
                                            <span class="truncate pr-2 flex items-center gap-2">
                                                Layer: <strong>{{ layer.name }}</strong>
                                                <a v-if="frame.media?.find(m => m.collection_name === 'depth_map' && m.custom_properties?.target_collection === 'layer_'+layer.id)"
                                                   :href="frame.media.find(m => m.collection_name === 'depth_map' && m.custom_properties?.target_collection === 'layer_'+layer.id).original_url" 
                                                   target="_blank" 
                                                   class="text-[10px] bg-indigo-100 text-indigo-700 font-bold px-2 py-0.5 rounded hover:bg-indigo-200 transition inline-flex items-center gap-1" title="Tiefenkarte öffnen">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    Tiefenkarte
                                                </a>
                                            </span>
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

        <!-- Confirm Popup -->
        <DialogModal :show="confirmPopup.show" @close="confirmPopup.show = false" maxWidth="md">
            <template #title>{{ confirmPopup.title }}</template>
            <template #content>
                <p class="text-sm text-gray-600">{{ confirmPopup.message }}</p>
            </template>
            <template #footer>
                <SecondaryButton @click="confirmPopup.show = false">Abbrechen</SecondaryButton>
                <button @click="executeConfirm" class="ml-3 inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                    Bestätigen
                </button>
            </template>
        </DialogModal>

        <!-- Description AI Chat Modal -->
        <DialogModal :show="showDescAiModal" @close="showDescAiModal = false" maxWidth="4xl">
            <template #title>
                <div class="flex items-center gap-2">
                    <SparklesIcon class="w-5 h-5 text-brand-500" />
                    <span>KI Projektbeschreibung-Assistent</span>
                </div>
            </template>
            <template #content>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[500px]">
                    <!-- LEFT: Chat Sidebar -->
                    <div class="lg:col-span-5 flex flex-col bg-gray-50 border rounded-xl overflow-hidden shadow-sm">
                        <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar" id="descAiChatWindow">
                            <div v-if="!descAiChatMessages.length" class="h-full flex flex-col items-center justify-center text-center opacity-40 px-6">
                                <SparklesIcon class="w-10 h-10 mb-2" />
                                <p class="text-sm font-medium italic">Lass dir von der KI eine Beschreibung entwerfen (Llama3.1).</p>
                            </div>
                            
                            <div v-for="(msg, idx) in descAiChatMessages" :key="idx" :class="['flex flex-col', msg.role === 'user' ? 'items-end' : 'items-start']">
                                <div :class="['max-w-[90%] px-3 py-2 rounded-2xl text-sm shadow-sm', msg.role === 'user' ? 'bg-brand-600 text-white rounded-tr-none' : 'bg-white text-gray-800 border rounded-tl-none']">
                                    <div class="whitespace-pre-wrap font-medium leading-relaxed">{{ msg.content }}</div>
                                    <div v-if="msg.role === 'assistant' && extractCodeFromMessage(msg.content)" class="mt-3 pt-3 border-t">
                                        <button @click="applyDescAiCode(msg.content)" class="w-full py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-700 text-xs font-black rounded-lg transition-all flex items-center justify-center gap-2">
                                            Text übernehmen
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="isDescAiLoading" class="flex gap-1.5 p-3">
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                <div class="w-1.5 h-1.5 bg-brand-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                            </div>
                        </div>
                        
                        <div class="p-3 border-t bg-white">
                            <div class="relative">
                                <textarea v-model="descAiCurrentPrompt" @keydown.enter.prevent="sendDescAiChat" placeholder="Schreibe die Beschreibung kürzer..." class="w-full pl-3 pr-10 py-2 border-gray-300 rounded-xl text-sm focus:ring-brand-500 focus:border-brand-500 resize-none min-h-[45px] max-h-[100px]"></textarea>
                                <button @click="sendDescAiChat" :disabled="!descAiCurrentPrompt.trim() || isDescAiLoading" class="absolute right-2 bottom-2 p-1.5 text-brand-600 hover:text-brand-800 disabled:opacity-30 transition">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RIGHT: Text Editor -->
                    <div class="lg:col-span-7 flex flex-col bg-white border rounded-xl overflow-hidden shadow-sm">
                        <div class="p-3 border-b bg-gray-50 flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Aktueller Rohtext ({{ descAiTarget === 'project' ? 'Projekt' : 'Wohnung' }})</span>
                        </div>
                        <textarea v-if="descAiTarget === 'project'" v-model="projectForm.description" class="flex-1 w-full p-4 text-sm resize-none focus:ring-brand-500 border-none"></textarea>
                        <textarea v-else v-model="modalData.description" class="flex-1 w-full p-4 text-sm resize-none focus:ring-brand-500 border-none"></textarea>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showDescAiModal = false">Schließen</SecondaryButton>
            </template>
        </DialogModal>

        <!-- Innovative Color Palette Modal -->
        <DialogModal :show="showColorPaletteModal" @close="showColorPaletteModal = false" maxWidth="4xl">
            <template #title>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center text-brand-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-gray-900">Innovativer Farb-Editor</h3>
                        <p class="text-xs text-brand-500 font-bold uppercase tracking-wider">Harmonische Farbschemata für dein Branding</p>
                    </div>
                </div>
            </template>
            <template #content>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 min-h-[500px]">
                    <!-- Left Column: Base Color Selection -->
                    <div class="lg:col-span-4 space-y-6">
                        <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100 flex flex-col items-center shadow-sm">
                            <label class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4 block w-full text-center">Grundfarbe wählen</label>
                            
                            <div class="relative w-32 h-32 mb-6 group">
                                <input type="color" v-model="paletteBaseColor" @input="updatePaletteSuggestions" class="absolute inset-0 w-full h-full p-0 border-0 rounded-full cursor-pointer opacity-0 z-10" />
                                <div class="w-full h-full rounded-full border-4 border-white shadow-xl transition-transform group-hover:scale-105" :style="{ backgroundColor: paletteBaseColor }"></div>
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                     <svg class="w-8 h-8 text-white filter drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                </div>
                            </div>
                            
                            <TextInput v-model="paletteBaseColor" @input="updatePaletteSuggestions" class="w-full text-center font-mono font-bold text-lg mb-6 rounded-xl border-gray-200" />
                            
                            <div class="w-full border-t border-gray-200 pt-6">
                                <button @click="generateAiPalettes" :disabled="isAiPaletteLoading" class="w-full py-3 px-4 bg-gradient-to-br from-brand-600 to-indigo-700 text-white rounded-xl font-bold flex items-center justify-center gap-3 shadow-lg shadow-brand-200 hover:scale-[1.02] active:scale-95 transition-all text-sm disabled:opacity-50">
                                    <SparklesIcon v-if="!isAiPaletteLoading" class="w-5 h-5" />
                                    <svg v-else class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    {{ isAiPaletteLoading ? 'KI analysiert...' : 'KI Design-Magic' }}
                                </button>
                                <p class="text-[10px] text-gray-400 mt-2 text-center italic">KI schlägt Paletten basierend auf dem Projektthema vor.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Suggestions Grid -->
                    <div class="lg:col-span-8 overflow-y-auto max-h-[550px] pr-2 custom-scrollbar">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="(palette, pIdx) in suggestedPalettes" :key="pIdx" class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm group hover:border-brand-200 hover:shadow-md transition-all">
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-[11px] font-black uppercase tracking-widest text-brand-600/60">{{ palette.name }}</span>
                                    <button @click="applyPalette(palette.colors)" class="px-3 py-1 bg-brand-50 text-brand-700 text-[10px] font-black rounded-lg opacity-0 group-hover:opacity-100 transition-all hover:bg-brand-600 hover:text-white uppercase tracking-widest">Wählen</button>
                                </div>
                                <div class="flex h-16 rounded-xl overflow-hidden cursor-pointer active:scale-[0.98] transition-transform" @click="applyPalette(palette.colors)">
                                    <div v-for="(color, cIdx) in palette.colors" :key="cIdx" class="flex-1 transition-all hover:flex-[1.5]" :style="{ backgroundColor: color }" :title="color">
                                        <div class="w-full h-full flex items-center justify-center opacity-0 hover:opacity-100">
                                            <span class="text-[10px] font-black pointer-events-none" :style="{ color: cIdx === 2 ? '#888' : palette.colors[2] }">{{ color }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <div v-for="color in palette.colors" :key="color" class="text-[9px] font-mono text-gray-400 font-bold">{{ color }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!suggestedPalettes.length" class="h-full flex items-center justify-center text-gray-400 italic py-20">
                             Wähle eine Grundfarbe für Vorschläge...
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex justify-between w-full">
                    <p class="text-xs text-gray-400 italic self-center">Tipp: Probiere verschiedene Grundfarben aus, um math. Harmonien zu sehen.</p>
                    <SecondaryButton @click="showColorPaletteModal = false" class="px-6 rounded-xl">Fertig</SecondaryButton>
                </div>
            </template>
        </DialogModal>

    </AppLayout>
</template>
