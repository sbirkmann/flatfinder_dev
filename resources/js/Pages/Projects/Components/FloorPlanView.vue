<template>
    <div class="w-full h-full flex flex-col bg-[#f8f9fa] relative overflow-hidden">
        
        <!-- Main Area: Floor Plan Image viewer -->
        <div ref="containerRef" class="flex-1 relative flex flex-col items-center justify-center bg-gray-100 overflow-hidden select-none outline-none"
            tabindex="0"
            @wheel.prevent="handleWheel"
            @mousedown.left="onMouseDown"
            @mousemove.prevent="onMouseMove"
            @mouseup="onMouseUp"
            @mouseleave="onMouseUp"
            @keydown="handleKeydown"
            @keyup="handleKeyup"
            :class="{'cursor-grab': activeFloorMedia && !isDragging && !drawMode && !drawModePoint && draggedPointIndex === null && !draggedPolyId && !draggedPointId, 'cursor-grabbing': isDragging || draggedPolyId || draggedPointId, 'cursor-crosshair': drawMode || drawModePoint}">
            
            <!-- Zoom Controls -->
            <div v-if="activeFloorMedia" class="absolute bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-1 p-1 bg-white/90 backdrop-blur-md border border-gray-200 shadow-lg rounded-full pointer-events-auto">
                <button @click="zoomOut" class="p-2 text-gray-700 hover:text-[#ab715c] hover:bg-gray-100 rounded-full transition-colors" title="Verkleinern">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7" /></svg>
                </button>
                <div class="px-2 text-xs font-bold text-gray-500 min-w-[3rem] text-center select-none">{{ Math.round(zoomLevel * 100) }}%</div>
                <button @click="zoomIn" class="p-2 text-gray-700 hover:text-[#ab715c] hover:bg-gray-100 rounded-full transition-colors" title="Vergrößern">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" /></svg>
                </button>
                <button @click="resetZoom" class="p-2 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-full transition-colors" title="Zoom zurücksetzen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </button>
            </div>

            <!-- Image Container -->
            <div v-if="activeFloorMedia" class="absolute transform-origin-center pointer-events-none" :style="wrapperStyle">
                <img :src="activeFloorMedia" @load="onImgLoad" class="w-full h-full drop-shadow-sm block pointer-events-none" alt="Etagenansicht" draggable="false" style="object-fit: fill;" />
                
                <!-- Polygons Layer -->
                <svg class="absolute top-0 left-0 w-full h-full pointer-events-auto overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" @click="handleSvgClick">
                    <polygon 
                        v-for="(poly, idx) in floorPolygons" 
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
                    <template v-for="(pt, idx) in floorPoints" :key="pt.id">
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
            
            <!-- Fallback if no media -->
            <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400 w-full h-full pointer-events-none">
                <svg class="w-20 h-20 mb-4 text-gray-300 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <h3 class="text-2xl font-light text-gray-500 mb-1">Kein Grundriss verfügbar</h3>
                <p class="text-sm font-medium">Für diese Etage wurde noch kein 2D-Grundriss hinterlegt.</p>
            </div>
        </div>

        <!-- Tooltip -->
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

        <!-- Floating Controls Dropdowns (Top Right) -->
        <div class="absolute top-6 right-6 flex flex-col gap-3 z-50 pointer-events-auto w-[240px]">
            <!-- House Custom Dropdown -->
            <div v-if="(project.houses || []).length > 0" class="relative group custom-house-dropdown">
                <div class="absolute -top-2.5 left-3 px-1.5 bg-white backdrop-blur-md rounded-md z-10 text-[9px] font-black uppercase tracking-widest text-[#ab715c]">Haus</div>
                <div @click="houseDropdownOpen = !houseDropdownOpen; floorDropdownOpen = false" class="w-full relative flex items-center justify-between bg-white/95 backdrop-blur-md border border-gray-200/80 text-gray-800 text-[14px] font-bold rounded-[14px] px-4 py-3.5 shadow-lg transition-all duration-300 cursor-pointer hover:bg-white hover:border-[#ab715c]/30 hover:shadow-xl outline-none select-none">
                    <span class="truncate pr-4">{{ project.houses.find(h => h.id === activeHouseId)?.name || 'Bitte wählen' }}</span>
                    <div class="text-gray-400 group-hover:text-[#ab715c] transition-all duration-300" :class="{ 'rotate-180 text-[#ab715c]': houseDropdownOpen }">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>
                
                <!-- Dropdown List -->
                <transition enter-active-class="transition duration-200 ease-out" enter-from-class="transform -translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform -translate-y-2 opacity-0">
                    <div v-if="houseDropdownOpen" class="absolute z-50 w-full mt-2 bg-white/95 backdrop-blur-xl rounded-[14px] shadow-2xl border border-gray-100 py-1.5 max-h-[280px] overflow-y-auto overflow-x-hidden">
                        <div v-for="h in project.houses" :key="h.id" @click.stop="activeHouseId = h.id; houseDropdownOpen = false" class="px-4 py-2.5 text-[14px] font-bold cursor-pointer transition-colors flex items-center justify-between" :class="activeHouseId === h.id ? 'bg-[#ab715c]/10 text-[#ab715c]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'">
                            <span class="truncate">{{ h.name }}</span>
                            <div v-if="activeHouseId === h.id" class="w-2 h-2 rounded-full bg-[#ab715c]"></div>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Floor Custom Dropdown -->
            <div class="relative group mt-1 custom-floor-dropdown">
                <div class="absolute -top-2.5 left-3 px-1.5 bg-white/95 backdrop-blur-md rounded-md z-10 text-[9px] font-black uppercase tracking-widest text-[#ab715c]" :class="{'opacity-50 text-gray-400': availableFloors.length === 0}">Etage</div>
                <div @click="if(availableFloors.length > 0) { floorDropdownOpen = !floorDropdownOpen; houseDropdownOpen = false }" class="w-full relative flex items-center justify-between bg-white/95 backdrop-blur-md border border-gray-200/80 text-[14px] font-bold rounded-[14px] px-4 py-3.5 shadow-lg transition-all duration-300 select-none outline-none" :class="availableFloors.length === 0 ? 'opacity-70 bg-gray-50/90 text-gray-400 cursor-not-allowed shadow-none' : 'text-gray-800 cursor-pointer hover:bg-white hover:border-[#ab715c]/30 hover:shadow-xl'">
                    <span class="truncate pr-4" :class="{'text-gray-400': availableFloors.length === 0}">{{ availableFloors.length === 0 ? '--' : (availableFloors.find(f => f.id === activeFloorId)?.name || 'Bitte wählen') }}</span>
                    <div v-if="availableFloors.length > 0" class="text-gray-400 group-hover:text-[#ab715c] transition-all duration-300" :class="{ 'rotate-180 text-[#ab715c]': floorDropdownOpen }">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>

                <!-- Dropdown List -->
                <transition enter-active-class="transition duration-200 ease-out" enter-from-class="transform -translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform -translate-y-2 opacity-0">
                    <div v-if="floorDropdownOpen && availableFloors.length > 0" class="absolute z-50 w-full mt-2 bg-white/95 backdrop-blur-xl rounded-[14px] shadow-2xl border border-gray-100 py-1.5 max-h-[280px] overflow-y-auto overflow-x-hidden">
                        <div v-for="floor in availableFloors" :key="floor.id" @click.stop="activeFloorId = floor.id; floorDropdownOpen = false" class="px-4 py-2.5 text-[14px] font-bold cursor-pointer transition-colors flex items-center justify-between" :class="activeFloorId === floor.id ? 'bg-[#ab715c]/10 text-[#ab715c]' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'">
                            <span class="truncate">{{ floor.name }}</span>
                            <div v-if="activeFloorId === floor.id" class="w-2 h-2 rounded-full bg-[#ab715c]"></div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>

        <!-- Toolbar (Left) Auth Only -->
        <div v-if="isAuth && activeFloorMedia" class="absolute top-6 left-6 flex flex-col gap-2 z-50 pointer-events-auto">
            <div class="bg-white/95 backdrop-blur-sm p-4 rounded-[16px] shadow-lg border border-gray-200 text-sm w-64" @mousedown.stop>
                <h4 class="font-bold text-gray-800 mb-3 border-b border-gray-100 pb-2 flex items-center gap-2"><svg class="w-4 h-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg> Admin Tools</h4>
                <div class="flex flex-col gap-2">
                    <button @click="toggleDrawMode" :class="['flex items-center justify-center gap-2 w-full px-4 py-2 rounded-xl text-xs font-bold transition shadow-sm', drawMode ? 'bg-[#ab715c] text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        {{ drawMode ? 'Zeichnen beenden' : 'Neues Polygon zeichnen' }}
                    </button>
                    <p v-if="drawMode" class="text-[10px] text-brand-600 leading-tight mt-1 px-1">Klicke auf das Bild, um Ecken zu setzen. Schließe mit Klick auf Eckpunkt 1.</p>
                    
                    <button @click="toggleDrawModePoint" :class="['flex items-center justify-center gap-2 w-full px-4 py-2 rounded-xl text-xs font-bold transition shadow-sm mt-1', drawModePoint ? 'bg-[#ab715c] text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ drawModePoint ? 'Platzieren beenden' : 'Neuen Punkt setzen' }}
                    </button>
                    <p v-if="drawModePoint" class="text-[10px] text-brand-600 leading-tight mt-1 px-1">Klicke auf das Bild, um einen Punkt zu platzieren.</p>
                </div>
            </div>

            <!-- Property Panel for Polygon AND Points -->
            <div v-if="(selectedPolygon || selectedPoint) && !drawMode && !drawModePoint" class="bg-white/95 backdrop-blur-sm p-5 rounded-[16px] shadow-lg border border-brand-200 text-sm w-64 mt-2 max-h-[70vh] overflow-y-auto" @mousedown.stop>
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-bold text-gray-800 flex items-center gap-1"><svg class="w-4 h-4 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" /></svg> Eigenschaften</h4>
                    <button @click="deleteSelectedElement" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-full transition" title="Löschen"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                </div>
                
                <div class="space-y-4">
                    <!-- Point Appearance Config -->
                    <template v-if="selectedPoint">
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Icon Typ</label>
                            <select v-model="selectedPoint.icon_type" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                                <option value="preset">Standard Forms</option>
                                <option value="custom">Eigenes SVG</option>
                            </select>
                        </div>
                        <div v-if="selectedPoint.icon_type === 'preset' || !selectedPoint.icon_type">
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Form</label>
                            <select v-model="selectedPoint.preset_icon" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                                <option value="pin">Pin (Map)</option>
                                <option value="circle">Kreis</option>
                                <option value="star">Stern</option>
                                <option value="square">Quadrat</option>
                            </select>
                        </div>
                        <div v-if="selectedPoint.icon_type === 'custom'">
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">SVG Code</label>
                            <textarea v-model="selectedPoint.custom_svg" rows="3" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="<svg>...</svg>" @change="savePolygonsToFloor"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Größe (%) - {{ selectedPoint.size || 100 }}</label>
                            <input type="range" v-model.number="selectedPoint.size" min="10" max="500" class="w-full" @change="savePolygonsToFloor" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Freie Farbe (Optional)</label>
                            <input type="color" v-model="selectedPoint.custom_color" class="w-full h-8 cursor-pointer border-0 p-0" @change="savePolygonsToFloor" />
                            <button v-if="selectedPoint.custom_color" @click="selectedPoint.custom_color = null; savePolygonsToFloor()" class="text-[10px] text-red-500 mt-1 hover:underline">Farbe zurücksetzen</button>
                        </div>
                        <hr class="border-gray-100 my-2" />
                    </template>
                    
                    <div>
                        <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Verlinken mit</label>
                        <select v-model="activeElement.link_type" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                            <option value="none">-- Ohne --</option>
                            <option value="apartment">Wohnung</option>
                            <option value="view">3D-Aufbau/Finder-Ansicht</option>
                            <option value="slider">Slider</option>
                            <option value="tour_point">Virtuelle Tour</option>
                            <option value="video">Externe Video URL</option>
                        </select>
                    </div>

                    <div v-if="activeElement.link_type === 'view'">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Ziel-Ansicht</label>
                        <select v-model="activeElement.target_view_id" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                            <option :value="null">-- Ansicht wählen --</option>
                            <option v-for="v in (project.views || [])" :key="v.id" :value="v.id">{{ v.name }}</option>
                        </select>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input type="checkbox" v-model="activeElement.tooltip_active" @change="savePolygonsToFloor" class="rounded text-[#ab715c] focus:ring-[#ab715c] w-4 h-4" />
                            <span class="text-xs font-bold text-gray-700">Tooltip anzeigen</span>
                        </label>
                        <div v-if="activeElement.tooltip_active">
                            <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Tooltip Text</label>
                            <textarea v-model="activeElement.tooltip_text" rows="2" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="Beschriftung..." @change="savePolygonsToFloor"></textarea>
                        </div>
                    </div>

                    <div v-if="activeElement.link_type === 'video'">
                        <label class="block text-[11px] font-bold tracking-widest uppercase text-gray-500 mb-1">Video Link</label>
                        <input type="text" v-model="activeElement.target_url" class="w-full py-1.5 px-2 border-gray-300 rounded text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" placeholder="https://..." @change="savePolygonsToFloor" />
                    </div>

                    <div v-if="activeElement.link_type === 'slider'">
                        <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Ziel-Slider</label>
                        <select v-model="activeElement.slider_id" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                            <option :value="null">-- Wählen --</option>
                            <option v-for="s in (project.sliders || [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <div v-if="activeElement.link_type === 'tour_point'">
                        <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Panorama (360°) Punkt</label>
                        <select v-model="activeElement.tour_point_id" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                            <option :value="null">-- Punkt wählen --</option>
                            <template v-for="t in project.virtual_tours" :key="t.id">
                                <optgroup :label="t.name">
                                    <option v-for="p in t.points" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </optgroup>
                            </template>
                        </select>
                    </div>

                    <div v-if="activeElement.link_type === 'apartment'">
                        <label class="block text-[10px] font-bold tracking-widest uppercase text-gray-500 mb-2">Zugehörige Wohnung</label>
                        <select v-model="activeElement.apartment_id" class="w-full py-2 px-3 border border-gray-200 rounded-xl text-xs bg-gray-50 font-bold focus:ring-[#ab715c] focus:border-[#ab715c]" @change="savePolygonsToFloor">
                            <option :value="null">-- Wählen --</option>
                            <option v-for="ap in (project.apartments || [])" :key="ap.id" :value="ap.id">{{ ap.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div v-if="selectedPolygon && !drawMode && (isAltHeld || isCtrlHeld)" class="bg-gray-800 text-white p-3 rounded-lg shadow-xl text-xs flex flex-col gap-1 mt-2">
                <div v-if="isCtrlHeld" class="flex items-center gap-2 text-green-400 font-bold"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg> Klick auf Kante = Punkt einfügen</div>
                <div v-if="isAltHeld" class="flex items-center gap-2 text-red-400 font-bold"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg> Klick auf Punkt = Punkt löschen</div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const isAuth = computed(() => !!page.props.auth?.user);

const props = defineProps({
    project: Object,
    activeApartmentId: { type: [Number, String], default: null },
});

const emit = defineEmits(['apartment-click', 'deselect', 'slider-click', 'view-click', 'tour-click', 'video-click']);

const activeHouseId = ref(null);
const activeFloorId = ref(null);

const houseDropdownOpen = ref(false);
const floorDropdownOpen = ref(false);

// Close dropdowns when clicking outside
onMounted(() => {
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.custom-house-dropdown')) houseDropdownOpen.value = false;
        if (!e.target.closest('.custom-floor-dropdown')) floorDropdownOpen.value = false;
    });
});

// Watch for external apartment changes
watch(() => props.activeApartmentId, (newId) => {
    if (newId && props.project?.apartments) {
        const ap = props.project.apartments.find(a => a.id == newId);
        if (ap) {
            if (ap.house_id) activeHouseId.value = ap.house_id;
            if (ap.floor_id) activeFloorId.value = ap.floor_id;
        }
    }
}, { immediate: true });

onMounted(() => {
    if (!activeHouseId.value && props.project?.houses?.length > 0) {
        activeHouseId.value = props.project.houses[0].id;
    }
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize);
});

// Watch house change to select its first floor
watch(activeHouseId, (newId) => {
    if (newId) {
        const floors = props.project?.floors?.filter(f => f.house_id === newId) || [];
        if (floors.length > 0 && !floors.some(f => f.id === activeFloorId.value)) {
            activeFloorId.value = floors[0].id;
        }
    }
}, { immediate: true });

// Computed Data
const availableFloors = computed(() => {
    if (!activeHouseId.value) return [];
    return props.project?.floors?.filter(f => f.house_id === activeHouseId.value).sort((a,b) => a.index - b.index) || [];
});

const activeFloor = computed(() => {
    return props.project?.floors?.find(f => f.id === activeFloorId.value) || null;
});

const activeFloorMedia = computed(() => {
    if (!activeFloor.value?.media || activeFloor.value.media.length === 0) return null;
    return activeFloor.value.media[0].original_url;
});

const visibleApartmentIds = computed(() => {
    return (props.project?.apartments || []).map(a => a.id);
});

// Reset zoom when changing image
watch(activeFloorMedia, () => {
    resetZoom();
});


// --- Zoom & Pan Logic ---
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

const setZoom = (newZoom) => {
    zoomLevel.value = Math.max(1, Math.min(newZoom, 5));
    clampTranslation();
};

const zoomIn = () => setZoom(zoomLevel.value + 0.2);
const zoomOut = () => setZoom(zoomLevel.value - 0.2);
const resetZoom = () => {
    zoomLevel.value = 1;
    translateX.value = 0;
    translateY.value = 0;
    nextTick(handleResize);
};
const handleWheel = (e) => {
    if (!activeFloorMedia.value) return;
    const delta = e.deltaY < 0 ? 0.1 : -0.1;
    setZoom(zoomLevel.value + delta);
};

const handleResize = () => {
    if (!naturalW.value || !containerRef.value) return;
    const cW = containerRef.value.clientWidth;
    const cH = containerRef.value.clientHeight;
    // Fit so it touches at least one axis natively (100%)
    imgBaseScale.value = Math.min(cW / naturalW.value, cH / naturalH.value);
    clampTranslation();
};

const onImgLoad = (e) => {
    if (!e.target) return;
    naturalW.value = e.target.naturalWidth;
    naturalH.value = e.target.naturalHeight;
    resetZoom();
};

const tiltX = ref(0);
const tiltY = ref(0);

const wrapperStyle = computed(() => {
    const totalScale = imgBaseScale.value * zoomLevel.value;
    return {
        width: `${naturalW.value}px`,
        height: `${naturalH.value}px`,
        transform: `translate(-50%, -50%) perspective(1500px) translate(${translateX.value}px, ${translateY.value}px) scale(${totalScale}) rotateX(${tiltX.value}deg) rotateY(${tiltY.value}deg)`,
        left: '50%',
        top: '50%',
        transition: (tiltX.value === 0 && tiltY.value === 0) ? 'transform 0.4s ease-out' : 'none'
    };
});


// --- Polygon Drawing Logic ---
const floorPolygons = ref([]);
const drawMode = ref(false);
const currentDrawPoints = ref([]);
const selectedPolyId = ref(null);
const draggedPointIndex = ref(null);
const draggedPolyId = ref(null);

const isAltHeld = ref(false);
const isCtrlHeld = ref(false);

const handleKeydown = (e) => {
    if (e.key === 'Alt') { isAltHeld.value = true; e.preventDefault(); }
    if (e.key === 'Control') { isCtrlHeld.value = true; }
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (!drawMode.value && !drawModePoint.value) deleteSelectedElement();
    }
};

const handleKeyup = (e) => {
    if (e.key === 'Alt') { isAltHeld.value = false; }
    if (e.key === 'Control') { isCtrlHeld.value = false; }
};

const floorPoints = ref([]);
const drawModePoint = ref(false);
const selectedPointId = ref(null);
const draggedPointId = ref(null);

watch(activeFloor, (newFloor) => {
    if(!newFloor) { floorPolygons.value = []; floorPoints.value = []; return; }
    try {
        floorPolygons.value = newFloor.polygons ? (typeof newFloor.polygons === 'string' ? JSON.parse(newFloor.polygons) : newFloor.polygons) : [];
        floorPoints.value = newFloor.points ? (typeof newFloor.points === 'string' ? JSON.parse(newFloor.points) : newFloor.points) : [];
    } catch(e) {
        floorPolygons.value = [];
        floorPoints.value = [];
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

const selectedPolygon = computed(() => floorPolygons.value.find(p => p.id === selectedPolyId.value));
const selectedPoint = computed(() => floorPoints.value.find(p => p.id === selectedPointId.value));
const activeElement = computed(() => selectedPolygon.value || selectedPoint.value);

const onMouseDown = (e) => {
    if (drawMode.value || drawModePoint.value || draggedPointIndex.value !== null || draggedPolyId.value || draggedPointId.value) return;
    if (!activeFloorMedia.value) return;
    isDragging.value = true;
    dragStart.x = e.clientX;
    dragStart.y = e.clientY;
    currentTranslate.x = translateX.value;
    currentTranslate.y = translateY.value;
};

const onMouseMove = (e) => {
    // 3D Tilt Effect on mouse move
    if (containerRef.value && !isDragging.value && !drawMode.value && draggedPolyId.value === null && draggedPointId.value === null && draggedPointIndex.value === null) {
        const rect = containerRef.value.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        tiltX.value = ((y / rect.height) - 0.5) * -12; // max rotation degrees
        tiltY.value = ((x / rect.width) - 0.5) * 12;
    } else {
        tiltX.value = 0;
        tiltY.value = 0;
    }

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
        const deltaX = (e.movementX / (imgBaseScale.value * zoomLevel.value) / naturalW.value) * 100;
        const deltaY = (e.movementY / (imgBaseScale.value * zoomLevel.value) / naturalH.value) * 100;
        selectedPoint.value.x += deltaX;
        selectedPoint.value.y += deltaY;
        return;
    }

    // 3. Pan Background Image
    if (isDragging.value) {
        translateX.value = currentTranslate.x + (e.clientX - dragStart.x);
        translateY.value = currentTranslate.y + (e.clientY - dragStart.y);
        clampTranslation();
    }
};

const onMouseUp = () => {
    tiltX.value = 0;
    tiltY.value = 0;
    isDragging.value = false;
    draggedPointIndex.value = null;
    if (draggedPolyId.value || draggedPointId.value) {
        draggedPolyId.value = null;
        draggedPointId.value = null;
        savePolygonsToFloor(); // auto save after drag
    }
};

const startDragPolygon = (poly, e) => {
    if (drawMode.value || drawModePoint.value || !isAuth.value) return;
    if (selectedPolyId.value !== poly.id) selectPolygon(poly);

    if (e.ctrlKey) {
        // Find closest edge and insert point
        const rect = e.currentTarget.parentNode.getBoundingClientRect();
        const mouseX = ((e.clientX - rect.left) / rect.width) * 100;
        const mouseY = ((e.clientY - rect.top) / rect.height) * 100;

        let minDist = Infinity;
        let insertIndex = -1;
        const pts = poly.points;
        for (let i = 0; i < pts.length; i++) {
            const p1 = pts[i];
            const p2 = pts[(i+1)%pts.length];
            const dist = getDistanceToSegment({x: mouseX, y: mouseY}, p1, p2);
            if (dist < minDist) {
                minDist = dist;
                insertIndex = i + 1;
            }
        }
        if (insertIndex !== -1 && minDist < 3) { // Threshold
            poly.points.splice(insertIndex, 0, {x: mouseX, y: mouseY});
            draggedPointIndex.value = insertIndex;
            return;
        }
    }
    draggedPolyId.value = poly.id;
};

const startDragPoint = (index, e) => {
    if (e.altKey && selectedPolygon.value && selectedPolygon.value.points.length > 3) {
        selectedPolygon.value.points.splice(index, 1);
        savePolygonsToFloor();
        return;
    }
    draggedPointIndex.value = index;
};

const startDragElementPoint = (pt, e) => {
    if (drawMode.value || drawModePoint.value || !isAuth.value) return;
    if (selectedPointId.value !== pt.id) selectPoint(pt);
    draggedPointId.value = pt.id;
};

const getDistanceToSegment = (p, p1, p2) => {
    const l2 = Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2);
    if (l2 === 0) return Math.sqrt(Math.pow(p.x - p1.x, 2) + Math.pow(p.y - p1.y, 2));
    let t = ((p.x - p1.x) * (p2.x - p1.x) + (p.y - p1.y) * (p2.y - p1.y)) / l2;
    t = Math.max(0, Math.min(1, t));
    const projX = p1.x + t * (p2.x - p1.x);
    const projY = p1.y + t * (p2.y - p1.y);
    return Math.sqrt(Math.pow(p.x - projX, 2) + Math.pow(p.y - projY, 2));
};

const hoveredPolyAp = ref(null);
const hoveredPointTooltipText = ref('');
const tooltipPos = ref({ x: 0, y: 0 });

const handlePolyMouseMove = (poly, e) => {
    if (poly.tooltip_active && poly.tooltip_text) {
        hoveredPointTooltipText.value = poly.tooltip_text;
        tooltipPos.value = { x: e.clientX, y: e.clientY };
        hoveredPolyAp.value = null;
    } else {
        const ap = props.project.apartments?.find(a => a.id === poly.apartment_id);
        if (ap) {
            hoveredPolyAp.value = ap;
            hoveredPointTooltipText.value = '';
            tooltipPos.value = { x: e.clientX, y: e.clientY };
        }
    }
    if (!isAuth.value) return;
    if (selectedPolyId.value === poly.id && !drawMode.value && draggedPointIndex.value === null && draggedPolyId.value === null) {
        const polyElem = e.target;
        polyElem.style.cursor = isCtrlHeld.value ? 'crosshair' : 'move';
    }
};

const handlePointMouseMove = (pt, e) => {
    if (pt.tooltip_active && pt.tooltip_text) {
        hoveredPointTooltipText.value = pt.tooltip_text;
        tooltipPos.value = { x: e.clientX, y: e.clientY };
        hoveredPolyAp.value = null;
        return;
    }
    const ap = props.project.apartments?.find(a => a.id === pt.apartment_id);
    if (ap) {
        hoveredPolyAp.value = ap;
        hoveredPointTooltipText.value = '';
        tooltipPos.value = { x: e.clientX, y: e.clientY };
    }
};

const handlePolyMouseLeave = (e) => {
    hoveredPolyAp.value = null;
    hoveredPointTooltipText.value = '';
    e.target.style.cursor = '';
};

const handlePointMouseLeave = () => {
    hoveredPolyAp.value = null;
    hoveredPointTooltipText.value = '';
};

const handlePolygonPublicClick = (poly) => {
    handlePublicClickForElement(poly);
};
const handlePointPublicClick = (pt) => {
    handlePublicClickForElement(pt);
};

const handlePublicClickForElement = (el) => {
    if (el.link_type === 'view' && el.target_view_id) {
        emit('view-click', el.target_view_id);
    } else if (el.link_type === 'slider' && el.slider_id) {
        emit('slider-click', el.slider_id);
    } else if (el.link_type === 'tour_point' && el.tour_point_id) {
        emit('tour-click', el.tour_point_id);
    } else if (el.link_type === 'video' && el.target_url) {
        emit('video-click', el.target_url);
    } else if (el.apartment_id) {
        emit('apartment-click', el.apartment_id);
    }
};

const hexToRgba = (hex, alpha) => {
    if (!hex) return `rgba(255,255,255,${alpha})`;
    const [r, g, b] = hex.match(/\w\w/g).map(x => parseInt(x, 16));
    return `rgba(${r},${g},${b},${alpha})`;
};

const getPolygonFill = (poly, idx) => {
    if (selectedPolyId.value === poly.id || poly.apartment_id === props.activeApartmentId) return 'rgba(171, 113, 92, 0.6)';
    if (poly.link_type === 'view') return 'rgba(255, 255, 255, 0.4)';
    if (poly.link_type === 'slider') return 'rgba(255, 255, 255, 0.25)';
    if (poly.link_type === 'tour_point') return 'rgba(255, 255, 255, 0.25)';
    if (poly.apartment_id) {
        const cs = props.project.color_settings || {};
        const ap = props.project.apartments?.find(a => a.id === poly.apartment_id);
        if (ap && ap.status) {
            const s = ap.status.toLowerCase();
            if (s === 'verkauft')   return hexToRgba(cs.verkauft?.base  || '#e60000', 0.55);
            if (s === 'vermietet')  return hexToRgba(cs.vermietet?.base || '#ff4d4d', 0.55);
            if (s === 'reserviert') return hexToRgba(cs.reserviert?.base || '#ffcc00', 0.55);
            return hexToRgba(cs.frei?.base || '#70cc52', 0.55);
        }
    }
    return 'rgba(255,255,255,0.3)';
};

const getPolygonStroke = (poly, idx) => {
    if (selectedPolyId.value === poly.id || poly.apartment_id === props.activeApartmentId) return '#ab715c';
    return '#ffffff';
};

const getPointFill = (pt, idx) => {
    if (pt.custom_color) return pt.custom_color;
    return getPolygonFill(pt, idx);
};
const getPointStroke = (pt, idx) => {
    if (selectedPointId.value === pt.id || pt.apartment_id === props.activeApartmentId) return '#ab715c';
    return '#ffffff';
};

const selectPolygon = (poly) => {
    selectedPolyId.value = poly.id;
    selectedPointId.value = null;
};
const selectPoint = (pt) => {
    selectedPointId.value = pt.id;
    selectedPolyId.value = null;
};

const toggleDrawMode = () => {
    drawMode.value = !drawMode.value;
    if (drawMode.value) {
        drawModePoint.value = false;
        selectedPolyId.value = null;
        selectedPointId.value = null;
        currentDrawPoints.value = [];
    }
};

const toggleDrawModePoint = () => {
    drawModePoint.value = !drawModePoint.value;
    if (drawModePoint.value) {
        drawMode.value = false;
        selectedPolyId.value = null;
        selectedPointId.value = null;
        currentDrawPoints.value = [];
    }
};

const handleSvgClick = (e) => {
    if (!drawMode.value && !drawModePoint.value) {
        selectedPolyId.value = null;
        selectedPointId.value = null;
        emit('deselect');
        return;
    }

    const rect = e.currentTarget.getBoundingClientRect();
    const xPercent = ((e.clientX - rect.left) / rect.width) * 100;
    const yPercent = ((e.clientY - rect.top) / rect.height) * 100;

    if (drawModePoint.value) {
        const newPt = {
            id: 'pt_' + Date.now(),
            x: xPercent,
            y: yPercent,
            link_type: 'none',
            icon_type: 'preset',
            preset_icon: 'pin',
            size: 100,
            custom_svg: '',
            custom_color: null,
            tooltip_active: false,
            tooltip_text: ''
        };
        floorPoints.value.push(newPt);
        selectedPointId.value = newPt.id;
        drawModePoint.value = false;
        savePolygonsToFloor();
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
    if (currentDrawPoints.value.length > 2) {
        const newPoly = {
            id: 'poly_' + Date.now(),
            points: [...currentDrawPoints.value],
            link_type: 'none',
            tooltip_active: false,
            tooltip_text: ''
        };
        floorPolygons.value.push(newPoly);
        selectedPolyId.value = newPoly.id;
        savePolygonsToFloor();
    }
    drawMode.value = false;
    currentDrawPoints.value = [];
};

const deleteSelectedElement = () => {
    if (selectedPolyId.value) {
        floorPolygons.value = floorPolygons.value.filter(p => p.id !== selectedPolyId.value);
        selectedPolyId.value = null;
        savePolygonsToFloor();
    } else if (selectedPointId.value) {
        floorPoints.value = floorPoints.value.filter(p => p.id !== selectedPointId.value);
        selectedPointId.value = null;
        savePolygonsToFloor();
    }
};

const savePolygonsToFloor = () => {
    if(!activeFloor.value) return;
    window.axios.post(`/projects/${props.project.id}/relation/store`, {
        model: 'Floor',
        payload: { polygons: floorPolygons.value, points: floorPoints.value },
        id: activeFloor.value.id
    }).catch(err => alert("Speichern fehlgeschlagen."));
};

</script>
