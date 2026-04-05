<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ClockIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    UserIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedAction = ref(props.filters?.action || '');
const selectedType = ref(props.filters?.subject_type || '');

const actionColors = {
    created: 'bg-green-100 text-green-700',
    updated: 'bg-blue-100 text-blue-700',
    deleted: 'bg-red-100 text-red-700',
    status_changed: 'bg-amber-100 text-amber-700',
    login: 'bg-indigo-100 text-indigo-700',
    inquiry_received: 'bg-rose-100 text-rose-700',
    inquiry_replied: 'bg-purple-100 text-purple-700',
    export: 'bg-cyan-100 text-cyan-700',
    media_uploaded: 'bg-teal-100 text-teal-700',
    media_deleted: 'bg-orange-100 text-orange-700',
};

const actionLabels = {
    created: 'Erstellt',
    updated: 'Aktualisiert',
    deleted: 'Gelöscht',
    status_changed: 'Status geändert',
    login: 'Angemeldet',
    inquiry_received: 'Anfrage erhalten',
    inquiry_replied: 'Anfrage beantwortet',
    export: 'Export',
    media_uploaded: 'Upload',
    media_deleted: 'Medien gelöscht',
};

const typeLabels = {
    'Project': 'Projekt',
    'Apartment': 'Wohnung',
    'Inquiry': 'Anfrage',
    'Contact': 'Kontakt',
    'Slider': 'Slider',
    'Feature': 'Ausstattung',
    'Integration': 'Integration',
};

const applyFilters = () => {
    const params = {};
    if (search.value) params.search = search.value;
    if (selectedAction.value) params.action = selectedAction.value;
    if (selectedType.value) params.subject_type = selectedType.value;

    window.location.href = route('activity-log.index', params);
};

const clearFilters = () => {
    search.value = '';
    selectedAction.value = '';
    selectedType.value = '';
    window.location.href = route('activity-log.index');
};

const getSubjectTypeLabel = (type) => {
    if (!type) return '';
    const base = type.replace('App\\Models\\', '');
    return typeLabels[base] || base;
};
</script>

<template>
    <AppLayout title="Aktivitätsprotokoll">
        <template #header>
            <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                <ClockIcon class="w-6 h-6 text-indigo-500" />
                Aktivitätsprotokoll
            </h2>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Filters -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Suche</label>
                            <div class="relative">
                                <MagnifyingGlassIcon class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input v-model="search" type="text" placeholder="Benutzer, Label..." class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" @keydown.enter="applyFilters" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Aktion</label>
                            <select v-model="selectedAction" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Alle</option>
                                <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Typ</label>
                            <select v-model="selectedType" class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Alle</option>
                                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg hover:bg-indigo-700 transition">
                            <FunnelIcon class="w-4 h-4 inline mr-1" /> Filtern
                        </button>
                        <button @click="clearFilters" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition">
                            <ArrowPathIcon class="w-4 h-4 inline mr-1" /> Zurücksetzen
                        </button>
                    </div>
                </div>

                <!-- Log Entries -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="divide-y divide-gray-100">
                        <div v-for="log in logs.data" :key="log.id" class="px-6 py-4 hover:bg-gray-50/50 transition flex items-center gap-4">
                            <!-- User Avatar -->
                            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                <UserIcon class="w-4 h-4 text-indigo-600" />
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900">
                                    <span class="font-bold">{{ log.user?.name || 'System' }}</span>
                                    hat
                                    <span class="px-1.5 py-0.5 rounded text-[11px] font-bold" :class="actionColors[log.action] || 'bg-gray-100 text-gray-600'">
                                        {{ actionLabels[log.action] || log.action }}
                                    </span>
                                    <span v-if="log.subject_type" class="text-gray-500"> · {{ getSubjectTypeLabel(log.subject_type) }}</span>
                                    <span v-if="log.subject_label" class="font-semibold text-gray-800"> "{{ log.subject_label }}"</span>
                                </p>
                                <p v-if="log.properties" class="text-xs text-gray-400 mt-0.5 truncate">
                                    <template v-if="log.properties.old && log.properties.new">
                                        {{ log.properties.old }} → {{ log.properties.new }}
                                    </template>
                                    <template v-else-if="log.properties.source">
                                        Quelle: {{ log.properties.source }}
                                    </template>
                                </p>
                            </div>

                            <!-- Timestamp -->
                            <div class="text-xs text-gray-400 shrink-0 text-right">
                                {{ new Date(log.created_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
                            </div>
                        </div>

                        <div v-if="!logs.data?.length" class="p-12 text-center text-gray-400">
                            <ClockIcon class="w-10 h-10 mx-auto mb-3 text-gray-300" />
                            <p class="text-sm">Noch keine Aktivitäten protokolliert.</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-500">
                            {{ logs.from }}–{{ logs.to }} von {{ logs.total }} Einträgen
                        </p>
                        <div class="flex gap-1">
                            <template v-for="link in logs.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1.5 text-xs rounded-lg border transition"
                                    :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
