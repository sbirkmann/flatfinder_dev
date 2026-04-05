<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    ArrowLeftIcon,
    EnvelopeIcon,
    PhoneIcon,
    ChatBubbleLeftRightIcon,
    UserIcon,
    CalendarIcon,
    BuildingOfficeIcon,
    HomeIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    inquiry: Object,
});

const statusColors = {
    new: 'bg-blue-100 text-blue-800 border-blue-200',
    in_progress: 'bg-amber-100 text-amber-800 border-amber-200',
    done: 'bg-green-100 text-green-800 border-green-200',
    rejected: 'bg-red-100 text-red-800 border-red-200',
};

const statusLabels = {
    new: 'Neu',
    in_progress: 'In Bearbeitung',
    done: 'Erledigt',
    rejected: 'Abgelehnt',
};

const showReplyForm = ref(false);
const replyForm = useForm({
    subject: `Re: Anfrage von ${props.inquiry.name}`,
    body: '',
});

const statusForm = useForm({
    status: props.inquiry.status || 'new',
    notes: props.inquiry.notes || '',
});

const sendReply = () => {
    replyForm.post(route('inquiries.reply', props.inquiry.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReplyForm.value = false;
            replyForm.reset('body');
        },
    });
};

const updateStatus = () => {
    statusForm.patch(route('inquiries.update-status', props.inquiry.id), {
        preserveScroll: true,
    });
};

const visitor = computed(() => props.inquiry.visitor);
const leadScore = computed(() => visitor.value?.lead_score ?? 0);
const leadLabel = computed(() => visitor.value?.lead_label ?? '⚪ Unbekannt');
</script>

<template>
    <AppLayout title="Anfrage Detail">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('inquiries.index')" class="text-gray-400 hover:text-gray-600 transition">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <h2 class="font-bold text-xl text-gray-800 leading-tight">
                    Anfrage von {{ inquiry.name }}
                </h2>
                <span class="px-3 py-1 rounded-full text-xs font-bold border" :class="statusColors[inquiry.status] || 'bg-gray-100'">
                    {{ statusLabels[inquiry.status] || inquiry.status }}
                </span>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Contact Info Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <UserIcon class="w-5 h-5 text-indigo-500" /> Kontaktdaten
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-if="inquiry.name" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <UserIcon class="w-5 h-5 text-gray-400" />
                                    <div>
                                        <p class="text-xs text-gray-500">Name</p>
                                        <p class="text-sm font-semibold text-gray-900">{{ inquiry.name }}</p>
                                    </div>
                                </div>
                                <div v-if="inquiry.email" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <EnvelopeIcon class="w-5 h-5 text-gray-400" />
                                    <div>
                                        <p class="text-xs text-gray-500">E-Mail</p>
                                        <a :href="`mailto:${inquiry.email}`" class="text-sm font-semibold text-indigo-600 hover:underline">{{ inquiry.email }}</a>
                                    </div>
                                </div>
                                <div v-if="inquiry.phone" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <PhoneIcon class="w-5 h-5 text-gray-400" />
                                    <div>
                                        <p class="text-xs text-gray-500">Telefon</p>
                                        <a :href="`tel:${inquiry.phone}`" class="text-sm font-semibold text-indigo-600 hover:underline">{{ inquiry.phone }}</a>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <CalendarIcon class="w-5 h-5 text-gray-400" />
                                    <div>
                                        <p class="text-xs text-gray-500">Eingegangen</p>
                                        <p class="text-sm font-semibold text-gray-900">{{ new Date(inquiry.created_at).toLocaleString('de-DE') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message -->
                        <div v-if="inquiry.message" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">
                                <ChatBubbleLeftRightIcon class="w-5 h-5 text-indigo-500" /> Nachricht
                            </h3>
                            <div class="prose prose-sm max-w-none text-gray-700 bg-gray-50 rounded-lg p-4" v-html="inquiry.message.replace(/\n/g, '<br>')"></div>
                        </div>

                        <!-- Reply Form -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <EnvelopeIcon class="w-5 h-5 text-indigo-500" /> Antworten
                                </h3>
                                <SecondaryButton v-if="!showReplyForm" @click="showReplyForm = true" :disabled="!inquiry.email">
                                    {{ inquiry.email ? 'E-Mail Antwort schreiben' : 'Keine E-Mail vorhanden' }}
                                </SecondaryButton>
                            </div>

                            <form v-if="showReplyForm" @submit.prevent="sendReply" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Betreff</label>
                                    <input v-model="replyForm.subject" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachricht</label>
                                    <textarea v-model="replyForm.body" rows="6" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="Ihre Antwort..."></textarea>
                                </div>
                                <div class="flex items-center gap-3">
                                    <PrimaryButton type="submit" :disabled="replyForm.processing || !replyForm.body">
                                        {{ replyForm.processing ? 'Wird gesendet...' : 'Antwort senden' }}
                                    </PrimaryButton>
                                    <SecondaryButton @click="showReplyForm = false">Abbrechen</SecondaryButton>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Status & Notes -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-sm font-bold text-gray-700 mb-3">Status</h3>
                            <form @submit.prevent="updateStatus" class="space-y-3">
                                <select v-model="statusForm.status" class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="new">Neu</option>
                                    <option value="in_progress">In Bearbeitung</option>
                                    <option value="done">Erledigt</option>
                                    <option value="rejected">Abgelehnt</option>
                                </select>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Interne Notizen</label>
                                    <textarea v-model="statusForm.notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Interne Notiz..."></textarea>
                                </div>
                                <PrimaryButton type="submit" class="w-full justify-center" :disabled="statusForm.processing">Speichern</PrimaryButton>
                            </form>
                        </div>

                        <!-- Project & Apartment -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-3">
                            <h3 class="text-sm font-bold text-gray-700 mb-2">Zuordnung</h3>
                            <div v-if="inquiry.project" class="flex items-center gap-2 text-sm">
                                <BuildingOfficeIcon class="w-4 h-4 text-indigo-400" />
                                <Link :href="`/projects/${inquiry.project.id}`" class="text-indigo-600 hover:underline font-medium">
                                    {{ inquiry.project.name }}
                                </Link>
                            </div>
                            <div v-if="inquiry.apartment" class="flex items-center gap-2 text-sm">
                                <HomeIcon class="w-4 h-4 text-emerald-400" />
                                <span class="text-gray-900 font-medium">{{ inquiry.apartment.name }}</span>
                            </div>
                            <div v-if="inquiry.source" class="flex items-center gap-2 text-sm">
                                <EyeIcon class="w-4 h-4 text-gray-400" />
                                <span class="text-gray-600">Quelle: <span class="font-medium">{{ inquiry.source }}</span></span>
                            </div>
                        </div>

                        <!-- Lead Scoring -->
                        <div v-if="visitor" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h3 class="text-sm font-bold text-gray-700 mb-3">Lead Score</h3>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-14 h-14 rounded-xl flex items-center justify-center text-xl font-black"
                                    :class="leadScore >= 70 ? 'bg-red-100 text-red-700' : leadScore >= 45 ? 'bg-orange-100 text-orange-700' : leadScore >= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500'">
                                    {{ leadScore }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold">{{ leadLabel }}</p>
                                    <p class="text-xs text-gray-500">{{ visitor.visit_count || 1 }} Besuche</p>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500"
                                    :class="leadScore >= 70 ? 'bg-red-500' : leadScore >= 45 ? 'bg-orange-500' : leadScore >= 20 ? 'bg-yellow-500' : 'bg-gray-400'"
                                    :style="{ width: leadScore + '%' }">
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-500">
                                <div>Browser: <span class="font-medium text-gray-700">{{ visitor.browser || '–' }}</span></div>
                                <div>Gerät: <span class="font-medium text-gray-700">{{ visitor.device || '–' }}</span></div>
                                <div>OS: <span class="font-medium text-gray-700">{{ visitor.os || '–' }}</span></div>
                                <div>Sprache: <span class="font-medium text-gray-700">{{ visitor.language || '–' }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
