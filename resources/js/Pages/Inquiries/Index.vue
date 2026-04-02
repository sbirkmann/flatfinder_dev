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
} from '@heroicons/vue/24/outline';

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

const openInquiry = (inq) => {
    openDetail.value = inq;
    statusForm.status = inq.status;
    statusForm.notes  = inq.notes || '';
};

const saveStatus = () => {
    statusForm.patch(route('inquiries.update-status', openDetail.value.id), {
        preserveScroll: true,
        onSuccess: () => { openDetail.value = null; },
    });
};

const deleteInquiry = (id) => {
    if (confirm('Anfrage wirklich löschen?')) {
        router.delete(route('inquiries.destroy', id), { preserveScroll: true });
    }
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
            </div>

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
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Anfragender</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bezug</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Nachricht</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Datum</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="inq in inquiries.data" :key="inq.id"
                            :class="['hover:bg-gray-50 transition cursor-pointer', !inq.read_at ? 'bg-blue-50/40' : '']"
                            @click="openInquiry(inq)">
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
                                <button @click="deleteInquiry(inq.id)"
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

                        <!-- Message -->
                        <div v-if="openDetail.message" class="bg-gray-50 rounded-xl p-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Nachricht</h4>
                            <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ openDetail.message }}</p>
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
