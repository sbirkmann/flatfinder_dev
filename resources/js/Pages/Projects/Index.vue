<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    projects: Array,
});
</script>

<template>
    <AppLayout title="Projekte">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Projekte
                </h2>
                <Link href="/projects/create" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 transition text-white rounded shadow text-sm font-medium">
                    Neues Projekt anlegen
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div v-if="projects.length === 0" class="text-gray-500 text-center py-4">
                        Noch keine Projekte angelegt.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="project in projects" :key="project.id" class="border rounded-lg overflow-hidden shadow transition hover:shadow-lg">
                            <div class="bg-gray-100 h-48 w-full flex items-center justify-center text-gray-400 relative">
                                <span v-if="project.media && project.media.length > 0">
                                    <img :src="project.media[0].original_url" class="absolute inset-0 w-full h-full object-cover" />
                                </span>
                                <span v-else>
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </span>
                            </div>
                            <div class="p-4 bg-white border-t border-brand-100">
                                <h3 class="font-bold text-lg text-gray-800">{{ project.name }}</h3>
                                <p class="text-sm text-gray-500 mb-4">{{ project.address }}, {{ project.zip }} {{ project.city }}</p>
                                <Link :href="`/projects/${project.id}`" class="text-brand-600 hover:text-brand-800 font-medium hover:underline flex items-center">
                                    Detailseite öffnen <span class="ml-1">&rarr;</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
