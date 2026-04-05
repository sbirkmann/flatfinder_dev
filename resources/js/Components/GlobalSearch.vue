<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import {
    FolderIcon, HomeIcon, UserIcon, ChatBubbleLeftRightIcon
} from '@heroicons/vue/24/solid';
import axios from 'axios';

const isOpen = ref(false);
const query = ref('');
const results = ref([]);
const isLoading = ref(false);
const selectedIndex = ref(0);
let debounceTimer = null;

const iconMap = {
    folder: FolderIcon,
    home: HomeIcon,
    user: UserIcon,
    chat: ChatBubbleLeftRightIcon,
};

const typeColors = {
    project: 'bg-indigo-100 text-indigo-700',
    apartment: 'bg-emerald-100 text-emerald-700',
    contact: 'bg-amber-100 text-amber-700',
    inquiry: 'bg-rose-100 text-rose-700',
};

const open = () => {
    isOpen.value = true;
    query.value = '';
    results.value = [];
    selectedIndex.value = 0;
};

const close = () => {
    isOpen.value = false;
    query.value = '';
    results.value = [];
};

const search = async () => {
    if (query.value.length < 2) {
        results.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get('/search', { params: { q: query.value } });
        results.value = response.data.results || [];
        selectedIndex.value = 0;
    } catch (e) {
        results.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(query, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 250);
});

const navigateTo = (result) => {
    close();
    router.visit(result.url);
};

const handleKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, results.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (e.key === 'Enter' && results.value[selectedIndex.value]) {
        e.preventDefault();
        navigateTo(results.value[selectedIndex.value]);
    } else if (e.key === 'Escape') {
        close();
    }
};

const handleGlobalKeyDown = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        open();
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeyDown);
});

defineExpose({ open });
</script>

<template>
    <!-- Trigger Button -->
    <button @click="open" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-400 bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-200 transition min-w-[200px]">
        <MagnifyingGlassIcon class="w-4 h-4" />
        <span>Suchen...</span>
        <kbd class="ml-auto text-[10px] bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded font-mono">⌘K</kbd>
    </button>

    <!-- Modal Overlay -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-start justify-center pt-[10vh]" @click.self="close">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="close"></div>
                
                <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden" @keydown="handleKeyDown">
                    <!-- Search Input -->
                    <div class="flex items-center border-b border-gray-200 px-4">
                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 shrink-0" />
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            placeholder="Projekte, Wohnungen, Kontakte durchsuchen..."
                            class="flex-1 px-3 py-4 text-sm bg-transparent border-0 focus:ring-0 focus:outline-none placeholder-gray-400"
                            autofocus
                        />
                        <button @click="close" class="text-gray-400 hover:text-gray-600 p-1">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Results -->
                    <div class="max-h-[400px] overflow-y-auto">
                        <div v-if="isLoading" class="p-8 text-center text-gray-400 text-sm">
                            <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Suche läuft...
                        </div>

                        <div v-else-if="query.length >= 2 && !results.length" class="p-8 text-center text-gray-400 text-sm">
                            Keine Ergebnisse für <span class="font-semibold text-gray-600">"{{ query }}"</span>
                        </div>

                        <div v-else-if="results.length" class="py-2">
                            <button
                                v-for="(result, index) in results"
                                :key="`${result.type}-${result.id}`"
                                @click="navigateTo(result)"
                                @mouseenter="selectedIndex = index"
                                class="w-full flex items-center gap-3 px-4 py-3 text-left transition"
                                :class="selectedIndex === index ? 'bg-indigo-50' : 'hover:bg-gray-50'"
                            >
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="typeColors[result.type] || 'bg-gray-100 text-gray-500'">
                                    <component :is="iconMap[result.icon] || FolderIcon" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">{{ result.title }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ result.subtitle }}</div>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" :class="typeColors[result.type] || 'bg-gray-100 text-gray-500'">
                                    {{ result.type_label }}
                                </span>
                            </button>
                        </div>

                        <div v-else class="p-6 text-center text-sm text-gray-400">
                            <p class="mb-1">Tippe mindestens 2 Zeichen zum Suchen</p>
                            <p class="text-xs">Durchsucht Projekte, Wohnungen, Kontakte und Anfragen</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center gap-4 border-t border-gray-200 px-4 py-2.5 bg-gray-50 text-[11px] text-gray-400">
                        <span class="flex items-center gap-1"><kbd class="bg-gray-200 text-gray-500 px-1 py-0.5 rounded text-[10px] font-mono">↑↓</kbd> Navigieren</span>
                        <span class="flex items-center gap-1"><kbd class="bg-gray-200 text-gray-500 px-1 py-0.5 rounded text-[10px] font-mono">↵</kbd> Öffnen</span>
                        <span class="flex items-center gap-1"><kbd class="bg-gray-200 text-gray-500 px-1 py-0.5 rounded text-[10px] font-mono">Esc</kbd> Schließen</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
