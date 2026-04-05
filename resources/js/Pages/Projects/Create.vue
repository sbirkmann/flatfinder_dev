<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    BuildingOfficeIcon, 
    MapPinIcon, 
    InformationCircleIcon, 
    SparklesIcon, 
    PhotoIcon,
    ArrowRightIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import DialogModal from '@/Components/DialogModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const form = useForm({
    name: '',
    address: '',
    zip: '',
    city: '',
    description: '',
    project_image: null,
    preview_image: null,
});

const submit = () => {
    form.post(route('projects.store'));
};

const showAiAssistant = ref(false);
const aiPrompt = ref('');
const aiLoading = ref(false);
const aiResult = ref('');

const openAiAssistant = () => {
    if (!form.name) {
        alert('Bitte geben Sie zuerst einen Projektnamen ein.');
        return;
    }
    aiPrompt.value = `Schreibe eine ansprechende, professionelle Projektbeschreibung für ein Neubauprojekt namens "${form.name}"`;
    if (form.city) aiPrompt.value += ` in ${form.city}`;
    aiPrompt.value += `. Der Text soll modern und einladend sein.`;
    showAiAssistant.value = true;
};

const generateAiDescription = async () => {
    aiLoading.value = true;
    aiResult.value = '';
    try {
        const response = await axios.post(route('ai.generate'), {
            prompt: aiPrompt.value,
            mode: 'text_description',
            model: 'llama3.1'
        });
        
        if (response.data.success) {
            // Support both direct content and markdown-wrapped content
            let content = response.data.content;
            const match = content.match(/```(?:[\w]*\n)?([\s\S]*?)```/);
            aiResult.value = match ? match[1].trim() : content.trim();
        }
    } catch (e) {
        console.error('AI Error:', e);
    } finally {
        aiLoading.value = false;
    }
};

const applyAiDescription = () => {
    form.description = aiResult.value;
    showAiAssistant.value = false;
};
</script>

<template>
    <AppLayout title="Projekt anlegen">
        <template #header>
            <div class="flex items-center gap-4">
                <div class="p-2 bg-white rounded-xl shadow-sm">
                    <BuildingOfficeIcon class="w-6 h-6 text-brand-500" />
                </div>
                <div>
                    <h2 class="font-black text-2xl text-gray-900 tracking-tight">
                        Neues Projekt anlegen
                    </h2>
                    <p class="text-sm text-gray-500 font-medium">Legen Sie das Fundament für Ihre digitale Vermarktung.</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- Section: Basisdaten -->
                    <div class="bg-white rounded-[32px] shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-8 border-b border-gray-50 flex items-center gap-4 bg-gray-50/30">
                            <div class="p-2.5 bg-brand-50 rounded-xl">
                                <InformationCircleIcon class="w-6 h-6 text-brand-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-lg text-gray-900">Basisdaten</h3>
                                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Grundlegende Informationen</p>
                            </div>
                        </div>
                        
                        <div class="p-8 space-y-6">
                            <div>
                                <InputLabel for="name" value="Projektname" />
                                <div class="mt-1 relative">
                                    <TextInput id="name" v-model="form.name" type="text" class="block w-full pl-10" placeholder="z.B. Riverside Gardens" required />
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <BuildingOfficeIcon class="h-5 w-5 text-gray-400" />
                                    </div>
                                </div>
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div class="relative">
                                <div class="flex items-center justify-between mb-1">
                                    <InputLabel for="description" value="Beschreibung" />
                                    <button type="button" @click="openAiAssistant" 
                                            class="inline-flex items-center gap-1.5 text-xs font-black text-brand-600 hover:text-brand-700 transition">
                                        <SparklesIcon class="w-4 h-4" />
                                        KI-Textassistent
                                    </button>
                                </div>
                                <textarea id="description" v-model="form.description" rows="5" 
                                          class="w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-2xl shadow-sm text-sm"
                                          placeholder="Beschreiben Sie das Projekt..."></textarea>
                                <InputError :message="form.errors.description" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Standort -->
                    <div class="bg-white rounded-[32px] shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-8 border-b border-gray-50 flex items-center gap-4 bg-gray-50/30">
                            <div class="p-2.5 bg-blue-50 rounded-xl">
                                <MapPinIcon class="w-6 h-6 text-blue-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-lg text-gray-900">Standort</h3>
                                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Wo befindet sich das Projekt?</p>
                            </div>
                        </div>

                        <div class="p-8 space-y-6">
                            <div>
                                <InputLabel for="address" value="Adresse" />
                                <div class="mt-1 relative">
                                    <TextInput id="address" v-model="form.address" type="text" class="block w-full pl-10" placeholder="Straße, Hausnummer" />
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <MapPinIcon class="h-5 w-5 text-gray-400" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-6">
                                <div class="col-span-1">
                                    <InputLabel for="zip" value="PLZ" />
                                    <TextInput id="zip" v-model="form.zip" type="text" class="mt-1 block w-full" placeholder="12345" />
                                </div>
                                <div class="col-span-2">
                                    <InputLabel for="city" value="Stadt" />
                                    <TextInput id="city" v-model="form.city" type="text" class="mt-1 block w-full" placeholder="Stadtname" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Bilder -->
                    <div class="bg-white rounded-[32px] shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-8 border-b border-gray-50 flex items-center gap-4 bg-gray-50/30">
                            <div class="p-2.5 bg-amber-50 rounded-xl">
                                <PhotoIcon class="w-6 h-6 text-amber-600" />
                            </div>
                            <div>
                                <h3 class="font-black text-lg text-gray-900">Visuelle Assets</h3>
                                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Bilder & Vorschaubilder</p>
                            </div>
                        </div>

                        <div class="p-8 grid grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <InputLabel value="Hauptbild (Visualisierung)" />
                                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 flex flex-col items-center justify-center hover:border-brand-300 transition cursor-pointer bg-gray-50/50 group relative overflow-hidden h-40">
                                    <input type="file" @change="e => form.project_image = e.target.files[0]" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*" />
                                    <PhotoIcon class="w-10 h-10 text-gray-300 group-hover:text-brand-400 transition" />
                                    <span class="mt-2 text-xs font-bold text-gray-400 group-hover:text-brand-600">{{ form.project_image ? form.project_image.name : 'Bild auswählen' }}</span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <InputLabel value="Preview Image (Small)" />
                                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 flex flex-col items-center justify-center hover:border-blue-300 transition cursor-pointer bg-gray-50/50 group relative overflow-hidden h-40">
                                    <input type="file" @change="e => form.preview_image = e.target.files[0]" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*" />
                                    <PhotoIcon class="w-10 h-10 text-gray-300 group-hover:text-blue-400 transition" />
                                    <span class="mt-2 text-xs font-bold text-gray-400 group-hover:text-blue-600">{{ form.preview_image ? form.preview_image.name : 'Vorschaubild auswählen' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-4 p-8 bg-gray-50/50 rounded-[32px] border border-gray-100">
                        <Link :href="route('projects.index')" class="text-sm font-black text-gray-400 hover:text-gray-600 transition tracking-tighter">
                            Abbrechen
                        </Link>
                        <button type="submit" :disabled="form.processing" 
                                class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-black py-4 px-10 rounded-2xl transition shadow-lg shadow-brand-500/20 disabled:opacity-50 active:scale-95 group">
                            {{ form.processing ? 'Wird erstellt...' : 'Projekt final anlegen' }}
                            <ArrowRightIcon v-if="!form.processing" class="w-5 h-5 group-hover:translate-x-1 transition" />
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- AI Assistant Modal -->
        <DialogModal :show="showAiAssistant" @close="showAiAssistant = false" maxWidth="2xl">
            <template #title>
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-brand-50 rounded-lg">
                        <SparklesIcon class="w-6 h-6 text-brand-600" />
                    </div>
                    <span>KI-Textassistent</span>
                </div>
            </template>
            <template #content>
                <div class="space-y-6 py-4">
                    <div>
                        <InputLabel value="Thema / Fokus für die KI" />
                        <textarea v-model="aiPrompt" rows="3" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-xl shadow-sm text-sm" placeholder="Worauf soll sich die KI konzentrieren?"></textarea>
                    </div>

                    <div v-if="aiResult" class="bg-brand-50/30 p-6 rounded-2xl border border-brand-100 relative group animate-in slide-in-from-bottom duration-500">
                        <h4 class="text-[10px] font-black text-brand-600 uppercase tracking-widest mb-3">Generierter Vorschlag:</h4>
                        <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap font-medium">{{ aiResult }}</div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <button @click="showAiAssistant = false" class="text-sm font-black text-gray-400 hover:text-gray-600">Schließen</button>
                    <div class="flex items-center gap-3">
                        <button v-if="aiResult" @click="applyAiDescription" class="px-6 py-2.5 bg-emerald-600 text-white font-black rounded-xl hover:bg-emerald-700 transition active:scale-95 shadow-lg shadow-emerald-500/20 text-sm">
                            Text übernehmen
                        </button>
                        <button @click="generateAiDescription" :disabled="aiLoading" 
                                class="px-6 py-2.5 bg-brand-600 text-white font-black rounded-xl hover:bg-brand-700 transition active:scale-95 shadow-lg shadow-brand-500/20 disabled:opacity-50 text-sm flex items-center gap-2">
                            <SparklesIcon class="w-4 h-4" :class="{'animate-spin': aiLoading}" />
                            {{ aiLoading ? 'KI denkt nach...' : (aiResult ? 'Neu generieren' : 'Text generieren') }}
                        </button>
                    </div>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>
