<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    MagnifyingGlassIcon,
    FunnelIcon,
    TrashIcon,
    ChevronDownIcon,
    EnvelopeIcon,
    PhoneIcon,
    HomeIcon,
    BuildingOfficeIcon,
    ChatBubbleLeftRightIcon,
    ArrowTrendingUpIcon,
    UserIcon,
    ClockIcon,
    SparklesIcon,
    ClipboardIcon,
    CheckIcon,
    ArrowDownTrayIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';
import DialogModal from '@/Components/DialogModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    inquiries: Object,
    counts:    Object,
    projects:  Array,
    filters:   Object,
});

// --- Filter state synced with URL ---
const search     = ref(props.filters?.search     || '');
const statusF    = ref(props.filters?.status     || '');
const projectF   = ref(props.filters?.project_id || '');

const applyFilters = () => {
    router.get(route('inquiries.index'), {
        search:     search.value || undefined,
        status:     statusF.value || undefined,
        project_id: projectF.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilters = () => {
    search.value = ''; statusF.value = ''; projectF.value = '';
    applyFilters();
};

// --- Status update ---
const openDetail      = ref(null);
const statusForm      = useForm({ status: '', notes: '' });

// --- AI Reply Drafter ---
const aiReplyDraft = ref('');
const isAiDrafting = ref(false);
const showCopyToast = ref(false);

const openInquiry = (inq) => {
    openDetail.value = inq;
    statusForm.status = inq.status;
    statusForm.notes  = inq.notes || '';
    aiReplyDraft.value = ''; // Reset on open
};

const generateReplyDraft = async () => {
    if (!openDetail.value) return;
    isAiDrafting.value = true;
    try {
        const v = openDetail.value.visitor;
        const favorites = v?.events?.filter(e => e.event_type === 'favorite')?.length || 0;
        const viewedCount = v?.apartments_viewed_count || 0;
        
        const prompt = `Erstelle einen persönlichen, professionellen Antwort-Entwurf (Deutsch) für diese Immobilien-Anfrage:
        Kunden-Nachricht: "${openDetail.value.message}"
        
        Zusatzinfo zum Lead-Verhalten:
        - Hat ${viewedCount} verschiedene Wohnungen angesehen.
        - Hat ${favorites} Favoriten markiert.
        - Projekt: ${openDetail.value.project?.name || 'Unbekannt'}
        
        Schreibe eine Antwort, die auf das Interesse eingeht und (falls passend) Bezug auf die besuchten Wohnungen nimmt. Sei freundlich und verkaufsorientiert. Respond ONLY with the draft content.`;

        const response = await fetch('/api/ai/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') },
            body: JSON.stringify({ prompt }),
        });
        const data = await response.json();
        if (data.success) {
            aiReplyDraft.value = data.content;
        }
    } catch (e) {
        console.error('AI Draft Error:', e);
    } finally {
        isAiDrafting.value = false;
    }
};

const copyToClipboard = () => {
    navigator.clipboard.writeText(aiReplyDraft.value);
    showCopyToast.value = true;
    setTimeout(() => showCopyToast.value = false, 2000);
};

const saveStatus = () => {
    statusForm.patch(route('inquiries.update-status', openDetail.value.id), {
        preserveScroll: true,
        onSuccess: () => { openDetail.value = null; },
    });
};

const inqToDelete = ref(null);
const confirmDelete = (id) => inqToDelete.value = id;
const doDelete = () => {
    router.delete(route('inquiries.destroy', inqToDelete.value), { 
        preserveScroll: true,
        onSuccess: () => inqToDelete.value = null
    });
};

// --- Bulk Selection ---
const selectedIds = ref([]);
const isAllSelected = computed(() => {
    if (!props.inquiries.data.length) return false;
    return props.inquiries.data.every(i => selectedIds.value.includes(i.id));
});
const toggleAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.inquiries.data.map(i => i.id);
    }
};
const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
};
const bulkAction = (action) => {
    if (!selectedIds.value.length) return;
    router.post(route('inquiries.bulk-action'), {
        ids: selectedIds.value,
        action,
    }, { preserveScroll: true, onSuccess: () => selectedIds.value = [] });
};

const openDetailPage = (inq) => {
    router.visit(route('inquiries.show', inq.id));
};

// --- Helpers ---
const statusConfig = {
    new:         { label: 'Neu',            bg: 'bg-blue-100',   text: 'text-blue-700' },
    in_progress: { label: 'In Bearbeitung', bg: 'bg-amber-100',  text: 'text-amber-700' },
    done:        { label: 'Erledigt',       bg: 'bg-green-100',  text: 'text-green-700' },
    rejected:    { label: 'Abgelehnt',      bg: 'bg-red-100',    text: 'text-red-700' },
};

const totalCount = computed(() =>
    Object.values(props.counts || {}).reduce((a, b) => a + b, 0)
);

const fmt = (d) => d ? new Date(d).toLocaleString('de-DE', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
}) : '–';
</script>

<template>
    <AppLayout title="Anfragen">
        <Head title="Anfragen" />

        <template #header>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <ChatBubbleLeftRightIcon class="w-5 h-5 text-brand-500" /> Anfragen
            </h2>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">

            <!-- KPI Summary -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <button @click="statusF=''; applyFilters()"
                        :class="['rounded-2xl p-4 text-left transition border-2', !statusF ? 'border-brand-400 bg-brand-50' : 'border-transparent bg-white hover:bg-gray-50']">
                    <div class="text-2xl font-black text-gray-800">{{ totalCount }}</div>
                    <div class="text-xs font-bold text-gray-500 mt-0.5">Alle</div>
                </button>
                <button v-for="(cfg, key) in statusConfig" :key="key"
                        @click="statusF = key; applyFilters()"
                        :class="['rounded-2xl p-4 text-left transition border-2', statusF === key ? 'border-brand-400' : 'border-transparent bg-white hover:bg-gray-50']"
                        :style="statusF === key ? '' : ''">
                    <div :class="['text-2xl font-black', cfg.text.replace('text-','text-')]">{{ counts[key] || 0 }}</div>
                    <div :class="['text-xs font-bold mt-0.5', cfg.text]">{{ cfg.label }}</div>
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
                <!-- Search -->
                <div class="relative flex-1 min-w-[200px]">
                    <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Name, E-Mail, Telefon ..."
                           class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500" />
                </div>
                <!-- Project filter -->
                <div class="min-w-[180px]">
                    <select v-model="projectF" @change="applyFilters"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500">
                        <option value="">Alle Projekte</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <!-- Status filter -->
                <div class="min-w-[160px]">
                    <select v-model="statusF" @change="applyFilters"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500">
                        <option value="">Alle Status</option>
                        <option v-for="(cfg, key) in statusConfig" :key="key" :value="key">{{ cfg.label }}</option>
                    </select>
                </div>
                <button @click="resetFilters" class="px-3 py-2 text-sm font-bold text-gray-400 hover:text-gray-700 transition">
                    Zurücksetzen
                </button>
                <a :href="route('export.inquiries', { project_id: projectF || undefined, status: statusF || undefined })" class="ml-auto px-3 py-2 text-sm font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5 transition">
                    <ArrowDownTrayIcon class="w-4 h-4" /> CSV Export
                </a>
            </div>

            <!-- Bulk Action Bar -->
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0 -translate-y-2">
                <div v-if="selectedIds.length" class="bg-indigo-600 text-white rounded-2xl p-4 flex items-center gap-3">
                    <span class="text-sm font-bold">{{ selectedIds.length }} ausgewählt</span>
                    <div class="flex gap-2 ml-auto">
                        <button @click="bulkAction('mark_done')" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 rounded-lg text-xs font-bold transition">Als erledigt</button>
                        <button @click="bulkAction('mark_rejected')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 rounded-lg text-xs font-bold transition">Abgelehnt</button>
                        <button @click="bulkAction('delete')" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 rounded-lg text-xs font-bold transition">Löschen</button>
                        <button @click="selectedIds = []" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-bold transition">Abwählen</button>
                    </div>
                </div>
            </Transition>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div v-if="!inquiries.data.length" class="py-20 text-center">
                    <ChatBubbleLeftRightIcon class="w-14 h-14 mx-auto text-gray-300 mb-4" />
                    <h3 class="text-lg font-black text-gray-500">Keine Anfragen gefunden</h3>
                    <p class="text-sm text-gray-400 mt-1">Passe deine Filter an oder warte auf neue Anfragen.</p>
                </div>
                <table v-else class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-3 w-10">
                                <input type="checkbox" :checked="isAllSelected" @change="toggleAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Anfragender</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lead Score</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bezug</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nachricht</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Datum</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="inq in inquiries.data" :key="inq.id"
                            :class="['hover:bg-gray-50 transition cursor-pointer', !inq.read_at ? 'bg-blue-50/40' : '', selectedIds.includes(inq.id) ? 'bg-indigo-50/50' : '']"
                            @click="openInquiry(inq)">
                            <!-- Checkbox -->
                            <td class="px-3 py-4" @click.stop>
                                <input type="checkbox" :checked="selectedIds.includes(inq.id)" @change="toggleSelect(inq.id)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </td>
                            <!-- Contact -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-black text-sm shrink-0">
                                        {{ inq.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-900 text-sm truncate">{{ inq.name }}</span>
                                            <span v-if="!inq.read_at" class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <a v-if="inq.email" :href="'mailto:' + inq.email" @click.stop class="flex items-center gap-1 text-xs text-brand-600 hover:underline">
                                                <EnvelopeIcon class="w-3 h-3" />{{ inq.email }}
                                            </a>
                                            <a v-if="inq.phone" :href="'tel:' + inq.phone" @click.stop class="flex items-center gap-1 text-xs text-gray-500">
                                                <PhoneIcon class="w-3 h-3" />{{ inq.phone }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <!-- Lead Score Column -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2" v-if="inq.visitor">
                                    <div class="w-10 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-full transition-all" :class="inq.visitor.lead_score >= 70 ? 'bg-red-500' : inq.visitor.lead_score >= 45 ? 'bg-orange-500' : 'bg-brand-400'" :style="{ width: inq.visitor.lead_score + '%' }"></div>
                                    </div>
                                    <span class="text-xs font-black" :class="inq.visitor.lead_score >= 70 ? 'text-red-600' : inq.visitor.lead_score >= 45 ? 'text-orange-600' : 'text-gray-900'">{{ inq.visitor.lead_score }}</span>
                                </div>
                                <div v-else class="text-[10px] text-gray-300">Unbekannt</div>
                            </td>
                            <!-- Reference -->
                            <td class="px-5 py-4">
                                <div class="space-y-0.5 text-xs">
                                    <div v-if="inq.project" class="flex items-center gap-1 font-semibold text-gray-700">
                                        <BuildingOfficeIcon class="w-3.5 h-3.5 text-brand-400" />{{ inq.project.name }}
                                    </div>
                                    <div v-if="inq.house" class="flex items-center gap-1 text-gray-500">
                                        <HomeIcon class="w-3 h-3" />{{ inq.house.name }}
                                    </div>
                                    <div v-if="inq.apartment" class="flex items-center gap-1 text-gray-500">
                                        <HomeIcon class="w-3 h-3" />{{ inq.apartment.name }}
                                    </div>
                                    <div v-if="!inq.project && !inq.house && !inq.apartment" class="text-gray-300">–</div>
                                </div>
                            </td>
                            <!-- Message preview -->
                            <td class="px-5 py-4 max-w-[220px]">
                                <p class="text-sm text-gray-500 line-clamp-2">{{ inq.message || '–' }}</p>
                            </td>
                            <!-- Status -->
                            <td class="px-5 py-4">
                                <span :class="['px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap',
                                    statusConfig[inq.status]?.bg, statusConfig[inq.status]?.text]">
                                    {{ statusConfig[inq.status]?.label }}
                                </span>
                            </td>
                            <!-- Date -->
                            <td class="px-5 py-4 text-xs text-gray-400 whitespace-nowrap">{{ fmt(inq.created_at) }}</td>
                            <!-- Actions -->
                            <td class="px-5 py-4" @click.stop>
                                <button @click="confirmDelete(inq.id)"
                                        class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-300 hover:text-red-500 transition">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="inquiries.last_page > 1" class="border-t border-gray-100 px-5 py-3 flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ inquiries.from }}–{{ inquiries.to }} von {{ inquiries.total }}</span>
                    <div class="flex gap-1">
                        <a v-if="inquiries.prev_page_url" :href="inquiries.prev_page_url"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-50 transition">← Zurück</a>
                        <a v-if="inquiries.next_page_url" :href="inquiries.next_page_url"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-50 transition">Weiter →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail / Edit Drawer -->
        <transition name="slide-right">
            <div v-if="openDetail" class="fixed inset-0 z-50 flex justify-end" @click.self="openDetail = null">
                <div class="absolute inset-0 bg-black/30" @click="openDetail = null"></div>
                <div class="relative bg-white w-full max-w-md h-full shadow-2xl flex flex-col z-10">
                    <!-- Header -->
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-black text-gray-900">{{ openDetail.name }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ fmt(openDetail.created_at) }}</p>
                        </div>
                        <button @click="openDetail = null" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Scroll body -->
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                        
                        <!-- NEW: Lead-Score Detail Header -->
                        <div v-if="openDetail.visitor" class="bg-gradient-to-br from-brand-50 to-indigo-50 border border-brand-100 rounded-2xl p-5 relative overflow-hidden">
                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-black text-brand-700 uppercase tracking-widest">Lead-Potenzial</h4>
                                    <span class="px-2 py-0.5 bg-brand-500 text-white text-[10px] font-black rounded-full shadow-sm">{{ openDetail.visitor.lead_label }}</span>
                                </div>
                                <div class="flex items-end gap-3 translate-y-1">
                                    <div class="text-4xl font-black text-gray-900 leading-none">{{ openDetail.visitor.lead_score }}</div>
                                    <div class="text-xs font-bold text-gray-500 mb-1">Punkte / 100</div>
                                </div>
                                <div class="w-full bg-gray-200/50 rounded-full h-1.5 mt-4 overflow-hidden">
                                     <div class="h-full transition-all duration-1000" :class="openDetail.visitor.lead_score >= 70 ? 'bg-red-500' : 'bg-brand-500'" :style="{ width: openDetail.visitor.lead_score + '%' }"></div>
                                </div>
                                
                                <div class="mt-4 grid grid-cols-2 gap-4 text-[10px] font-bold text-gray-500">
                                    <div class="flex items-center gap-1.5"><ChatBubbleLeftRightIcon class="w-3.5 h-3.5" /> {{ openDetail.visitor.visit_count_display }} Besuche</div>
                                    <div class="flex items-center gap-1.5"><BuildingOfficeIcon class="w-3.5 h-3.5" /> {{ openDetail.visitor.apartments_viewed_count }} Whg. angesehen</div>
                                </div>
                            </div>
                            <ArrowTrendingUpIcon class="absolute -right-4 -bottom-4 w-24 h-24 text-brand-100/40" />
                        </div>
                        <!-- Contact info -->
                        <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Kontaktdaten</h4>
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-bold text-gray-700 w-16 shrink-0">Name</span>
                                <span class="text-gray-600">{{ openDetail.name }}</span>
                            </div>
                            <div v-if="openDetail.email" class="flex items-center gap-2 text-sm">
                                <span class="font-bold text-gray-700 w-16 shrink-0">E-Mail</span>
                                <a :href="'mailto:' + openDetail.email" class="text-brand-600 hover:underline">{{ openDetail.email }}</a>
                            </div>
                            <div v-if="openDetail.phone" class="flex items-center gap-2 text-sm">
                                <span class="font-bold text-gray-700 w-16 shrink-0">Telefon</span>
                                <a :href="'tel:' + openDetail.phone" class="text-brand-600 hover:underline">{{ openDetail.phone }}</a>
                            </div>
                        </div>

                        <!-- Reference -->
                        <div v-if="openDetail.project || openDetail.house || openDetail.apartment"
                             class="bg-gray-50 rounded-xl p-4 space-y-2">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Bezug</h4>
                            <div v-if="openDetail.project" class="flex items-center gap-2 text-sm">
                                <BuildingOfficeIcon class="w-4 h-4 text-brand-400" />
                                <span class="font-semibold text-gray-700">{{ openDetail.project.name }}</span>
                                <span class="text-xs text-gray-400">Projekt</span>
                            </div>
                            <div v-if="openDetail.house" class="flex items-center gap-2 text-sm">
                                <HomeIcon class="w-4 h-4 text-gray-400" />
                                <span class="font-semibold text-gray-700">{{ openDetail.house.name }}</span>
                                <span class="text-xs text-gray-400">Haus</span>
                            </div>
                            <div v-if="openDetail.apartment" class="flex items-center gap-2 text-sm">
                                <HomeIcon class="w-4 h-4 text-gray-400" />
                                <span class="font-semibold text-gray-700">{{ openDetail.apartment.name }}</span>
                                <span class="text-xs text-gray-400">Wohnung</span>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div v-if="openDetail.visitor?.activity_timeline?.length" class="bg-gray-50 rounded-xl p-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <ClockIcon class="w-4 h-4" /> Besucher-Verlauf
                            </h4>
                            <div class="relative space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
                                <div v-for="(event, idx) in openDetail.visitor.activity_timeline.slice(0, 15)" :key="idx" class="relative pl-8">
                                    <div class="absolute left-0 top-0.5 w-4 h-4 rounded-full bg-white border-2 border-brand-500 z-10 flex items-center justify-center text-[8px]">
                                        {{ event.icon }}
                                    </div>
                                    <div class="flex justify-between items-start">
                                        <span class="text-xs font-bold text-gray-700">{{ event.label }}</span>
                                        <span class="text-[10px] text-gray-400 font-mono">{{ event.date }} {{ event.time }}</span>
                                    </div>
                                </div>
                                <p v-if="openDetail.visitor.activity_timeline.length > 15" class="text-[10px] text-gray-400 italic pl-8">
                                    + {{ openDetail.visitor.activity_timeline.length - 15 }} weitere Events...
                                </p>
                            </div>
                        </div>

                        <!-- Message -->
                        <div v-if="openDetail.message" class="bg-gray-50 rounded-xl p-4 relative group">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex justify-between items-center">
                                Nachricht
                                <button @click="generateReplyDraft" :disabled="isAiDrafting" class="text-brand-600 hover:text-brand-800 text-[10px] font-black flex items-center gap-1 bg-white px-2 py-1 rounded-lg border shadow-sm transition transform active:scale-95">
                                    <SparklesIcon v-if="!isAiDrafting" class="w-3 h-3" />
                                    <svg v-else class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    {{ isAiDrafting ? 'KI entwirft...' : 'KI: Antwort entwerfen' }}
                                </button>
                            </h4>
                            <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ openDetail.message }}</p>
                            
                            <!-- AI Draft Result -->
                            <transition name="fade">
                                <div v-if="aiReplyDraft" class="mt-4 bg-brand-900 rounded-xl p-4 shadow-xl border border-white/10 relative overflow-hidden">
                                     <div class="absolute inset-0 bg-gradient-to-br from-brand-500/10 to-transparent pointer-events-none"></div>
                                     <div class="relative z-10">
                                         <div class="flex items-center justify-between mb-2">
                                             <span class="text-[10px] font-black text-brand-300 uppercase tracking-widest">KI Antwort-Entwurf</span>
                                             <button @click="copyToClipboard" class="text-white/70 hover:text-white transition flex items-center gap-1 text-[10px] font-bold">
                                                 <template v-if="showCopyToast">
                                                     <CheckIcon class="w-3 h-3 text-emerald-400" />
                                                     <span class="text-emerald-400">Kopiert!</span>
                                                 </template>
                                                 <template v-else>
                                                     <ClipboardIcon class="w-3 h-3" />
                                                     Kopieren
                                                 </template>
                                             </button>
                                         </div>
                                         <p class="text-[13px] text-brand-50 leading-relaxed italic">{{ aiReplyDraft }}</p>
                                     </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Status change -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Status ändern</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="(cfg, key) in statusConfig" :key="key"
                                        @click="statusForm.status = key"
                                        :class="['px-3 py-2.5 rounded-xl text-sm font-bold transition border-2',
                                            statusForm.status === key
                                                ? `border-current ${cfg.bg} ${cfg.text}`
                                                : 'border-gray-100 bg-gray-50 text-gray-500 hover:bg-gray-100']">
                                    {{ cfg.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Internal notes -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Interne Notizen</h4>
                            <textarea v-model="statusForm.notes" rows="4" placeholder="Notizen, Gesprächsprotokoll ..."
                                      class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500 resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="border-t border-gray-100 px-6 py-4 flex gap-3">
                        <button @click="openDetail = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-500 hover:bg-gray-50 transition">
                            Schließen
                        </button>
                        <button @click="saveStatus" :disabled="statusForm.processing"
                                class="flex-1 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold transition disabled:opacity-50">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Delete Confirmation Modal -->
        <DialogModal :show="!!inqToDelete" @close="inqToDelete = null">
            <template #title>Anfrage löschen</template>
            <template #content>
                Soll diese Anfrage wirklich unwiderruflich gelöscht werden?
            </template>
            <template #footer>
                <SecondaryButton @click="inqToDelete = null">Abbrechen</SecondaryButton>
                <DangerButton @click="doDelete" class="ml-3">Löschen</DangerButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.slide-right-enter-active, .slide-right-leave-active {
    transition: opacity 0.25s ease;
}
.slide-right-enter-active .relative, .slide-right-leave-active .relative {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-right-enter-from .relative { transform: translateX(100%); }
.slide-right-leave-to .relative   { transform: translateX(100%); }
.slide-right-enter-from, .slide-right-leave-to { opacity: 0; }
</style>
