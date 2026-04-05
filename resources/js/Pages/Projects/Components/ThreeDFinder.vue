<template>
    <div ref="containerRef" 
         class="w-full h-full relative bg-gray-100 overflow-hidden outline-none select-none"
         :class="{'cursor-grab': activeImageUrl && !isDragging && !drawMode && !draggedPointIndex && !draggedPolyId, 'cursor-grabbing': isDragging || draggedPolyId, 'cursor-crosshair': drawMode}"
         tabindex="0"
         @mousedown="onMouseDown" 
         @mousemove.prevent="onMouseMove" 
         @mouseup="onMouseUp" 
         @mouseleave="onMouseUp"
         @wheel.prevent="handleWheel"
         @keydown="handleKeydown">

        <!-- Draggable Wrapper -->
        <div class="absolute w-full h-full pointer-events-none origin-center py-0 z-0" :style="wrapperStyle">
            
            <template v-if="activeImageUrl && !isLoadingFrames">
                <!-- If depth map is available, load WebGL renderer -->
                <ShadowRenderer 
                    v-if="activeDepthMapUrl"
                    :image-url="activeImageUrl"
                    :depth-map-url="activeDepthMapUrl"
                    :sun-azimuth="sunData?.diff || 0"
                    :sun-altitude="sunData?.altitude || 30"
                    :intensity="showSun && sunData?.altitude > 0 ? 1.0 : 0.0"
                    @load="onImgLoad"
                    :style="[dynamicImgStyle]"
                    class="w-full h-full pointer-events-none transition-[filter,transform] duration-75 ease-in-out"
                    :class="{ 'scale-105 blur-[2px] opacity-90 brightness-110': isAutoPlaying  }"
                 />
                 
                <!-- Traditional image render (Buffered with v-show for flicker-free animation) -->
                <template v-else>
                    <img v-for="frame in activeViewFrames" :key="'img_' + frame.id"
                         v-show="activeFrame?.id === frame.id"
                         :src="getFrameImageUrl(frame)" 
                         class="w-full h-full pointer-events-none transition-[filter,transform] duration-75 ease-in-out absolute inset-0" 
                         :class="{ 'scale-105 blur-[2px] opacity-90 brightness-110': isAutoPlaying  }"
                         :style="[dynamicImgStyle, {objectFit: 'fill'}]"
                         @load="activeFrame?.id === frame.id ? onImgLoad($event) : null" 
                         alt="3D Frame" />
                </template>
            </template>

            <!-- Sun Flare Layer -->
            <div v-if="showSun && sunData?.isVisible" class="absolute pointer-events-none z-10 duration-75 ease-in-out" :style="{
                    left: `${sunData.xPercent}%`,
                    top: `${sunData.yPercent}%`,
                    transform: 'translate(-50%, -50%)',
                    width: '300px',
                    height: '300px',
                    mixBlendMode: 'screen',
                    opacity: sunData.intensity
                }">
                <div class="absolute inset-0 m-auto rounded-full bg-white shadow-[0_0_120px_40px_#facc15]" style="width: 50px; height: 50px; filter: blur(5px);"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle,rgba(253,224,71,0.5)_0%,rgba(234,88,12,0.1)_40%,transparent_70%)]"></div>
            </div>

            <!-- Polygons Layer -->
            <svg class="absolute top-0 left-0 w-full h-full pointer-events-auto overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" @click="handleSvgClick">
                <polygon 
                    v-for="(poly, idx) in framePolygons" 
                    :key="poly.id"
                    :points="poly.points.map(p => `${p.x},${p.y}`).join(' ')"
                    :fill="getPolygonFill(poly, idx)"
                    :stroke="getPolygonStroke(poly, idx)"
                    stroke-width="0.3"
                    vector-effect="non-scaling-stroke"
                    class="transition-colors duration-200"
                    :class="{
                        'opacity-80': selectedPolyId === poly.id, 
                        'opacity-40 hover:opacity-60': selectedPolyId !== poly.id, 
                        'cursor-pointer': !isAuth, 
                        'cursor-move hover:stroke-white': isAuth && selectedPolyId === poly.id && !isCtrlHeld,
                        'cursor-crosshair': isAuth && selectedPolyId === poly.id && isCtrlHeld,
                        'pointer-events-none': poly.apartment_id && !visibleApartmentIds.includes(poly.apartment_id)
                    }"
                    @click.stop="!isAuth ? handlePolygonPublicClick(poly) : selectPolygon(poly)"
                    @dblclick.stop="isAuth ? handlePolygonPublicClick(poly) : null"
                    @mousedown.stop="startDragPolygon(poly, $event)"
                    @mousemove.stop="handlePolyMouseMove(poly, $event)"
                    @mouseleave="handlePolyMouseLeave"
                />

                <!-- Points Layer -->
                <template v-for="(pt, idx) in framePoints" :key="pt.id">
                    <g :transform="`translate(${pt.x}, ${pt.y})`"
                       :class="{
                           'opacity-100': selectedPointId === pt.id || pt.apartment_id === activeApartmentId,
                           'opacity-60 hover:opacity-80': selectedPointId !== pt.id,
                           'cursor-pointer': !isAuth,
                           'cursor-move': isAuth && selectedPointId === pt.id,
                           'pointer-events-none': pt.apartment_id && !visibleApartmentIds.includes(pt.apartment_id)
                       }"
                       @click.stop="!isAuth ? handlePointPublicClick(pt) : selectPoint(pt)"
                       @dblclick.stop="isAuth ? handlePointPublicClick(pt) : null"
                       @mousedown.stop="startDragElementPoint(pt, $event)"
                       @mousemove.stop="handlePointMouseMove(pt, $event)"
                       @mouseleave="handlePointMouseLeave">
                       
                        <!-- Custom SVG -->
                        <g v-if="pt.icon_type === 'custom'" v-html="pt.custom_svg" 
                           :fill="getPointFill(pt, idx)" 
                           :transform="`scale(${pt.size / 100})`" style="transform-origin: center;"></g>
                        
                        <!-- Preset Circle -->
                        <circle v-else-if="pt.icon_type === 'circle'" r="1.5"
                                :fill="getPointFill(pt, idx)"
                                :stroke="getPointStroke(pt, idx)" stroke-width="0.3"
                                vector-effect="non-scaling-stroke"
                                :transform="`scale(${pt.size / 100})`" />
                        
                        <!-- Preset Pin -->
                        <path v-else-if="pt.preset_icon === 'pin' || !pt.preset_icon" 
                              d="M0,-3 C1.6,-3 3,-1.6 3,0 C3,2 0,5 0,5 C0,5 -3,2 -3,0 C-3,-1.6 -1.6,-3 0,-3 Z"
                              :fill="getPointFill(pt, idx)"
                              :stroke="getPointStroke(pt, idx)" stroke-width="0.2"
                              vector-effect="non-scaling-stroke"
                              :transform="`scale(${pt.size / 100}) translate(0, -1)`" />
                              
                        <!-- Preset Star -->
                        <polygon v-else-if="pt.preset_icon === 'star'"
                                 points="0,-3 0.9,-0.9 3.2,-0.9 1.3,0.5 2,2.8 0,1.4 -2,2.8 -1.3,0.5 -3.2,-0.9 -0.9,-0.9"
                                 :fill="getPointFill(pt, idx)"
                                 :stroke="getPointStroke(pt, idx)" stroke-width="0.2"
                                 vector-effect="non-scaling-stroke"
                                 :transform="`scale(${pt.size / 100})`" />
                                 
                        <!-- Preset Square -->
                        <rect v-else-if="pt.preset_icon === 'square'"
                              x="-1.5" y="-1.5" width="3" height="3"
                              :fill="getPointFill(pt, idx)"
                              :stroke="getPointStroke(pt, idx)" stroke-width="0.2"
                              vector-effect="non-scaling-stroke"
                              :transform="`scale(${pt.size / 100})`" />
                    </g>
                </template>

                <!-- Active drawing points -->
                <circle v-for="(p, i) in currentDrawPoints" :key="'curr_'+i" :cx="p.x" :cy="p.y" r="0.6" fill="red" vector-effect="non-scaling-stroke" />
                <polyline v-if="currentDrawPoints.length > 0" 
                          :points="currentDrawPoints.map(p => `${p.x},${p.y}`).join(' ')" 
                          fill="none" stroke="red" stroke-width="0.3" vector-effect="non-scaling-stroke" stroke-dasharray="1 1" />

                <!-- Edit Points for selected Polygon -->
                <template v-if="selectedPolygon && !drawMode && isAuth">
                    <circle v-for="(p, i) in selectedPolygon.points" :key="'edit_'+i"
                            :cx="p.x" :cy="p.y" r="0.6" 
                            fill="white" stroke="#ab715c" stroke-width="0.3" 
                            vector-effect="non-scaling-stroke"
                            class="hover:fill-brand-200"
                            :class="isAltHeld ? 'cursor-pointer stroke-red-500 fill-red-100 hover:fill-red-500 hover:stroke-red-600' : 'cursor-move'"
                            @mousedown.stop="startDragPoint(i, $event)" />
                </template>
            </svg>

        </div>

        <!-- Tooltip (Teleported to body to avoid overflow clipping) -->
        <Teleport to="body">
            <div v-if="hoveredPolyAp && tooltipPos.x > 0" class="fixed pointer-events-none z-[9998] bg-white rounded-[18px] shadow-[0_8px_32px_rgba(0,0,0,0.12)] overflow-hidden" 
                 :style="{ left: tooltipPos.x + 15 + 'px', top: tooltipPos.y + 15 + 'px', minWidth: '300px', maxWidth: '360px' }">

                 <!-- Row 1: Avatar + Name + Price -->
                 <div class="flex items-center gap-3 px-4 pt-4 pb-3">
                     <div class="relative w-11 h-11 shrink-0">
                         <img :src="hoveredPolyAp.media?.[0]?.original_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=150&q=80'" class="w-full h-full object-cover rounded-full border border-gray-100 shadow-sm" alt="Wohnung" />
                         <div class="absolute -top-1 -left-1 bg-white rounded-full p-[3px] shadow-sm border border-gray-100 z-10 flex items-center justify-center">
                             <svg class="w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                         </div>
                     </div>
                     <span class="text-[15px] font-medium text-gray-600 tracking-tight truncate flex-1">{{ hoveredPolyAp.name }}</span>
                     <div class="bg-[#dcf0d5] text-[#3f6327] px-3 py-1 rounded-full font-black shrink-0 flex items-baseline gap-1 shadow-sm">
                         <template v-if="hoveredPolyAp.price">
                             <span class="text-[11px] font-bold opacity-70">EUR</span>
                             <span class="text-[15px] font-black tracking-tight">{{ new Intl.NumberFormat('de-DE').format(hoveredPolyAp.price) }}</span>
                         </template>
                         <template v-else-if="hoveredPolyAp.warm_rent">
                             <span class="text-[11px] font-bold opacity-70">EUR</span>
                             <span class="text-[15px] font-black tracking-tight">{{ new Intl.NumberFormat('de-DE').format(hoveredPolyAp.warm_rent) }}</span>
                         </template>
                         <template v-else>
                             <span class="text-[13px] font-black tracking-tight uppercase">Auf Anfrage</span>
                         </template>
                     </div>
                 </div>

                 <!-- Row 2: Details (Sqm, Floor, Rooms) -->
                 <div class="bg-gray-50 flex items-center justify-between px-6 py-3 border-t border-gray-100">
                     <div class="flex flex-col items-start">
                         <span class="text-[14px] font-black tracking-tight text-gray-800 leading-tight">{{ hoveredPolyAp.sqm || '–' }}<span class="text-[11px] ml-0.5">m²</span></span>
                         <span class="text-[11px] font-semibold text-gray-400 leading-none mt-0.5">Fläche</span>
                     </div>
                     <div class="w-px h-[28px] bg-gray-200"></div>
                     <div class="flex flex-col items-center">
                         <span class="text-[14px] font-black tracking-tight text-gray-800 leading-tight">{{ project.floors?.find(f => f.id === hoveredPolyAp.floor_id)?.name || 'EG' }}</span>
                         <span class="text-[11px] font-semibold text-gray-400 leading-none mt-0.5">Geschoss</span>
                     </div>
                     <div class="w-px h-[28px] bg-gray-200"></div>
                     <div class="flex flex-col items-end">
                         <span class="text-[14px] font-black tracking-tight text-gray-800 leading-tight">{{ hoveredPolyAp.rooms || '–' }}</span>
                         <span class="text-[11px] font-semibold text-gray-400 leading-none mt-0.5">Zimmer</span>
                     </div>
                 </div>
            </div>

            <!-- Custom Point Tooltip -->
            <div v-if="hoveredPointTooltipText && tooltipPos.x > 0" class="fixed pointer-events-none z-[9998] bg-black/90 text-white rounded-lg shadow-xl overflow-hidden px-4 py-3" 
                 :style="{ left: tooltipPos.x + 15 + 'px', top: tooltipPos.y + 15 + 'px', minWidth: '150px', maxWidth: '300px' }">
                 <p class="text-[13px] font-bold tracking-tight whitespace-pre-wrap leading-snug">{{ hoveredPointTooltipText }}</p>
            </div>
        </Teleport>

        <!-- Fallback Placeholder -->
        <div v-if="!activeImageUrl" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none p-6 z-10">
            <svg class="w-24 h-24 mb-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2-1m2 1l-2 1m2-1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
            </svg>
            <h2 class="text-3xl font-light text-gray-500 mb-2">Kein Bild verfügbar</h2>
            <div class="max-w-md mx-auto text-gray-400 text-center flex flex-col gap-2">
                <span v-if="activeFrame">Frame #{{ activeFrame.index }} enthält kein Bild im Layer "{{ activeLayer?.name || 'default' }}".</span>
            </div>
        </div>

        <!-- Top Left Public Interaction Buttons -->
        <div class="absolute top-6 left-6 right-6 flex items-center gap-3 z-50">
            <!-- Fullscreen -->
            <button @click="toggleFullscreen" class="w-10 h-10 flex items-center justify-center bg-white/95 backdrop-blur-sm shadow-md rounded-full border border-gray-200 text-gray-700 hover:text-black hover:bg-white transition" title="Vollbild">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
            </button>
            
            <!-- Verfügbarkeit -->
            <button @click="isAvailabilityOnly = !isAvailabilityOnly" :class="['px-4 h-10 flex items-center gap-2 bg-white/95 backdrop-blur-sm shadow-md rounded-full border border-gray-200 text-[13px] font-bold transition', isAvailabilityOnly ? 'text-[#ab715c]' : 'text-gray-700']">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" :class="isAvailabilityOnly ? 'text-[#ab715c]' : 'text-[#5b873e]'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                <span class="hidden sm:inline">Verfügbarkeit</span>
            </button>
            
            <!-- Etagen Filter (Quick Icon Overlay) -->
            <div class="relative" v-if="project.floors?.length" @mouseenter="isEtagenDropdownOpen = true" @mouseleave="isEtagenDropdownOpen = false">
                <button class="px-3 h-10 flex items-center justify-center bg-white/95 backdrop-blur-sm shadow-md rounded-full text-gray-700 hover:text-black hover:bg-white transition hidden sm:inline-flex" title="Etagen Übersicht">
                    <svg class="w-5 h-5 transition-transform" :class="{'scale-110 text-[#ab715c]': isEtagenDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                </button>
                <!-- Flyout Dropdown Container -->
                <transition enter-active-class="transition duration-100 ease-out" enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100" leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-show="isEtagenDropdownOpen" class="absolute top-full left-0 pt-2 w-48 z-50 origin-top-left">
                        <div class="bg-white/95 backdrop-blur-md border border-gray-200 shadow-xl rounded-[16px] p-2 flex flex-col gap-1 pointer-events-auto">
                        <label v-for="floor in project.floors" :key="'fl_' + floor.id" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 rounded-[8px] cursor-pointer transition w-full">
                            <input type="checkbox" :value="floor.id" v-model="eventBus.filters.floors" class="w-4 h-4 rounded-[4px] border-gray-300 text-[#ab715c] focus:ring-[#ab715c] shadow-sm cursor-pointer shrink-0">
                            <span class="text-[13px] font-bold text-gray-700 truncate mb-0.5 leading-none">{{ floor.name }}</span>
                        </label>
                        <div class="w-full border-t border-gray-100 my-1 pb-1 pt-2">
                            <button @click="eventBus.filters.floors = []" class="w-full text-center text-[10px] font-bold uppercase tracking-wider text-gray-400 hover:text-black transition">Alle anzeigen</button>
                        </div>
                    </div>
                </div>
                </transition>
            </div>

            <!-- Sun Controls (Quick Icon Overlay) -->
            <div class="relative" v-if="!activeLayer || activeLayer.sun_simulation || activeLayer.sun_simulation === undefined" @mouseenter="isSunDropdownOpen = true" @mouseleave="isSunDropdownOpen = false">
                <button class="px-3 h-10 flex items-center justify-center bg-white/95 backdrop-blur-sm shadow-md rounded-full text-gray-700 hover:text-black hover:bg-white transition hidden sm:inline-flex" title="Sonnenstand" :class="{'text-yellow-500': showSun}">
                    <svg class="w-5 h-5 transition-transform" :class="{'scale-110 text-yellow-500': isSunDropdownOpen || showSun}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7a5 5 0 100 10 5 5 0 000-10zM2 13h2a1 1 0 100-2H2a1 1 0 100 2zm18 0h2a1 1 0 100-2h-2a1 1 0 100 2zM11 2v2a1 1 0 102 0V2a1 1 0 10-2 0zm0 18v2a1 1 0 102 0v-2a1 1 0 10-2 0zM5.99 4.58a1 1 0 111.41 1.41L6.05 7.34a1 1 0 01-1.41-1.41l1.35-1.35zm12.02 12.02a1 1 0 111.41 1.41l-1.35 1.35a1 1 0 11-1.41-1.41l1.35-1.35zM5.99 19.42l-1.35-1.35a1 1 0 111.41-1.41l1.35 1.35a1 1 0 11-1.41 1.41zm12.02-12.02l1.35-1.35a1 1 0 011.41 1.41l-1.35 1.35a1 1 0 11-1.41-1.41z"/></svg>
                </button>
                <transition enter-active-class="transition duration-100 ease-out" enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100" leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-show="isSunDropdownOpen" class="absolute top-full left-0 pt-2 w-64 z-50 origin-top-left">
                        <div class="bg-white/95 backdrop-blur-md border border-gray-200 shadow-xl rounded-[16px] p-4 flex flex-col gap-3 pointer-events-auto">
                            <label class="flex items-center gap-2 mb-1 cursor-pointer">
                                <input type="checkbox" v-model="showSun" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400 w-4 h-4" />
                                <span class="text-[13px] font-bold text-gray-700">Sonnenstand aktivieren</span>
                            </label>
                            
                            <div v-if="showSun" class="w-full">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">Uhrzeit</span>
                                    <span class="text-[12px] font-black text-gray-800">{{ currentTimeLabel }}</span>
                                </div>
                                <input type="range" v-model="sunHour" min="0" max="23" step="0.1" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-yellow-400" />
                                <div class="flex justify-between mt-1 text-[9px] font-bold text-gray-400">
                                    <span>00:00</span><span>12:00</span><span>24:00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Heatmap Filter (Quick Icon Overlay) -->
            <div class="relative" @mouseenter="isHeatmapDropdownOpen = true" @mouseleave="isHeatmapDropdownOpen = false">
                <button class="px-3 h-10 flex items-center justify-center bg-white/95 backdrop-blur-sm shadow-md rounded-full text-gray-700 hover:text-black hover:bg-white transition hidden sm:inline-flex" title="Heatmap Modus" :class="{'text-[#ab715c]': activeHeatmapMode !== 'none'}">
                    <svg class="w-5 h-5 transition-transform" :class="{'scale-110 text-[#ab715c]': isHeatmapDropdownOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>
                </button>
                <!-- Flyout Dropdown Container -->
                <transition enter-active-class="transition duration-100 ease-out" enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100" leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0">
                    <div v-show="isHeatmapDropdownOpen" class="absolute top-full left-0 pt-2 w-48 z-50 origin-top-left">
                        <div class="bg-white/95 backdrop-blur-md border border-gray-200 shadow-xl rounded-[16px] p-2 flex flex-col gap-1 pointer-events-auto">
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 rounded-[8px] cursor-pointer transition w-full">
                                <input type="radio" value="none" v-model="activeHeatmapMode" class="w-4 h-4 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer">
                                <span class="text-[13px] font-bold text-gray-700 truncate mb-0.5 leading-none">Normal</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 rounded-[8px] cursor-pointer transition w-full">
                                <input type="radio" value="price" v-model="activeHeatmapMode" class="w-4 h-4 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer">
                                <span class="text-[13px] font-bold text-gray-700 truncate mb-0.5 leading-none">Preis Heatmap</span>
                            </label>
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 rounded-[8px] cursor-pointer transition w-full">
                                <input type="radio" value="sqm" v-model="activeHeatmapMode" class="w-4 h-4 text-[#ab715c] focus:ring-[#ab715c] cursor-pointer">
                                <span class="text-[13px] font-bold text-gray-700 truncate mb-0.5 leading-none">Flächen Heatmap</span>
                            </label>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Auto-Tour Editor Toggle (Admin) -->
            <button v-if="isAuth" @click="isTourEditorOpen = !isTourEditorOpen" :class="['px-4 h-10 flex items-center gap-2 bg-white/95 backdrop-blur-sm shadow-md rounded-full text-[13px] font-bold transition mr-2', isTourEditorOpen ? 'text-brand-500 hover:text-brand-600 ring-2 ring-brand-500' : 'text-gray-700 hover:text-black hover:bg-white']" title="Storyboard REC Modus">
                <span class="block w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                <span class="hidden sm:inline">REC Mode</span>
            </button>

            <!-- Auto-Tour Button -->
            <button v-if="props.project?.auto_tour_settings?.active && props.project?.auto_tour_settings?.storyboard?.length > 0" @click="isPlayingStory ? isPlayingStory = false : startAutoTour()" :class="['px-4 h-10 flex items-center gap-2 bg-white/95 backdrop-blur-sm shadow-md rounded-full text-[13px] font-bold transition', isPlayingStory ? 'text-red-500 hover:text-red-600' : 'text-gray-700 hover:text-black hover:bg-white']" title="Kino-Tour starten">
                <svg v-if="!isPlayingStory" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                <span class="hidden sm:inline">{{ isPlayingStory ? 'Tour stoppen' : 'Auto-Tour' }}</span>
            </button>

            <!-- Alle Filter -->
            <button @click="showFiltersPopup = true" class="px-4 h-10 flex items-center gap-2 bg-white/95 backdrop-blur-sm shadow-md rounded-full text-[13px] font-bold text-gray-700 hover:text-black hover:bg-white transition ml-auto">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                <span class="hidden sm:inline">Alle Filter</span>
            </button>
        </div>

        <!-- Filters Popup -->
        <div v-if="showFiltersPopup" class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-[24px] w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 flex justify-between items-center bg-gray-50 border-b border-gray-100">
                    <h3 class="font-bold text-lg text-gray-800">Filter anpassen</h3>
                    <button @click="showFiltersPopup = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 text-gray-500 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto w-full space-y-6">
                    <!-- Preis -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Preis (EUR)</h4>
                        <div class="text-[14px] font-medium text-gray-800 tracking-tight mb-2">
                            {{ new Intl.NumberFormat('de-DE').format(priceModel.min) }} EUR - {{ new Intl.NumberFormat('de-DE').format(priceModel.max) }} EUR
                        </div>
                        <DualSlider v-model="priceModel" :min="priceBoundaries.min" :max="priceBoundaries.max" :step="1000" />
                    </div>
                    <!-- Fläche -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Fläche (m²)</h4>
                        <div class="text-[14px] font-medium text-gray-800 tracking-tight mb-2">
                            {{ sqmModel.min }} m² - {{ sqmModel.max }} m²
                        </div>
                        <DualSlider v-model="sqmModel" :min="sqmBoundaries.min" :max="sqmBoundaries.max" :step="1" />
                    </div>
                    <!-- Zimmer -->
                    <div v-if="uniqueRooms.length">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Zimmer</h4>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="r in uniqueRooms" :key="r" class="cursor-pointer relative group">
                                <input type="checkbox" :value="r" v-model="eventBus.filters.rooms" class="peer sr-only" />
                                <div class="px-4 py-2 bg-gray-50 border border-gray-200 rounded-full text-sm font-medium text-gray-600 transition peer-checked:bg-[#2b2b2b] peer-checked:text-white peer-checked:border-[#2b2b2b] hover:border-gray-300">{{ r }}</div>
                            </label>
                        </div>
                    </div>
                    <!-- Etagen -->
                    <div v-if="project.floors?.length">
                        <h4 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-wider">Etage</h4>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="f in project.floors" :key="f.id" class="cursor-pointer relative group">
                                <input type="checkbox" :value="f.id" v-model="eventBus.filters.floors" class="peer sr-only" />
                                <div class="px-4 py-2 bg-gray-50 border border-gray-200 rounded-full text-sm font-medium text-gray-600 transition peer-checked:bg-[#2b2b2b] peer-checked:text-white peer-checked:border-[#2b2b2b] hover:border-gray-300">{{ f.name }}</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="p-5 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                    <button @click="eventBus.filters = { floors: [], rooms: [], availabilities: [], priceMin: null, priceMax: null, sqmMin: null, sqmMax: null }" class="text-sm font-bold text-gray-600 hover:text-black underline">Filter zurücksetzen</button>
                    <button @click="showFiltersPopup = false" class="px-6 py-2.5 bg-[#ab715c] text-white rounded-full font-bold shadow-md hover:bg-[#8e5c4a] transition">Übernehmen</button>
                </div>
            </div>
        </div>

        <!-- Toolbar (Left) Auth Only -->
        <div v-if="isAuth" class="absolute top-20 left-6 flex flex-col gap-2 z-50 mt-2">
            <div class="bg-white/95 backdrop-blur-sm p-3 rounded-[12px] shadow-md border border-gray-200 text-sm w-64" @mousedown.stop>
                <h4 class="font-bold text-gray-800 mb-2 border-b border-gray-100 pb-2">Admin Tools</h4>
                <div class="flex items-center justify-between mb-3 text-xs">
                    <span class="font-medium text-gray-600">Ansicht:</span>
                    <select v-model="activeViewId" class="py-1 px-2 border-gray-300 rounded focus:ring-[#ab715c] font-bold bg-[#fbfbfb] text-xs max-w-[120px]">
                        <option v-for="v in views" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </div>
                
                <div class="flex justify-between items-center mb-4 text-xs">
                    <span class="font-medium text-gray-600 border-t pt-2 w-full flex flex-col gap-2">
                        <div class="flex justify-between items-center w-full">
                            Frame {{ activeFrame?.index || 0 }} - Stop:
                            <input type="checkbox" v-model="isStopFrameModel" @change="toggleStopFrame" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4 shadow-sm" :disabled="!activeFrame" />
                        </div>
                        <div class="flex justify-between items-center w-full">
                            Frame-Blick = Norden:
                            <input type="checkbox" v-model="isNorthModel" @change="toggleNorth" class="rounded text-brand-600 focus:ring-brand-500 w-4 h-4 shadow-sm" :disabled="!activeFrame" />
                        </div>
                    </span>
                </div>

                <div class="flex justify-between items-center mb-3 text-xs border-t pt-3">
                    <span class="font-medium text-gray-600">Animation aktiv:</span>
                    <input type="checkbox" v-model="isAnimationActive" class="rounded text-[#ab715c] focus:ring-[#ab715c] w-4 h-4 shadow-sm" />
                </div>

                <div class="flex flex-col gap-2 pt-3 border-t">
                    <button @click="toggleDrawMode" :class="['flex items-center justify-center gap-1 w-full px-3 py-1.5 rounded text-xs font-bold transition', drawMode ? 'bg-[#ab715c] text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        {{ drawMode ? 'Zeichnen beenden' : 'Neues Polygon zeichnen' }}
                    </button>
                    <p v-if="drawMode" class="text-[10px] text-brand-600 leading-tight">Klicke auf das Bild, um Ecken zu setzen. Schließe mit Klick nahe an Ecke 1.</p>
                    
                    <button @click="toggleDrawModePoint" :class="['flex items-center justify-center gap-1 w-full px-3 py-1.5 rounded text-xs font-bold transition', drawModePoint ? 'bg-[#ab715c] text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ drawModePoint ? 'Platzieren beenden' : 'Neuen Punkt setzen' }}
                    </button>
                    <p v-if="drawModePoint" class="text-[10px] text-brand-600 leading-tight mt-1 px-1">Klicke auf das Bild, um einen Punkt zu platzieren.</p>
                </div>
            </div>

            <!-- Property Panel for Polygon AND Points -->
            <div v-if="(selectedPolygon || selectedPoint) && !drawMode && !drawModePoint" class="bg-white/95 backdrop-blur-sm p-4 rounded-[12px] shadow-md border border-brand-200 text-sm w-64 mt-2 max-h-[70vh] overflow-y-auto" @mousedown.stop>
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-bold text-gray-800 flex items-center gap-1"><svg class="w-4 h-4 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" /></svg> Eigenschaften</h4>
                    <button @click="deleteSelectedElement" class="text-red-500 hover:text-red-700 transition" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                </div>
                
                <div class="space-y-3">
                    <!-- Point Appearance Config -->
                    <template v-if="selectedPoint">
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Icon Typ</label>
                            <select v-model="selectedPoint.icon_type" class="w-full py-1.5 px-2 border border-gray-200 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                                <option value="preset">Standard Forms</option>
                                <option value="custom">Eigenes SVG</option>
                            </select>
                        </div>
                        <div v-if="selectedPoint.icon_type === 'preset' || !selectedPoint.icon_type">
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Form</label>
                            <select v-model="selectedPoint.preset_icon" class="w-full py-1.5 px-2 border border-gray-200 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                                <option value="pin">Pin (Map)</option>
                                <option value="circle">Kreis</option>
                                <option value="star">Stern</option>
                                <option value="square">Quadrat</option>
                            </select>
                        </div>
                        <div v-if="selectedPoint.icon_type === 'custom'">
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">SVG Code</label>
                            <textarea v-model="selectedPoint.custom_svg" rows="3" class="w-full py-1.5 px-2 border border-gray-200 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="<svg>...</svg>" @change="savePolygonsToFrame"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Größe (%) - {{ selectedPoint.size || 100 }}</label>
                            <input type="range" v-model.number="selectedPoint.size" min="10" max="500" class="w-full" @change="savePolygonsToFrame" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Freie Farbe (Optional)</label>
                            <input type="color" v-model="selectedPoint.custom_color" class="w-full h-8 cursor-pointer border-0 p-0" @change="savePolygonsToFrame" />
                            <button v-if="selectedPoint.custom_color" @click="selectedPoint.custom_color = null; savePolygonsToFrame()" class="text-[10px] text-red-500 mt-1 hover:underline">Farbe zurücksetzen</button>
                        </div>
                        <hr class="border-gray-100 my-2" />
                    </template>
                    
                    <div>
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Verlinken mit</label>
                        <select v-model="activeElement.link_type" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                            <option value="none">-- Ohne --</option>
                            <option value="apartment">Wohnung</option>
                            <option value="view">Ansicht</option>
                            <option value="slider">Slider</option>
                            <option value="tour_point">Virtuelle Tour</option>
                            <option value="video">Externe Video URL</option>
                        </select>
                    </div>

                    <div v-if="activeElement.link_type === 'view'">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Ziel-Ansicht</label>
                        <select v-model="activeElement.target_view_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                            <option :value="null">-- Ansicht wählen --</option>
                            <option v-for="v in views.filter(view => view.id !== activeViewId)" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input type="checkbox" v-model="activeElement.tooltip_active" @change="savePolygonsToFrame" class="rounded text-[#ab715c] focus:ring-[#ab715c] w-4 h-4" />
                            <span class="text-xs font-bold text-gray-700">Tooltip anzeigen</span>
                        </label>
                        <div v-if="activeElement.tooltip_active">
                            <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Tooltip Text</label>
                            <textarea v-model="activeElement.tooltip_text" rows="2" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="Beschriftung..." @change="savePolygonsToFrame"></textarea>
                        </div>
                    </div>

                    <div v-if="activeElement.link_type === 'video'">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Video Link</label>
                        <input type="text" v-model="activeElement.target_url" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="https://..." @change="savePolygonsToFrame" />
                    </div>

                    <div v-if="activeElement.link_type === 'tour_point'">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Panorama (360°) Punkt</label>
                        <select v-model="activeElement.tour_point_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                            <option :value="null">-- Punkt wählen --</option>
                            <template v-for="t in project.virtual_tours" :key="t.id">
                                <optgroup :label="t.name">
                                    <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </optgroup>
                            </template>
                        </select>
                    </div>

                    <template v-if="activeElement.link_type === 'apartment' || (!activeElement.link_type && activeElement.apartment_id)">
                        <!-- Assign Link Type for Legacy Compatibility dynamically -->
                        <span v-show="false">{{ activeElement.link_type === undefined ? activeElement.link_type = 'apartment' : '' }}</span>
                        
                        <div>
                            <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Haus</label>
                            <select v-model="activeElement.house_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="h in project.houses" :key="h.id" :value="h.id">{{ h.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Wohnung</label>
                            <select v-model="activeElement.apartment_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" :disabled="!activeElement.house_id">
                                <option :value="null">-- Wählen --</option>
                                <option v-for="ap in availableApartmentsForPoly" :key="ap.id" :value="ap.id">{{ ap.name }}</option>
                            </select>
                        </div>
                        <div v-if="activeElement.apartment_id">
                            <label class="flex items-center gap-2 mt-3 cursor-pointer">
                                <input type="checkbox" v-model="activeElement.isBestView" class="rounded text-[#ab715c] focus:ring-[#ab715c] w-4 h-4" />
                                <span class="text-xs font-bold text-gray-700">Als "Best View" hinterlegen</span>
                            </label>
                        </div>
                    </template>
                    
                    <div v-if="activeElement.link_type === 'slider'" class="mt-3">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Slider wählen</label>
                        <select v-model="activeElement.slider_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFrame">
                            <option :value="null">-- Wählen --</option>
                            <option v-for="sl in project.sliders" :key="sl.id" :value="sl.id">{{ sl.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-gray-100">
                        <button @click="copyElement" class="flex flex-col items-center justify-center py-2 bg-gray-50 rounded border hover:bg-white hover:text-[#ab715c] text-xs text-gray-600 transition font-bold shadow-sm">
                            <svg class="w-4 h-4 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                            Duplizieren
                        </button>
                    </div>

                    <button @click="savePolygonsToFrame" class="w-full mt-2 py-2.5 bg-gray-800 hover:bg-black text-white font-bold rounded text-xs shadow-sm transition">Speichern</button>
                </div>
            </div>
        </div>

        <!-- Bottom Right UI Container -->
        <div class="absolute top-20 md:top-auto md:bottom-6 right-6 flex flex-col items-end gap-3 z-50 pointer-events-none">
            
            <!-- Zoom Controls Desktop (Vertical Pill) -->
            <div class="hidden md:flex flex-col bg-[#e6e6e6]/95 backdrop-blur-md rounded-[24px] shadow-lg overflow-hidden w-12 py-1 pointer-events-auto">
                <button @click="zoomIn" class="w-12 h-12 flex items-center justify-center text-gray-600 hover:text-black transition">
                    <svg class="w-5 h-5 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                </button>
                <div class="w-8 h-[1px] bg-gray-300 mx-auto"></div>
                <button @click="zoomOut" class="w-12 h-12 flex items-center justify-center text-gray-600 hover:text-black transition">
                    <svg class="w-5 h-5 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                </button>
            </div>

            <!-- Zoom Controls Mobile -->
            <div class="flex md:hidden items-center pointer-events-none relative z-[60]">
                <!-- Click Outside Overlay -->
                <div v-if="showMobileZoomControls" @click.stop="showMobileZoomControls = false" @touchstart.stop="showMobileZoomControls = false" class="fixed inset-0 z-0 bg-transparent pointer-events-auto"></div>
                
                <div class="flex items-center relative z-10 pointer-events-auto">
                    <transition 
                    enter-active-class="transform transition duration-300 ease-[cubic-bezier(0.25,1,0.5,1)] origin-right" 
                    enter-from-class="scale-x-0 opacity-0 translate-x-4" 
                    enter-to-class="scale-x-100 opacity-100 translate-x-0" 
                    leave-active-class="transform transition duration-200 ease-in origin-right" 
                    leave-from-class="scale-x-100 opacity-100 translate-x-0" 
                    leave-to-class="scale-x-0 opacity-0 translate-x-4">
                    <div v-if="showMobileZoomControls" class="flex bg-[#e6e6e6]/95 backdrop-blur-md rounded-full shadow-lg h-12 overflow-hidden px-1 mr-2 absolute right-full top-0">
                        <button @click="zoomOut" class="w-12 h-12 flex items-center justify-center text-gray-600 active:text-black transition" title="Zoom Out">
                            <svg class="w-5 h-5 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" /></svg>
                        </button>
                        <div class="h-8 w-[1px] bg-gray-300 my-auto"></div>
                        <button @click="zoomIn" class="w-12 h-12 flex items-center justify-center text-gray-600 active:text-black transition" title="Zoom In">
                            <svg class="w-5 h-5 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                </transition>

                <!-- Toggle Button -->
                <button @click="showMobileZoomControls = !showMobileZoomControls" :class="['w-12 h-12 flex items-center justify-center bg-[#e6e6e6]/95 backdrop-blur-md rounded-full shadow-lg transition z-10 shrink-0 outline-none', showMobileZoomControls ? 'text-black bg-white' : 'text-gray-600 active:text-black']" aria-label="Toggle Zoom Controls">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </button>
                </div>
            </div>

            <!-- Rotation / Pan Navigation (Vertical Stacked Pill with Center Compass) -->
            <div class="relative flex flex-col items-center bg-[#e6e6e6]/95 backdrop-blur-md rounded-[24px] shadow-[0_8px_24px_rgba(0,0,0,0.12)] w-12 h-[150px] py-1 pointer-events-auto">
                <!-- Rotate Left (Top) -->
                <button @click="prevFrame" class="w-12 h-10 flex items-center justify-center text-gray-600 hover:text-black transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                </button>
                
                <div class="flex-1"></div>

                <!-- Rotate Right (Bottom) -->
                <button @click="nextFrame(false)" class="w-12 h-10 flex items-center justify-center text-gray-600 hover:text-black transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="transform: scaleX(-1)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" /></svg>
                </button>
                
                <!-- Center Compass / Play 360 Toggle -->
                <button @click="play360Spin" 
                        class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[54px] h-[54px] bg-white rounded-full shadow-lg flex items-center justify-center transition-transform z-10 hover:scale-105"
                        :class="{'animate-pulse pointer-events-none': isSpinning360}">
                    <!-- Custom Compass Arrow -->
                    <div class="w-0 h-0 border-l-[6px] border-r-[6px] border-b-[12px] border-l-transparent border-r-transparent border-b-[#f43f5e]" :class="{'animate-spin': isSpinning360}"></div>
                </button>
            </div>

            <!-- Layer Selector (Right Bottom) -->
            <div class="pointer-events-auto mt-1 flex flex-col items-end">
                <!-- Desktop View -->
                <div class="hidden md:flex flex-col items-end gap-2">
                    <transition name="fade">
                        <div v-if="isLoadingFrames" class="bg-white/95 backdrop-blur-sm shadow rounded-[12px] px-4 py-3 flex items-center gap-3 border border-gray-100">
                            <svg class="animate-spin h-5 w-5 text-[#ab715c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-bold text-gray-700 tracking-tight">Lade Caches...</span>
                        </div>
                    </transition>

                    <div class="bg-white/95 backdrop-blur-sm shadow-md rounded-[16px] border border-gray-200 overflow-hidden min-w-[150px]" v-if="activeViewLayers.length > 0">
                        <div class="p-1 flex flex-col-reverse">
                            <button 
                                v-for="layer in activeViewLayers" 
                                :key="layer.id"
                                @click="activeLayerId = layer.id"
                                :class="['w-full text-left px-4 py-2.5 text-[13px] font-bold rounded-[12px] transition', activeLayerId === layer.id ? 'bg-[#5b873e] text-white shadow-sm shadow-[#5b873e]/20' : 'text-gray-600 hover:bg-gray-100 hover:text-black']"
                            >
                                {{ layer.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile View -->
                <div class="flex md:hidden items-center pointer-events-none relative z-[60]" v-if="activeViewLayers.length > 0 || isLoadingFrames">
                    <!-- Click Outside Overlay -->
                    <div v-if="showMobileLayers" @click.stop="showMobileLayers = false" @touchstart.stop="showMobileLayers = false" class="fixed inset-0 z-0 bg-transparent pointer-events-auto"></div>
                    
                    <div class="flex items-center relative z-10 pointer-events-auto">
                        <transition 
                            enter-active-class="transform transition duration-300 ease-[cubic-bezier(0.25,1,0.5,1)] origin-right" 
                            enter-from-class="scale-x-0 opacity-0 translate-x-4" 
                            enter-to-class="scale-x-100 opacity-100 translate-x-0" 
                            leave-active-class="transform transition duration-200 ease-in origin-right" 
                            leave-from-class="scale-x-100 opacity-100 translate-x-0" 
                            leave-to-class="scale-x-0 opacity-0 translate-x-4">
                            <div v-if="showMobileLayers" class="absolute right-[calc(100%+0.5rem)] bottom-0 flex flex-col-reverse bg-[#e6e6e6]/95 backdrop-blur-md rounded-[16px] shadow-lg overflow-hidden min-w-[140px] p-1">
                                <button 
                                    v-for="layer in activeViewLayers" 
                                    :key="layer.id"
                                    @click="activeLayerId = layer.id; showMobileLayers = false"
                                    :class="['w-full text-left px-4 py-2.5 text-[13px] font-bold rounded-[12px] transition', activeLayerId === layer.id ? 'bg-[#5b873e] text-white shadow-sm' : 'text-gray-600 active:bg-gray-100 active:text-black']"
                                >
                                    {{ layer.name }}
                                </button>
                                
                                <div v-if="isLoadingFrames" class="px-4 py-2.5 flex items-center gap-2 border-b border-gray-200/50 mb-1">
                                    <svg class="animate-spin h-3.5 w-3.5 text-[#ab715c]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="text-[11px] font-bold text-gray-500">Lade...</span>
                                </div>
                            </div>
                        </transition>

                        <!-- Toggle Button -->
                        <button @click="showMobileLayers = !showMobileLayers" :class="['w-12 h-12 flex items-center justify-center bg-[#e6e6e6]/95 backdrop-blur-md rounded-full shadow-lg transition z-10 shrink-0 outline-none', showMobileLayers ? 'text-black bg-white' : 'text-gray-600 active:text-black']" aria-label="Toggle Layers">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Visual Auto-Tour Editor (Auth Only) -->
        <transition name="slide-up">
            <div v-if="isTourEditorOpen" class="absolute bottom-8 left-1/2 -translate-x-1/2 z-[110] bg-white/95 backdrop-blur-md rounded-[20px] shadow-[0_10px_40px_rgba(0,0,0,0.15)] border border-gray-200 pointer-events-auto p-4 w-[90%] max-w-[800px] max-h-[50vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between font-bold border-b border-gray-100 pb-2 mb-3 shrink-0">
                    <span class="text-brand-600 flex items-center gap-2"><span class="w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span> Storyboard REC</span>
                    <button @click="isTourEditorOpen = false" class="text-gray-400 hover:text-black"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                
                <div class="flex-1 overflow-y-auto mb-3 space-y-2 pr-2 custom-scrollbar">
                    <draggable v-model="draftStoryboard" item-key="uuid" handle=".cursor-move" class="space-y-2">
                        <template #item="{ element, index }">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-2 flex gap-2 items-center text-sm">
                                <span class="cursor-move text-gray-400 hover:text-brand-500 flex items-center">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </span>
                                <select v-model="element.type" class="text-xs py-1 border-gray-300 rounded shrink-0">
                                    <option value="spin_to">Kamerafahrt</option>
                                    <option value="tooltip">Tooltip</option>
                                    <option value="video">Video</option>
                                    <option value="audio">Audio</option>
                                    <option value="highlight">Highlight</option>
                                    <option value="virtual_tour">Virtuelle Tour</option>
                                </select>
                                
                                <div class="flex-1 flex gap-2 min-w-0">
                                    <input v-if="element.type === 'spin_to'" v-model.number="element.targetFrameIndex" type="number" class="w-full text-xs py-1 border-gray-300 rounded" placeholder="Frame">
                                    <input v-if="element.type === 'tooltip'" v-model="element.text" type="text" class="w-full text-xs py-1 border-gray-300 rounded" placeholder="Text">
                                    <input v-if="['video', 'audio'].includes(element.type)" v-model="element.url" type="text" class="w-full text-xs py-1 border-gray-300 rounded" placeholder="URL">
                                    <select v-if="element.type === 'highlight'" v-model="element.apartment_id" class="w-full text-xs py-1 border-gray-300 rounded">
                                        <option value="">Wohnung wählen</option>
                                        <option v-for="ap in visibleApartments" :key="ap.id" :value="ap.id">{{ ap.name }}</option>
                                    </select>
                                    <template v-if="element.type === 'virtual_tour'">
                                       <select v-model="element.virtual_tour_id" class="w-[50%] text-xs py-1 border-gray-300 rounded">
                                           <option value="">Tour wählen</option>
                                           <option v-for="vt in project.virtual_tours" :key="vt.id" :value="vt.id">{{ vt.name }}</option>
                                       </select>
                                       <input v-model.number="element.yaw" type="number" class="w-[25%] text-xs py-1 border-gray-300 rounded" placeholder="Yaw">
                                       <input v-model.number="element.pitch" type="number" class="w-[25%] text-xs py-1 border-gray-300 rounded" placeholder="Pitch">
                                    </template>
                                    <input v-model.number="element.duration" type="number" class="w-20 shrink-0 text-xs py-1 border-gray-300 rounded" placeholder="ms">
                                </div>
                                
                                <button @click="draftStoryboard.splice(index, 1)" class="text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                            </div>
                        </template>
                    </draggable>
                    <div v-if="draftStoryboard.length === 0" class="text-center text-gray-400 text-xs py-4">Noch keine Schritte hinzugefügt. Nutze die Buttons unten.</div>
                </div>
                
                <div class="flex flex-wrap gap-2 shrink-0 border-t border-gray-100 pt-3">
                    <button @click="addRecordFrame" class="px-3 py-1.5 bg-[#f0f9eb] text-[#3f6327] rounded text-xs font-bold border border-[#cbeaa0] flex hover:bg-[#dcf0d5] transition">
                        + Aktuellen Frame
                    </button>
                    <button @click="draftStoryboard.push({ type: 'tooltip', text: '', duration: 3000, uuid: Date.now() })" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded text-xs font-bold border border-gray-300 hover:bg-gray-200 transition">
                        + Tooltip
                    </button>
                    <button @click="draftStoryboard.push({ type: 'virtual_tour', virtual_tour_id: null, yaw: 0, pitch: 0, duration: 1000, uuid: Date.now() })" class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs font-bold border border-blue-200 hover:bg-blue-100 transition">
                        + Tour öffnen
                    </button>
                    <div class="ml-auto flex gap-2">
                        <!-- Test Play Button -->
                        <button @click="startAutoTour(draftStoryboard)" class="px-4 py-1.5 bg-brand-50 text-brand-700 rounded text-xs font-bold hover:bg-brand-100 transition">
                            Vorschau Play
                        </button>
                        <button @click="saveStoryboard" class="px-4 py-1.5 bg-brand-600 text-white rounded text-xs font-bold shadow-sm hover:bg-brand-500 transition">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </transition>
        <!-- Mobile Bottom Layout Container -->
        <div class="absolute bottom-0 left-0 right-0 z-[100] md:hidden flex flex-col pointer-events-none w-full max-w-[100vw]">
            
            <!-- Mobile Bottom Banner for Selected Apartment -->
            <transition 
                enter-active-class="transform transition duration-300 ease-[cubic-bezier(0.25,1,0.5,1)]" 
                enter-from-class="translate-y-10 opacity-0" 
                enter-to-class="translate-y-0 opacity-100" 
                leave-active-class="transform transition duration-200 ease-in" 
                leave-from-class="translate-y-0 opacity-100" 
                leave-to-class="translate-y-10 opacity-0">
                <div v-if="mobileSelectedApartment" class="px-5 mb-3 pointer-events-auto">
                    <div class="bg-black/95 backdrop-blur-md text-white p-4 rounded-[16px] shadow-2xl flex justify-between items-center cursor-pointer"
                         @click="$emit('apartment-click', mobileSelectedApartmentId); mobileSelectedApartmentId = null">
                        <div>
                            <h3 class="text-[16px] font-black tracking-tight">{{ mobileSelectedApartment.name || 'Wohnung' }}</h3>
                            <p class="text-[12px] text-gray-300 font-semibold mt-0.5">
                                <span v-if="mobileSelectedApartment.rooms">{{ mobileSelectedApartment.rooms }} Zi. | </span>
                                <span v-if="mobileSelectedApartment.sqm">{{ mobileSelectedApartment.sqm }} m²</span>
                                <template v-if="(mobileSelectedApartment.marketing_type === 'Miete' ? mobileSelectedApartment.warm_rent : mobileSelectedApartment.price)">
                                    | {{ new Intl.NumberFormat('de-DE').format(mobileSelectedApartment.marketing_type === 'Miete' ? mobileSelectedApartment.warm_rent : mobileSelectedApartment.price) }} EUR
                                </template>
                                <template v-else>
                                    | Auf Anfrage
                                </template>
                            </p>
                        </div>
                        <div class="bg-white/10 p-2 w-8 h-8 rounded-full flex shrink-0 items-center justify-center ml-4">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Horizontal Apartment List -->
            <div class="flex gap-4 overflow-x-auto px-5 pb-5 pt-2 snap-x snap-mandatory scroll-smooth w-full pointer-events-auto hide-scrollbar z-100"
                 id="mobileApartmentListScrollContainer"
                 v-show="visibleApartments.length > 0">
                
                <!-- Empty spacer at start to allow first item to be centered perfectly if needed-->
                <div class="shrink-0 w-1"></div>

                <div v-for="ap in visibleApartments" 
                     :key="ap.id"
                     :id="'mobile-ap-card-' + ap.id"
                     class="w-[280px] shrink-0 snap-center bg-white rounded-[18px] shadow-[0_4px_24px_rgba(0,0,0,0.08)] border border-gray-100 overflow-hidden cursor-pointer transition-all duration-300"
                     :class="{'ring-2 ring-black scale-[0.98]': (mobileSelectedApartmentId || activeApartmentId) === ap.id}"
                     @click="mobileSelectedApartmentId = ap.id"
                >
                    <!-- Row 1: Avatar + Name + Price -->
                    <div class="flex items-center gap-3 px-4 pt-4 pb-3">
                        <div class="relative w-11 h-11 shrink-0">
                            <img :src="ap.media?.[0]?.original_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=150&q=80'" class="w-full h-full object-cover rounded-full border border-gray-100 shadow-sm" alt="Wohnung" />
                            <div class="absolute -top-1 -right-1 bg-white rounded-full p-[3px] shadow-sm border border-gray-100 z-10 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-[#ab715c]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            </div>
                        </div>
                        <span class="text-[15px] font-black text-gray-800 tracking-tight truncate flex-1">{{ ap.name }}</span>
                        
                        <div class="bg-[#dcf0d5] text-[#3f6327] px-3 py-1 rounded-full shrink-0 flex items-baseline gap-1 shadow-sm">
                            <template v-if="ap.price">
                                <span class="text-[13px] font-black tracking-tight">{{ new Intl.NumberFormat('de-DE').format(ap.price) }}</span>
                                <span class="text-[10px] font-bold opacity-70">EUR</span>
                            </template>
                            <template v-else-if="ap.warm_rent">
                                <span class="text-[13px] font-black tracking-tight">{{ new Intl.NumberFormat('de-DE').format(ap.warm_rent) }}</span>
                                <span class="text-[10px] font-bold opacity-70">EUR</span>
                            </template>
                            <template v-else>
                                <span class="text-[11px] font-black tracking-tight uppercase">Auf Anfrage</span>
                            </template>
                        </div>
                    </div>

                    <!-- Row 2: Details (Sqm, Floor, Rooms) -->
                    <div class="flex items-center justify-between px-6 py-3 border-t border-gray-50 bg-white">
                        <div class="flex flex-col items-start gap-0.5">
                            <span class="text-[13px] font-black tracking-tight text-gray-800 leading-tight">{{ ap.sqm || '–' }}<span class="text-[10px] ml-0.5 opacity-80">m²</span></span>
                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Fläche</span>
                        </div>
                        <div class="w-px h-[24px] bg-gray-100"></div>
                        <div class="flex flex-col items-center gap-0.5">
                            <span class="text-[13px] font-black tracking-tight text-gray-800 leading-tight">{{ project.floors?.find(f => f.id === ap.floor_id)?.name || 'EG' }}</span>
                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Geschoss</span>
                        </div>
                        <div class="w-px h-[24px] bg-gray-100"></div>
                        <div class="flex flex-col items-end gap-0.5">
                            <span class="text-[13px] font-black tracking-tight text-gray-800 leading-tight">{{ ap.rooms || '–' }}</span>
                            <span class="text-[10px] font-semibold text-gray-400 leading-none">Zimmer</span>
                        </div>
                    </div>
                </div>
                
                <!-- Empty spacer at end -->
                <div class="shrink-0 w-1"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { eventBus } from '../eventBus.js';
import DualSlider from '@/Components/DualSlider.vue';
import ShadowRenderer from './ShadowRenderer.vue';

// --- AI Depth Map Generation ---
const activeDepthMapUrl = computed(() => {
    if (!activeFrame.value) return null;
    const expectedCollection = activeLayerId.value ? `layer_${activeLayerId.value}` : 'default';
    
    // Check for layer-specific depth map
    let dm = activeFrame.value.media?.find(m => m.collection_name === 'depth_map' && m.custom_properties?.target_collection === expectedCollection);
    
    // Fallbacks
    if (!dm) dm = activeFrame.value.media?.find(m => m.collection_name === 'depth_map' && m.custom_properties?.target_collection === 'default');
    if (!dm) dm = activeFrame.value.media?.find(m => m.collection_name === 'depth_map');
    
    return dm ? dm.original_url : null;
});

// --- Sun Visualization Logic ---
const isSunDropdownOpen = ref(false);
const showSun = ref(false);
const sunHour = ref(new Date().getHours() + new Date().getMinutes() / 60);

const currentTimeLabel = computed(() => {
    const h = Math.floor(sunHour.value);
    const m = Math.floor((sunHour.value % 1) * 60);
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')} Uhr`;
});

const getSunPosition = (hour, lat, lng) => {
    const rad = Math.PI / 180;
    const now = new Date();
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate(), Math.floor(hour), (hour % 1) * 60);
    
    const dayMs = 1000 * 60 * 60 * 24;
    const J1970 = 2440588, J2000 = 2451545;
    const toDays = (d) => (d.valueOf() / dayMs - 0.5 + J1970) - J2000;

    const d = toDays(date);
    const lw = rad * -lng;
    const phi = rad * lat;

    const e = rad * 23.4397;
    const M = rad * (357.5291 + 0.98560028 * d);
    const C = rad * (1.9148 * Math.sin(M) + 0.02 * Math.sin(2 * M) + 0.0003 * Math.sin(3 * M));
    const lambda = M + C + rad * 102.9372 + Math.PI;

    const dec = Math.asin(Math.sin(e) * Math.sin(lambda));
    const ra = Math.atan2(Math.cos(e) * Math.sin(lambda), Math.cos(lambda));
    const siderealTime = rad * (280.16 + 360.9856235 * d) - lw;
    const H = siderealTime - ra;

    const alt = Math.asin(Math.sin(phi) * Math.sin(dec) + Math.cos(phi) * Math.cos(dec) * Math.cos(H));
    const az = Math.atan2(Math.sin(H), Math.cos(H) * Math.sin(phi) - Math.tan(dec) * Math.cos(phi));

    return {
        azimuth: (az * 180 / Math.PI + 180) % 360,
        altitude: alt * 180 / Math.PI
    };
};

const page = usePage();
const props = defineProps({
    project: Object,
    activeApartmentId: { type: [Number, String], default: null },
    isVideoOpen: { type: Boolean, default: false },
    isTourOpen: { type: Boolean, default: false }
});

import axios from 'axios';
import draggable from 'vuedraggable';

// --- Story Engine (Auto-Tour with Video Continuation) ---
const isPlayingStory = ref(false);
const isTourEditorOpen = ref(false);
const draftStoryboard = ref([]);

watch(isTourEditorOpen, (open) => {
    if (open) {
        // Deep clone current storyboard
        draftStoryboard.value = JSON.parse(JSON.stringify(props.project?.auto_tour_settings?.storyboard || []));
    }
});

const saveStoryboard = async () => {
    try {
        const payload = {
            auto_tour_settings: {
                ...props.project?.auto_tour_settings,
                active: true,
                storyboard: draftStoryboard.value
            }
        };
        const response = await axios.patch(`/projects/${props.project.id}/auto-tour`, payload);
        alert('Storyboard erfolgreich gespeichert!');
        // Optionally update props locally, though Inertia reload might be better.
    } catch (e) {
        alert('Fehler beim Speichern: ' + e.message);
    }
};

const addRecordFrame = () => {
    if (!activeFrame.value) return;
    draftStoryboard.value.push({
        type: 'spin_to',
        targetFrameIndex: activeFrame.value.index,
        duration: 2500,
        uuid: Date.now() + Math.random().toString()
    });
};

const emit = defineEmits(['video-click', 'apartment-click', 'slider-click', 'tour-click', 'deselect']);

let resumeVideoPromise = null;
let currentAudio = null;

watch(() => props.isVideoOpen, (isOpen) => {
    if (!isOpen && resumeVideoPromise) {
        setTimeout(() => {
            resumeVideoPromise();
            resumeVideoPromise = null;
        }, 500); // short wait before continuing tour
    }
});

watch(() => props.isTourOpen, (isOpen) => {
    if (!isOpen && resumeVideoPromise) {
        setTimeout(() => {
            resumeVideoPromise();
            resumeVideoPromise = null;
        }, 500);
    }
});

// Stop tour when unmounted or manually aborted
watch(isPlayingStory, (isPlaying) => {
    if (!isPlaying && currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }
});

const startAutoTour = async (overrideStoryboard = null) => {
    const settings = props.project?.auto_tour_settings;
    const storyboard = overrideStoryboard || settings?.storyboard;
    if (!storyboard?.length) {
        alert("Leider ist für dieses Projekt noch keine Auto-Tour konfiguriert.");
        return;
    }
    
    if (isPlayingStory.value) return;
    isPlayingStory.value = true;

    for (const step of storyboard) {
        if (!isPlayingStory.value) break;

        if (step.type === 'spin_to') {
            await new Promise(resolve => animateToFrame(step.targetFrameIndex, resolve));
            if (step.duration) await new Promise(resolve => setTimeout(resolve, step.duration));
        } else if (step.type === 'tooltip') {
            hoveredPointTooltipText.value = step.text;
            tooltipPos.value = { x: window.innerWidth / 2 - 100, y: window.innerHeight / 2 };
            await new Promise(resolve => setTimeout(resolve, step.duration || 3000));
            hoveredPointTooltipText.value = null;
        } else if (step.type === 'video') {
            emit('video-click', step.url);
            await new Promise(resolve => {
                 resumeVideoPromise = resolve;
            });
        } else if (step.type === 'audio') {
            if (currentAudio) currentAudio.pause();
            currentAudio = new Audio(step.url);
            currentAudio.play().catch(e => console.error("Audio blockiert:", e));
            if (step.duration) {
                await new Promise(resolve => setTimeout(resolve, step.duration));
                currentAudio.pause();
                currentAudio = null;
            }
        } else if (step.type === 'highlight') {
            emit('apartment-click', step.apartment_id);
            await new Promise(resolve => setTimeout(resolve, step.duration || 3000));
            emit('deselect');
        } else if (step.type === 'virtual_tour') {
            emit('tour-click', step.virtual_tour_id, step.yaw || 0, step.pitch || 0);
            // It will trigger the prop isTourOpen=true in PublicShow,
            // we wait until isTourOpen becomes false (detected via watcher resolving resumeVideoPromise).
            await new Promise(resolve => {
                 resumeVideoPromise = resolve;
            });
        }
    }
    isPlayingStory.value = false;
};

const mobileSelectedApartmentId = ref(null);
const mobileSelectedApartment = computed(() => props.project?.apartments?.find(a => a.id === mobileSelectedApartmentId.value) || null);
const showMobileZoomControls = ref(false);
const showMobileLayers = ref(false);

const visibleApartments = computed(() => {
    return (props.project?.apartments || []).filter(a => visibleApartmentIds.value.includes(a.id));
});

watch(() => mobileSelectedApartmentId.value || props.activeApartmentId, (newId) => {
    if (newId && typeof document !== 'undefined') {
        nextTick(() => {
            const el = document.getElementById('mobile-ap-card-' + newId);
            if (el) {
                const container = document.getElementById('mobileApartmentListScrollContainer');
                if (container) {
                    const scrollLeft = el.offsetLeft - container.offsetWidth / 2 + el.offsetWidth / 2;
                    container.scrollTo({ left: scrollLeft, behavior: 'smooth' });
                }
            }
        });
    }
});

const isAuth = computed(() => !!page.props.auth?.user);

const isAltHeld = ref(false);
const isCtrlHeld = ref(false);

const handleGlobalKeydown = (e) => {
    if (e.key === 'Alt') isAltHeld.value = true;
    if (e.key === 'Control') isCtrlHeld.value = true;
};
const handleGlobalKeyup = (e) => {
    if (e.key === 'Alt') isAltHeld.value = false;
    if (e.key === 'Control') isCtrlHeld.value = false;
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('keyup', handleGlobalKeyup);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('keyup', handleGlobalKeyup);
});

// --- Filter Logic ---
const isEtagenDropdownOpen = ref(false);
const isHeatmapDropdownOpen = ref(false);
const isAvailabilityOnly = ref(false);
const showFiltersPopup = ref(false);

const priceBoundaries = computed(() => {
    const prices = (props.project.apartments || []).map(a => parseFloat(a.price)).filter(p => !isNaN(p) && p > 0);
    if (!prices.length) return { min: 0, max: 1000000 };
    return { min: Math.floor(Math.min(...prices)), max: Math.ceil(Math.max(...prices)) };
});

const sqmBoundaries = computed(() => {
    const sqms = (props.project.apartments || []).map(a => parseFloat(a.sqm)).filter(s => !isNaN(s) && s > 0);
    if (!sqms.length) return { min: 0, max: 500 };
    return { min: Math.floor(Math.min(...sqms)), max: Math.ceil(Math.max(...sqms)) };
});

const priceModel = computed({
    get: () => ({ min: eventBus.filters.priceMin ?? priceBoundaries.value.min, max: eventBus.filters.priceMax ?? priceBoundaries.value.max }),
    set: (val) => { eventBus.filters.priceMin = val.min; eventBus.filters.priceMax = val.max; }
});

const sqmModel = computed({
    get: () => ({ min: eventBus.filters.sqmMin ?? sqmBoundaries.value.min, max: eventBus.filters.sqmMax ?? sqmBoundaries.value.max }),
    set: (val) => { eventBus.filters.sqmMin = val.min; eventBus.filters.sqmMax = val.max; }
});

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        page.props.appElement?.requestFullscreen?.() || document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
};

const visibleApartmentIds = computed(() => {
    let filtered = props.project.apartments || [];
    
    // Availability Toggle
    if (isAvailabilityOnly.value) {
        filtered = filtered.filter(a => a.status?.toLowerCase() === 'frei');
    }
    
    if (eventBus.filters.floors && eventBus.filters.floors.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.floors.includes(a.floor_id));
    }
    if (eventBus.filters.rooms && eventBus.filters.rooms.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.rooms.includes(parseFloat(a.rooms)));
    }
    if (eventBus.filters.availabilities && eventBus.filters.availabilities.length > 0) {
        filtered = filtered.filter(a => eventBus.filters.availabilities.includes(a.available_from));
    }
    if (eventBus.filters.priceMin) {
        filtered = filtered.filter(a => parseFloat(a.price) >= parseFloat(eventBus.filters.priceMin));
    }
    if (eventBus.filters.priceMax) {
        filtered = filtered.filter(a => parseFloat(a.price) <= parseFloat(eventBus.filters.priceMax));
    }
    if (eventBus.filters.sqmMin) {
        filtered = filtered.filter(a => parseFloat(a.sqm) >= parseFloat(eventBus.filters.sqmMin));
    }
    if (eventBus.filters.sqmMax) {
        filtered = filtered.filter(a => parseFloat(a.sqm) <= parseFloat(eventBus.filters.sqmMax));
    }

    return filtered.map(a => a.id);
});

const uniqueRooms = computed(() => {
    if (!props.project.apartments) return [];
    return [...new Set(props.project.apartments.map(a => parseFloat(a.rooms)).filter(Boolean))].sort((a,b) => a-b);
});

const uniqueAvailabilities = computed(() => {
    if (!props.project.apartments) return [];
    return [...new Set(props.project.apartments.map(a => a.available_from).filter(Boolean))].sort();
});

const isSpinning360 = ref(false);

const play360Spin = async () => {
    if (isSpinning360.value || !activeViewFrames.value?.length) return;
    isSpinning360.value = true;
    
    // Aktuellen Animationsstatus merken und temporär stoppen
    const wasAnimationActive = isAnimationActive.value;
    isAnimationActive.value = false;
    
    const totalFrames = activeViewFrames.value.length;
    for(let i = 0; i < totalFrames; i++) {
        await new Promise(resolve => setTimeout(resolve, 80));
        nextFrame(true); // Pass true to ignore stop_frames during this spin
    }
    
    isSpinning360.value = false;
    
    // Animationsstatus wiederherstellen, falls er vorher aktiv war
    if (wasAnimationActive) {
        isAnimationActive.value = true;
    }
};

// --- Base State ---
const activeViewId = ref(null);
const activeLayerId = ref(null);
const isLoadingFrames = ref(false);
const activeFrameIndex = ref(0);

const views = computed(() => props.project?.views || []);
const activeView = computed(() => views.value.find(v => v.id === activeViewId.value));
const activeViewLayers = computed(() => activeView.value?.layers || []);
const activeLayer = computed(() => activeViewLayers.value.find(l => l.id === activeLayerId.value));
const activeViewFrames = computed(() => activeView.value?.frames || []);

const activeFrame = computed(() => {
    if (activeViewFrames.value.length === 0) return null;
    return activeViewFrames.value.find(f => f.index === activeFrameIndex.value) 
        || activeViewFrames.value[0];
});

const sunData = computed(() => {
    if (!showSun.value || !activeFrame.value) return null;
    if (activeLayer.value && activeLayer.value.sun_simulation !== undefined && !activeLayer.value.sun_simulation) return null;
    
    const projectLat = 51.165691;
    const projectLon = 10.451526;
    
    const pos = getSunPosition(sunHour.value, projectLat, projectLon);
    
    const frames = activeViewFrames.value;
    if (!frames.length) return null;
    
    const northFrame = frames.find(f => f.is_north);
    const northIdx = northFrame ? northFrame.index : 0;
    const currentIdx = activeFrame.value.index;
    
    const anglePerFrame = 360 / frames.length;
    let cameraAzimuth = ((currentIdx - northIdx) * anglePerFrame) % 360;
    if (cameraAzimuth < 0) cameraAzimuth += 360;
    
    let diff = (pos.azimuth - cameraAzimuth + 540) % 360 - 180;
    
    const isVisible = pos.altitude > 0 && Math.abs(diff) < 70;
    const xPercent = 50 + (diff / 70) * 50; 
    const yPercent = 70 - (pos.altitude / 60) * 60;
    
    const intensity = Math.max(0, 1 - Math.abs(diff) / 70) * Math.min(1, pos.altitude / 10);
    
    return { ...pos, diff, cameraAzimuth, isVisible, xPercent, yPercent, intensity };
});

const dynamicImgStyle = computed(() => {
    if (!sunData.value || !showSun.value) return {};
    if (sunData.value.altitude < 0) {
        const brightness = Math.max(0.25, 1 - (Math.abs(sunData.value.altitude) / 15));
        return { filter: `brightness(${brightness}) sepia(0.3) hue-rotate(180deg) saturate(0.8)` }; 
    }
    let filterStr = "";
    if (sunData.value.altitude < 15) {
        filterStr += `sepia(0.2) saturate(1.1) hue-rotate(-5deg) brightness(0.95) `;
    } else {
        filterStr += `brightness(1.02) saturate(1.05) `;
    }
    return { filter: filterStr.trim() };
});

// Animation State
const isAnimationActive = ref(!isAuth.value);
const isAutoPlaying = ref(false);
const playInterval = ref(null);

watch(isAuth, (val) => {
   if (!val) isAnimationActive.value = true;
}, { immediate: true });

watch(() => props.activeApartmentId, async (newId) => {
    if (!newId) return;
    const ap = props.project?.apartments?.find(a => a.id === newId);
    if (!ap || !ap.best_view_id || !ap.best_frame_id) return;

    let viewChanged = false;
    if (activeViewId.value !== ap.best_view_id) {
        activeViewId.value = ap.best_view_id;
        viewChanged = true;
    }

    if (viewChanged) {
        await nextTick();
    }

    const targetFrame = activeViewFrames.value.find(f => f.id === ap.best_frame_id);
    if (targetFrame) {
        animateToFrame(targetFrame.index);
    }
}, { immediate: true });

const stopAnimation = () => {
    clearInterval(playInterval.value);
    playInterval.value = null;
    isAutoPlaying.value = false;
};

// Navigate to a specific frame index via shortest path
const animateToFrame = (targetFrameIndex, onDone = null) => {
    stopAnimation();
    const frames = [...activeViewFrames.value].sort((a, b) => a.index - b.index);
    if (!frames.length) return;
    if (activeFrameIndex.value === targetFrameIndex) { onDone?.(); return; }
    const currIdx = frames.findIndex(f => f.index === activeFrameIndex.value);
    const targetIdx = frames.findIndex(f => f.index === targetFrameIndex);
    if (targetIdx === -1) return;
    const fwd = (targetIdx - currIdx + frames.length) % frames.length;
    const bwd = (currIdx - targetIdx + frames.length) % frames.length;
    const dir = fwd <= bwd ? 1 : -1;
    isAutoPlaying.value = true;
    playInterval.value = setInterval(() => {
        let ci = frames.findIndex(f => f.index === activeFrameIndex.value);
        ci = (ci + dir + frames.length) % frames.length;
        activeFrameIndex.value = frames[ci].index;
        if (frames[ci].index === targetFrameIndex) {
            stopAnimation();
            onDone?.();
        }
    }, 80);
};

// Spin to next stop frame
const spinToStopFrame = (direction = 1) => {
    if (isAutoPlaying.value) stopAnimation();
    const frames = [...activeViewFrames.value].sort((a, b) => a.index - b.index);
    if (!frames.length) return;
    
    const hasStopFrames = frames.some(f => f.is_stop_frame);
    let steps = 0;
    const maxSteps = frames.length; // Wenn keine markiert sind, rotiere max 1 mal

    isAutoPlaying.value = true;
    playInterval.value = setInterval(() => {
        let ci = frames.findIndex(f => f.index === activeFrameIndex.value);
        ci = (ci + direction + frames.length) % frames.length;
        activeFrameIndex.value = frames[ci].index;
        steps++;
        if ((hasStopFrames && frames[ci].is_stop_frame) || steps >= maxSteps) {
            stopAnimation();
        }
    }, 80);
};

// Next/Prev nav buttons
const nextFrame = (manualStep = false) => {
    if (activeViewFrames.value.length === 0) return;
    
    if (!isAnimationActive.value) {
        stopAnimation();
        const frames = [...activeViewFrames.value].sort((a, b) => a.index - b.index);
        const ci = frames.findIndex(f => f.index === activeFrameIndex.value);
        activeFrameIndex.value = frames[(ci + 1 + frames.length) % frames.length].index;
        return;
    }
    
    spinToStopFrame(1);
};

const prevFrame = (manualStep = false) => {
    if (activeViewFrames.value.length === 0) return;
    if (!isAnimationActive.value) {
        stopAnimation();
        const frames = [...activeViewFrames.value].sort((a, b) => a.index - b.index);
        const ci = frames.findIndex(f => f.index === activeFrameIndex.value);
        activeFrameIndex.value = frames[(ci - 1 + frames.length) % frames.length].index;
        return;
    }
    spinToStopFrame(-1);
};

// --- View / Layer Init & Watchers ---
onMounted(() => {
    if (views.value.length > 0) {
        const startView = views.value.find(v => v.is_start) || views.value[0];
        activeViewId.value = startView.id;
    }
    window.addEventListener('resize', handleResize);
});
onUnmounted(() => {
    clearInterval(playInterval.value);
    window.removeEventListener('resize', handleResize);
});

watch(activeViewId, () => {
    if (activeViewLayers.value.length > 0) {
        activeLayerId.value = activeViewLayers.value[0].id;
    } else {
        activeLayerId.value = null;
    }
    const frames = activeViewFrames.value;
    if (frames.length > 0) {
        const sorted = [...frames].sort((a,b)=>a.index-b.index);
        const stopF = sorted.find(f => f.is_stop_frame);
        activeFrameIndex.value = stopF ? stopF.index : sorted[0].index;
    }
}, { immediate: true });

// When an apartment is selected, navigate to its best view + animate to best frame
watch(() => props.activeApartmentId, (aptId) => {
    if (!aptId) return;
    const apt = props.project.apartments?.find(a => a.id === aptId);
    if (!apt?.best_view_id) return;
    
    const targetViewId = apt.best_view_id;
    const targetFrameIndex = apt.bestFrame?.index ?? null;
    if (targetFrameIndex === null) return;

    const doNavigate = () => {
        animateToFrame(targetFrameIndex);
    };

    if (activeViewId.value !== targetViewId) {
        // Switch view first, then animate after view frames load
        activeViewId.value = targetViewId;
        nextTick(() => setTimeout(doNavigate, 100));
    } else {
        doNavigate();
    }
});

// Switch to target view if requested by another component (e.g. FloorPlanView)
watch(() => eventBus.targetViewId, (viewId) => {
    if (viewId) {
        activeViewId.value = viewId;
        eventBus.targetViewId = null;
    }
}, { immediate: true });

// --- Image Loading and Zoom/Pan ---
const containerRef = ref(null);
const imgBaseScale = ref(1);
const zoomLevel = ref(1);
const naturalW = ref(0);
const naturalH = ref(0);

const translateX = ref(0);
const translateY = ref(0);
const isDragging = ref(false);
const dragStart = { x: 0, y: 0 };
const currentTranslate = { x: 0, y: 0 };

const getFrameImageUrl = (frame) => {
    if (!frame) return null;
    const expectedCollection = activeLayerId.value ? `layer_${activeLayerId.value}` : 'default';
    let media = frame.media?.find(m => m.collection_name === expectedCollection);
    if (!media) media = frame.media?.find(m => m.collection_name === 'default');
    return media ? media.original_url : null;
};

const activeImageUrl = computed(() => {
    return getFrameImageUrl(activeFrame.value);
});

const maxTranslate = computed(() => {
    if (!containerRef.value || !naturalW.value) return { x: 0, y: 0 };
    const cW = containerRef.value.clientWidth;
    const cH = containerRef.value.clientHeight;
    const currentScale = imgBaseScale.value * zoomLevel.value;
    const scaledW = naturalW.value * currentScale;
    const scaledH = naturalH.value * currentScale;
    return {
        x: Math.max(0, (scaledW - cW) / 2),
        y: Math.max(0, (scaledH - cH) / 2)
    };
});

const clampTranslation = () => {
    const maxT = maxTranslate.value;
    translateX.value = Math.max(-maxT.x, Math.min(maxT.x, translateX.value));
    translateY.value = Math.max(-maxT.y, Math.min(maxT.y, translateY.value));
};

const MIN_ZOOM = 1;
const MAX_ZOOM = 5;

const setZoom = (newZoom) => {
    zoomLevel.value = Math.max(MIN_ZOOM, Math.min(newZoom, MAX_ZOOM));
    clampTranslation();
};

const zoomIn = () => setZoom(zoomLevel.value + 0.2);
const zoomOut = () => setZoom(zoomLevel.value - 0.2);
const handleWheel = (e) => {
    const delta = e.deltaY < 0 ? 0.1 : -0.1;
    setZoom(zoomLevel.value + delta);
};

const handleResize = () => {
    if (!containerRef.value) return;
    
    const cW = containerRef.value.clientWidth;
    const cH = containerRef.value.clientHeight;
    imgBaseScale.value = Math.max(cW / naturalW.value, cH / naturalH.value);
    
    // Bild neu skalieren und auf Basis-Zoom setzen
    zoomLevel.value = MIN_ZOOM;
    translateX.value = 0;
    translateY.value = 0;
    
    clampTranslation();
};

const onImgLoad = (e) => {
    if (!e.target) return;
    naturalW.value = e.target.naturalWidth;
    naturalH.value = e.target.naturalHeight;
    handleResize(); 
};

const wrapperStyle = computed(() => {
    const totalScale = imgBaseScale.value * zoomLevel.value;
    return {
        width: `${naturalW.value}px`,
        height: `${naturalH.value}px`,
        transform: `translate(-50%, -50%) translate(${translateX.value}px, ${translateY.value}px) scale(${totalScale})`,
        left: '50%',
        top: '50%'
    };
});

const onMouseDown = (e) => {
    if (drawMode.value || drawModePoint.value || draggedPointIndex.value !== null || draggedPointId.value || draggedPolyId.value) return;
    if (!activeImageUrl.value) return;
    isDragging.value = true;
    dragStart.x = e.clientX;
    dragStart.y = e.clientY;
    currentTranslate.x = translateX.value;
    currentTranslate.y = translateY.value;
};

// --- Polygon & Drawing Tool Logic ---
const framePolygons = ref([]);
const framePoints = ref([]);
const drawMode = ref(false);
const drawModePoint = ref(false);
const currentDrawPoints = ref([]);
const selectedPolyId = ref(null);
const selectedPointId = ref(null);
const draggedPointIndex = ref(null);
const draggedPolyId = ref(null);
const draggedPointId = ref(null);

watch(activeFrame, (newFrame) => {
    if(!newFrame) { framePolygons.value = []; framePoints.value = []; return; }
    try {
        framePolygons.value = newFrame.polygons ? (typeof newFrame.polygons === 'string' ? JSON.parse(newFrame.polygons) : newFrame.polygons) : [];
        framePoints.value = newFrame.points ? (typeof newFrame.points === 'string' ? JSON.parse(newFrame.points) : newFrame.points) : [];
    } catch(e) {
        framePolygons.value = [];
        framePoints.value = [];
    }
    selectedPolyId.value = null;
    selectedPointId.value = null;
    drawMode.value = false;
    drawModePoint.value = false;
    currentDrawPoints.value = [];
    draggedPolyId.value = null;
    draggedPointId.value = null;
    draggedPointIndex.value = null;
}, { immediate: true });

const selectedPolygon = computed(() => framePolygons.value.find(p => p.id === selectedPolyId.value));
const selectedPoint = computed(() => framePoints.value.find(p => p.id === selectedPointId.value));
const activeElement = computed(() => selectedPolygon.value || selectedPoint.value);

const onMouseMove = (e) => {
    // 1. Polygon Point Dragging
    if (draggedPointIndex.value !== null && selectedPolygon.value) {
        const deltaX = (e.movementX / (imgBaseScale.value * zoomLevel.value) / naturalW.value) * 100;
        const deltaY = (e.movementY / (imgBaseScale.value * zoomLevel.value) / naturalH.value) * 100;
        selectedPolygon.value.points[draggedPointIndex.value].x += deltaX;
        selectedPolygon.value.points[draggedPointIndex.value].y += deltaY;
        return;
    }

    // 2. Entire Polygon Dragging
    if (draggedPolyId.value && selectedPolygon.value) {
        const deltaX = (e.movementX / (imgBaseScale.value * zoomLevel.value) / naturalW.value) * 100;
        const deltaY = (e.movementY / (imgBaseScale.value * zoomLevel.value) / naturalH.value) * 100;
        selectedPolygon.value.points.forEach(p => {
            p.x += deltaX;
            p.y += deltaY;
        });
        return;
    }
    
    // 2.5 Point Dragging
    if (draggedPointId.value && selectedPoint.value) {
        let deltaX = (e.movementX / (imgBaseScale.value * zoomLevel.value) / naturalW.value) * 100;
        let deltaY = (e.movementY / (imgBaseScale.value * zoomLevel.value) / naturalH.value) * 100;
        
        // Disable point editing when rotating horizontally (movementX > movementY)
        if (Math.abs(e.movementX) > Math.abs(e.movementY) * 1.5) {
            deltaX = 0; deltaY = 0;
            draggedPointId.value = null; 
            isDragging.value = true;
            dragStart.x = e.clientX;
            dragStart.y = e.clientY;
            currentTranslate.x = translateX.value;
            currentTranslate.y = translateY.value;
            return;
        }

        selectedPoint.value.x += deltaX;
        selectedPoint.value.y += deltaY;
        return;
    }

    // 3. Panning
    if (!isDragging.value) return;
    
    let nextX = currentTranslate.x + (e.clientX - dragStart.x); 
    let nextY = currentTranslate.y + (e.clientY - dragStart.y);

    const maxT = maxTranslate.value;
    nextX = Math.max(-maxT.x, Math.min(maxT.x, nextX));
    nextY = Math.max(-maxT.y, Math.min(maxT.y, nextY));

    translateX.value = nextX;
    translateY.value = nextY;
};

const onMouseUp = () => {
    isDragging.value = false;
    draggedPointIndex.value = null;
    if (draggedPolyId.value || draggedPointId.value) {
        draggedPolyId.value = null;
        draggedPointId.value = null;
        savePolygonsToFrame(); // auto save after drag
    }
};

const handleKeydown = (e) => {
    if (e.key === 'ArrowRight') nextFrame(true);
    if (e.key === 'ArrowLeft') prevFrame(true);
};

const startDragPolygon = (poly, e) => {
    if (drawMode.value || drawModePoint.value || !isAuth.value) return;
    if (selectedPolyId.value !== poly.id) {
        selectPolygon(poly);
    }
    
    if (e.ctrlKey) {
        const svgContainer = e.target.closest('svg');
        if (svgContainer) {
            const svgRect = svgContainer.getBoundingClientRect();
            const xPercent = ((e.clientX - svgRect.left) / svgRect.width) * 100;
            const yPercent = ((e.clientY - svgRect.top) / svgRect.height) * 100;
            
            let minDistance = Infinity;
            let insertIndex = -1;
            
            const pts = poly.points;
            const sqr = (x) => x * x;
            const dist2 = (v, w) => sqr(v.x - w.x) + sqr(v.y - w.y);
            const distToSeg2 = (p, v, w) => {
                const l2 = dist2(v, w);
                if (l2 === 0) return dist2(p, v);
                let t = ((p.x - v.x) * (w.x - v.x) + (p.y - v.y) * (w.y - v.y)) / l2;
                t = Math.max(0, Math.min(1, t));
                return dist2(p, { x: v.x + t * (w.x - v.x), y: v.y + t * (w.y - v.y) });
            };
            
            for (let i = 0; i < pts.length; i++) {
                const p1 = pts[i];
                const p2 = pts[(i + 1) % pts.length];
                const dist = distToSeg2({ x: xPercent, y: yPercent }, p1, p2);
                if (dist < minDistance) {
                    minDistance = dist;
                    insertIndex = (i + 1) % pts.length;
                }
            }
            
            if (insertIndex !== -1) {
                poly.points.splice(insertIndex, 0, { x: xPercent, y: yPercent });
                draggedPointIndex.value = insertIndex;
                return;
            }
        }
    }

    draggedPolyId.value = poly.id;
};


const handlePolygonPublicClick = (poly) => {
    if (poly.link_type === 'view' && poly.target_view_id) {
        activeViewId.value = poly.target_view_id;
    } else if (poly.link_type === 'slider' && poly.slider_id) {
        emit('slider-click', poly.slider_id);
    } else if (poly.link_type === 'tour_point' && poly.tour_point_id) {
        emit('tour-click', poly.tour_point_id);
    } else if (poly.link_type === 'video' && poly.target_url) {
        emit('video-click', poly.target_url);
    } else if (poly.apartment_id) {
        if (typeof window !== 'undefined' && window.innerWidth < 768) {
            mobileSelectedApartmentId.value = poly.apartment_id;
        } else {
            emit('apartment-click', poly.apartment_id);
        }
    }
};

const handlePointPublicClick = (pt) => {
    if (pt.link_type === 'view' && pt.target_view_id) {
        activeViewId.value = pt.target_view_id;
    } else if (pt.link_type === 'slider' && pt.slider_id) {
        emit('slider-click', pt.slider_id);
    } else if (pt.link_type === 'tour_point' && pt.tour_point_id) {
        emit('tour-click', pt.tour_point_id);
    } else if (pt.link_type === 'video' && pt.target_url) {
        emit('video-click', pt.target_url);
    } else if (pt.apartment_id) {
        if (typeof window !== 'undefined' && window.innerWidth < 768) {
            mobileSelectedApartmentId.value = pt.apartment_id;
        } else {
            emit('apartment-click', pt.apartment_id);
        }
    }
};

const activeHeatmapMode = ref('none'); // 'none', 'price', 'sqm'
const heatmapStats = computed(() => {
    let aps = props.project?.apartments || [];
    let prices = aps.map(a => parseFloat(a.price)).filter(p => !isNaN(p) && p > 0);
    let sqms = aps.map(a => parseFloat(a.sqm)).filter(s => !isNaN(s) && s > 0);
    return {
        priceMin: prices.length ? Math.min(...prices) : 0,
        priceMax: prices.length ? Math.max(...prices) : 1,
        sqmMin: sqms.length ? Math.min(...sqms) : 0,
        sqmMax: sqms.length ? Math.max(...sqms) : 1,
    };
});
const getHeatmapColor = (value, min, max) => {
    if(!value || max === min) return 'rgba(200,200,200,0.6)';
    let ratio = (value - min) / (max - min);
    ratio = Math.max(0, Math.min(1, ratio));
    // 0 = Blue (240), 1 = Red (0). So hue = (1-ratio)*240
    const hue = (1 - ratio) * 240;
    return `hsla(${hue}, 80%, 50%, 0.65)`;
};

const hexToRgba = (hex, alpha) => {
    if (!hex) return `rgba(255,255,255,${alpha})`;
    const [r, g, b] = hex.match(/\w\w/g).map(x => parseInt(x, 16));
    return `rgba(${r},${g},${b},${alpha})`;
};

const getPolygonFill = (poly, idx) => {
    if (selectedPolyId.value === poly.id || poly.apartment_id === props.activeApartmentId || (poly.apartment_id && poly.apartment_id === mobileSelectedApartmentId.value)) return 'rgba(171, 113, 92, 0.6)';
    if (poly.link_type === 'view') return 'rgba(255, 255, 255, 0.4)';
    if (poly.link_type === 'slider') return 'rgba(255, 255, 255, 0.25)';
    if (poly.apartment_id) {
        if (!visibleApartmentIds.value.includes(poly.apartment_id)) {
            return 'rgba(255, 255, 255, 0.05)';
        }

        const cs = props.project.color_settings || {};
        const ap = props.project.apartments?.find(a => a.id === poly.apartment_id);

        if (activeHeatmapMode.value !== 'none' && ap) {
            if (activeHeatmapMode.value === 'price') {
                return getHeatmapColor(parseFloat(ap.price), heatmapStats.value.priceMin, heatmapStats.value.priceMax);
            }
            if (activeHeatmapMode.value === 'sqm') {
                return getHeatmapColor(parseFloat(ap.sqm), heatmapStats.value.sqmMin, heatmapStats.value.sqmMax);
            }
        }

        const status = ap?.status?.toLowerCase();
        if (status === 'verkauft')   return hexToRgba(cs.verkauft?.base  || '#e60000', 0.55);
        if (status === 'vermietet')  return hexToRgba(cs.vermietet?.base || '#ff4d4d', 0.55);
        if (status === 'reserviert') return hexToRgba(cs.reserviert?.base || '#ffcc00', 0.55);
        return hexToRgba(cs.frei?.base || '#70cc52', 0.55);
    }
    return 'rgba(255,255,255,0.4)';
};

const getPolygonStroke = (poly, idx) => {
    if (selectedPolyId.value === poly.id || poly.apartment_id === props.activeApartmentId || (poly.apartment_id && poly.apartment_id === mobileSelectedApartmentId.value)) return '#ffffff';
    if (poly.link_type === 'view') return '#ffffff';
    if (poly.apartment_id && !visibleApartmentIds.value.includes(poly.apartment_id)) {
        return 'rgba(255, 255, 255, 0.1)';
    }
    return '#ffffff';
};

const getPointFill = (pt, idx) => {
    if (pt.custom_color) return pt.custom_color;
    
    if (selectedPointId.value === pt.id || pt.apartment_id === props.activeApartmentId || (pt.apartment_id && pt.apartment_id === mobileSelectedApartmentId.value)) return '#ab715c';
    if (pt.link_type === 'view') return '#ffffff';
    if (pt.link_type === 'slider') return 'rgba(255, 255, 255, 0.7)';
    if (pt.apartment_id) {
        if (!visibleApartmentIds.value.includes(pt.apartment_id)) {
            return 'rgba(255, 255, 255, 0.2)';
        }
        const cs = props.project.color_settings || {};
        const ap = props.project.apartments?.find(a => a.id === pt.apartment_id);
        const status = ap?.status?.toLowerCase();
        if (status === 'verkauft')   return cs.verkauft?.base  || '#e60000';
        if (status === 'vermietet')  return cs.vermietet?.base || '#ff4d4d';
        if (status === 'reserviert') return cs.reserviert?.base || '#ffcc00';
        return cs.frei?.base || '#70cc52';
    }
    return '#ffffff';
};

const getPointStroke = (pt, idx) => {
    if (pt.custom_color) return '#ffffff';
    if (selectedPointId.value === pt.id || pt.apartment_id === props.activeApartmentId || (pt.apartment_id && pt.apartment_id === mobileSelectedApartmentId.value)) return '#ffffff';
    if (pt.link_type === 'view') return '#000000';
    if (pt.apartment_id && !visibleApartmentIds.value.includes(pt.apartment_id)) {
        return 'rgba(255, 255, 255, 0.4)';
    }
    return '#ffffff';
};

const hoveredPolyAp = ref(null);
const hoveredPointTooltipText = ref('');
const tooltipPos = ref({ x: 0, y: 0 });

const handlePolyMouseMove = (poly, e) => {
    if (poly.tooltip_active && poly.tooltip_text) {
        hoveredPointTooltipText.value = poly.tooltip_text;
        tooltipPos.value = { x: e.clientX, y: e.clientY };
        hoveredPolyAp.value = null;
        return;
    }
    if (poly.link_type === 'view') return;
    const ap = props.project.apartments?.find(a => a.id === poly.apartment_id);
    if (ap) {
        hoveredPolyAp.value = ap;
        hoveredPointTooltipText.value = '';
        tooltipPos.value = { x: e.clientX, y: e.clientY };
    }
};

const handlePointMouseMove = (pt, e) => {
    if (pt.tooltip_active && pt.tooltip_text) {
        hoveredPointTooltipText.value = pt.tooltip_text;
        tooltipPos.value = { x: e.clientX, y: e.clientY };
        hoveredPolyAp.value = null;
        return;
    }
    if (pt.link_type === 'view') return;
    const ap = props.project.apartments?.find(a => a.id === pt.apartment_id);
    if (ap) {
        hoveredPolyAp.value = ap;
        hoveredPointTooltipText.value = '';
        tooltipPos.value = { x: e.clientX, y: e.clientY };
    }
};

const handlePolyMouseLeave = () => {
    hoveredPolyAp.value = null;
    hoveredPointTooltipText.value = '';
};
const handlePointMouseLeave = () => {
    hoveredPolyAp.value = null;
    hoveredPointTooltipText.value = '';
};

const toggleDrawMode = () => {
    drawMode.value = !drawMode.value;
    if (drawMode.value) {
        drawModePoint.value = false;
        currentDrawPoints.value = [];
        selectedPointId.value = null;
        selectedPolyId.value = null;
    }
};

const toggleDrawModePoint = () => {
    drawModePoint.value = !drawModePoint.value;
    if (drawModePoint.value) {
        drawMode.value = false;
        currentDrawPoints.value = [];
        selectedPolyId.value = null;
        selectedPointId.value = null;
    }
};

const handleSvgClick = (e) => {
    if (!drawMode.value && !drawModePoint.value) {
        selectedPolyId.value = null;
        selectedPointId.value = null;
        emit('deselect');
        if (mobileSelectedApartmentId.value) {
            mobileSelectedApartmentId.value = null;
        }
        return;
    }
    
    const rect = e.currentTarget.getBoundingClientRect();
    const xPercent = ((e.clientX - rect.left) / rect.width) * 100;
    const yPercent = ((e.clientY - rect.top) / rect.height) * 100;
    
    if (drawModePoint.value) {
        const newPt = {
            id: 'pt_' + Math.random().toString(36).substr(2, 9),
            x: xPercent,
            y: yPercent,
            link_type: 'none',
            icon_type: 'preset',
            preset_icon: 'pin',
            size: 100,
            custom_svg: '',
            custom_color: null,
            house_id: null,
            apartment_id: null,
            tooltip_active: false,
            tooltip_text: '',
            isBestView: false,
        };
        framePoints.value.push(newPt);
        selectedPointId.value = newPt.id;
        drawModePoint.value = false;
        savePolygonsToFrame();
        return;
    }

    if (currentDrawPoints.value.length > 2) {
        const first = currentDrawPoints.value[0];
        const dist = Math.sqrt(Math.pow(xPercent - first.x, 2) + Math.pow(yPercent - first.y, 2));
        if (dist < 3) {
            finishDrawing();
            return;
        }
    }
    
    currentDrawPoints.value.push({ x: xPercent, y: yPercent });
};

const finishDrawing = () => {
    const newPoly = {
        id: 'poly_' + Math.random().toString(36).substr(2, 9),
        points: [...currentDrawPoints.value],
        link_type: 'none',
        house_id: null,
        apartment_id: null,
        tooltip_active: false,
        tooltip_text: '',
        isBestView: false,
    };
    framePolygons.value.push(newPoly);
    drawMode.value = false;
    currentDrawPoints.value = [];
    selectedPolyId.value = newPoly.id;
    savePolygonsToFrame(); 
};

const selectPolygon = (poly) => {
    if (drawMode.value || drawModePoint.value) return;
    selectedPolyId.value = poly.id;
    selectedPointId.value = null;
};

const selectPoint = (pt) => {
    if (drawMode.value || drawModePoint.value) return;
    selectedPointId.value = pt.id;
    selectedPolyId.value = null;
};

const deleteSelectedElement = () => {
    if (selectedPolyId.value) {
        framePolygons.value = framePolygons.value.filter(p => p.id !== selectedPolyId.value);
        selectedPolyId.value = null;
        savePolygonsToFrame();
    } else if (selectedPointId.value) {
        framePoints.value = framePoints.value.filter(p => p.id !== selectedPointId.value);
        selectedPointId.value = null;
        savePolygonsToFrame();
    }
};

const copyElement = () => {
    if (selectedPolyId.value && selectedPolygon.value) {
        const copied = JSON.parse(JSON.stringify(selectedPolygon.value));
        copied.id = 'poly_' + Math.random().toString(36).substr(2, 9);
        copied.points.forEach(p => { p.x += 2; p.y += 2; });
        framePolygons.value.push(copied);
        selectedPolyId.value = copied.id;
        savePolygonsToFrame();
    } else if (selectedPointId.value && selectedPoint.value) {
        const copied = JSON.parse(JSON.stringify(selectedPoint.value));
        copied.id = 'pt_' + Math.random().toString(36).substr(2, 9);
        copied.x += 2;
        copied.y += 2;
        framePoints.value.push(copied);
        selectedPointId.value = copied.id;
        savePolygonsToFrame();
    }
};

const availableApartmentsForPoly = computed(() => {
    if (!activeElement.value?.house_id) return [];
    return props.project.apartments?.filter(a => a.house_id === activeElement.value.house_id) || [];
});

const startDragPoint = (index, e) => {
    if (drawMode.value) return;
    if (e.altKey && isAuth.value) {
        if (selectedPolygon.value.points.length > 3) {
            selectedPolygon.value.points.splice(index, 1);
            savePolygonsToFrame();
        } else {
            alert("Ein Polygon muss mindestens 3 Punkte haben.");
        }
        return;
    }
    draggedPointIndex.value = index;
};

const startDragElementPoint = (pt, e) => {
    if (drawMode.value || drawModePoint.value || !isAuth.value) return;
    if (selectedPointId.value !== pt.id) selectPoint(pt);
    draggedPointId.value = pt.id;
};

const isStopFrameModel = computed({
    get: () => activeFrame.value?.is_stop_frame || false,
    set: (val) => {
        if(activeFrame.value) activeFrame.value.is_stop_frame = val;
    }
});

const toggleStopFrame = () => {
    if(!activeFrame.value) return;
    window.axios.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Frame',
        payload: { is_stop_frame: activeFrame.value.is_stop_frame },
        id: activeFrame.value.id
    });
};

const isNorthModel = computed({
    get: () => activeFrame.value?.is_north || false,
    set: (val) => {
        if(activeFrame.value) activeFrame.value.is_north = val;
    }
});

const toggleNorth = () => {
    if(!activeFrame.value) return;
    window.axios.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Frame',
        payload: { is_north: activeFrame.value.is_north },
        id: activeFrame.value.id
    });
};

const savePolygonsToFrame = () => {
    if(!activeFrame.value) return;

    // Optimistically update the Inertia model directly so returning to the frame retains data
    activeFrame.value.polygons = JSON.parse(JSON.stringify(framePolygons.value));
    activeFrame.value.points = JSON.parse(JSON.stringify(framePoints.value));

    window.axios.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Frame',
        payload: { polygons: framePolygons.value, points: framePoints.value },
        id: activeFrame.value.id
    }).then(res => {
        if (activeElement.value?.apartment_id && activeElement.value?.isBestView) {
            window.axios.post(`/projects/${props.project.id}/relation/store`, {
                model: 'Apartment',
                payload: {
                    best_view_id: activeView.value.id,
                    best_frame_id: activeFrame.value.id,
                },
                id: activeElement.value.apartment_id
            });
        }
    }).catch(err => alert("Speichern fehlgeschlagen."));
};
onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', function() {
            setTimeout(()=>{
                handleResize();
            }, 100);
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Alt') isAltHeld.value = true;
            if (e.key === 'Control') isCtrlHeld.value = true;
        });
        window.addEventListener('keyup', (e) => {
            if (e.key === 'Alt') isAltHeld.value = false;
            if (e.key === 'Control') isCtrlHeld.value = false;
        });
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
        // Clean up key listeners if needed, though they aren't fully tracked historically here. 
        // Best practice is to extract them if needed, but resize is the main point here.
    }
});

</script>
