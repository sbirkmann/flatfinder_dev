<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch, defineAsyncComponent } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/20/solid';
import { eventBus } from './eventBus.js';

const ThreeDFinder = defineAsyncComponent(() => import('./Components/ThreeDFinder.vue'));
const FloorPlanView = defineAsyncComponent(() => import('./Components/FloorPlanView.vue'));
const PublicHotspotViewer = defineAsyncComponent(() => import('./Components/PublicHotspotViewer.vue'));
const DualSlider = defineAsyncComponent(() => import('@/Components/DualSlider.vue'));
const PoiMap = defineAsyncComponent(() => import('./Components/PoiMap.vue'));
const ApartmentCompareModal = defineAsyncComponent(() => import('./Components/ApartmentCompareModal.vue'));
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const showContactForm = ref(false);
const contactForm = useForm({
    apartment_id: null,
    source: 'Website',
    fields: {}
});

const openContactForm = () => {
    contactForm.apartment_id = activeApartment.value?.id || null;
    const fieldsConfig = props.project.contact_form_config?.fields || [];
    const fieldsData = {};
    fieldsConfig.forEach(f => { fieldsData[f.id] = ''; });
    contactForm.fields = fieldsData;
    showContactForm.value = true;
};

const submitContactForm = () => {
    contactForm.post(route('projects.public.inquire', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            showContactForm.value = false;
            contactForm.reset();
        }
    });
};

const props = defineProps({
    project: Object,
    isAuthenticated: Boolean,
});

const projectLogo = computed(() => {
    return props.project.media?.find(m => m.collection_name === 'logo');
});

const projectPdf = computed(() => {
    return props.project.media?.find(m => m.collection_name === 'project_pdf')
        || props.project.media?.find(m => m.collection_name === 'default')
        || null;
});

const projectPreviewImage = computed(() => {
    return props.project.media?.find(m => m.collection_name === 'preview_image') || null;
});

const projectImage = computed(() => {
    return props.project.media?.find(m => m.collection_name === 'project_image') || null;
});

const showPreviewPopup = ref(false);

onMounted(() => {
    // Check URL parameters for deep linking
    const urlParams = new URLSearchParams(window.location.search);
    const deepLinkApartmentId = urlParams.get('apartment');
    const deepLinkTourPointId = urlParams.get('tour_point');

    if (deepLinkApartmentId) {
        setTimeout(() => {
            const aptId = parseInt(deepLinkApartmentId, 10);
            if (aptId) openApartment(aptId);
        }, 500);
    }

    // Original tour point check with yaw/pitch
    if (deepLinkTourPointId) {
        setTimeout(() => {
            const pointId = parseInt(deepLinkTourPointId, 10);
            const initialYaw = parseFloat(urlParams.get('yaw'));
            const initialPitch = parseFloat(urlParams.get('pitch'));
            if (pointId) openTourPopup(pointId, initialYaw, initialPitch);
        }, 500);
    }

    // New Merkliste & Tour-Views checklist handling
    const deepLinkMerkliste = urlParams.get('merkliste');
    const deepLinkTours = urlParams.get('tours');
    if (deepLinkMerkliste || deepLinkTours) {
        try {
            if (deepLinkMerkliste) {
                const ids = deepLinkMerkliste.split(',').map(id => parseInt(id, 10)).filter(id => !isNaN(id));
                if (ids.length) {
                    const currentFavs = JSON.parse(localStorage.getItem('flatplan_favorites') || '[]');
                    const newFavs = [...new Set([...currentFavs, ...ids])];
                    favorites.value = newFavs;
                    localStorage.setItem('flatplan_favorites', JSON.stringify(newFavs));
                }
            }
            if (deepLinkTours) {
                const currentTours = JSON.parse(localStorage.getItem('flatplan_tour_favs') || '[]');
                const parts = decodeURIComponent(deepLinkTours).split(',').filter(Boolean);
                parts.forEach(p => {
                    const [idStr, yawStr, pitchStr] = p.split('_');
                    const id = parseInt(idStr, 10);
                    const yaw = parseFloat(yawStr);
                    const pitch = parseFloat(pitchStr);
                    if (!isNaN(id) && !isNaN(yaw) && !isNaN(pitch)) {
                        const fpString = `${id}_${Math.round(yaw * 10)}_${Math.round(pitch * 10)}`;
                        if (!currentTours.some(t => t.key === fpString)) {
                            // Find corresponding name
                            let pointName = 'Blickpunkt';
                            (props.project.virtual_tours || []).forEach(t => {
                                const pt = t.points?.find(pt => pt.id == id);
                                if (pt) pointName = pt.name;
                            });
                            currentTours.push({ key: fpString, id, name: pointName, yaw, pitch });
                        }
                    }
                });
                tourFavorites.value = currentTours;
                localStorage.setItem('flatplan_tour_favs', JSON.stringify(currentTours));
            }
            
            setTimeout(() => { showWishlist.value = true; }, 800);
        } catch (e) {}
    }

    if (projectPreviewImage.value) {
        showPreviewPopup.value = true;
        setTimeout(() => {
            showPreviewPopup.value = false;
        }, 5000);
    }

    // --- Automatically open configured slider on load ---
    if (props.project?.initial_slider_id) {
        openSliderPopup(props.project.initial_slider_id);
    }

    // --- Visitor Tracking (only for non-authenticated users) ---
    if (!props.isAuthenticated) {
        initTracking();
    }
});

// --- Tracking System ---
const visitorId = ref(null);

const generateFingerprint = () => {
    const parts = [
        navigator.userAgent,
        navigator.language,
        screen.width + 'x' + screen.height,
        new Date().getTimezoneOffset(),
        navigator.hardwareConcurrency || '',
    ];
    // Simple hash
    let hash = 0;
    const str = parts.join('|');
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash |= 0;
    }
    return Math.abs(hash).toString(36) + '_' + str.length.toString(36);
};

const detectBrowser = () => {
    const ua = navigator.userAgent;
    if (ua.includes('Firefox')) return 'Firefox';
    if (ua.includes('Edg')) return 'Edge';
    if (ua.includes('Chrome')) return 'Chrome';
    if (ua.includes('Safari')) return 'Safari';
    if (ua.includes('Opera') || ua.includes('OPR')) return 'Opera';
    return 'Other';
};

const detectOS = () => {
    const ua = navigator.userAgent;
    if (ua.includes('Windows')) return 'Windows';
    if (ua.includes('Mac')) return 'macOS';
    if (ua.includes('Linux')) return 'Linux';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
    return 'Other';
};

const detectDevice = () => {
    const w = window.innerWidth;
    if (w < 768) return 'mobile';
    if (w < 1024) return 'tablet';
    return 'desktop';
};

const initTracking = async () => {
    try {
        const res = await fetch('/api/tracking/identify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                project_id: props.project.id,
                fingerprint: generateFingerprint(),
                browser: detectBrowser(),
                os: detectOS(),
                device: detectDevice(),
                language: navigator.language,
                referrer: document.referrer || null,
                screen_resolution: screen.width + 'x' + screen.height,
            }),
        });
        const data = await res.json();
        visitorId.value = data.visitor_id;

        // Track initial page view
        trackEvent('page_view');
    } catch (e) {
        console.warn('Tracking init failed', e);
    }
};

const trackEvent = (eventType, targetId = null, targetType = null, meta = null) => {
    if (!visitorId.value) return;
    fetch('/api/tracking/track', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            visitor_id: visitorId.value,
            project_id: props.project.id,
            event_type: eventType,
            target_id: targetId,
            target_type: targetType,
            meta: meta,
        }),
    }).catch(() => {});
};



const apartmentPdf = computed(() => {
    if (!activeApartment.value || !activeApartment.value.media) return null;
    return activeApartment.value.media.find(m => m.collection_name === 'expose');
});

const showMobileViewDropdown = ref(false);

const primaryBase = computed(() => props.project.color_settings?.primary?.base || '#ab715c');
const primaryHover = computed(() => props.project.color_settings?.primary?.hover || '#96624f');
const primaryText = computed(() => props.project.color_settings?.primary?.text || '#ffffff');

const availableCount = computed(() => {
    return props.project.apartments?.filter(a => a.status === 'Frei').length || 0;
});
const totalCount = computed(() => props.project.apartments?.length || 0);

const hasMiete = computed(() => props.project.apartments?.some(a => a.marketing_type === 'Miete'));
const priceLabel = computed(() => hasMiete.value ? 'Bruttomietzins' : 'Kaufpreis');

const unformattedPriceRange = computed(() => {
    if (!props.project.apartments?.length) return null;
    const prices = props.project.apartments.map(a => parseFloat(a.marketing_type === 'Miete' ? (a.warm_rent || a.price) : a.price)).filter(p => !isNaN(p) && p > 0);
    if (!prices.length) return null;
    return { min: Math.min(...prices), max: Math.max(...prices) };
});

const priceRangeOverview = computed(() => {
    const range = unformattedPriceRange.value;
    if (!range) return 'auf Anfrage';
    const format = (v) => new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(v);
    return range.min === range.max ? `${format(range.min)} EUR` : `${format(range.min)} - ${format(range.max)} EUR`;
});

const priceRangeFilter = computed(() => {
    const range = unformattedPriceRange.value;
    if (!range) return 'auf Anfrage';
    const format = (v) => new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
    return range.min === range.max ? `${format(range.min)} EUR` : `${format(range.min)} EUR - ${format(range.max)} EUR`;
});

const unformattedSqmRange = computed(() => {
    if (!props.project.apartments?.length) return null;
    const sqms = props.project.apartments.map(a => parseFloat(a.sqm)).filter(p => !isNaN(p) && p > 0);
    if (!sqms.length) return null;
    return { min: Math.min(...sqms), max: Math.max(...sqms) };
});

const sqmRange = computed(() => {
    const range = unformattedSqmRange.value;
    if (!range) return '-';
    const format = (v) => v.toFixed(2);
    return range.min === range.max ? `${format(range.min)} m²` : `${format(range.min)} - ${format(range.max)} m²`;
});

const roomsRange = computed(() => {
    if (!props.project.apartments?.length) return '-';
    const rms = props.project.apartments.map(a => parseFloat(a.rooms)).filter(p => !isNaN(p) && p > 0);
    if (!rms.length) return '-';
    const min = Math.min(...rms);
    const max = Math.max(...rms);
    return min === max ? `${min}` : `${min} - ${max}`;
});

// Filter Lists
const availableFloors = computed(() => {
    const floorIds = [...new Set(props.project.apartments?.map(a => a.floor_id).filter(Boolean))];
    const floors = floorIds.map(id => props.project.floors?.find(f => f.id === id)).filter(Boolean);
    return floors.sort((a,b) => (a.index || 0) - (b.index || 0));
});

const availableRooms = computed(() => {
    const rms = [...new Set(props.project.apartments?.map(a => parseFloat(a.rooms)).filter(p => !isNaN(p) && p > 0))];
    return rms.sort((a,b) => a - b);
});

const availableDates = computed(() => {
    const dts = [...new Set(props.project.apartments?.map(a => a.available_from).filter(Boolean))];
    return dts.sort();
});

const availableFeatures = computed(() => {
    const featureMap = new Map();
    (props.project.apartments || []).forEach(a => {
        (a.features || []).forEach(f => {
            if (!featureMap.has(f.id)) featureMap.set(f.id, f);
        });
    });
    return [...featureMap.values()].sort((a, b) => a.name.localeCompare(b.name));
});

// --- UI State ---
const listLayout = ref('card');       // 'card' | 'compact'
const showWishlist = ref(false);
const showFiltersPopup = ref(false);
const showMapPopup = ref(false);
const mapPopupAddress = ref('');

const openMapPopup = (address) => {
    mapPopupAddress.value = address || 'Deutschland';
    showMapPopup.value = true;
    trackEvent('map_open', null, null, { address });
};

const showSliderPopup = ref(false);
const activeSlider = ref(null);
const sliderPopupIndex = ref(0);

const handleCustomButton = (btn) => {
    if (!btn || !btn.action_type) return;

    if (btn.action_type === 'url') {
        const url = btn.action_target;
        if (url) window.open(url, '_blank', 'noreferrer');
    } else if (btn.action_type === 'slider') {
        const sliderId = parseInt(btn.action_target, 10);
        if (sliderId) {
            openSliderPopup(sliderId);
        }
    } else if (btn.action_type === 'tour_point') {
        const pointId = parseInt(btn.action_target, 10);
        if (pointId) {
            openTourPopup(pointId);
        }
    } else if (btn.action_type === 'tooltip') {
        activeTooltip.value = btn.action_target || "Kein Inhalt hinterlegt.";
    } else if (btn.action_type === 'apartment') {
        const aid = parseInt(btn.action_target, 10);
        if (aid) openApartment(aid);
    } else if (btn.action_type === 'ansicht') {
        const vid = parseInt(btn.action_target, 10);
        if (vid) switchToView(vid);
    } else if (btn.action_type === 'etage') {
        const fid = parseInt(btn.action_target, 10);
        if (fid) {
            eventBus.filters.floors = [fid];
            eventBus.setView('etagenansicht');
        }
    } else if (btn.action_type === 'house') {
        const hid = parseInt(btn.action_target, 10);
        // Fallback for house filtering if/when added
    } else if (btn.action_type === 'iframe') {
        activeIframe.value = btn.action_target;
    } else if (btn.action_type === 'video') {
        activeVideo.value = btn.action_target;
    }
};

const activeTourPoint = ref(null);
const activeTooltip = ref(null);
const activeIframe = ref(null);
const activeVideo = ref(null);
const currentTourYaw = ref(0);
const currentTourPitch = ref(0);

const handleTourPositionChange = (pos) => {
    currentTourYaw.value = pos.yaw;
    currentTourPitch.value = pos.pitch;
};

const tourFavorites = ref(JSON.parse(localStorage.getItem('flatplan_tour_favs') || '[]'));

const toggleTourFavorite = () => {
    if (!activeTourPoint.value) return;
    const fpString = `${activeTourPoint.value.id}_${Math.round(currentTourYaw.value * 10)}_${Math.round(currentTourPitch.value * 10)}`;
    const existingIdx = tourFavorites.value.findIndex(t => t.key === fpString);
    if (existingIdx !== -1) {
        tourFavorites.value.splice(existingIdx, 1);
    } else {
        tourFavorites.value.push({
            key: fpString,
            id: activeTourPoint.value.id,
            name: activeTourPoint.value.name,
            yaw: currentTourYaw.value,
            pitch: currentTourPitch.value
        });
        trackEvent('favorite_tour', activeTourPoint.value.id, 'tour');
    }
    localStorage.setItem('flatplan_tour_favs', JSON.stringify(tourFavorites.value));
};

const isTourFavorite = () => {
    if (!activeTourPoint.value) return false;
    const fpString = `${activeTourPoint.value.id}_${Math.round(currentTourYaw.value * 10)}_${Math.round(currentTourPitch.value * 10)}`;
    return tourFavorites.value.some(t => t.key === fpString);
};

const toggleTourFavoriteByObj = (tFav) => {
    tourFavorites.value = tourFavorites.value.filter(t => t.key !== tFav.key);
    localStorage.setItem('flatplan_tour_favs', JSON.stringify(tourFavorites.value));
};

const copyShareLink = (type, id = null) => {
    try {
        let url = window.location.origin + window.location.pathname;
        if (type === 'apartment') {
            url += '?apartment=' + id;
        } else if (type === 'tour') {
            url += '?tour_point=' + id + '&yaw=' + (currentTourYaw.value || 0).toFixed(2) + '&pitch=' + (currentTourPitch.value || 0).toFixed(2);
        } else if (type === 'merkliste') {
            const toursStr = tourFavorites.value.map(t => `${t.id}_${(t.yaw || 0).toFixed(2)}_${(t.pitch || 0).toFixed(2)}`).join(',');
            url += '?merkliste=' + favorites.value.join(',') + (toursStr.length ? '&tours=' + encodeURIComponent(toursStr) : '');
        }
        
        const fallbackCopy = () => {
            try {
                const el = document.createElement('textarea');
                el.value = url;
                el.setAttribute('readonly', '');
                el.style.position = 'absolute';
                el.style.left = '-9999px';
                document.body.appendChild(el);
                el.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(el);
                if (successful) {
                    alert('Deep-Link wurde in die Zwischenablage kopiert!');
                } else {
                    prompt('Bitte diesen Link kopieren (Strg+C):', url);
                }
            } catch (err) {
                prompt('Bitte diesen Link kopieren (Strg+C):', url);
            }
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(() => {
                alert('Deep-Link wurde in die Zwischenablage kopiert!');
            }).catch(() => fallbackCopy());
        } else {
            fallbackCopy();
        }
    } catch (e) {
        console.error("Link share error", e);
    }
};

const openTourPopup = (pointId, initialYaw = 0, initialPitch = 0) => {
    let point = null;
    (props.project.virtual_tours || []).forEach(t => {
        const p = t.points?.find(pt => pt.id == pointId);
        if (p) point = p;
    });

    if (point) {
        // inject initial viewpoints if we had deep link attached
        point.initialYaw = initialYaw;
        point.initialPitch = initialPitch;
        currentTourYaw.value = initialYaw;
        currentTourPitch.value = initialPitch;

        activeTourPoint.value = point;
        trackEvent('tour_open', pointId, 'tour', { name: point.name });
    }
};

const handleTourAction = (action) => {
    if (!action) return;
    
    if (action.type === 'slider') {
        openSliderPopup(action.target);
    } else if (action.type === 'view') {
        activeTourPoint.value = null;
        switchToView(action.target);
    } else if (action.type === 'apartment') {
        activeTourPoint.value = null;
        openApartment(action.target);
    } else if (action.type === 'point') { 
        openTourPopup(action.target);
    } else if (action.type === 'tooltip') {
        activeTooltip.value = action.target;
    } else if (action.type === 'video') {
        activeVideo.value = action.target;
    }
};

const openSliderPopup = (sliderId) => {
    activeSlider.value = props.project.sliders?.find(s => s.id === sliderId) || null;
    if (activeSlider.value) {
        sliderPopupIndex.value = 0;
        showSliderPopup.value = true;
        trackEvent('slider_open', sliderId, 'slider', { name: activeSlider.value.name });
    }
};
const switchToView = (viewId) => {
    eventBus.targetViewId = viewId;
    eventBus.setView('3d-finder');
};

const uniqueFloors = computed(() => {
    return props.project.floors || [];
});

const uniqueRooms = computed(() => {
    if (!props.project.apartments) return [];
    const rooms = props.project.apartments.map(a => a.rooms).filter(Boolean);
    return [...new Set(rooms)].sort((a, b) => a - b);
});

const availabilityOnly = ref(false);  // only show status=Frei
const resetFilters = () => {
    eventBus.filters.floors = [];
    eventBus.filters.rooms = [];
    eventBus.filters.availabilities = [];
    eventBus.filters.features = [];
    eventBus.filters.priceMin = null;
    eventBus.filters.priceMax = null;
    eventBus.filters.sqmMin = null;
    eventBus.filters.sqmMax = null;
    availabilityOnly.value = false;
};

const sidebarScrollRef = ref(null);
const scrollToApartments = () => {
    const el = document.getElementById('apartmentsList');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const printApartments = () => {
    const apts = filteredApartments.value;
    const p = props.project;
    const logo = props.project.media?.find(m => m.collection_name === 'logo');
    const previewImg = props.project.media?.find(m => m.collection_name === 'preview') ||
                       props.project.media?.find(m => m.collection_name === 'project_image');

    // Build active filter labels
    const activeFilters = [];
    if (eventBus.filters.priceMin !== null || eventBus.filters.priceMax !== null) {
        const fmt = v => new Intl.NumberFormat('de-DE').format(v);
        activeFilters.push(`Preis: ${fmt(priceModel.value.min)} – ${fmt(priceModel.value.max)} EUR`);
    }
    if (eventBus.filters.sqmMin !== null || eventBus.filters.sqmMax !== null) {
        activeFilters.push(`Fläche: ${sqmModel.value.min} – ${sqmModel.value.max} m²`);
    }
    if (eventBus.filters.floors.length) {
        const names = eventBus.filters.floors.map(id => p.floors?.find(f => f.id === id)?.name).filter(Boolean);
        activeFilters.push(`Etage: ${names.join(', ')}`);
    }
    if (eventBus.filters.rooms.length) {
        activeFilters.push(`Zimmer: ${eventBus.filters.rooms.join(', ')} Zi`);
    }
    if (eventBus.filters.availabilities.length) {
        activeFilters.push(`Bezugstermin: ${eventBus.filters.availabilities.join(', ')}`);
    }
    if (eventBus.filters.features.length) {
        const names = eventBus.filters.features.map(id => {
            for (const a of p.apartments || []) {
                const f = (a.features || []).find(f => f.id === id);
                if (f) return (f.icon ? f.icon + ' ' : '') + f.name;
            }
            return id;
        });
        activeFilters.push(`Merkmale: ${names.join(', ')}`);
    }
    if (availabilityOnly.value) activeFilters.push('Nur verfügbare');

    const rows = apts.map(a => {
        const floor = p.floors?.find(f => f.id === a.floor_id)?.name || '–';
        const price = a.price ? new Intl.NumberFormat('de-DE').format(a.price) + ' EUR' : 'auf Anfrage';
        const sqm = a.sqm ? Math.round(a.sqm * 10) / 10 + ' m²' : '–';
        const features = (a.features || []).map(f => (f.icon || '') + ' ' + f.name).join(', ');
        const statusColor = a.status?.toLowerCase() === 'frei' ? '#2d7a2d' : a.status?.toLowerCase() === 'reserviert' ? '#9a7a00' : '#b03030';
        return `
            <tr>
                <td>${a.name || '–'}</td>
                <td>${a.rooms || '–'}</td>
                <td>${sqm}</td>
                <td>${floor}</td>
                <td>${price}</td>
                <td><span style="color:${statusColor};font-weight:700">${a.status || '–'}</span></td>
                <td style="font-size:11px">${features}</td>
            </tr>`;
    }).join('');

    const html = `<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>${p.name} – Wohnungsliste</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1a1a1a; background: #fff; }
        .header { display: flex; align-items: flex-start; gap: 24px; padding: 28px 36px 20px; border-bottom: 2px solid #e5e5e5; }
        .header-img { width: 160px; height: 110px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
        .header-img-placeholder { width: 160px; height: 110px; background: #f0f0f0; border-radius: 8px; flex-shrink: 0; }
        .header-info { flex: 1; }
        .logo { max-height: 40px; max-width: 180px; object-fit: contain; margin-bottom: 10px; }
        .project-name { font-size: 22px; font-weight: 900; color: #111; letter-spacing: -0.5px; margin-bottom: 4px; }
        .project-sub { font-size: 12px; color: #666; margin-bottom: 6px; }
        .project-desc { font-size: 12px; color: #444; line-height: 1.5; max-width: 560px; }
        .filters { padding: 10px 36px; background: #f9f7f5; border-bottom: 1px solid #e5e5e5; font-size: 11px; color: #666; }
        .filters span { display: inline-block; background: #ecddd4; color: #7a4a30; border-radius: 999px; padding: 2px 10px; margin: 2px; font-weight: 600; }
        .content { padding: 20px 36px 36px; }
        .section-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { background: #f4f4f4; }
        th { text-align: left; padding: 9px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 9px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background: #fafafa; }
        .meta { text-align: right; font-size: 10px; color: #bbb; padding: 16px 36px 0; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 15mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="header">
        ${previewImg ? `<img class="header-img" src="${previewImg.original_url}" alt="Projektbild" />` : '<div class="header-img-placeholder"></div>'}
        <div class="header-info">
            ${logo ? `<img class="logo" src="${logo.original_url}" alt="Logo" />` : ''}
            <div class="project-name">${p.name}</div>
            ${(p.address || p.zip || p.city) ? `<div class="project-sub">${[p.address, p.zip, p.city].filter(Boolean).join(' – ')}</div>` : ''}
            ${p.description ? `<div class="project-desc">${p.description}</div>` : ''}
        </div>
    </div>
    ${activeFilters.length ? `<div class="filters">Filter: ${activeFilters.map(f => `<span>${f}</span>`).join(' ')}</div>` : ''}
    <div class="content">
        <div class="section-title">${apts.length} Wohnung${apts.length !== 1 ? 'en' : ''}</div>
        <table>
            <thead><tr><th>Bezeichnung</th><th>Zi.</th><th>Fläche</th><th>Etage</th><th>Preis</th><th>Status</th><th>Merkmale</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
    </div>
    <div class="meta">Erstellt am ${new Date().toLocaleDateString('de-DE')} – ${window.location.host}</div>
    <script>window.onload = () => window.print();<\/script>
</body></html>`;

    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
};

// --- Detail Sidebar & Favorites ---
const activeApartment = ref(null);
const openApartment = (id) => {
    activeApartment.value = props.project.apartments?.find(a => a.id === id) || null;
    if (activeApartment.value && !isSidebarOpen.value) {
        isSidebarOpen.value = true;
    }
    trackEvent('apartment_view', id, 'apartment', { name: activeApartment.value?.name });
};
const closeApartment = () => {
    activeApartment.value = null;
};

const favorites = ref(JSON.parse(localStorage.getItem('flatplan_favorites') || '[]'));
const toggleFavorite = (id) => {
    if (favorites.value.includes(id)) {
        favorites.value = favorites.value.filter(fid => fid !== id);
    } else {
        favorites.value.push(id);
        trackEvent('favorite', id, 'apartment');
    }
    localStorage.setItem('flatplan_favorites', JSON.stringify(favorites.value));
};

// --- Compare Apartments logic ---
const compareList = ref([]);
const showCompareModal = ref(false);

const toggleCompare = (apt) => {
    const idx = compareList.value.findIndex(a => a.id === apt.id);
    if (idx > -1) {
        compareList.value.splice(idx, 1);
    } else {
        if (compareList.value.length < 3) {
            compareList.value.push(apt);
        } else {
            alert('Maximal 3 Wohnungen auf einmal vergleichen!');
        }
    }
};

const isComparing = (id) => compareList.value.some(a => a.id === id);
const isFavorite = (id) => favorites.value.includes(id);

const isSidebarOpen = ref(true);
onMounted(() => {
    if (typeof window !== 'undefined') {
        const mql = window.matchMedia('(min-width: 768px)');
        
        // Initiale Zuweisung
        if (!mql.matches) {
            isSidebarOpen.value = false;
        }

        // Bei Resize den Status ändern (Desktop = true, Mobile = false)
        mql.addEventListener('change', (e) => {
            if (e.matches) {
                isSidebarOpen.value = true;
            } else {
                isSidebarOpen.value = false;
            }
        });
    }
});

const showTourPopup = ref(false);

watch(showTourPopup, (v) => {
    if (v && activeApartment.value) {
        trackEvent('tour_open', activeApartment.value.id, 'apartment', { url: activeApartment.value.virtual_tour_url });
    }
});

// --- Fullscreen Image Group Slider ---
const activeImageGroup = ref(null);
const activeSlideIndex = ref(0);
const openImageGroup = (group) => {
    if (!group.media || !group.media.length) return;
    activeImageGroup.value = group;
    activeSlideIndex.value = 0;
};
const nextSlide = () => {
    if (activeSlideIndex.value < (activeImageGroup.value?.media?.length || 1) - 1) activeSlideIndex.value++;
};
const prevSlide = () => {
    if (activeSlideIndex.value > 0) activeSlideIndex.value--;
};

const filteredApartments = computed(() => {
    let filtered = props.project.apartments || [];

    // Nur verfügbare
    if (availabilityOnly.value) {
        filtered = filtered.filter(a => a.status?.toLowerCase() === 'frei');
    }

    // Etagen Filter
    if (eventBus.filters.floors && eventBus.filters.floors.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.floors.includes(a.floor_id));
    }

    // Zimmer Filter
    if (eventBus.filters.rooms && eventBus.filters.rooms.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.rooms.includes(parseFloat(a.rooms)));
    }

    // Datum Filter
    if (eventBus.filters.availabilities && eventBus.filters.availabilities.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.availabilities.includes(a.available_from));
    }

    // Preis Filter
    if (eventBus.filters.priceMin !== null && eventBus.filters.priceMin !== undefined) {
        filtered = filtered.filter(a => parseFloat(a.price) >= parseFloat(eventBus.filters.priceMin));
    }
    if (eventBus.filters.priceMax !== null && eventBus.filters.priceMax !== undefined) {
        filtered = filtered.filter(a => parseFloat(a.price) <= parseFloat(eventBus.filters.priceMax));
    }

    // Fläche Filter
    if (eventBus.filters.sqmMin !== null && eventBus.filters.sqmMin !== undefined) {
        filtered = filtered.filter(a => parseFloat(a.sqm) >= parseFloat(eventBus.filters.sqmMin));
    }
    if (eventBus.filters.sqmMax !== null && eventBus.filters.sqmMax !== undefined) {
        filtered = filtered.filter(a => parseFloat(a.sqm) <= parseFloat(eventBus.filters.sqmMax));
    }

    // Features Filter
    if (eventBus.filters.features && eventBus.filters.features.length > 0) {
        filtered = filtered.filter(a => {
            const aptFeatureIds = (a.features || []).map(f => f.id);
            return eventBus.filters.features.every(fid => aptFeatureIds.includes(fid));
        });
    }

    return filtered;
});

const filteredApartmentsCount = computed(() => filteredApartments.value.length);

const priceBoundaries = computed(() => {
    const prices = (props.project.apartments || []).map(a => parseFloat(a.price)).filter(p => !isNaN(p) && p > 0);
    if (!prices.length) return { min: 0, max: 1000000 };
    return { min: Math.floor(Math.min(...prices)), max: Math.ceil(Math.max(...prices)) };
});

const sqmBoundaries = computed(() => {
    const sqms = (props.project.apartments || []).map(a => parseFloat(a.sqm)).filter(s => !isNaN(s) && s > 0);
    if (!sqms.length) return { min: 0, max: 500 };
    return { min: Math.floor(Math.min(...sqms)), max: Math.ceil(Math.max(...sqms)) };
});

const priceModel = computed({
    get: () => ({ min: eventBus.filters.priceMin ?? priceBoundaries.value.min, max: eventBus.filters.priceMax ?? priceBoundaries.value.max }),
    set: (val) => { eventBus.filters.priceMin = val.min; eventBus.filters.priceMax = val.max; }
});

const sqmModel = computed({
    get: () => ({ min: eventBus.filters.sqmMin ?? sqmBoundaries.value.min, max: eventBus.filters.sqmMax ?? sqmBoundaries.value.max }),
    set: (val) => { eventBus.filters.sqmMin = val.min; eventBus.filters.sqmMax = val.max; }
});
</script>

<template>
    <Head :title="project.name" />

    <!-- Intro Popup (Preview Image) -->
    <transition name="fade">
        <div v-if="showPreviewPopup" class="fixed inset-0 z-[100] flex items-center justify-center bg-black cursor-pointer" @click="showPreviewPopup = false">
            <img :src="projectPreviewImage.original_url" class="absolute inset-0 w-full h-full object-cover opacity-90 transition-transform duration-[5000ms] scale-100 hover:scale-105" alt="Preview Image" />
            
            <div class="relative z-10 text-white flex flex-col items-center gap-8">
                <img v-if="projectLogo" :src="projectLogo.original_url" alt="Project Logo" class="h-24 md:h-32 w-auto object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.5)]" />
                <button class="px-8 py-3 border border-white/80 bg-black/20 backdrop-blur-sm text-white rounded-full hover:bg-white hover:text-black transition uppercase text-[13px] font-bold tracking-widest shadow-[0_4px_15px_rgba(0,0,0,0.2)]">
                    Projekt betreten
                </button>
            </div>
            
            <!-- Progress bar indicating 5s wait -->
            <div class="absolute bottom-0 left-0 h-1 bg-brand-500 animate-[load_5s_linear]"></div>
        </div>
    </transition>
    
    <!-- Google Maps Popup Overlay -->
    <Teleport to="body">
        <transition name="fade">
            <div v-if="showMapPopup" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-[5vw] backdrop-blur-sm shadow-2xl" @click.self="showMapPopup = false">
                <div class="bg-white rounded-[24px] shadow-2xl w-full h-full max-w-none overflow-hidden relative border border-gray-100 flex flex-col">
                    <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-[#fcfaf9] flex items-center justify-center text-[#ab715c]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </div>
                            <div>
                                <h3 class="font-black text-[18px] text-gray-900 tracking-tight leading-none mb-1">Standort ansehen</h3>
                                <p class="text-[12px] font-bold text-gray-400 max-w-[200px] md:max-w-full truncate select-all">{{ mapPopupAddress }}</p>
                            </div>
                        </div>
                        <button @click="showMapPopup = false" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-800 transition">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="w-full flex-1 bg-gray-100 relative">
                        <PoiMap v-if="project.poi_settings?.active" 
                                :address-string="mapPopupAddress" 
                                :poi-settings="project.poi_settings" />
                        <iframe v-else
                            :src="'https://maps.google.com/maps?q=' + encodeURIComponent(mapPopupAddress) + '&t=&z=15&ie=UTF8&iwloc=&output=embed'"
                            width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Slider Popup Overlay -->
        <transition name="fade">
            <div v-if="showSliderPopup" class="fixed inset-0 z-[9999] bg-black/90 flex flex-col backdrop-blur-sm" @click.self="showSliderPopup = false">

                <!-- ═══ TOP BAR ═══ -->
                <div class="shrink-0 flex items-center justify-between px-4 md:px-6 h-14 bg-black/60 backdrop-blur-md border-b border-white/10 z-50">
                    <!-- Left: Icon + Slider Name + Counter -->
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-white/80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </div>
                        <span class="text-white font-bold text-[14px] truncate max-w-[140px] md:max-w-xs">{{ activeSlider?.name }}</span>
                        <span v-if="activeSlider?.slides?.length > 1" class="bg-white/15 text-white/80 text-[11px] font-bold px-2.5 py-0.5 rounded-full shrink-0">
                            {{ sliderPopupIndex + 1 }} / {{ activeSlider.slides.length }}
                        </span>
                    </div>

                    <!-- Center: Dots (desktop only) -->
                    <div v-if="activeSlider?.slides?.length > 1" class="hidden md:flex items-center gap-1.5 absolute left-1/2 -translate-x-1/2">
                        <button v-for="(slide, i) in activeSlider.slides" :key="'topDot_'+slide.id"
                                @click="sliderPopupIndex = i"
                                class="rounded-full transition-all duration-300"
                                :class="sliderPopupIndex === i ? 'w-6 h-2 bg-[#ab715c]' : 'w-2 h-2 bg-white/40 hover:bg-white/70'">
                        </button>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex items-center gap-1 shrink-0">
                        <!-- Share -->
                        <button @click="() => { if(navigator?.share) { navigator.share({ title: activeSlider?.name, url: window.location.href }) } else { navigator.clipboard?.writeText(window.location.href) } }"
                                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/10 text-white/70 hover:text-white transition"
                                title="Teilen">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        </button>
                        <!-- PDF Download (only when current slide is PDF) -->
                        <a v-if="activeSlider?.slides?.[sliderPopupIndex]?.type === 'pdf' && activeSlider.slides[sliderPopupIndex].media?.find(m => m.collection_name === 'slide_pdf')"
                           :href="activeSlider.slides[sliderPopupIndex].media.find(m => m.collection_name === 'slide_pdf').original_url"
                           download
                           class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/10 text-white/70 hover:text-white transition"
                           title="PDF herunterladen">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                        <!-- Divider -->
                        <div class="w-px h-5 bg-white/20 mx-1"></div>
                        <!-- Close -->
                        <button @click="showSliderPopup = false"
                                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-white/10 text-white/70 hover:text-white transition"
                                title="Schließen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <!-- ═══ SLIDE CONTENT AREA ═══ -->
                <div v-if="activeSlider && activeSlider.slides?.length" class="flex-1 min-h-0 relative group">
                    <template v-for="(slide, i) in activeSlider.slides" :key="slide.id">
                        <transition name="fade">
                            <div v-show="sliderPopupIndex === i" class="absolute inset-0 flex flex-col items-center justify-center px-16 py-4">

                                <h3 v-if="slide.title" class="text-white text-2xl md:text-3xl font-black font-sans mb-4 tracking-tight drop-shadow-lg text-center shrink-0">{{ slide.title }}</h3>

                                <!-- Typ: Bild -->
                                <img v-if="slide.type === 'image' && slide.media?.length"
                                     :src="slide.media[0].original_url"
                                     class="max-w-full max-h-full object-contain shadow-2xl rounded-xl"
                                     alt="Slide Image" />

                                <!-- Typ: Iframe -->
                                <iframe v-else-if="slide.type === 'iframe' && slide.iframe_url"
                                        :src="slide.iframe_url"
                                        class="w-full h-full border-0 rounded-xl shadow-2xl bg-white"
                                        allowfullscreen></iframe>

                                <!-- Typ: Infoframe -->
                                <div v-else-if="slide.type === 'infoframe' && slide.infoframe"
                                     class="w-full h-full max-w-4xl bg-white rounded-xl shadow-2xl p-8 overflow-y-auto prose">
                                    <div v-html="slide.infoframe.content"></div>
                                </div>

                                <!-- Typ: PDF Viewer -->
                                <div v-else-if="slide.type === 'pdf' && slide.media?.find(m => m.collection_name === 'slide_pdf')"
                                     class="w-full h-full flex flex-col rounded-xl overflow-hidden shadow-2xl bg-[#1e1e2e]">
                                    <!-- PDF Toolbar -->
                                    <div class="flex items-center gap-2 px-4 py-2 bg-[#2a2a3e] shrink-0 border-b border-white/5">
                                        <svg class="w-4 h-4 text-red-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>
                                        <span class="text-white/70 text-[12px] font-medium truncate flex-1">
                                            {{ slide.media.find(m => m.collection_name === 'slide_pdf').file_name }}
                                        </span>
                                        <a :href="slide.media.find(m => m.collection_name === 'slide_pdf').original_url"
                                           download
                                           class="flex items-center gap-1.5 bg-[#ab715c] hover:bg-[#9a6450] text-white text-[11px] font-bold px-3 py-1 rounded-full transition shrink-0">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Download
                                        </a>
                                    </div>
                                    <!-- PDF embedded -->
                                    <iframe
                                        :src="slide.media.find(m => m.collection_name === 'slide_pdf').original_url + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH'"
                                        class="w-full flex-1 border-0 bg-white"
                                        type="application/pdf"
                                    ></iframe>
                                </div>
                            </div>
                        </transition>
                    </template>

                    <!-- Prev Button -->
                    <button v-if="activeSlider.slides.length > 1"
                            @click="sliderPopupIndex = (sliderPopupIndex - 1 + activeSlider.slides.length) % activeSlider.slides.length"
                            class="absolute left-2 md:left-4 top-1/2 -translate-y-1/2 w-12 h-12 md:w-14 md:h-14 bg-black/40 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition opacity-0 group-hover:opacity-100 z-50 backdrop-blur-md">
                        <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <!-- Next Button -->
                    <button v-if="activeSlider.slides.length > 1"
                            @click="sliderPopupIndex = (sliderPopupIndex + 1) % activeSlider.slides.length"
                            class="absolute right-2 md:right-4 top-1/2 -translate-y-1/2 w-12 h-12 md:w-14 md:h-14 bg-black/40 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition opacity-0 group-hover:opacity-100 z-50 backdrop-blur-md">
                        <svg class="w-6 h-6 md:w-8 md:h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <!-- Mobile bottom dots -->
                    <div v-if="activeSlider.slides.length > 1" class="md:hidden absolute bottom-3 left-0 right-0 flex justify-center gap-2 z-50">
                        <button v-for="(slide, i) in activeSlider.slides" :key="'dot_'+slide.id"
                                @click="sliderPopupIndex = i"
                                class="h-2.5 rounded-full transition-all duration-300"
                                :class="sliderPopupIndex === i ? 'w-7 bg-[#ab715c]' : 'w-2.5 bg-white/50 hover:bg-white'"></button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Virtual Tour Popup -->
        <transition name="fade">
            <div v-if="showTourPopup && activeApartment?.virtual_tour_url" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-[5vw] backdrop-blur-sm" @click.self="showTourPopup = false">
                <div class="bg-white rounded-[24px] shadow-2xl w-full h-full overflow-hidden relative flex flex-col">
                    <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-[#fcfaf9] flex items-center justify-center text-[#ab715c]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2-1m2 1l-2 1m2-1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" /></svg>
                            </div>
                            <div>
                                <h3 class="font-black text-[18px] text-gray-900 tracking-tight leading-none mb-1">3D-Rundgang</h3>
                                <p class="text-[12px] font-bold text-gray-400">{{ activeApartment?.name }}</p>
                            </div>
                        </div>
                        <button @click="showTourPopup = false" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-800 transition">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="w-full flex-1 bg-gray-100">
                        <iframe :src="activeApartment.virtual_tour_url" width="100%" height="100%" style="border:0;" allowfullscreen loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ===== Filter Popup Overlay ===== -->
        <transition name="slide-up">
            <div v-if="showFiltersPopup" class="fixed inset-0 z-[9999] flex items-end md:items-center justify-center" @click.self="showFiltersPopup = false">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFiltersPopup = false"></div>

                <!-- Panel -->
                <div class="relative z-10 bg-white w-full md:max-w-[480px] max-h-[90vh] rounded-t-[24px] md:rounded-[24px] flex flex-col shadow-2xl">
                    <!-- Header -->
                    <div class="px-6 pt-5 pb-4 flex items-center justify-between shrink-0 border-b border-gray-100">
                        <div>
                            <h3 class="text-[18px] font-black text-gray-900 tracking-tight">Filter</h3>
                            <p class="text-[12px] text-gray-400 font-semibold mt-0.5">{{ filteredApartmentsCount }} Wohnung{{ filteredApartmentsCount !== 1 ? 'en' : '' }} gefunden</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="resetFilters" class="text-[12px] font-bold text-gray-400 hover:text-[#ab715c] transition">Zurücksetzen</button>
                            <button @click="showFiltersPopup = false" class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="flex-1 overflow-y-auto px-6 py-6 space-y-8 scrollbar-hide">

                        <!-- Preis -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">{{ priceLabel }}</span>
                            </div>
                            <div class="text-[14px] font-medium text-gray-800 mb-4">
                                {{ new Intl.NumberFormat('de-DE').format(priceModel.min) }} – {{ new Intl.NumberFormat('de-DE').format(priceModel.max) }} EUR
                            </div>
                            <DualSlider v-model="priceModel" :min="priceBoundaries.min" :max="priceBoundaries.max" :step="1000" />
                        </div>

                        <!-- Fläche -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">Wohnfläche</span>
                            </div>
                            <div class="text-[14px] font-medium text-gray-800 mb-4">{{ sqmModel.min }} – {{ sqmModel.max }} m²</div>
                            <DualSlider v-model="sqmModel" :min="sqmBoundaries.min" :max="sqmBoundaries.max" :step="1" />
                        </div>

                        <!-- Etage -->
                        <div v-if="availableFloors.length">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">Etage</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="floor in availableFloors" :key="floor.id"
                                        @click="eventBus.filters.floors.includes(floor.id) ? eventBus.filters.floors = eventBus.filters.floors.filter(f => f !== floor.id) : eventBus.filters.floors.push(floor.id)"
                                        :class="['px-3 py-1.5 rounded-full text-[13px] font-semibold border transition', eventBus.filters.floors.includes(floor.id) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-700 border-gray-200 hover:border-[#ab715c]']">
                                    {{ floor.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Zimmer -->
                        <div v-if="availableRooms.length">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">Zimmer</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="room in availableRooms" :key="room"
                                        @click="eventBus.filters.rooms.includes(room) ? eventBus.filters.rooms = eventBus.filters.rooms.filter(r => r !== room) : eventBus.filters.rooms.push(room)"
                                        :class="['px-3 py-1.5 rounded-full text-[13px] font-semibold border transition', eventBus.filters.rooms.includes(room) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-700 border-gray-200 hover:border-[#ab715c]']">
                                    {{ room }} Zi
                                </button>
                            </div>
                        </div>

                        <!-- Bezugstermin -->
                        <div v-if="availableDates.length">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">Bezugstermin</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="date in availableDates" :key="date"
                                        @click="eventBus.filters.availabilities.includes(date) ? eventBus.filters.availabilities = eventBus.filters.availabilities.filter(d => d !== date) : eventBus.filters.availabilities.push(date)"
                                        :class="['px-3 py-1.5 rounded-full text-[13px] font-semibold border transition', eventBus.filters.availabilities.includes(date) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-700 border-gray-200 hover:border-[#ab715c]']">
                                    {{ date }}
                                </button>
                            </div>
                        </div>

                        <!-- Merkmale -->
                        <div v-if="availableFeatures.length">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-[16px] h-[16px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span class="text-[12px] font-bold text-gray-400 uppercase tracking-wider">Merkmale</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="feature in availableFeatures" :key="feature.id"
                                        @click="eventBus.filters.features.includes(feature.id) ? eventBus.filters.features = eventBus.filters.features.filter(f => f !== feature.id) : eventBus.filters.features.push(feature.id)"
                                        :class="['inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[13px] font-semibold border transition', eventBus.filters.features.includes(feature.id) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-700 border-gray-200 hover:border-[#ab715c]']">
                                    <span v-if="feature.icon">{{ feature.icon }}</span>
                                    {{ feature.name }}
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-100 shrink-0">
                        <button @click="showFiltersPopup = false" class="w-full bg-[#ab715c] text-white py-3 rounded-[12px] font-bold text-[15px] hover:bg-[#96624f] transition shadow-md">
                            {{ filteredApartmentsCount }} Ergebnis{{ filteredApartmentsCount !== 1 ? 'se' : '' }} anzeigen
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>


    <div class="h-screen w-full flex bg-white overflow-hidden font-sans relative">
        
        <!-- Toggle Menu Icon (Mobile Only when closed) -->
        <button v-show="!isSidebarOpen" @click="isSidebarOpen = true"
                class="md:hidden absolute top-4 left-4 z-[85] w-12 h-12 bg-white rounded-full shadow-[0_4px_20px_rgba(0,0,0,0.1)] flex items-center justify-center text-gray-800 hover:text-[#ab715c] transition border border-gray-100">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>

        <!-- Toggle Edge Button (Desktop only) -->
        <button @click="isSidebarOpen = !isSidebarOpen"
                class="hidden md:flex absolute top-1/2 -translate-y-1/2 z-[85] w-6 h-12 bg-white border border-gray-200 border-l-0 shadow-md flex items-center justify-center text-gray-400 hover:text-[#ab715c] transition-all duration-[400ms] ease-[cubic-bezier(0.25,1,0.5,1)] rounded-r-md cursor-pointer"
                :style="{ left: isSidebarOpen ? '450px' : '0' }">
            <svg v-if="isSidebarOpen" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            <svg v-else class="w-4 h-4 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>

        <!-- Mobile Backdrop Overlay when open -->
        <div v-if="isSidebarOpen" @click="isSidebarOpen = false; activeApartment = null;" class="md:hidden absolute inset-0 bg-black/40 z-[70] backdrop-blur-sm transition-opacity"></div>

        <div :class="['shrink-0 flex flex-col bg-white transition-all duration-[400ms] ease-[cubic-bezier(0.25,1,0.5,1)] z-[100] overflow-hidden', 
                      'absolute md:relative inset-y-0 left-0',
                      isSidebarOpen ? 'w-[85%] max-w-[400px] h-full md:max-w-none md:w-[450px] shadow-2xl md:shadow-none border-r border-gray-200 translate-x-0' : 'w-[85%] max-w-[400px] h-full md:max-w-none md:w-0 -translate-x-full md:translate-x-0 md:border-r-0']">
            
            <!-- Close Button inside Sidebar (Mobile only now) -->
            <button @click="isSidebarOpen = false" class="md:hidden absolute top-4 right-4 z-[90] w-10 h-10 flex items-center justify-center rounded-full bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-gray-800 transition shadow-sm border border-gray-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div v-if="isAuthenticated" class="absolute top-7 right-20 z-50">
                <Link :href="route('projects.show', project.id)" class="text-[10px] bg-brand-500 text-white px-2 py-1.5 rounded shadow hover:bg-brand-600 transition flex items-center gap-1 font-bold">
                    <ArrowLeftIcon class="w-3 h-3" />
                    Backend
                </Link>
            </div>

            <!-- Header / Logo -->
            <div class="p-6 md:p-8 shrink-0 flex items-center justify-between">
                <div v-if="projectLogo">
                    <img :src="projectLogo.original_url" class="h-10 w-auto object-contain" alt="Project Logo">
                </div>
            </div>

            <!-- Scrollable Content in Sidebar -->
            <div class="flex-1 overflow-y-auto px-6 md:px-8 space-y-8 scrollbar-hide pb-8">
                <!-- Project Image & Title -->
                <div class="pt-2 relative">
                    
                    <h1 class="text-[32px] font-black leading-[1.1] mb-5 text-gray-900 tracking-tight flex items-start justify-between gap-4">
                        <span class="text-pretty mt-1">{{ project.name }}</span>
                        <button v-if="project.has_google_map" @click="openMapPopup(project.address + ', ' + project.zip + ' ' + project.city)" class="shrink-0 w-11 h-11 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-[#ab715c] hover:text-white hover:border-[#ab715c] transition shadow-sm" title="Auf Karte anzeigen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </button>
                    </h1>
                    
                    <div class="flex items-start gap-3 bg-gray-50/80 rounded-2xl p-4 mb-6 border border-gray-100">
                        <svg class="w-5 h-5 text-[#ab715c] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <div class="text-[13.5px] font-semibold text-gray-700 leading-snug">
                            {{ project.address }}<br>
                            {{ project.zip }} {{ project.city }}
                        </div>
                    </div>

                    <img v-if="projectImage" :src="projectImage.original_url" class="w-full h-[240px] object-cover rounded-2xl mb-6 shadow-sm border border-gray-100/50" alt="Projektbild">
                    
                    <p class="text-[14.5px] text-gray-600 leading-[1.6] font-medium text-pretty">{{ project.description || 'Bitte im Backend eine Projekt-Beschreibung (Kurzbeschreibung) hinterlegen, um diesen Standardtext zu ersetzen.' }}</p>
                </div>
                
                <!-- Übersicht Card -->
                <div class="bg-[#fcfaf9] rounded-[20px] shadow-sm border border-[#ede7e3] p-6 mt-2">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-black text-[18px] text-gray-900 tracking-tight">Projektübersicht</h3>
                        <div class="bg-[#e4eedb] text-[#5b873e] px-3 py-1.5 rounded-full text-[12px] font-bold tracking-tight shrink-0 shadow-sm border border-[#d2e4c4]">
                            <span class="text-[#416828] font-black text-[13px]">{{ availableCount }}</span> / <span class="opacity-80">{{ totalCount }}</span> frei
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                        <!-- Block 1: Price -->
                        <div class="flex flex-col">
                            <div class="text-[11px] font-bold uppercase tracking-widest text-[#ab715c] mb-1.5 flex items-center gap-1.5 opacity-90">
                                <svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                {{ priceLabel }}
                            </div>
                            <div class="font-black text-[15px] text-gray-800 tracking-tight">
                                {{ priceRangeOverview }}
                            </div>
                        </div>

                        <!-- Block 2: Area -->
                        <div class="flex flex-col pl-4 border-l border-[#ede7e3]">
                            <div class="text-[11px] font-bold uppercase tracking-widest text-[#ab715c] mb-1.5 flex items-center gap-1.5 opacity-90">
                                <svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                Fläche
                            </div>
                            <div class="font-black text-[15px] text-gray-800 tracking-tight">
                                {{ sqmRange }}
                            </div>
                        </div>

                        <!-- Block 3: Rooms -->
                        <div class="flex flex-col col-span-2 pt-5 border-t border-[#ede7e3]">
                            <div class="text-[11px] font-bold uppercase tracking-widest text-[#ab715c] mb-1.5 flex items-center gap-1.5 opacity-90">
                                <svg class="w-[14px] h-[14px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                                Zimmer
                            </div>
                            <div class="font-black text-[15px] text-gray-800 tracking-tight">
                                {{ roomsRange }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projekt PDF Button -->
                <div v-if="projectPdf" class="mt-6">
                    <button @click="activeIframe = projectPdf.original_url" class="w-full bg-[#fbfbfb] border border-gray-200 text-[14px] font-bold text-gray-800 rounded-[12px] py-3.5 hover:bg-[#ab715c] hover:text-white hover:border-[#ab715c] transition shadow-[0_2px_4px_rgba(0,0,0,0.02)] text-center flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Projekt Exposé
                    </button>
                </div>

                <!-- Filters -->
                <div class="mt-8">
                    <!-- Price Filter -->
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">{{ priceLabel }}</span>
                        </div>
                        <div class="text-[15px] font-medium text-gray-800 tracking-tight mb-4">
                            {{ new Intl.NumberFormat('de-DE').format(priceModel.min) }} EUR - {{ new Intl.NumberFormat('de-DE').format(priceModel.max) }} EUR
                        </div>
                        <DualSlider v-model="priceModel" :min="priceBoundaries.min" :max="priceBoundaries.max" :step="1000" />
                    </div>

                    <!-- Area Filter -->
                    <div class="mb-10">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">Wohnfläche</span>
                        </div>
                        <div class="text-[15px] font-medium text-gray-800 tracking-tight mb-4">
                            {{ sqmModel.min }} m² - {{ sqmModel.max }} m²
                        </div>
                        <DualSlider v-model="sqmModel" :min="sqmBoundaries.min" :max="sqmBoundaries.max" :step="1" />
                    </div>

                    <!-- Floor Checkboxes -->
                    <div class="mb-8" v-if="availableFloors.length">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">Etage</span>
                        </div>
                        <div class="grid grid-cols-2 gap-y-4 gap-x-2 pl-1">
                            <label v-for="floor in availableFloors" :key="floor.id" class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" :value="floor.id" v-model="eventBus.filters.floors" class="w-5 h-5 rounded-[4px] border-gray-300 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer shadow-sm">
                                <span class="text-[15px] text-gray-800 group-hover:text-black transition">{{ floor.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Room Checkboxes -->
                    <div class="mb-8" v-if="availableRooms.length">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">Zimmeranzahl</span>
                        </div>
                        <div class="grid grid-cols-2 gap-y-4 gap-x-2 pl-1">
                            <label v-for="room in availableRooms" :key="room" class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" :value="room" v-model="eventBus.filters.rooms" class="w-5 h-5 rounded-[4px] border-gray-300 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer shadow-sm">
                                <span class="text-[15px] text-gray-800 group-hover:text-black transition">{{ room }} Zi-Whg</span>
                            </label>
                        </div>
                    </div>

                    <!-- Available Checkboxes -->
                    <div class="mb-6" v-if="availableDates.length">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">Bezugstermin</span>
                        </div>
                        <div class="grid grid-cols-2 gap-y-4 gap-x-2 pl-1">
                            <label v-for="date in availableDates" :key="date" class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" :value="date" v-model="eventBus.filters.availabilities" class="w-5 h-5 rounded-[4px] border-gray-300 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer shadow-sm">
                                <span class="text-[15px] text-gray-800 group-hover:text-black transition">{{ date }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Feature Checkboxes -->
                    <div class="mb-8" v-if="availableFeatures.length">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-[18px] h-[18px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-[13px] font-bold text-gray-400">Merkmale</span>
                        </div>
                        <div class="grid grid-cols-2 gap-y-4 gap-x-2 pl-1">
                            <label v-for="feature in availableFeatures" :key="feature.id" class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" :value="feature.id" v-model="eventBus.filters.features" class="w-5 h-5 rounded-[4px] border-gray-300 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer shadow-sm">
                                <span class="text-[15px] text-gray-800 group-hover:text-black transition">
                                    <span v-if="feature.icon" class="mr-1">{{ feature.icon }}</span>{{ feature.name }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Wohnungen Liste -->
                    <div id="apartmentsList" class="mt-12 mb-4" v-if="filteredApartments.length">

                        <!-- Sticky Toolbar -->
                        <div class="sticky top-0 z-[60] bg-white/95 backdrop-blur-sm border-b border-gray-100 mb-3 py-3 flex items-center justify-between">

                            <!-- Left: Merkliste Button -->
                            <button @click="showWishlist = true" class="flex items-center gap-1.5 text-[13px] font-bold text-gray-700 hover:text-[#ab715c] transition px-1">
                                <svg class="w-5 h-5" :class="favorites.length ? 'text-[#ab715c]' : 'text-gray-400'" :fill="favorites.length ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                <span v-if="favorites.length" class="bg-[#ab715c] text-white text-[10px] font-black rounded-full w-4 h-4 flex items-center justify-center">{{ favorites.length }}</span>
                            </button>

                            <!-- Right: Actions -->
                            <div class="flex items-center gap-1">
                                <!-- Print -->
                                <button @click="printApartments" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-[#ab715c] transition" title="Drucken / PDF">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                </button>
                                <!-- Layout Toggle -->
                                <button @click="listLayout = listLayout === 'card' ? 'compact' : 'card'" :class="['w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition', listLayout === 'compact' ? 'bg-gray-100 text-[#ab715c]' : 'text-gray-400']" title="Layout">
                                    <svg v-if="listLayout === 'card'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
                                </button>
                                <!-- Filter -->
                                <button @click="showFiltersPopup = true"
                                        :class="['w-8 h-8 flex items-center justify-center rounded-full transition relative', (eventBus.filters.floors.length || eventBus.filters.rooms.length || eventBus.filters.availabilities.length || eventBus.filters.features.length || eventBus.filters.priceMin !== null || eventBus.filters.sqmMin !== null) ? 'bg-[#ab715c] text-white hover:bg-[#96624f]' : 'hover:bg-gray-100 text-gray-400 hover:text-[#ab715c]']" title="Filter">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- CARD LAYOUT -->
                        <div v-if="listLayout === 'card'" class="flex flex-col gap-3">
                            <div v-for="apt in filteredApartments" :key="apt.id" @click="openApartment(apt.id)" class="w-full bg-white rounded-[20px] shadow-sm border border-[#ede7e3] p-3.5 flex gap-4 transition-all duration-300 hover:shadow-md hover:border-gray-300 hover:-translate-y-0.5 cursor-pointer relative group">
                                
                                <!-- Avatar / Bild -->
                                <div class="relative w-14 h-14 shrink-0">
                                    <img :src="apt.media?.[0]?.original_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=150&q=80'" class="w-full h-full object-cover rounded-full border border-gray-100 shadow-sm" alt="Wohnung" />
                                <!-- Heart Toggle -->
                                    <button @click.stop="toggleFavorite(apt.id)" class="absolute -top-1 -right-1 bg-white rounded-full p-1 shadow-sm border border-gray-100 transition flex items-center justify-center hover:scale-110 z-10">
                                        <svg class="w-3.5 h-3.5 transition" :class="isFavorite(apt.id) ? 'text-[#ab715c]' : 'text-gray-300'" :fill="isFavorite(apt.id) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                    </button>
                                    <!-- Compare Toggle -->
                                    <button v-if="project.comparison_settings?.active" @click.stop="toggleCompare(apt)" class="absolute -top-1 -left-1 bg-white rounded-full p-1 shadow-sm border transition flex items-center justify-center hover:scale-110 z-10" :class="isComparing(apt.id) ? 'border-[#ab715c] text-[#ab715c] bg-[#ab715c]/10' : 'border-gray-100 text-gray-300'" title="Vergleichen">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                    </button>
                                </div>
                                
                                <!-- Content -->
                                <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-[15px] font-medium text-gray-600 tracking-tight truncate pr-2 pt-0.5">{{ apt.name }}</span>
                                        <div class="bg-[#dcf0d5] text-[#3f6327] px-2.5 py-0.5 rounded-full font-bold shrink-0 shadow-sm whitespace-nowrap" style="font-size: 11px;">
                                            <template v-if="apt.price"><span class="text-[13px] mr-1">{{ new Intl.NumberFormat('de-DE').format(apt.price) }}</span> EUR</template>
                                            <template v-else><span class="text-[12px]">Preis auf Anfrage</span></template>
                                        </div>
                                    </div>
                                    <div class="flex items-end justify-between text-gray-800">
                                        <div class="flex flex-col">
                                            <span class="text-[13px] font-black tracking-tight mb-[1px]">{{ Math.round(apt.sqm * 10) / 10 }} m²</span>
                                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Fläche</span>
                                        </div>
                                        <div class="w-px h-5 bg-gray-200 self-center"></div>
                                        <div class="flex flex-col text-center">
                                            <span class="text-[13px] font-black tracking-tight mb-[1px]">{{ project.floors?.find(f => f.id === apt.floor_id)?.name || 'EG' }}</span>
                                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Geschoss</span>
                                        </div>
                                        <div class="w-px h-5 bg-gray-200 self-center"></div>
                                        <div class="flex flex-col text-right">
                                            <span class="text-[13px] font-black tracking-tight mb-[1px]">{{ apt.rooms }}</span>
                                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Zimmer</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COMPACT LIST LAYOUT -->
                        <div v-else class="flex flex-col divide-y divide-gray-100">
                            <div v-for="apt in filteredApartments" :key="apt.id" @click="openApartment(apt.id)" class="py-2.5 flex flex-col gap-1 cursor-pointer hover:bg-gray-50 px-1 transition rounded-[8px]">
                                <!-- Row 1: Name + Price -->
                                <div class="flex items-center justify-between">
                                    <span class="text-[14px] font-black tracking-tight text-gray-900">{{ apt.name }}</span>
                                    <div class="bg-[#dcf0d5] text-[#3f6327] px-2 py-0.5 rounded-full font-bold shrink-0 whitespace-nowrap" style="font-size: 10px;">
                                        <template v-if="apt.price"><span class="text-[12px] mr-0.5">{{ new Intl.NumberFormat('de-DE').format(apt.price) }}</span> EUR</template>
                                        <template v-else>auf Anfrage</template>
                                    </div>
                                </div>
                                <!-- Row 2: Heart + Avatar | Area | Floor | Rooms -->
                                <div class="flex items-center gap-3 text-gray-600">
                                    <!-- Compare -->
                                    <button v-if="project.comparison_settings?.active" @click.stop="toggleCompare(apt)" class="shrink-0 p-1 rounded hover:bg-gray-100 transition" :class="isComparing(apt.id) ? 'text-[#ab715c] bg-[#ab715c]/10' : 'text-gray-300'" title="Vergleichen">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                    </button>
                                    <!-- Heart -->
                                    <button @click.stop="toggleFavorite(apt.id)" class="shrink-0 p-1 rounded hover:bg-gray-100 transition">
                                        <svg class="w-4 h-4 transition" :class="isFavorite(apt.id) ? 'text-[#ab715c]' : 'text-gray-300'" :fill="isFavorite(apt.id) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                    </button>
                                    <!-- Avatar -->
                                    <img :src="apt.media?.[0]?.original_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=60&q=60'" class="w-6 h-6 rounded-full object-cover border border-gray-100 shadow-sm shrink-0" />
                                    <span class="text-[12px] font-semibold text-gray-500">{{ Math.round(apt.sqm * 10) / 10 }} m²</span>
                                    <span class="text-gray-300">|</span>
                                    <span class="text-[12px] font-semibold text-gray-500">{{ project.floors?.find(f => f.id === apt.floor_id)?.name || 'EG' }}</span>
                                    <span class="text-gray-300">|</span>
                                    <span class="text-[12px] font-semibold text-gray-500">{{ apt.rooms }}</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
            
            <!-- Active Apartment Overlay Slide-In -->
            <transition name="slide-forward">
                <div v-show="activeApartment" class="absolute inset-0 z-[70] bg-white flex flex-col h-full w-full">
                    
                    <!-- Header -->
                    <div class="pt-6 px-6 pb-4 shrink-0 flex items-center gap-3 bg-white sticky top-0 z-[71]">
                        <button @click="closeApartment" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-[#ab715c] transition shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </button>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="text-[10px] font-bold tracking-widest text-[#ab715c] uppercase">{{ activeApartment?.marketing_type === 'Miete' ? 'Wohnung zur Miete' : 'Wohnung zum Kauf' }}</span>
                            <h2 class="text-[20px] font-black tracking-tight text-gray-900 truncate">{{ activeApartment?.name }}</h2>
                        </div>
                    </div>

                    <!-- Scroll Content -->
                    <div v-if="activeApartment" class="flex-1 overflow-y-auto px-6 pb-24 space-y-6 scrollbar-hide">
                        
                        <!-- Actions / Badges Row -->
                        <div class="flex items-center gap-3">
                            <div class="bg-[#dcf0d5] text-[#3f6327] px-3 py-1 rounded-full font-bold text-[12px] shadow-sm">{{ activeApartment.status || 'Objekt' }}</div>
                            <button @click="copyShareLink('apartment', activeApartment.id)" class="flex items-center gap-1.5 text-[12px] font-bold text-gray-500 hover:text-gray-800 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6.632l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                Teilen
                            </button>
                            <button @click="toggleFavorite(activeApartment.id)" :class="['flex items-center gap-1.5 text-[12px] font-bold transition ml-auto', isFavorite(activeApartment.id) ? 'text-red-500' : 'text-gray-500 hover:text-gray-800']">
                                <svg class="w-4 h-4" :fill="isFavorite(activeApartment.id) ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                Merken
                            </button>
                        </div>
                        
                        <!-- Exposé Button -->
                        <div v-if="apartmentPdf" class="mt-2">
                            <button @click="activeIframe = apartmentPdf.original_url" class="group w-full flex items-center justify-between p-3.5 border border-gray-200 rounded-[12px] bg-white hover:bg-gray-50 transition shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                                <div class="flex items-center gap-3 text-left">
                                    <svg class="w-5 h-5 text-[#ab715c] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    <span class="text-[14px] font-bold text-gray-800">Wohnungs-Exposé</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#ab715c] group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </button>
                        </div>

                        <!-- Eigene Buttons -->
                        <div v-if="activeApartment.custom_buttons?.length" class="flex flex-col gap-2.5 mt-2">
                            <button v-for="btn in activeApartment.custom_buttons" :key="btn.id" @click="handleCustomButton(btn)"
                                    class="group w-full flex items-center justify-between p-3.5 border border-gray-200 rounded-[12px] bg-white hover:bg-gray-50 transition shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                                <div class="flex items-center gap-3 text-left">
                                    <svg v-if="btn.action_type === 'slider'" class="w-5 h-5 text-[#ab715c] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <svg v-else-if="btn.action_type === 'tour_point'" class="w-5 h-5 text-[#ab715c] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    <svg v-else class="w-5 h-5 text-[#ab715c] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                    <span class="text-[14px] font-bold text-gray-800">{{ btn.title }}</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#ab715c] group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </button>
                        </div>

                        <!-- 3D-Rundgang Box -->
                        <div v-if="activeApartment.virtual_tour_url" 
                             class="w-full border border-gray-200 rounded-[16px] bg-white overflow-hidden shadow-[0_2px_8px_rgba(0,0,0,0.04)] group">
                            <!-- Preview Image -->
                            <div class="w-full h-[180px] overflow-hidden">
                                <img v-if="activeApartment.media?.length" 
                                     :src="activeApartment.media[0].original_url" 
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                                     alt="3D-Rundgang Vorschau" />
                                <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2-1m2 1l-2 1m2-1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" /></svg>
                                </div>
                            </div>
                            <!-- Link Row -->
                            <button @click="showTourPopup = true"
                                    class="w-full flex items-center justify-between px-4 py-3.5 hover:bg-gray-50 transition">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#ab715c] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2-1m2 1l-2 1m2-1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" /></svg>
                                    <span class="text-[14px] font-bold text-gray-800">3D-Rundgang ähnliche Wohnung</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-[#ab715c] group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </button>
                        </div>

                        <!-- Image Groups List (Grundriss, Kurzbaubeschrieb, etc) -->
                        <div v-if="activeApartment.imageGroups?.length" class="space-y-2.5">
                            <button v-for="group in activeApartment.imageGroups" :key="group.id" @click="openImageGroup(group)"
                                    class="w-full flex items-center justify-between p-3.5 border border-gray-200 rounded-[12px] bg-white hover:bg-gray-50 transition shadow-[0_2px_4px_rgba(0,0,0,0.02)]">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="font-bold text-[14px] text-gray-800">{{ group.name }}</span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>

                        <!-- Feature Badges -->
                        <div v-if="activeApartment.features?.length" class="flex flex-wrap gap-2">
                            <span v-for="feature in activeApartment.features" :key="feature.id"
                                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#f5f0ec] text-[#7a5a48] text-[12px] font-semibold border border-[#e8ddd5] shadow-sm">
                                <span v-if="feature.icon" class="text-[14px]">{{ feature.icon }}</span>
                                {{ feature.name }}
                            </span>
                        </div>

                        <!-- Daten Block -->
                        <div class="bg-[#fcfaf9] rounded-[16px] p-5 shadow-[0_2px_4px_rgba(0,0,0,0.01)] border border-gray-100">
                            <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-4">Daten</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <!-- Preis -->
                                <div class="col-span-2 bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex justify-between items-center">
                                    <span class="text-[13px] font-semibold text-gray-500">{{ activeApartment.marketing_type === 'Miete' ? 'Bruttomiete' : 'Kaufpreis' }}</span>
                                    <span class="text-[15px] font-black text-gray-900">
                                        {{ (activeApartment.marketing_type === 'Miete' ? activeApartment.warm_rent : activeApartment.price)
                                            ? new Intl.NumberFormat('de-DE').format(activeApartment.marketing_type === 'Miete' ? activeApartment.warm_rent : activeApartment.price) + ' EUR'
                                            : 'auf Anfrage' }}
                                    </span>
                                </div>
                                <template v-if="activeApartment.marketing_type === 'Miete'">
                                    <div class="bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex flex-col gap-0.5">
                                        <span class="text-[11px] font-bold text-gray-400 uppercase">Kaltmiete</span>
                                        <span class="text-[14px] font-black text-gray-800">{{ activeApartment.price ? new Intl.NumberFormat('de-DE').format(activeApartment.price) + ' €' : '–' }}</span>
                                    </div>
                                    <div class="bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex flex-col gap-0.5">
                                        <span class="text-[11px] font-bold text-gray-400 uppercase">NK-Akonto</span>
                                        <span class="text-[14px] font-black text-gray-800">{{ activeApartment.additional_costs ? new Intl.NumberFormat('de-DE').format(activeApartment.additional_costs) + ' €' : '–' }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Eigenschaften Block -->
                        <div class="bg-[#fcfaf9] rounded-[16px] p-5 shadow-[0_2px_4px_rgba(0,0,0,0.01)] border border-gray-100">
                            <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-4">Eigenschaften</h4>
                            
                            <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                                <div class="flex flex-col text-center items-center">
                                    <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                    <span class="text-[14px] font-black tracking-tight text-gray-900 mb-[2px]">{{ Math.round(activeApartment.sqm * 10) / 10 }} m²</span>
                                    <span class="text-[11px] font-semibold text-gray-500 leading-none">Fläche</span>
                                </div>
                                <div class="flex flex-col text-center items-center">
                                    <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    <span class="text-[14px] font-black tracking-tight text-gray-900 mb-[2px]">{{ project.floors?.find(f => f.id === activeApartment.floor_id)?.name || 'EG' }}</span>
                                    <span class="text-[11px] font-semibold text-gray-500 leading-none">Geschoss</span>
                                </div>
                                <div class="flex flex-col text-center items-center">
                                    <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                                    <span class="text-[14px] font-black tracking-tight text-gray-900 mb-[2px]">{{ activeApartment.rooms }}</span>
                                    <span class="text-[11px] font-semibold text-gray-500 leading-none">Zimmer</span>
                                </div>
                                <div class="flex flex-col text-center items-center">
                                    <svg class="w-5 h-5 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-[14px] font-black tracking-tight text-gray-900 mb-[2px]">{{ activeApartment.available_from || 'Sofort' }}</span>
                                    <span class="text-[11px] font-semibold text-gray-500 leading-none">Bezugstermin</span>
                                </div>
                            </div>
                        </div>

                        <!-- Räume Box -->
                        <div v-if="activeApartment.rooms_list?.length" class="bg-[#fcfaf9] rounded-[16px] p-5 shadow-[0_2px_4px_rgba(0,0,0,0.01)] border border-gray-100">
                            <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-3">Raumaufteilung</h4>
                            <div class="divide-y divide-gray-100">
                                <div v-for="room in activeApartment.rooms_list" :key="room.id" class="flex justify-between items-center py-2">
                                    <span class="text-[13px] font-medium text-gray-700">{{ room.name }}</span>
                                    <span v-if="room.sqm" class="text-[13px] font-black text-gray-900 tabular-nums">{{ room.sqm }} m²</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200">
                                <span class="text-[12px] font-bold text-gray-500">Gesamt</span>
                                <span class="text-[14px] font-black text-gray-800">{{ activeApartment.rooms_list.reduce((s, r) => s + (parseFloat(r.sqm) || 0), 0).toFixed(1) }} m²</span>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Sticky Action Footer inside Slide-In -->
                    <div v-if="activeApartment" class="p-5 w-full bg-white border-t border-gray-100 sticky bottom-0 z-[72]">
                        <button @click="openContactForm" class="w-full bg-[#99adc4] text-white py-3.5 rounded-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#869cb4] transition shadow-md">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            {{ project.contact_form_config?.title || 'Jetzt bewerben' }}
                        </button>
                    </div>
                </div>
            </transition>

            <!-- =================== MERKLISTE SLIDE-IN =================== -->
            <transition name="slide-forward">
                <div v-if="showWishlist" class="absolute inset-0 z-[80] bg-white flex flex-col h-full w-full">
                    <!-- Header -->
                    <div class="pt-6 px-6 pb-4 shrink-0 flex items-center gap-4 bg-white sticky top-0 z-[81] border-b border-gray-100">
                        <button @click="showWishlist = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-[#ab715c] transition shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        </button>
                        <div>
                            <span class="text-[10px] font-bold tracking-widest text-[#ab715c] uppercase">Meine Auswahl</span>
                            <h2 class="text-[20px] font-black tracking-tight text-gray-900">Merkliste</h2>
                        </div>
                        <span v-if="favorites.length || tourFavorites.length" class="ml-2 bg-[#ab715c] text-white text-[12px] font-black rounded-full px-2.5 py-0.5">{{ favorites.length + tourFavorites.length }}</span>
                        
                        <!-- Teilen Button (Neu) -->
                        <button v-if="favorites.length || tourFavorites.length" @click="copyShareLink('merkliste')" class="ml-auto w-10 h-10 flex items-center justify-center rounded-full bg-gray-50 hover:bg-[#ab715c] text-gray-400 hover:text-white transition" title="Merkliste teilen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                        </button>
                    </div>

                    <!-- List -->
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">
                        <div v-if="!favorites.length && !tourFavorites.length" class="flex flex-col items-center justify-center h-full text-center gap-3 pb-20">
                            <svg class="w-12 h-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                            <p class="text-gray-400 font-semibold">Noch keine Objekte gemerkt.</p>
                            <p class="text-gray-300 text-sm">Klicke bei einer Wohnung oder in der Tour auf das Herz-Icon.</p>
                        </div>
                        
                        <div v-if="favorites.length" class="flex flex-col gap-3">
                            <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-1" v-if="tourFavorites.length">Wohnungen</h4>
                            <div v-for="apt in project.apartments.filter(a => favorites.includes(a.id))" :key="apt.id"
                                class="w-full bg-white rounded-[16px] shadow-sm border border-gray-100 p-3.5 flex gap-4 cursor-pointer hover:shadow-md transition group relative"
                                @click="showWishlist = false; openApartment(apt.id)">
                                <!-- Avatar -->
                                <div class="relative w-14 h-14 shrink-0">
                                    <img :src="apt.media?.[0]?.original_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=150&q=80'" class="w-full h-full object-cover rounded-full border border-gray-100 shadow-sm" />
                                    <button @click.stop="toggleFavorite(apt.id)" class="absolute -top-1 -right-1 bg-white rounded-full p-1 shadow-sm border border-gray-100 flex items-center justify-center hover:scale-110 transition">
                                        <svg class="w-3.5 h-3.5 text-[#ab715c]" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    </button>
                                </div>
                                <!-- Info -->
                                <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <span class="text-[15px] font-bold text-gray-800 truncate pr-2">{{ apt.name }}</span>
                                        <div class="bg-[#dcf0d5] text-[#3f6327] px-2 py-0.5 rounded-full font-bold shrink-0" style="font-size:10px;">
                                            <template v-if="apt.price">{{ new Intl.NumberFormat('de-DE').format(apt.price) }} EUR</template>
                                            <template v-else>auf Anfrage</template>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-[12px] text-gray-500 font-semibold">
                                        <span>{{ Math.round(apt.sqm * 10) / 10 }} m²</span>
                                        <span class="text-gray-200">|</span>
                                        <span>{{ project.floors?.find(f => f.id === apt.floor_id)?.name || 'EG' }}</span>
                                        <span class="text-gray-200">|</span>
                                        <span>{{ apt.rooms }} Zi.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="tourFavorites.length" class="flex flex-col gap-3">
                            <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-1" v-if="favorites.length">Virtuelle Touren</h4>
                            <div v-for="tFav in tourFavorites" :key="tFav.key"
                                class="w-full bg-white rounded-[16px] shadow-sm border border-gray-100 p-3.5 flex gap-4 cursor-pointer hover:shadow-md transition group relative"
                                @click="showWishlist = false; openTourPopup(tFav.id, tFav.yaw, tFav.pitch)">
                                <!-- Icon -->
                                <div class="relative w-14 h-14 shrink-0 bg-gray-50 flex items-center justify-center rounded-full border border-gray-100 shadow-sm">
                                    <svg class="w-6 h-6 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <button @click.stop="toggleTourFavoriteByObj(tFav)" class="absolute -top-1 -right-1 bg-white rounded-full p-1 shadow-sm border border-gray-100 flex items-center justify-center hover:scale-110 transition">
                                        <svg class="w-3.5 h-3.5 text-[#ab715c]" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    </button>
                                </div>
                                <!-- Info -->
                                <div class="flex-1 min-w-0 flex flex-col py-1 justify-center">
                                    <span class="text-[15px] font-bold text-gray-800 truncate pr-2">{{ tFav.name }}</span>
                                    <span class="text-[12px] text-gray-400 font-semibold mt-0.5">Gespeicherter Blickwinkel</span>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Sticky Footer (Merkliste) -->
                    <div class="px-5 py-4 w-full bg-white border-t border-gray-100 sticky bottom-0 z-[82]" v-if="favorites.length || tourFavorites.length">
                        <button @click="showWishlist = false; openContactForm()" class="w-full bg-[#ab715c] text-white py-3.5 rounded-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#8e5c4a] transition shadow-md">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            {{ project.contact_form_config?.title || 'Jetzt bewerben' }}
                        </button>
                    </div>
                </div>
            </transition>

            <!-- =================== FILTER POPUP =================== -->
            <transition name="fade">
                <div v-if="showFiltersPopup" class="absolute inset-0 z-[90] flex flex-col" @click.self="showFiltersPopup = false">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-black/40" @click="showFiltersPopup = false"></div>
                    <!-- Panel from bottom -->
                    <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[24px] shadow-2xl z-[91] max-h-[85%] flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                            <h3 class="text-[17px] font-black tracking-tight text-gray-900">Filter</h3>
                            <button @click="showFiltersPopup = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 transition">
                                <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-6">
                            <!-- Etagen -->
                            <div v-if="uniqueFloors.length">
                                <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    Etage
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <label v-for="floor in uniqueFloors" :key="floor.id" :class="['flex items-center gap-2 px-3 py-1.5 rounded-full border text-[13px] font-semibold cursor-pointer transition', eventBus.filters.floors.includes(floor.id) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#ab715c]']">
                                        <input type="checkbox" :value="floor.id" v-model="eventBus.filters.floors" class="hidden" />
                                        {{ floor.name }}
                                    </label>
                                </div>
                            </div>
                            <!-- Zimmer -->
                            <div v-if="uniqueRooms.length">
                                <h4 class="text-[12px] font-bold text-gray-400 uppercase tracking-widest mb-3">Zimmer</h4>
                                <div class="flex flex-wrap gap-2">
                                    <label v-for="r in uniqueRooms" :key="r" :class="['flex items-center gap-2 px-3 py-1.5 rounded-full border text-[13px] font-semibold cursor-pointer transition', eventBus.filters.rooms.includes(r) ? 'bg-[#ab715c] text-white border-[#ab715c]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#ab715c]']">
                                        <input type="checkbox" :value="r" v-model="eventBus.filters.rooms" class="hidden" />
                                        {{ r }} Zi.
                                    </label>
                                </div>
                            </div>
                            <!-- Nur Verfügbare -->
                            <div>
                                <button @click="availabilityOnly = !availabilityOnly"
                                    :class="['w-full flex items-center justify-between px-4 py-3 rounded-[12px] border text-[14px] font-bold transition', availabilityOnly ? 'bg-[#dcf0d5] text-[#3f6327] border-[#b2d9a1]' : 'bg-white text-gray-600 border-gray-200']">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        Nur verfügbare Wohnungen
                                    </span>
                                    <div :class="['w-5 h-5 rounded-full border-2 flex items-center justify-center transition', availabilityOnly ? 'bg-[#3f6327] border-[#3f6327]' : 'border-gray-300']">
                                        <svg v-if="availabilityOnly" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-100 shrink-0 flex gap-3">
                            <button @click="resetFilters; showFiltersPopup = false" class="flex-1 py-3 rounded-[12px] border border-gray-200 text-[14px] font-bold text-gray-500 hover:bg-gray-50 transition">Zurücksetzen</button>
                            <button @click="showFiltersPopup = false; scrollToApartments()" class="flex-1 bg-[#ab715c] text-white py-3 rounded-[12px] text-[14px] font-bold hover:bg-[#8e5c4a] transition shadow-md">
                                {{ filteredApartmentsCount }} Anzeigen
                            </button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Compare Floating Banner -->
            <transition name="slide-up">
                <div v-show="compareList.length > 0" class="shrink-0 bg-[#ab715c] text-white px-4 py-3 border-t border-[#966350] flex items-center justify-between shadow-[0_-15px_20px_rgba(0,0,0,0.05)] z-[65] relative">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        <div>
                            <span class="text-[13px] font-bold block">{{ compareList.length }} von 3 Wohnungen</span>
                            <span class="text-[10px] text-white/80 block -mt-0.5">für Vergleich markiert</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="showCompareModal = true" class="bg-white text-[#ab715c] px-3 py-1.5 rounded-[8px] text-[12px] font-bold shadow-sm hover:bg-gray-50 transition active:scale-95">Vergleich Starten</button>
                        <button @click="compareList = []" class="p-1 rounded-full hover:bg-white/20 transition active:scale-95" title="Leeren">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </transition>

            <!-- Sticky Footer Area mit Filter Count (Nur wenn NICHT in Detailansicht!) -->
            <div v-show="!activeApartment" class="px-5 md:px-6 py-3 shrink-0 flex items-center gap-2 sticky bottom-0 bg-[#fbfbfb] z-[60] shadow-[0_-15px_20px_rgba(0,0,0,0.02)] w-full border-t border-gray-100">
                <!-- Ohne Filter -->
                <button @click="resetFilters" class="text-[13px] font-bold text-gray-400 hover:text-gray-800 transition whitespace-nowrap px-1">Filter zurücksetzen</button>
                
                <!-- Verfügbarkeit Toggle -->
                <button @click="availabilityOnly = !availabilityOnly"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-bold border transition ml-auto shrink-0', availabilityOnly ? 'bg-[#dcf0d5] text-[#3f6327] border-[#b2d9a1]' : 'bg-white text-gray-500 border-gray-200 hover:border-[#ab715c]']">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Nur verfügbare
                </button>

                <button @click="scrollToApartments" class="bg-[#ab715c] text-white px-4 py-2 rounded-full font-bold text-[13px] flex items-center shadow-md hover:bg-[#8e5c4a] transition whitespace-nowrap shrink-0">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    {{ filteredApartmentsCount }} Anzeigen
                </button>
            </div>
            
        </div>

        <!-- Right Content Area (Fills remaining space) -->
        <div class="w-full flex-1 h-full min-h-0 bg-white relative flex flex-col overflow-hidden">
            <!-- View Selector Switch Container -->
            <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[70] bg-white shadow-[0_5px_20px_rgba(0,0,0,0.1)] border border-gray-100 rounded-3xl md:rounded-full flex flex-col md:flex-row p-1 transition-all duration-300 pointer-events-auto cursor-pointer md:cursor-default" :class="isSidebarOpen ? '' : 'top-6'" @click="showMobileViewDropdown = !showMobileViewDropdown">
                <button 
                    :class="['px-5 py-2.5 rounded-full text-[13px] font-bold transition whitespace-nowrap flex items-center justify-center gap-2', eventBus.activeView === '3d-finder' ? 'bg-[#2b2b2b] text-white shadow-sm order-first md:order-none' : 'text-gray-600 hover:bg-gray-50 mt-1 md:mt-0', (eventBus.activeView !== '3d-finder' && !showMobileViewDropdown) ? 'hidden md:flex' : 'flex']"
                    @click.stop="eventBus.activeView === '3d-finder' ? showMobileViewDropdown = !showMobileViewDropdown : (eventBus.setView('3d-finder'), showMobileViewDropdown = false)" 
                >
                    <svg class="w-[18px] h-[18px]" :class="eventBus.activeView === '3d-finder' ? 'text-white' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2-1m2 1l-2 1m2-1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" /></svg>
                    3D Finder
                    <svg v-if="eventBus.activeView === '3d-finder'" class="w-4 h-4 ml-1 opacity-60 md:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <button 
                    :class="['px-5 py-2.5 rounded-full text-[13px] font-bold transition whitespace-nowrap flex items-center justify-center gap-2', eventBus.activeView === 'etagenansicht' ? 'bg-[#2b2b2b] text-white shadow-sm order-first md:order-none' : 'text-gray-600 hover:bg-gray-50 mt-1 md:mt-0', (eventBus.activeView !== 'etagenansicht' && !showMobileViewDropdown) ? 'hidden md:flex' : 'flex']"
                    @click.stop="eventBus.activeView === 'etagenansicht' ? showMobileViewDropdown = !showMobileViewDropdown : (eventBus.setView('etagenansicht'), showMobileViewDropdown = false)" 
                >
                    <svg class="w-[18px] h-[18px]" :class="eventBus.activeView === 'etagenansicht' ? 'text-white' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    Etagenansicht
                    <svg v-if="eventBus.activeView === 'etagenansicht'" class="w-4 h-4 ml-1 opacity-60 md:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>

            <!-- Dynamic Views -->
            <div class="flex-1 w-full h-full relative">
                <ThreeDFinder v-if="eventBus.activeView === '3d-finder'" :project="project" :active-apartment-id="activeApartment?.id" @apartment-click="openApartment" @deselect="closeApartment" @slider-click="openSliderPopup" @tour-click="openTourPopup" @video-click="v => activeVideo = v" />
                <FloorPlanView v-if="eventBus.activeView === 'etagenansicht'" :project="project" :active-apartment-id="activeApartment?.id" @apartment-click="openApartment" @deselect="closeApartment" @slider-click="openSliderPopup" @view-click="switchToView" @tour-click="openTourPopup" @video-click="v => activeVideo = v" />
            </div>
        </div>

        <!-- Image Group Fullscreen Slider -->
        <transition name="fade">
            <div v-if="activeImageGroup" class="fixed inset-0 z-[120] bg-black/95 flex flex-col backdrop-blur-md">
                <div class="px-6 py-4 flex justify-between items-center text-white shrink-0 border-b border-white/10">
                    <div>
                        <h3 class="font-bold text-[19px]">{{ activeImageGroup.name }}</h3>
                        <p class="text-[13px] text-gray-400 font-medium">Bild {{ activeSlideIndex + 1 }} von {{ activeImageGroup.media?.length }}</p>
                    </div>
                    <button @click="activeImageGroup = null" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="flex-1 relative flex items-center justify-center overflow-hidden w-full h-full p-6">
                    <button v-if="activeSlideIndex > 0" @click="prevSlide" class="absolute left-6 w-12 h-12 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/80 text-white z-10 transition border border-white/20 shadow-lg">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    
                    <img :key="activeSlideIndex" :src="activeImageGroup.media[activeSlideIndex].original_url" class="max-w-full max-h-full object-contain drop-shadow-2xl rounded-sm" alt="Slide" />
                    
                    <button v-if="activeSlideIndex < (activeImageGroup.media?.length || 1) - 1" @click="nextSlide" class="absolute right-6 w-12 h-12 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/80 text-white z-10 transition border border-white/20 shadow-lg">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
            </div>
        </transition>

        <!-- Virtual Tour Modal -->
        <transition name="fade">
            <div v-if="activeTourPoint" class="fixed inset-0 z-[200] bg-black/95 flex flex-col backdrop-blur-md">
                <div class="px-6 py-4 flex justify-between items-center text-white shrink-0 border-b border-white/10 relative z-50 pointer-events-none">
                    <div class="pointer-events-auto">
                        <h3 class="font-bold text-[19px]">{{ activeTourPoint.name }}</h3>
                        <p class="text-[13px] text-gray-400 font-medium tracking-wide">Virtuelle Tour - 360°</p>
                    </div>
                    <div class="pointer-events-auto flex items-center gap-3">
                        <button @click="toggleTourFavorite()" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 transition shadow-xl" :class="isTourFavorite() ? 'text-[#ab715c]' : 'hover:bg-[#ab715c] text-white'" title="Blickwinkel merken">
                            <svg class="w-5 h-5" :fill="isTourFavorite() ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                        </button>
                        <button @click="copyShareLink('tour', activeTourPoint.id)" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-[#ab715c] transition shadow-xl text-white" title="Deep Link kopieren">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                        </button>
                        <button @click="activeTourPoint = null" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition shadow-xl text-white outline-none">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                
                <div class="flex-1 relative">
                    <PublicHotspotViewer :point="activeTourPoint" :project="project" @action="handleTourAction" @position-changed="handleTourPositionChange" />
                </div>
            </div>
        </transition>

        <!-- Tooltip Modal -->
        <transition name="fade">
            <div v-if="activeTooltip" class="fixed inset-0 z-[150] bg-black/40 flex items-center justify-center p-4 backdrop-blur-sm" @click.self="activeTooltip = null">
                <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8 max-w-sm w-full relative">
                    <button @click="activeTooltip = null" class="absolute top-4 right-4 text-gray-400 hover:text-black transition">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <div class="mt-2 text-gray-700 text-center whitespace-pre-wrap font-medium">
                        {{ activeTooltip }}
                    </div>
                </div>
            </div>
        </transition>

        <!-- IFrame Modal -->
        <transition name="fade">
            <div v-if="activeIframe" class="fixed inset-0 z-[160] bg-black/80 flex items-center justify-center p-4 md:p-10 backdrop-blur-md" @click.self="activeIframe = null">
                <div class="bg-white rounded-xl shadow-2xl overflow-hidden w-full max-w-6xl h-full flex flex-col relative">
                    <div class="bg-gray-100 flex justify-end p-2 border-b">
                        <button @click="activeIframe = null" class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-gray-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <iframe :src="activeIframe" class="w-full flex-1 border-0" allowfullscreen></iframe>
                </div>
            </div>
        </transition>

        <!-- Video Modal -->
        <transition name="fade">
            <div v-if="activeVideo" class="fixed inset-0 z-[160] bg-black/80 flex items-center justify-center p-4 md:p-10 backdrop-blur-md" @click.self="activeVideo = null">
                <div class="bg-black rounded-xl shadow-2xl overflow-hidden w-full max-w-4xl aspect-video flex flex-col relative">
                    <button @click="activeVideo = null" class="absolute top-2 right-2 z-10 w-8 h-8 rounded-full bg-white/10 hover:bg-white/30 flex items-center justify-center text-white transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <iframe :src="activeVideo" class="w-full h-full border-0" allowfullscreen allow="autoplay; encrypted-media; picture-in-picture"></iframe>
                </div>
            </div>
        </transition>


        <div v-if="project.floating_bar?.active && project.floating_bar?.buttons?.length" 
             class="fixed z-[90] flex flex-col items-center gap-3 transition-all duration-500"
             :class="{
                'right-6 top-1/2 -translate-y-1/2': project.floating_bar.position === 'right',
                'right-6 bottom-8': project.floating_bar.position === 'right_bottom',
                'left-6 top-1/2 -translate-y-1/2': project.floating_bar.position === 'left',
                'left-6 bottom-8': project.floating_bar.position === 'left_bottom',
             }">
             
            <!-- Optional Logo -->
            <div v-if="projectLogo && project.floating_bar.show_logo !== false">
                <img :src="projectLogo.original_url" alt="Logo" class="object-contain drop-shadow-sm" 
                     :style="{ maxWidth: (project.floating_bar.logo_width || 80) + 'px' }" />
            </div>

            <!-- Floating Pill Base -->
            <div class="flex flex-col gap-2 p-2 rounded-full shadow-[0_10px_40px_rgba(0,0,0,0.15)] backdrop-blur-md border border-white/20"
                 :style="{ backgroundColor: (project.floating_bar.bg_color || '#ffffff') + 'dd' }">
                 
                <div v-for="btn in project.floating_bar.buttons" :key="btn.id" class="relative group isolate">
                 
                <!-- Hover Label Pill -->
                <div class="absolute top-1/2 -translate-y-1/2 pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center shadow-lg rounded-full font-bold text-[13px] whitespace-nowrap overflow-hidden z-0"
                     :class="[
                        project.floating_bar.position.startsWith('right') ? 'right-0 pr-12 pl-5 py-2.5 translate-x-[15px] group-hover:translate-x-0' : 'left-0 pl-12 pr-5 py-2.5 -translate-x-[15px] group-hover:translate-x-0'
                     ]"
                     :style="{ backgroundColor: project.floating_bar.active_color || '#ab715c', color: project.floating_bar.text_color || '#ffffff' }">
                     {{ btn.label }}
                </div>
                
                <!-- Base Circle Background for Hover -->
                <div class="absolute inset-0 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-0"
                     :style="{ backgroundColor: project.floating_bar.active_color || '#ab715c' }"></div>
                     
                <!-- Icon Button -->
                <button @click="handleCustomButton(btn)" 
                        class="relative z-10 w-11 h-11 flex items-center justify-center rounded-full transition-all duration-300 bg-transparent"
                        @mouseenter="$event.currentTarget.style.color = project.floating_bar.text_color || '#ffffff'"
                        @mouseleave="$event.currentTarget.style.color = project.floating_bar.active_color || '#ab715c'"
                        :style="{ color: project.floating_bar.active_color || '#ab715c' }"
                        :title="btn.label">
                    
                    <svg v-if="btn.icon_type === 'icon_info'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <svg v-else-if="btn.icon_type === 'icon_mail'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <svg v-else-if="btn.icon_type === 'icon_map'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>
                    <svg v-else-if="btn.icon_type === 'icon_compass'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2.83-2.83m-2.83 2.83l-2.83 2.83m2.83-2.83a4 4 0 11-5.66 5.66 4 4 0 015.66-5.66M12 4v0m0 16v0m-8-8h0m16 0h0" /></svg>
                    <svg v-else-if="btn.icon_type === 'icon_project'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    <div v-else-if="btn.icon_type === 'custom_svg'" class="flex items-center justify-center w-5 h-5" v-html="btn.custom_svg || '<svg fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1\'/></svg>'"></div>
                </button>
            </div>
          </div>
        </div>
        <!-- Contact Form Modal -->
        <DialogModal :show="showContactForm" @close="showContactForm = false" maxWidth="lg">
            <template #title>
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    <span>{{ project.contact_form_config?.title || 'Kontaktanfrage' }}</span>
                </div>
            </template>
            <template #content>
                <p class="text-[13px] text-gray-500 mb-6 font-medium leading-relaxed">{{ project.contact_form_config?.subtitle || 'Möchten Sie weitere Informationen erhalten? Kontaktieren Sie uns.' }}</p>
                <form @submit.prevent="submitContactForm" class="grid grid-cols-2 gap-4">
                    <div v-for="field in (project.contact_form_config?.fields || [])" :key="field.id" :class="field.width === 'half' ? 'col-span-1' : 'col-span-2'">
                        <InputLabel :for="'contact_'+field.id">
                            {{ field.label }} 
                            <span v-if="field.required" class="text-red-500 ml-0.5">*</span>
                        </InputLabel>
                        <textarea v-if="field.type === 'textarea'" :id="'contact_'+field.id" v-model="contactForm.fields[field.id]" :required="field.required" rows="4" class="mt-1 block w-full border-gray-300 focus:border-[#ab715c] focus:ring-[#ab715c] rounded-[10px] shadow-sm text-sm"></textarea>
                        <TextInput v-else :type="field.type" :id="'contact_'+field.id" v-model="contactForm.fields[field.id]" :required="field.required" class="mt-1 block w-full rounded-[10px]" />
                        <!-- Assuming dynamic error handling by iterating object is complex, generic error for whole form if needed -->
                    </div>
                    
                    <div class="col-span-2 mt-4 pt-4 border-t border-gray-100 flex gap-3">
                        <SecondaryButton @click="showContactForm = false" type="button" class="w-full justify-center py-3 rounded-[10px] shadow-sm border-gray-200">Abbrechen</SecondaryButton>
                        <button type="submit" :disabled="contactForm.processing" class="w-full bg-[#ab715c] text-white py-3 rounded-[10px] shadow-sm font-bold flex items-center justify-center transition" :class="contactForm.processing ? 'opacity-50' : 'hover:bg-[#8e5c4a]'">Anfrage senden</button>
                    </div>
                </form>
            </template>
        </DialogModal>

        <ApartmentCompareModal
            :show="showCompareModal"
            :apartments="compareList"
            :project="project"
            @close="showCompareModal = false"
            @remove="id => toggleCompare({ id })"
            @select="id => { showCompareModal = false; openApartment(id); }"
        />

    </div>
</template>

<style>
/* 
 * Dynamic Branding Colors Override using Vue 3 v-bind feature.
 * This overrides Tailwind classes across the entire page safely.
 */
.text-\[\#ab715c\] { color: v-bind('primaryBase') !important; }
.bg-\[\#ab715c\] { background-color: v-bind('primaryBase') !important; }
.border-\[\#ab715c\] { border-color: v-bind('primaryBase') !important; }
.ring-\[\#ab715c\] { --tw-ring-color: v-bind('primaryBase') !important; }
.focus\:border-\[\#ab715c\]:focus { border-color: v-bind('primaryBase') !important; }
.focus\:ring-\[\#ab715c\]:focus { --tw-ring-color: v-bind('primaryBase') !important; }
.hover\:text-\[\#ab715c\]:hover { color: v-bind('primaryHover') !important; }
.hover\:bg-\[\#ab715c\]:hover { background-color: v-bind('primaryHover') !important; }
.hover\:border-\[\#ab715c\]:hover { border-color: v-bind('primaryHover') !important; }

.text-\[\#96624f\] { color: v-bind('primaryHover') !important; }
.bg-\[\#96624f\] { background-color: v-bind('primaryHover') !important; }
.border-\[\#96624f\] { border-color: v-bind('primaryHover') !important; }
.hover\:text-\[\#96624f\]:hover { color: v-bind('primaryHover') !important; }
.hover\:bg-\[\#96624f\]:hover { background-color: v-bind('primaryHover') !important; }
.hover\:border-\[\#96624f\]:hover { border-color: v-bind('primaryHover') !important; }
</style>

<style scoped>
/* Transition for slide-forward (left to right layer) */
.slide-forward-enter-active, .slide-forward-leave-active {
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-forward-enter-from, .slide-forward-leave-to {
    transform: translateX(-100%);
}

/* Transition for filter popup (slide up from bottom) */
.slide-up-enter-active, .slide-up-leave-active {
    transition: opacity 0.3s ease;
}
.slide-up-enter-active .relative, .slide-up-leave-active .relative {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-up-enter-from, .slide-up-leave-to {
    opacity: 0;
}
.slide-up-enter-from .relative {
    transform: translateY(40px);
}
.slide-up-leave-to .relative {
    transform: translateY(40px);
}

/* Hide scrollbar for cleaner look in the sidebar */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

/* Fancy fade for popup */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 1s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes load {
    from { width: 0%; }
    to { width: 100%; }
}
.animate-\[load_5s_linear\] {
    animation: load 5s linear forwards;
}
</style>
