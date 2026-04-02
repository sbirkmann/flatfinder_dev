<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    users: Array,
});
</script>

<template>
    <AppLayout title="Benutzerverwaltung">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Benutzerverwaltung
                </h2>
                <Link href="/users/create" class="px-4 py-2 bg-blue-600 text-white rounded shadow text-sm font-medium">
                    Neuen Benutzer anlegen
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Superadmin</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="user in users" :key="user.id">
                                <td class="px-6 py-4 whitespace-nowrap font-medium">{{ user.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ user.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="user.is_superadmin" class="px-2 bg-green-100 text-green-800 text-xs rounded-full">Ja</span>
                                    <span v-else class="px-2 bg-gray-100 text-gray-800 text-xs rounded-full">Nein</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link :href="`/users/${user.id}/edit`" class="text-blue-600 hover:text-blue-900 text-sm">Bearbeiten</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
