<template>
  <div class="absolute inset-0 w-full h-full pointer-events-none" ref="container">
     <img :src="imageUrl" @load="handleNativeLoad" class="hidden" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, shallowRef } from 'vue';
import * as THREE from 'three';

const props = defineProps({
  imageUrl: { type: String, required: true },
  depthMapUrl: { type: String, required: true },
  sunAzimuth: { type: Number, default: 0 },
  sunAltitude: { type: Number, default: 30 },
  intensity: { type: Number, default: 1.0 },
});

const emit = defineEmits(['load']);

const container = ref(null);
const renderer = shallowRef(null);
const scene = shallowRef(null);
const camera = shallowRef(null);
const material = shallowRef(null);
const animationFrame = ref(null);

const handleNativeLoad = (e) => {
    emit('load', e);
};

// Shaders
const vertexShader = `
  varying vec2 vUv;
  void main() {
    vUv = uv;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;

const fragmentShader = `
  uniform sampler2D tDiffuse;
  uniform sampler2D tDepth;
  uniform vec3 uSunPosition;
  uniform float uIntensity;
  
  varying vec2 vUv;

  void main() {
    vec4 texColor = texture2D(tDiffuse, vUv);
    float depth = texture2D(tDepth, vUv).r;
    
    // Compute normal from depth map (finite difference)
    // Small step size for crisp, sharp edges instead of spongy/rounded blobs
    vec2 texStep = vec2(0.001, 0.001); 
    float d1 = texture2D(tDepth, vUv + vec2(texStep.x, 0.0)).r;
    float d2 = texture2D(tDepth, vUv - vec2(texStep.x, 0.0)).r;
    float d3 = texture2D(tDepth, vUv + vec2(0.0, texStep.y)).r;
    float d4 = texture2D(tDepth, vUv - vec2(0.0, texStep.y)).r;
    
    // Central difference for slopes
    float dx = d2 - d1; 
    float dy = d4 - d3; 
    
    // Z controls how flat the normal is. Lower Z = more intense bumps.
    vec3 normal = normalize(vec3(-dx * 12.0, -dy * 12.0, 1.0));
    
    // Convert mathematical sun direction to screen light direction
    vec3 lightSource = normalize(uSunPosition);
    vec3 lightDir = normalize(lightSource); // direction TO light source
    
    // Diffuse component (Lambertian reflectance)
    float diffuse = max(dot(normal, lightDir), 0.0);
    
    // --- Screen Space Cast Shadows from Depth Map ---
    float shadow = 1.0;
    
    // screen-space step direction towards light
    // In our rig, +X is right, +Y is up.
    vec2 stepDir = lightDir.xy * 0.003; 
    
    // Cast shadow if the sun is not completely behind the house (if z > 0, it means sun illuminates the front)
    float rayDepth = depth;
    float depthStep = lightDir.z * 0.03; // how fast the ray travels towards the light in Z
    
    vec2 shadowUv = vUv;
    for(int i = 1; i <= 20; i++) {
        shadowUv += stepDir;
        float d = texture2D(tDepth, shadowUv).r;
        rayDepth += depthStep;
        
        // Only apply shadow if we are inside the texture bounds
        float inBounds = step(0.0, shadowUv.x) * step(shadowUv.x, 1.0) * step(0.0, shadowUv.y) * step(shadowUv.y, 1.0);
        
        // If surface 'd' is CLOSER to camera than the ray (d > rayDepth)
        if (inBounds > 0.5 && d > rayDepth + 0.015) {
            shadow = 0.8; // Softer cast shadows!
        }
    }
    
    // Combine diffuse, ambient and cast shadow
    // Ambient light prevents completely dark backsides
    float ambient = 0.85; 
    // Powerful highlight physically brightens surfaces perfectly angled to the sun!
    float highlight = diffuse * 0.55; 
    
    float lightIntensity = (ambient + highlight) * shadow; 
    
    vec3 finalRender = texColor.rgb * lightIntensity;
    gl_FragColor = vec4(mix(texColor.rgb, finalRender, uIntensity), texColor.a);
  }
`;

let resizeObserver = null;
let currentLoadId = 0;

const loadTextures = () => {
  if (!props.imageUrl || !props.depthMapUrl) return;
  const loadId = ++currentLoadId;
  const loader = new THREE.TextureLoader();
  Promise.all([
    new Promise(res => loader.load(props.imageUrl, res)),
    new Promise(res => loader.load(props.depthMapUrl, res))
  ]).then(([colorTex, depthTex]) => {
    if (loadId !== currentLoadId) return; // abort stale load
    colorTex.generateMipmaps = false;
    colorTex.minFilter = THREE.LinearFilter;
    depthTex.generateMipmaps = false;
    depthTex.minFilter = THREE.LinearFilter;

    if (!material.value) {
      material.value = new THREE.ShaderMaterial({
        uniforms: {
          tDiffuse: { value: colorTex },
          tDepth: { value: depthTex },
          uSunPosition: { value: new THREE.Vector3(0, 0, 1) },
          uIntensity: { value: props.intensity }
        },
        vertexShader,
        fragmentShader,
        transparent: true
      });

      const geometry = new THREE.PlaneGeometry(2, 2);
      const mesh = new THREE.Mesh(geometry, material.value);
      scene.value.add(mesh);
    } else {
      if (material.value.uniforms.tDiffuse.value) material.value.uniforms.tDiffuse.value.dispose();
      if (material.value.uniforms.tDepth.value) material.value.uniforms.tDepth.value.dispose();
      material.value.uniforms.tDiffuse.value = colorTex;
      material.value.uniforms.tDepth.value = depthTex;
    }

    updateSunPosition();
    renderFn();
  }).catch(e => {
    console.error('ShadowRenderer Error Loading Textures:', e);
  });
};

const initThree = () => {
  if (!container.value || renderer.value) return;

  scene.value = new THREE.Scene();
  camera.value = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
  renderer.value = new THREE.WebGLRenderer({ alpha: true, antialias: true });
  renderer.value.setPixelRatio(window.devicePixelRatio);
  
  const updateSize = () => {
    if (!container.value || !renderer.value) return;
    const width = container.value.clientWidth;
    const height = container.value.clientHeight;
    if (width > 0 && height > 0) {
        renderer.value.setSize(width, height);
        renderFn();
    }
  };
  
  updateSize();
  container.value.appendChild(renderer.value.domElement);

  if (resizeObserver) resizeObserver.disconnect();
  resizeObserver = new ResizeObserver(() => {
    updateSize();
  });
  resizeObserver.observe(container.value);
  
  loadTextures();
};

const updateSunPosition = () => {
  if (!material.value) return;
  const radAux = Math.PI / 180;
  const az = props.sunAzimuth * radAux;
  const alt = props.sunAltitude * radAux;
  
  const x = Math.sin(az) * Math.cos(alt);
  const y = Math.sin(alt);
  const z = -Math.cos(az) * Math.cos(alt);

  material.value.uniforms.uSunPosition.value.set(x, y, z);
  material.value.uniforms.uIntensity.value = props.intensity;
};

const renderFn = () => {
  if (renderer.value && scene.value && camera.value) {
    renderer.value.render(scene.value, camera.value);
  }
};

watch([() => props.sunAzimuth, () => props.sunAltitude, () => props.intensity], () => {
  updateSunPosition();
  renderFn();
});

watch([() => props.imageUrl, () => props.depthMapUrl], () => {
  loadTextures();
});

onMounted(() => {
  initThree();
});

onUnmounted(() => {
  if (renderer.value) {
    renderer.value.dispose();
  }
  if (animationFrame.value) cancelAnimationFrame(animationFrame.value);
});
</script>

<style scoped>
canvas {
  width: 100% !important;
  height: 100% !important;
  display: block;
}
</style>
