<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ArrowPathIcon, ExclamationCircleIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    properties: Array
});

const form = useForm({});

const dispatchSync = () => {
    form.post(route('external-properties.dispatch-sync'), {
        preserveScroll: true
    });
};
</script>

<template>
    <AppLayout title="Externe Immobilien">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Externe Immobilien
                </h2>
                <PrimaryButton @click="dispatchSync" :disabled="form.processing" class="gap-2">
                    <ArrowPathIcon class="w-4 h-4" :class="{'animate-spin': form.processing}" />
                    Manuelle Sync starten
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white overflow-hidden shadow-sm border border-gray-100 sm:rounded-2xl">
                    <div v-if="!properties.length" class="p-12 text-center flex flex-col items-center">
                        <ExclamationCircleIcon class="w-12 h-12 text-gray-300 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900">Keine externen Immobilien gefunden</h3>
                        <p class="mt-1 text-sm text-gray-500">Es wurden noch keine Immobilien von deinen angebundenen CRMs importiert. Starte den Import oben rechts.</p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name / ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CRM</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zimmer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fläche (m²)</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preis</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="prop in properties" :key="prop.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ prop.name || 'Ohne Titel' }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ prop.external_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 uppercase">
                                        {{ prop.integration?.platform_name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ prop.rooms || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ prop.sqm || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ prop.price ? new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(prop.price) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium" :class="prop.status === 'available' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                                        <CheckCircleIcon v-if="prop.status === 'available'" class="w-3.5 h-3.5" />
                                        {{ prop.status || 'Unbekannt' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
