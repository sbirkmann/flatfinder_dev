<template>
    <div class="space-y-6 print:m-0 print:p-0">
        <!-- Date Filter & Actions -->
        <div class="flex items-center justify-between gap-4 flex-wrap print:hidden">
            <div class="flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-bold text-gray-600">Von:</label>
                    <input type="date" v-model="dateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-bold text-gray-600">Bis:</label>
                    <input type="date" v-model="dateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-brand-500 focus:border-brand-500" />
                </div>
                <button @click="loadStats" class="px-4 py-2 bg-brand-500 text-white text-sm font-bold rounded-lg hover:bg-brand-600 transition shadow-sm">
                    Laden
                </button>
                <div v-if="loading" class="text-sm text-gray-400 ml-2 animate-pulse">Laden...</div>
            </div>
            <div>
                <button @click="printReport" class="flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-bold rounded-lg hover:bg-gray-900 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    PDF Export
                </button>
            </div>
        </div>

        <!-- NEW: SMART AI INSIGHTS DASHBOARD -->
        <div v-if="stats" class="bg-gradient-to-br from-indigo-950 via-brand-950 to-black rounded-[32px] p-8 shadow-2xl relative overflow-hidden group border border-white/10 print:hidden transition-all duration-500 hover:shadow-brand-500/10">
            <!-- Animated Background Mesh -->
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_50%_50%,#ab715c_0%,transparent_50%)] animate-pulse"></div>
            
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="bg-gradient-to-br from-brand-400 to-brand-600 p-3 rounded-2xl shadow-lg shadow-brand-500/20">
                            <SparklesIcon class="w-7 h-7 text-white" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-white tracking-tight">Ollama Strategie-Hub</h3>
                            <p class="text-xs text-brand-300 font-bold uppercase tracking-widest opacity-70">Innovative Projekt-Evaluation</p>
                        </div>
                    </div>
                    <button @click="generateAiAdvice" :disabled="aiLoading" 
                            class="px-6 py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/10 rounded-xl text-white text-sm font-black transition-all flex items-center gap-2 group/btn active:scale-95 disabled:opacity-50 shadow-xl">
                        <ArrowPathIcon :class="{'animate-spin': aiLoading}" class="w-4 h-4 text-brand-400" />
                        {{ aiLoading ? 'KI denkt nach...' : 'Deep-Analysis starten' }}
                    </button>
                </div>

                <!-- Categorized Insights -->
                <div v-if="aiSections.length" class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <div v-for="section in aiSections" :key="section.title" class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm hover:bg-white/10 transition-colors">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xl">{{ section.icon }}</span>
                            <h4 class="text-sm font-black text-white uppercase tracking-wider">{{ section.title }}</h4>
                        </div>
                        <div class="text-brand-50/80 text-sm leading-relaxed font-medium prose prose-invert prose-sm max-w-none" v-html="section.content"></div>
                    </div>
                </div>

                <div v-else-if="!aiLoading" class="flex flex-col items-center justify-center py-12 text-white/40 bg-white/5 rounded-3xl border border-dashed border-white/10">
                    <div class="mb-4 text-4xl opacity-20">📊</div>
                    <h4 class="text-lg font-bold text-white mb-2">Bereit für die Analyse?</h4>
                    <p class="text-sm text-center max-w-sm mb-6">Klicken Sie auf "Deep-Analysis", um innovative Auswertungen zu Besucherverhalten, Vertriebschancen und Marktanpassungen zu erhalten.</p>
                    <button @click="generateAiAdvice" class="px-8 py-3 bg-brand-500 text-white font-black rounded-xl hover:bg-brand-600 transition-all shadow-lg shadow-brand-500/40 active:scale-95">
                        Auswertung starten
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="aiLoading && !aiSections.length" class="flex flex-col items-center justify-center py-12 space-y-4">
                    <div class="flex gap-2">
                        <div class="w-3 h-3 bg-brand-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                        <div class="w-3 h-3 bg-brand-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                        <div class="w-3 h-3 bg-brand-500 rounded-full animate-bounce"></div>
                    </div>
                    <p class="text-sm font-bold text-brand-200 animate-pulse">Lokal-Model analysiert Besucherströme und Verkaufsdaten...</p>
                </div>
            </div>
            
            <!-- Corner Accents -->
            <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-brand-500/10 rounded-full blur-[100px]"></div>
            <div class="absolute -left-20 -top-20 w-64 h-64 bg-indigo-500/10 rounded-full blur-[100px]"></div>
        </div>

        <!-- Sub Tabs -->
        <div class="flex gap-1 border-b border-gray-200 print:hidden overflow-x-auto">
            <button v-for="t in subTabs" :key="t.key" @click="subTab = t.key"
                    :class="[subTab === t.key ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700', 'px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap']">
                {{ t.label }}
            </button>
        </div>

        <!-- Print Header (Hidden on screen) -->
        <div class="hidden print:block mb-8 border-b border-gray-300 pb-4">
            <h1 class="text-2xl font-black text-gray-900">Projekt Statistik & Auswertung</h1>
            <p class="text-sm text-gray-500">Zeitraum: {{ dateFrom }} bis {{ dateTo }}</p>
            <p class="text-sm text-gray-500 font-bold mt-1">Ansicht: {{ currentTabLabel }}</p>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading && !stats" class="grid grid-cols-4 gap-4 print:hidden">
            <div v-for="n in 4" :key="n" class="bg-gray-100 animate-pulse rounded-xl h-24"></div>
        </div>

        <template v-if="stats">

            <!-- Sub Tab: Übersicht -->
            <div v-show="subTab === 'overview'" class="space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 print:grid-cols-4 print:gap-4 break-inside-avoid">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-2xl p-5 print:border-gray-300">
                        <div class="text-3xl font-black text-blue-700">{{ stats.summary.total_visitors }}</div>
                        <div class="text-sm font-bold text-blue-500 mt-1">Besucher</div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-2xl p-5 print:border-gray-300">
                        <div class="text-3xl font-black text-purple-700">{{ stats.summary.total_events }}</div>
                        <div class="text-sm font-bold text-purple-500 mt-1">Events</div>
                    </div>
                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 rounded-2xl p-5 print:border-gray-300">
                        <div class="text-3xl font-black text-emerald-700">{{ stats.summary.total_leads }}</div>
                        <div class="text-sm font-bold text-emerald-500 mt-1">Leads (Kontakte)</div>
                    </div>
                    <div class="bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 rounded-2xl p-5 print:border-gray-300">
                        <div class="text-3xl font-black text-amber-700">{{ stats.summary.conversion_rate }}%</div>
                        <div class="text-sm font-bold text-amber-500 mt-1">Conversion Rate</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 break-inside-avoid">
                    <!-- Events by Type -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 print:border-gray-300">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Aktivitäts-Mix</h4>
                        <div class="space-y-3">
                            <div v-for="(count, type) in stats.events_by_type" :key="type" class="flex items-center gap-4">
                                <span class="text-sm font-bold text-gray-600 w-40 truncate">{{ eventLabel(type) }}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden print:border print:border-gray-300 relative">
                                    <div class="bg-brand-500 h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2 print:bg-gray-400"
                                         :style="{ width: Math.max(barPercent(count, maxEventCount), 3) + '%' }">
                                        <span class="text-[11px] font-black text-white print:text-black">{{ count }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Usage (Search Intent) -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 print:border-gray-300">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Oft gesucht (Such-Filter)</h4>
                        <div class="space-y-3">
                            <div v-for="(count, filterName) in stats.filter_usage" :key="filterName" class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 print:border-gray-200">
                                <span class="text-sm font-medium text-gray-600 capitalize">{{ filterName }}</span>
                                <span class="text-sm font-black text-gray-800 bg-gray-100 px-2 py-0.5 rounded-full print:bg-white print:border">{{ count }}× angewendet</span>
                            </div>
                            <div v-if="!Object.keys(stats.filter_usage).length" class="text-sm text-gray-400 py-4 text-center">Keine Filter-Daten</div>
                        </div>
                    </div>
                </div>

                <!-- Timelines -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 break-inside-avoid">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Besucher pro Tag</h4>
                        <div class="space-y-1 max-h-[300px] overflow-y-auto print:max-h-none print:overflow-visible">
                            <div v-for="(count, date) in stats.visitors_per_day" :key="date" class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 print:border-gray-200">
                                <span class="text-sm font-medium text-gray-600">{{ date }}</span>
                                <span class="text-sm font-black text-gray-800">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Leads pro Tag</h4>
                        <div class="space-y-1 max-h-[300px] overflow-y-auto print:max-h-none print:overflow-visible">
                            <div v-for="(count, date) in stats.leads_per_day" :key="date" class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0 print:border-gray-200">
                                <span class="text-sm font-medium text-gray-600">{{ date }}</span>
                                <span class="text-sm font-black text-emerald-600">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.leads_per_day).length" class="text-sm text-gray-400 text-center py-4">In diesem Zeitraum keine Leads generiert</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub Tab: Bauträger -->
            <div v-show="subTab === 'developer'" class="space-y-6">
                
                <h3 class="text-xl font-black text-gray-800 print:mt-4">Portfolio & Status</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 break-inside-avoid">
                    <!-- Status Distribution -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Einheiten Status</h4>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-500"></span><span class="font-bold text-gray-700">Frei</span></div>
                                <span class="font-black">{{ stats.portfolio.distribution['Frei'] || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-500"></span><span class="font-bold text-gray-700">Reserviert</span></div>
                                <span class="font-black">{{ stats.portfolio.distribution['Reserviert'] || 0 }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span><span class="font-bold text-gray-700">Verkauft</span></div>
                                <span class="font-black">{{ stats.portfolio.distribution['Verkauft'] || 0 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Volume Distribution -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm col-span-1 lg:col-span-2">
                        <h4 class="text-lg font-black text-gray-800 mb-4">Finanzielles Volumen (€)</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Gesamt</div>
                                <div class="text-xl font-black text-gray-900">{{ formatCurrency(stats.portfolio.volume.total) }}</div>
                            </div>
                            <div class="p-4 bg-emerald-50 rounded-xl">
                                <div class="text-xs font-bold text-emerald-700 uppercase tracking-widest mb-1">Frei (Offen)</div>
                                <div class="text-xl font-black text-emerald-900">{{ formatCurrency(stats.portfolio.volume.available) }}</div>
                            </div>
                            <div class="p-4 bg-amber-50 rounded-xl">
                                <div class="text-xs font-bold text-amber-700 uppercase tracking-widest mb-1">Reserviert</div>
                                <div class="text-xl font-black text-amber-900">{{ formatCurrency(stats.portfolio.volume.reserved) }}</div>
                            </div>
                            <div class="p-4 bg-red-50 rounded-xl">
                                <div class="text-xs font-bold text-red-700 uppercase tracking-widest mb-1">Verkauft</div>
                                <div class="text-xl font-black text-red-900">{{ formatCurrency(stats.portfolio.volume.sold) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="text-xl font-black text-gray-800 mt-8 mb-4 border-b pb-2">Interaktions-Heatmap</h3>
                <!-- Interactions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 break-inside-avoid">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-md font-black text-gray-800 mb-4">Nach Haus</h4>
                        <div class="space-y-2">
                            <div v-for="(count, id) in stats.house_interactions" :key="id" class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0 border-dashed">
                                <span class="font-medium text-gray-600">Haus #{{ id }}</span>
                                <span class="font-black bg-gray-100 px-2 rounded">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.house_interactions).length" class="text-sm text-gray-400">Keine Daten</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-md font-black text-gray-800 mb-4">Nach Etage</h4>
                        <div class="space-y-2">
                            <div v-for="(count, id) in stats.floor_interactions" :key="id" class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0 border-dashed">
                                <span class="font-medium text-gray-600">Etage #{{ id }}</span>
                                <span class="font-black bg-gray-100 px-2 rounded">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.floor_interactions).length" class="text-sm text-gray-400">Keine Daten</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="text-md font-black text-gray-800 mb-4">Nach Layern</h4>
                        <div class="space-y-2">
                            <div v-for="(count, id) in stats.layer_interactions" :key="id" class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0 border-dashed">
                                <span class="font-medium text-gray-600">Layer #{{ id }}</span>
                                <span class="font-black bg-gray-100 px-2 rounded">{{ count }}</span>
                            </div>
                            <div v-if="!Object.keys(stats.layer_interactions).length" class="text-sm text-gray-400">Keine Daten</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sub Tab: Verkäufer -->
            <div v-show="subTab === 'sales'" class="space-y-6">
                
                <h3 class="text-xl font-black text-gray-800 print:mt-4">Sales Performance & Funnel</h3>
                
                <!-- Performance KPIs -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 break-inside-avoid">
                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-2xl p-6 print:border-gray-300">
                        <div class="text-3xl font-black text-indigo-700">{{ stats.sales_performance?.avg_time_on_market_days || 0 }} Tage</div>
                        <div class="text-sm font-bold text-indigo-800 mt-1">Ø Vermarktungsdauer bis Kauf</div>
                        <p class="text-xs text-indigo-600 mt-2">Dauer von Erstellung bis Reserviert/Verkauft</p>
                    </div>
                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 rounded-2xl p-6 print:border-gray-300">
                        <div class="text-3xl font-black text-emerald-700">{{ Object.values(stats.sales_performance?.sales_velocity || {}).reduce((a,b)=>a+b,0) }}</div>
                        <div class="text-sm font-bold text-emerald-800 mt-1">Verkaufte Einheiten (Gesamt)</div>
                        <p class="text-xs text-emerald-600 mt-2">Erfolgreich abgewickelte Verkäufe</p>
                    </div>
                    <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-2xl p-6 print:border-gray-300">
                        <div class="text-3xl font-black text-red-700">{{ stats.sales_performance?.cancellations || 0 }}</div>
                        <div class="text-sm font-bold text-red-800 mt-1">Reservierungs-Stornos</div>
                        <p class="text-xs text-red-600 mt-2">Rückläufer von Reserviert auf Frei</p>
                    </div>
                </div>

                <!-- Funnel -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm break-inside-avoid">
                    <h4 class="text-lg font-black text-gray-800 mb-6">Sales Funnel</h4>
                    <div class="flex flex-col md:flex-row items-stretch justify-center gap-4 text-center">
                        <div class="flex-1 bg-blue-50 border border-blue-200 rounded-xl p-6 relative">
                            <div class="text-4xl font-black text-blue-700 mb-2">{{ stats.summary.total_visitors }}</div>
                            <div class="text-sm font-bold text-blue-800">Gesamte Besucher</div>
                        </div>
                        <div class="flex items-center justify-center text-gray-300 print:hidden">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </div>
                        <div class="flex-1 bg-indigo-50 border border-indigo-200 rounded-xl p-6 relative">
                            <div class="text-4xl font-black text-indigo-700 mb-2">{{ Object.values(stats.top_apartments).reduce((a,b)=>a+b, 0) || 0 }}</div>
                            <div class="text-sm font-bold text-indigo-800">Wohnungsaufrufe</div>
                        </div>
                        <div class="flex items-center justify-center text-gray-300 print:hidden">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </div>
                        <div class="flex-1 bg-emerald-50 border border-emerald-200 rounded-xl p-6 relative">
                            <div class="text-4xl font-black text-emerald-700 mb-2">{{ stats.summary.total_leads }}</div>
                            <div class="text-sm font-bold text-emerald-800">Kontakte generiert</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm break-inside-avoid print:mt-8">
                    <h4 class="text-lg font-black text-gray-800 mb-4">Hot List: Meistgesehene Wohnungen</h4>
                    <div class="space-y-4">
                        <div v-for="(count, apId) in stats.top_apartments" :key="apId" class="flex flex-col md:flex-row md:items-center gap-2 md:gap-6 border-b border-gray-100 last:border-0 pb-3 last:pb-0">
                            <div class="w-48 shrink-0">
                                <span class="font-bold text-gray-800 text-md">{{ apartmentName(apId) }}</span>
                                <div class="text-xs text-gray-500 font-mono">{{ formatCurrency(apartmentPrice(apId)) }}</div>
                            </div>
                            
                            <div class="flex-1 bg-gray-100 rounded-full h-8 overflow-hidden relative">
                                <div class="bg-gradient-to-r from-emerald-400 to-emerald-500 h-full rounded-full transition-all flex items-center justify-end pr-3"
                                     :style="{ width: Math.max(barPercent(count, maxTopApCount), 5) + '%' }">
                                    <span class="text-xs font-black text-white shadow-sm">{{ count }} Aufrufe</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!Object.keys(stats.top_apartments).length" class="text-sm text-gray-400 py-4 text-center">Kein Interesse an konkreten Wohnungen verzeichnet.</div>
                    </div>
                </div>
            </div>

            <!-- Sub: Besucher Profil -->
            <div v-show="subTab === 'visitors'" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 break-inside-avoid">
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Geräte-Verteilung</h4>
                        <div v-for="(count, name) in stats.devices" :key="name" class="flex justify-between py-1.5 text-sm border-b border-gray-50 last:border-0">
                            <span class="text-gray-600 capitalize">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Top Browser</h4>
                        <div v-for="(count, name) in stats.browsers" :key="name" class="flex justify-between py-1.5 text-sm border-b border-gray-50 last:border-0">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h4 class="font-black text-gray-800 mb-3">Herkunftsländer</h4>
                        <div v-for="(count, name) in stats.countries" :key="name" class="flex justify-between py-1.5 text-sm border-b border-gray-50 last:border-0">
                            <span class="text-gray-600">{{ name || 'Unbekannt' }}</span>
                            <span class="font-bold text-gray-800">{{ count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub Tab: Referrer-Quellen -->
            <div v-show="subTab === 'referrers'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm break-inside-avoid">
                    <h3 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-2">
                        <GlobeAltIcon class="w-6 h-6 text-brand-500" />
                        Referrer-Analyse (Herkunft der Besucher)
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Top List -->
                        <div class="space-y-4">
                            <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Besucherquellen (Domains)</h4>
                            <div v-for="(count, name) in stats.referrers" :key="name" class="flex flex-col gap-1">
                                <div class="flex justify-between items-end">
                                    <span class="text-sm font-bold text-gray-700 capitalize">{{ name }}</span>
                                    <span class="text-xs font-black text-brand-600 bg-brand-50 px-2 py-0.5 rounded">{{ count }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                    <div class="bg-brand-500 h-full rounded-full transition-all duration-700" 
                                         :style="{ width: (count / stats.summary.total_visitors * 100) + '%' }"></div>
                                </div>
                            </div>
                            <div v-if="!Object.keys(stats.referrers).length" class="text-center py-8 text-gray-400 italic">
                                Keine Referrer-Daten vorhanden (Direkteinstiege).
                            </div>
                        </div>

                        <!-- Info Card -->
                        <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 flex flex-col justify-center">
                            <div class="text-4xl mb-4">🌍</div>
                            <h4 class="text-lg font-black text-gray-800 mb-2">Woher kommen Ihre Kunden?</h4>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Diese Übersicht zeigt Ihnen, welche Plattformen (z.B. Google, Facebook, Immowelt) den meisten Traffic auf Ihr Projekt lenken. 
                                Nutzen Sie diese Daten, um Ihr Marketing-Budget gezielter in die Kanäle mit der höchsten Performance zu investieren.
                            </p>
                            <div class="mt-6 flex items-center gap-4">
                                <div class="px-4 py-2 bg-white rounded-lg border shadow-sm">
                                    <div class="text-[10px] uppercase font-bold text-gray-400">Direkt / Unbekannt</div>
                                    <div class="text-xl font-black text-gray-900">{{ stats.summary.total_visitors - Object.values(stats.referrers).reduce((a,b)=>a+b, 0) }}</div>
                                </div>
                                <div class="px-4 py-2 bg-white rounded-lg border shadow-sm">
                                    <div class="text-[10px] uppercase font-bold text-gray-400">Über Quellen</div>
                                    <div class="text-xl font-black text-gray-900">{{ Object.values(stats.referrers).reduce((a,b)=>a+b, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub: Events Live -->
            <div v-show="subTab === 'events'" class="space-y-6 break-inside-avoid">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between print:hidden">
                        <h4 class="font-black text-gray-800">Letzte Aktionen (Live Stream)</h4>
                        <button @click="loadStats" class="text-sm text-brand-600 font-bold hover:text-brand-800 transition">↻ Aktualisieren</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Zeit</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Event</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ziel</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Besucher</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ev in stats.recent_events" :key="ev.id" class="border-b border-gray-50 hover:bg-gray-50 transition print:break-inside-avoid">
                                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ formatDate(ev.created_at) }}</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-bold print:border print:border-gray-200" :class="eventClass(ev.event_type)">{{ eventLabel(ev.event_type) }}</span></td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <span v-if="ev.target_type">{{ ev.target_type }} #{{ ev.target_id }}</span>
                                        <span v-else class="text-gray-300">–</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ ev.visitor?.device || 'Unknown' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sub Tab: Kampagnen -->
            <div v-show="subTab === 'campaigns'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm break-inside-avoid">
                    <h3 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-2">
                        <MegaphoneIcon class="w-6 h-6 text-brand-500" />
                        Kampagnen-Tracking
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase">Kampagne / UTM-Source</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase">Besucher</th>
                                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase">Leads</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase">Conv. Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(data, camp) in stats.campaigns" :key="camp" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full bg-brand-500"></div>
                                            <span class="font-bold text-gray-800">{{ camp }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium">{{ data.visitors }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-black">{{ data.leads }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900">{{ data.conversion_rate }}%</td>
                                </tr>
                                <tr v-if="!Object.keys(stats.campaigns || {}).length">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400 italic">
                                        Noch keine Kampagnen-Daten erfasst. 
                                        <br>Verwenden Sie URL-Parameter wie <code class="bg-gray-100 px-1 rounded">?campaign=meine_werbung</code> 
                                        um Klicks zu tracken.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div v-show="subTab === 'leads'" class="space-y-6">

                <h3 class="text-xl font-black text-gray-800 print:mt-4">Lead-Scoring & Priorisierung</h3>

                <!-- Score Distribution Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" v-if="stats.lead_scoring">
                    <div class="bg-gradient-to-br from-red-50 to-orange-50 border border-red-200 rounded-2xl p-5 text-center">
                        <div class="text-4xl font-black text-red-600">{{ stats.lead_scoring.distribution.hot }}</div>
                        <div class="text-sm font-bold text-red-700 mt-1">🔥 Heiße Leads</div>
                        <div class="text-[10px] text-red-400 mt-1">Score ≥ 70</div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-5 text-center">
                        <div class="text-4xl font-black text-orange-600">{{ stats.lead_scoring.distribution.warm }}</div>
                        <div class="text-sm font-bold text-orange-700 mt-1">🟠 Warme Leads</div>
                        <div class="text-[10px] text-orange-400 mt-1">Score 45–69</div>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-50 to-lime-50 border border-yellow-200 rounded-2xl p-5 text-center">
                        <div class="text-4xl font-black text-yellow-600">{{ stats.lead_scoring.distribution.interested }}</div>
                        <div class="text-sm font-bold text-yellow-700 mt-1">🟡 Interessiert</div>
                        <div class="text-[10px] text-yellow-400 mt-1">Score 20–44</div>
                    </div>
                    <div class="bg-gradient-to-br from-gray-50 to-slate-50 border border-gray-200 rounded-2xl p-5 text-center">
                        <div class="text-4xl font-black text-gray-500">{{ stats.lead_scoring.distribution.cold }}</div>
                        <div class="text-sm font-bold text-gray-600 mt-1">⚪ Besucher</div>
                        <div class="text-[10px] text-gray-400 mt-1">Score < 20</div>
                    </div>
                </div>

                <!-- Top Leads Table -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm" v-if="stats.lead_scoring?.top_leads?.length">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h4 class="font-black text-gray-800">Top 20 Leads – Priorisierung</h4>
                        <span class="text-xs text-gray-400 font-medium">Sortiert nach Lead-Score</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Score</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Label</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Interessen</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ø Budget</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Fenster</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Letzter Besuch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(lead, idx) in stats.lead_scoring.top_leads" :key="lead.id" class="border-b border-gray-50 hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-sm font-bold text-gray-400">{{ idx + 1 }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-12 bg-gray-100 rounded-full h-2 overflow-hidden">
                                                <div class="h-full rounded-full transition-all" :class="lead.lead_score >= 70 ? 'bg-red-500' : lead.lead_score >= 45 ? 'bg-orange-500' : lead.lead_score >= 20 ? 'bg-yellow-500' : 'bg-gray-300'" :style="{ width: lead.lead_score + '%' }"></div>
                                            </div>
                                            <span class="text-sm font-black" :class="lead.lead_score >= 70 ? 'text-red-600' : lead.lead_score >= 45 ? 'text-orange-600' : lead.lead_score >= 20 ? 'text-yellow-600' : 'text-gray-400'">{{ lead.lead_score }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold">{{ lead.lead_label }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="tag in lead.interests" :key="tag" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-brand-50 text-brand-700 border border-brand-100">
                                                {{ tag }}
                                            </span>
                                            <span v-if="!lead.interests?.length" class="text-gray-300 text-xs">—</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-gray-700">{{ lead.budget_summary }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-400">{{ lead.visit_count }}× · {{ lead.device }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(lead.last_visit_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Scoring Explanation -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="font-black text-gray-800 mb-3">So wird der Score berechnet</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-sm">
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">20 Pkt.</div>
                            <div class="text-xs text-gray-500">Wiederkehrende Besuche</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">20 Pkt.</div>
                            <div class="text-xs text-gray-500">Whg. angesehen</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">15 Pkt.</div>
                            <div class="text-xs text-gray-500">Favoriten</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">15 Pkt.</div>
                            <div class="text-xs text-gray-500">Kontakt geöffnet</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">10 Pkt.</div>
                            <div class="text-xs text-gray-500">3D-Touren</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">10 Pkt.</div>
                            <div class="text-xs text-gray-500">Slider/Galerie</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">5 Pkt.</div>
                            <div class="text-xs text-gray-500">Karte geöffnet</div>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <div class="font-black text-gray-700">5 Pkt.</div>
                            <div class="text-xs text-gray-500">Aktualitäts-Bonus</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub Tab: Matchmaker -->
            <div v-show="subTab === 'matchmaker'" class="space-y-6">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-6 h-6 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="text-xl font-black text-gray-800">Matchmaker Engine (Reverse-Match)</h3>
                </div>
                <p class="text-sm text-gray-500 max-w-2xl">Unsere KI analysiert das Besucherverhalten (Aufrufe, Favoriten) und findet automatisch passende, freie Wohnungen für Ihre heißesten Leads.</p>

                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden print:border-gray-300">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lead / Besucher</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Match Score</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Empfohlene Wohnung</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Grundlage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="match in stats.matchmaker" :key="match.visitor_id + '-' + match.apartment_id" class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">{{ match.visitor_label }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">ID: {{ match.visitor_id }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-12 bg-gray-200 rounded-full h-2 overflow-hidden">
                                            <div class="bg-brand-500 h-full transition-all duration-1000" :style="{ width: match.score + '%' }"></div>
                                        </div>
                                        <span class="text-sm font-black text-brand-600">{{ match.score }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900">{{ match.apartment_name }}</div>
                                    <div v-if="match.has_viewed" class="text-[10px] text-emerald-600 font-bold flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                        Bereits angesehen
                                    </div>
                                    <div v-else class="text-[10px] text-amber-600 font-bold flex items-center gap-0.5">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/></svg>
                                        Noch nicht gesehen
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-500 italic">{{ match.reason }}</div>
                                </td>
                            </tr>
                            <tr v-if="!stats.matchmaker?.length">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-400">
                                    Keine passenden Matches im gewählten Zeitraum gefunden.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </template>

        <!-- No data state -->
        <div v-if="!loading && !stats" class="bg-white rounded-2xl border border-gray-200 p-12 text-center print:hidden">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            <h3 class="text-xl font-black text-gray-500 mb-2">Statistik laden</h3>
            <p class="text-sm text-gray-400 mb-4">Wähle einen Zeitraum und klicke auf "Laden".</p>
            <button @click="loadStats" class="px-6 py-2.5 bg-brand-500 text-white font-bold rounded-xl hover:bg-brand-600 transition shadow-sm">Jetzt laden</button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { 
    SparklesIcon, 
    ArrowPathIcon, 
    GlobeAltIcon, 
    MegaphoneIcon,
    ArrowTrendingUpIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    projectId: { type: Number, required: true },
    apartments: { type: Array, default: () => [] },
});

const subTabs = [
    { key: 'overview', label: 'Dashboard' },
    { key: 'referrers', label: 'Quellen' },
    { key: 'campaigns', label: 'Kampagnen' },
    { key: 'developer', label: 'Portfolio & Status' },
    { key: 'sales', label: 'Vertrieb & Funnel' },
    { key: 'leads', label: 'Lead-Scoring' },
    { key: 'visitors', label: 'Besucher Profil' },
    { key: 'events', label: 'Aktivitäten' },
];

const subTab = ref('overview');
const loading = ref(false);
const stats = ref(null);

const dateFrom = ref(new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const dateTo = ref(new Date().toISOString().slice(0, 10));

const currentTabLabel = computed(() => {
    return subTabs.find(t => t.key === subTab.value)?.label || 'Unbekannt';
});

const loadStats = async () => {
    loading.value = true;
    try {
        const res = await fetch(`/tracking/stats/${props.projectId}?from=${dateFrom.value}&to=${dateTo.value}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (res.ok) {
            stats.value = await res.json();
        }
    } catch (e) {
        console.error('Stats load failed', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => loadStats());

const aiAdvice = ref('');
const aiSections = ref([]);
const aiLoading = ref(false);

const generateAiAdvice = async () => {
    if (!stats.value) return;
    aiLoading.value = true;
    aiAdvice.value = '';
    aiSections.value = [];
    
    try {
        const topUnits = stats.value.top_apartments ? Object.keys(stats.value.top_apartments).map(id => apartmentName(id)).join(', ') : 'Keine';
        const topUnitPerformers = stats.value.matchmaker_summary?.top_performing_unit_name || 'Unbekannt';
        const matchCount = stats.value.matchmaker_summary?.total_high_intent || 0;
        const convRate = stats.value.summary?.conversion_rate || 0;
        const filterUsage = Object.entries(stats.value.filter_usage || {}).map(([k, v]) => `${k}(${v}x)`).join(', ');
        const visitorCount = stats.value.summary?.total_visitors || 0;
        const returningVisitors = stats.value.summary?.returning_visitors || 0;
        const mobileRatio = stats.value.devices?.mobile || 0;
        const referrerUsage = Object.entries(stats.value.referrers || {}).map(([k, v]) => `${k}(${v}x)`).join(', ') || 'Keine (Direkt)';
        const campaignPerf = Object.entries(stats.value.campaigns || {}).map(([k, v]) => `${k}: ${v.visitors} Bes / ${v.leads} Leads`).join(', ') || 'Keine';

        const prompt = `Analysiere diese Immobiliendaten und gib innovative, strategische Auswertungen (Deutsch). 
        Antworte EXAKT in diesem Format (Benutze HTML-Tags wie <b>, <ul>, <li> für Struktur, aber KEIN Markdown-Code-Zäune):
        MARKETING: [Konkreter Insight für Online-Werbung/Quellen unter Berücksichtigung der Referrer & Kampagnen]
        SALES: [Optimierungs-Strategie für den Vertrieb/Matchmaker]
        PRODUCT: [Was am Web-Exposé oder den Einheiten geändert werden sollte]

        DATEN:
        - Besucher: ${visitorCount} (davon ${returningVisitors} wiederkehrend)
        - Referrer (Quellen): ${referrerUsage}
        - Kampagnen-Performance: ${campaignPerf}
        - Konvertierung: ${convRate}% (Ziel: >3%)
        - Top-Einheiten (Klicks): ${topUnits}
        - Top-Matches (System): ${topUnitPerformers} (${matchCount} Personen haben hohes Interesse!)
        - Suchverhalten: ${filterUsage}
        - Mobil-Nutzer: ${mobileRatio} von ${visitorCount}
        - Offenes Volumen: ${formatCurrency(stats.value.portfolio?.volume?.available || 0)}

        Sei innovativ, direkt und geil. Gib keine Standard-Tips sondern "Smart Data" Insights. Nutze HTML zur Formatierung der Highlights.`;

        const response = await axios.post(route('ai.generate'), {
            prompt,
            model: 'llama3.1'
        });
        
        if (response.data.success) {
            const content = response.data.content;
            
            // Parse sections
            const marketingMatch = content.match(/MARKETING:?\s*(.*?)(?=\n\w+:|$)/s);
            const salesMatch = content.match(/SALES:?\s*(.*?)(?=\n\w+:|$)/s);
            const productMatch = content.match(/PRODUCT:?\s*(.*?)(?=\n\w+:|$)/s);

            if (marketingMatch) aiSections.value.push({ title: 'Marketing', icon: '📢', content: marketingMatch[1].trim() });
            if (salesMatch) aiSections.value.push({ title: 'Vertrieb', icon: '💰', content: salesMatch[1].trim() });
            if (productMatch) aiSections.value.push({ title: 'Produkt', icon: '🏗️', content: productMatch[1].trim() });

            if (!aiSections.value.length) {
                aiAdvice.value = content;
                aiSections.value.push({ title: 'AI Analyse', icon: '✨', content: content });
            }
        }
    } catch (e) {
        console.error('AI Advice Error:', e);
    } finally {
        aiLoading.value = false;
    }
};

const printReport = () => {
    window.print();
};

// --- Helpers ---
const maxEventCount = computed(() => {
    if (!stats.value?.events_by_type) return 1;
    return Math.max(...Object.values(stats.value.events_by_type), 1);
});

const maxTopApCount = computed(() => {
    if (!stats.value?.top_apartments) return 1;
    return Math.max(...Object.values(stats.value.top_apartments), 1);
});

const barPercent = (count, max) => (count / max) * 100;

const eventLabels = {
    page_view: 'Aufruf: Projektseite',
    apartment_view: 'Aufruf: Wohnung',
    favorite: 'Favorisiert',
    map_open: 'Karte geöffnet',
    slider_open: 'Slider Ansicht',
    tour_open: '3D-Rundgang',
    contact_click: 'Kontakt (Lead)',
    filter_used: 'Filter genutzt',
    view_change: 'Perspektiven-Wechsel',
};
const eventLabel = (type) => eventLabels[type] || type;

const eventClass = (type) => {
    const classes = {
        page_view: 'bg-blue-100 text-blue-700',
        apartment_view: 'bg-emerald-100 text-emerald-700 text-xs',
        favorite: 'bg-red-100 text-red-700',
        contact_click: 'bg-indigo-100 text-indigo-700',
        filter_used: 'bg-gray-100 text-gray-700',
    };
    return classes[type] || 'bg-gray-100 text-gray-700';
};

const apartmentObj = (id) => {
    return props.apartments.find(a => a.id === parseInt(id));
};

const apartmentName = (id) => {
    const ap = apartmentObj(id);
    return ap ? ap.name : `ID #${id}`;
};

const apartmentPrice = (id) => {
    const ap = apartmentObj(id);
    return ap ? ap.price : 0;
};

const formatCurrency = (val) => {
    if (!val) return '0 €';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (d) => {
    if (!d) return '–';
    return new Date(d).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>
