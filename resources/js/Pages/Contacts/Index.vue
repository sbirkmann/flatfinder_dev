<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    UserIcon,
    PlusIcon,
    TrashIcon,
    PencilIcon,
    PhoneIcon,
    EnvelopeIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    contacts: Array,
});

// --- Create ---
const showCreate = ref(false);
const createForm = useForm({ name: '', email: '', phone: '', position: '', notes: '' });
const submitCreate = () => {
    createForm.post(route('contacts.store'), {
        onSuccess: () => { createForm.reset(); showCreate.value = false; },
    });
};

// --- Edit ---
const editContact = ref(null);
const editForm = useForm({ name: '', email: '', phone: '', position: '', notes: '' });
const openEdit = (c) => {
    editContact.value = c;
    editForm.name     = c.name;
    editForm.email    = c.email || '';
    editForm.phone    = c.phone || '';
    editForm.position = c.position || '';
    editForm.notes    = c.notes || '';
};
const submitEdit = () => {
    editForm.put(route('contacts.update', editContact.value.id), {
        onSuccess: () => { editContact.value = null; },
    });
};

// --- Delete ---
const confirmDelete = (id) => {
    if (confirm('Kontakt wirklich löschen?')) {
        router.delete(route('contacts.destroy', id));
    }
};
</script>

<template>
    <AppLayout title="Ansprechpartner">
        <Head title="Ansprechpartner" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <UserIcon class="w-5 h-5 text-brand-500" /> Ansprechpartner
                </h2>
                <button @click="showCreate = !showCreate"
                        class="flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold rounded-lg transition">
                    <PlusIcon class="w-4 h-4" /> Neuer Kontakt
                </button>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-6">

            <!-- Create Form -->
            <transition name="fade">
                <div v-if="showCreate" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-black text-gray-800 mb-4 flex items-center gap-2">
                        <PlusIcon class="w-5 h-5 text-brand-500" /> Neuer Ansprechpartner
                    </h3>
                    <form @submit.prevent="submitCreate" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Name *</label>
                            <input v-model="createForm.name" type="text" required placeholder="Max Mustermann"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Position</label>
                            <input v-model="createForm.position" type="text" placeholder="Projektleiter"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">E-Mail</label>
                            <input v-model="createForm.email" type="email" placeholder="max@beispiel.de"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Telefon</label>
                            <input v-model="createForm.phone" type="text" placeholder="+49 123 456789"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Notizen</label>
                            <textarea v-model="createForm.notes" rows="2" placeholder="Interne Notizen..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500"></textarea>
                        </div>
                        <div class="sm:col-span-2 flex gap-3 justify-end">
                            <button type="button" @click="showCreate = false"
                                    class="px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-800 transition">Abbrechen</button>
                            <button type="submit" :disabled="createForm.processing"
                                    class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold rounded-lg transition">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </transition>

            <!-- Contacts List -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div v-if="!contacts.length" class="py-16 text-center">
                    <UserIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
                    <h3 class="text-lg font-black text-gray-500">Noch keine Ansprechpartner</h3>
                    <p class="text-sm text-gray-400 mt-1">Klicke oben auf "Neuer Kontakt", um einen anzulegen.</p>
                </div>
                <table v-else class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Position</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Kontakt</th>
                            <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase">Projekte</th>
                            <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in contacts" :key="c.id" class="hover:bg-gray-50 transition">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-black text-sm shrink-0">
                                        {{ c.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="font-bold text-gray-900 text-sm">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-500">{{ c.position || '–' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-1">
                                    <a v-if="c.email" :href="'mailto:' + c.email" class="flex items-center gap-1.5 text-xs text-brand-600 hover:underline">
                                        <EnvelopeIcon class="w-3.5 h-3.5" /> {{ c.email }}
                                    </a>
                                    <a v-if="c.phone" :href="'tel:' + c.phone" class="flex items-center gap-1.5 text-xs text-gray-500">
                                        <PhoneIcon class="w-3.5 h-3.5" /> {{ c.phone }}
                                    </a>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-xs font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                                    {{ c.projects_count }} Projekt{{ c.projects_count !== 1 ? 'e' : '' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="openEdit(c)"
                                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition">
                                        <PencilIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="confirmDelete(c.id)"
                                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-50 text-gray-400 hover:text-red-500 transition">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Modal -->
        <transition name="fade">
            <div v-if="editContact" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="editContact = null">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-black text-gray-800">Kontakt bearbeiten</h3>
                        <button @click="editContact = null" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Name *</label>
                            <input v-model="editForm.name" type="text" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Position</label>
                            <input v-model="editForm.position" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">E-Mail</label>
                            <input v-model="editForm.email" type="email"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Telefon</label>
                            <input v-model="editForm.phone" type="text"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 mb-1">Notizen</label>
                            <textarea v-model="editForm.notes" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500"></textarea>
                        </div>
                        <div class="sm:col-span-2 flex gap-3 justify-end">
                            <button type="button" @click="editContact = null"
                                    class="px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-800 transition">Abbrechen</button>
                            <button type="submit" :disabled="editForm.processing"
                                    class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold rounded-lg transition">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
