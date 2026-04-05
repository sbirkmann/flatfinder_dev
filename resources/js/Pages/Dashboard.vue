<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
    FolderIcon, 
    ChatBubbleLeftRightIcon, 
    UsersIcon, 
    ArrowRightIcon,
    HomeModernIcon,
    EyeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ClockIcon,
    FireIcon,
    ArrowDownTrayIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    apartmentsByStatus: { type: Object, default: () => ({}) },
    visitorTrend: { type: Array, default: () => [] },
    inquiryTrend: { type: Array, default: () => [] },
    hotLeads: { type: Array, default: () => [] },
    recentProjects: { type: Array, default: () => [] },
    recentInquiries: { type: Array, default: () => [] },
    recentActivity: { type: Array, default: () => [] },
});

const statusColors = {
    'Frei': { bg: 'bg-emerald-500', text: 'text-emerald-700', light: 'bg-emerald-100' },
    'Reserviert': { bg: 'bg-amber-500', text: 'text-amber-700', light: 'bg-amber-100' },
    'Verkauft': { bg: 'bg-red-500', text: 'text-red-700', light: 'bg-red-100' },
    'Vermietet': { bg: 'bg-rose-500', text: 'text-rose-700', light: 'bg-rose-100' },
};

const inquiryStatusColors = {
    new: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    done: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

const inquiryStatusLabels = {
    new: 'Neu',
    in_progress: 'In Bearbeitung',
    done: 'Erledigt',
    rejected: 'Abgelehnt',
};

const inquiryDelta = computed(() => {
    const thisWeek = props.stats.new_inquiries_this_week || 0;
    const lastWeek = props.stats.new_inquiries_last_week || 0;
    if (lastWeek === 0) return thisWeek > 0 ? 100 : 0;
    return Math.round(((thisWeek - lastWeek) / lastWeek) * 100);
});

const maxVisitorCount = computed(() => Math.max(...props.visitorTrend.map(v => v.count), 1));
const maxInquiryCount = computed(() => Math.max(...props.inquiryTrend.map(v => v.count), 1));

const totalApartmentCount = computed(() => Object.values(props.apartmentsByStatus).reduce((a, b) => a + b, 0));

const getProjectImage = (project) => {
    const media = project.media?.find(m => m.collection_name === 'preview_image') 
                || project.media?.find(m => m.collection_name === 'project_image');
    return media?.original_url || null;
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-xl text-gray-800 leading-tight flex items-center gap-2">
                    <ChartBarIcon class="w-6 h-6 text-indigo-500" />
                    Dashboard
                </h2>
                <div class="flex items-center gap-2">
                    <a href="/export/inquiries" class="text-xs font-bold text-gray-400 hover:text-gray-700 flex items-center gap-1 transition">
                        <ArrowDownTrayIcon class="w-3.5 h-3.5" /> Anfragen CSV
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Stats Grid (Top KPIs) -->
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Projects -->
                    <Link :href="route('projects.index')" class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-indigo-200 transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center group-hover:bg-indigo-100 transition">
                                <FolderIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.projects }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Projekte</p>
                    </Link>

                    <!-- Apartments -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                                <HomeModernIcon class="w-5 h-5 text-emerald-600" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.apartments }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Wohnungen</p>
                    </div>

                    <!-- Inquiries -->
                    <Link :href="route('inquiries.index')" class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-rose-200 transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center group-hover:bg-rose-100 transition">
                                <ChatBubbleLeftRightIcon class="w-5 h-5 text-rose-600" />
                            </div>
                            <span v-if="inquiryDelta !== 0" class="flex items-center gap-0.5 text-[10px] font-black px-1.5 py-0.5 rounded-full"
                                :class="inquiryDelta > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                <ArrowTrendingUpIcon v-if="inquiryDelta > 0" class="w-3 h-3" />
                                <ArrowTrendingDownIcon v-else class="w-3 h-3" />
                                {{ Math.abs(inquiryDelta) }}%
                            </span>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.inquiries }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Anfragen</p>
                    </Link>

                    <!-- This Week -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                                <ArrowTrendingUpIcon class="w-5 h-5 text-purple-600" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.new_inquiries_this_week }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Diese Woche</p>
                    </div>

                    <!-- Visitors (30d) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                                <EyeIcon class="w-5 h-5 text-cyan-600" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.visitors_30d }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Besucher 30T</p>
                    </div>

                    <!-- Contacts -->
                    <Link :href="route('contacts.index')" class="group bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md hover:border-teal-200 transition-all">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center group-hover:bg-teal-100 transition">
                                <UsersIcon class="w-5 h-5 text-teal-600" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900">{{ stats.contacts }}</h3>
                        <p class="text-xs font-semibold text-gray-500 mt-0.5">Kontakte</p>
                    </Link>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Visitor Trend (7d) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <EyeIcon class="w-4 h-4 text-cyan-500" /> Besucher (7 Tage)
                        </h3>
                        <div class="flex items-end gap-1.5 h-24">
                            <div v-for="(day, i) in visitorTrend" :key="i" class="flex-1 flex flex-col items-center gap-1">
                                <span class="text-[10px] font-black text-gray-600">{{ day.count }}</span>
                                <div class="w-full bg-cyan-100 rounded-t-lg transition-all duration-500 min-h-[4px]"
                                    :style="{ height: (day.count / maxVisitorCount * 100) + '%' }">
                                    <div class="w-full h-full bg-gradient-to-t from-cyan-500 to-cyan-400 rounded-t-lg"></div>
                                </div>
                                <span class="text-[9px] text-gray-400 font-mono">{{ day.date }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Inquiry Trend (7d) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <ChatBubbleLeftRightIcon class="w-4 h-4 text-rose-500" /> Anfragen (7 Tage)
                        </h3>
                        <div class="flex items-end gap-1.5 h-24">
                            <div v-for="(day, i) in inquiryTrend" :key="i" class="flex-1 flex flex-col items-center gap-1">
                                <span class="text-[10px] font-black text-gray-600">{{ day.count }}</span>
                                <div class="w-full bg-rose-100 rounded-t-lg transition-all duration-500 min-h-[4px]"
                                    :style="{ height: (day.count / maxInquiryCount * 100) + '%' }">
                                    <div class="w-full h-full bg-gradient-to-t from-rose-500 to-rose-400 rounded-t-lg"></div>
                                </div>
                                <span class="text-[9px] text-gray-400 font-mono">{{ day.date }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Apartment Status Distribution -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                            <HomeModernIcon class="w-4 h-4 text-emerald-500" /> Wohnungsstatus
                        </h3>
                        <div v-if="totalApartmentCount > 0" class="space-y-3">
                            <!-- Stacked bar -->
                            <div class="w-full h-5 bg-gray-100 rounded-full overflow-hidden flex">
                                <div v-for="(count, status) in apartmentsByStatus" :key="status"
                                    class="h-full transition-all duration-700 first:rounded-l-full last:rounded-r-full"
                                    :class="statusColors[status]?.bg || 'bg-gray-400'"
                                    :style="{ width: (count / totalApartmentCount * 100) + '%' }">
                                </div>
                            </div>
                            <!-- Legend -->
                            <div class="grid grid-cols-2 gap-2">
                                <div v-for="(count, status) in apartmentsByStatus" :key="status" class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full shrink-0" :class="statusColors[status]?.bg || 'bg-gray-400'"></span>
                                    <span class="text-xs text-gray-600">{{ status }}</span>
                                    <span class="text-xs font-black text-gray-900 ml-auto">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-400 text-sm py-6">
                            Noch keine Wohnungen
                        </div>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Hot Leads -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                            <FireIcon class="w-5 h-5 text-orange-500" />
                            <h3 class="text-sm font-bold text-gray-700">Heiße Leads</h3>
                        </div>
                        <div v-if="hotLeads.length" class="divide-y divide-gray-100">
                            <div v-for="lead in hotLeads" :key="lead.id" class="px-6 py-3 hover:bg-gray-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-black"
                                        :class="lead.lead_score >= 70 ? 'bg-red-100 text-red-700' : lead.lead_score >= 45 ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700'">
                                        {{ lead.lead_score }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-900 truncate">{{ lead.fingerprint }} <span class="text-gray-400 font-normal">· {{ lead.visit_count }} Besuche</span></p>
                                        <p class="text-[10px] text-gray-500 truncate">{{ lead.project_name }} · {{ lead.device }} · {{ lead.last_visit }}</p>
                                    </div>
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full"
                                        :class="lead.lead_score >= 70 ? 'bg-red-100 text-red-700' : lead.lead_score >= 45 ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700'">
                                        {{ lead.lead_label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center text-gray-400 text-sm">
                            <FireIcon class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                            Noch keine qualifizierten Leads
                        </div>
                    </div>

                    <!-- Recent Inquiries -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                <ChatBubbleLeftRightIcon class="w-5 h-5 text-rose-500" /> Neue Anfragen
                            </h3>
                            <Link :href="route('inquiries.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                                Alle <ArrowRightIcon class="w-3 h-3" />
                            </Link>
                        </div>
                        <div v-if="recentInquiries.length" class="divide-y divide-gray-100">
                            <div v-for="inq in recentInquiries" :key="inq.id" class="px-6 py-3 hover:bg-gray-50 transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-700 font-bold text-xs shrink-0">
                                            {{ (inq.name || '?')[0].toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-gray-900 truncate">{{ inq.name }}</p>
                                            <p class="text-[10px] text-gray-500 truncate">{{ inq.project?.name || '–' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="inquiryStatusColors[inq.status] || 'bg-gray-100 text-gray-600'">
                                            {{ inquiryStatusLabels[inq.status] || inq.status || 'Neu' }}
                                        </span>
                                        <span class="text-[10px] text-gray-400">
                                            {{ new Date(inq.created_at).toLocaleDateString('de-DE') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center text-gray-400 text-sm">
                            Noch keine Anfragen erhalten.
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                                <ClockIcon class="w-5 h-5 text-indigo-500" /> Letzte Aktivitäten
                            </h3>
                            <Link :href="route('activity-log.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                                Alle <ArrowRightIcon class="w-3 h-3" />
                            </Link>
                        </div>
                        <div v-if="recentActivity.length" class="divide-y divide-gray-100">
                            <div v-for="act in recentActivity" :key="act.id" class="px-6 py-3 hover:bg-gray-50 transition">
                                <p class="text-xs text-gray-700">
                                    <span class="font-bold">{{ act.user_name }}</span>
                                    {{ act.action_label }}
                                    <span v-if="act.subject_type_label" class="text-gray-500">{{ act.subject_type_label }}</span>
                                    <span v-if="act.subject_label" class="font-semibold">"{{ act.subject_label }}"</span>
                                </p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ act.created_at }}</p>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center text-gray-400 text-sm">
                            <ClockIcon class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                            Keine Aktivitäten
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                            <FolderIcon class="w-5 h-5 text-indigo-500" /> Aktuelle Projekte
                        </h3>
                        <Link :href="route('projects.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                            Alle ansehen <ArrowRightIcon class="w-3 h-3" />
                        </Link>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 divide-x divide-gray-100">
                        <Link v-for="project in recentProjects" :key="project.id" 
                            :href="route('projects.show', project.id)"
                            class="p-5 hover:bg-gray-50 transition group">
                            <div class="w-full h-24 bg-gray-100 rounded-xl mb-3 overflow-hidden">
                                <img v-if="getProjectImage(project)" :src="getProjectImage(project)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <FolderIcon class="w-8 h-8 text-gray-300" />
                                </div>
                            </div>
                            <h4 class="text-sm font-bold text-gray-900 truncate group-hover:text-indigo-600 transition">{{ project.name }}</h4>
                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ project.city || project.address || 'Kein Standort' }}</p>
                        </Link>
                        <div v-if="!recentProjects.length" class="col-span-4 p-12 text-center text-gray-400 text-sm">
                            <FolderIcon class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                            Noch keine Projekte angelegt.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
