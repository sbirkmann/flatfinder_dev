<template>
<transition name="fade">
    <div v-if="show" class="fixed inset-0 z-[9999] bg-black/50 backdrop-blur-md flex items-center justify-center p-4 md:p-8" @click.self="$emit('close')">
        <div class="bg-[#fcfcfc] rounded-2xl w-full max-w-7xl h-full md:h-[90vh] shadow-2xl flex flex-col overflow-hidden relative border border-gray-200">
            <!-- Header -->
            <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 bg-white shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-[#fcfaf9] flex items-center justify-center text-[#ab715c]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-gray-900 tracking-tight">Wohnungsvergleich</h2>
                        <p class="text-xs text-gray-500 font-semibold">{{ apartments.length }} Objekte ausgewählt</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 transition flex justify-center items-center text-gray-500">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-x-auto overflow-y-auto w-full p-4 md:p-8 bg-gray-50">
                <div class="w-full min-w-[800px] max-w-6xl mx-auto">
                    <table class="w-full text-left bg-white rounded-xl shadow-sm border-hidden overflow-hidden table-fixed border-collapse">
                        <thead>
                            <tr>
                                <th class="w-[180px] md:w-[220px] border-b border-gray-100 p-4 bg-white/60"></th>
                                <th v-for="apt in apartments" :key="'hdr_'+apt.id" class="p-6 border-b border-l border-gray-100 text-center relative bg-white" :style="{ width: (100 / apartments.length) + '%' }">
                                    <button @click="$emit('remove', apt.id)" class="absolute top-2 right-2 p-1.5 rounded-full bg-white text-gray-400 hover:bg-gray-100 hover:text-red-500 shadow-sm border border-gray-100 transition z-10">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                    <div class="w-full aspect-[4/3] rounded-lg overflow-hidden bg-gray-100 mb-4 shadow-[0_2px_8px_rgba(0,0,0,0.04)] border border-gray-100 relative group">
                                        <img v-if="apt.media?.[0]" :src="apt.media[0].original_url" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                    </div>
                                    <h3 class="text-[17px] font-black text-gray-900 tracking-tight leading-tight">{{ apt.name }}</h3>
                                    <p class="text-sm font-semibold text-gray-500 mt-1">{{ getFloorName(apt.floor_id) }}</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium text-gray-700 divide-y divide-gray-100 pb-2">
                            <tr v-if="fields.includes('price')" class="group">
                                <td class="p-4 md:px-6 align-middle font-bold uppercase tracking-wider text-xs text-gray-400 group-hover:bg-gray-50 transition drop-shadow-sm">Preis</td>
                                <td v-for="apt in apartments" :key="'prc_'+apt.id" class="p-4 md:p-5 border-l border-gray-100 text-center group-hover:bg-gray-50 transition">
                                    <div class="text-[16px] xl:text-[18px] font-black text-gray-900">
                                        <template v-if="apt.marketing_type === 'Miete'">
                                            {{ apt.warm_rent ? formatNumber(apt.warm_rent) + ' € (Warm)' : (apt.price ? formatNumber(apt.price) + ' €' : 'auf Anfrage') }}
                                        </template>
                                        <template v-else>
                                            {{ apt.price ? formatNumber(apt.price) + ' €' : 'auf Anfrage' }}
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="fields.includes('size')" class="group">
                                <td class="p-4 md:px-6 align-middle font-bold uppercase tracking-wider text-xs text-gray-400 group-hover:bg-gray-50 transition drop-shadow-sm">Wohnfläche</td>
                                <td v-for="apt in apartments" :key="'sqm_'+apt.id" class="p-4 md:p-5 border-l border-gray-100 text-center group-hover:bg-gray-50 transition font-bold text-gray-800 text-[15px]">{{ apt.sqm || '-' }} m²</td>
                            </tr>
                            <tr v-if="fields.includes('rooms')" class="group">
                                <td class="p-4 md:px-6 align-middle font-bold uppercase tracking-wider text-xs text-gray-400 group-hover:bg-gray-50 transition drop-shadow-sm">Zimmer</td>
                                <td v-for="apt in apartments" :key="'rms_'+apt.id" class="p-4 md:p-5 border-l border-gray-100 text-center group-hover:bg-gray-50 transition font-bold text-gray-800 text-[15px]">{{ apt.rooms || '-' }}</td>
                            </tr>
                            <tr v-if="fields.includes('status')" class="group">
                                <td class="p-4 md:px-6 align-middle font-bold uppercase tracking-wider text-xs text-gray-400 group-hover:bg-gray-50 transition drop-shadow-sm">Status</td>
                                <td v-for="apt in apartments" :key="'sts_'+apt.id" class="p-4 md:p-5 border-l border-gray-100 text-center group-hover:bg-gray-50 transition">
                                    <span class="inline-block bg-[#dcf0d5] text-[#3f6327] px-3 py-1 rounded-[6px] border border-[#b2d9a1] text-[12px] font-bold shadow-sm whitespace-nowrap">{{ apt.status || 'Objekt' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="p-4 bg-white border-t border-gray-100"></td>
                                <td v-for="apt in apartments" :key="'act_'+apt.id" class="p-4 md:p-6 border-l border-t border-gray-100 text-center bg-white">
                                    <button @click="$emit('select', apt.id)" class="w-full py-3 bg-[#fcfaf9] border border-gray-200 text-[13px] hover:bg-[#ab715c] hover:text-white hover:border-[#ab715c] text-gray-700 rounded-[12px] font-bold transition shadow-[0_2px_4px_rgba(0,0,0,0.02)] block truncate px-2">
                                        Details ansehen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</transition>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    apartments: Array,
    project: Object
});
const emit = defineEmits(['close', 'remove', 'select']);

const fields = computed(() => {
    return props.project?.comparison_settings?.fields || ['name', 'price', 'size', 'rooms', 'status'];
});

const formatNumber = (num) => {
    if(!num) return '';
    return new Intl.NumberFormat('de-DE').format(num);
};

const getFloorName = (floorId) => {
    if(!floorId) return '';
    const f = props.project?.floors?.find(fl => fl.id === floorId);
    return f ? f.name : 'EG';
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
