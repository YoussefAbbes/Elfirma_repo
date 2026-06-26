class SensorModel {
    constructor(containerId, sensorType) {
        this.containerId = containerId;
        this.sensorType = sensorType;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.model = null;
        this.particles = [];
        this.clock = new THREE.Clock();
        this.isHovered = false;
        this.mouse = new THREE.Vector2();
        this.raycaster = new THREE.Raycaster();
        this.interactiveParts = [];
    }

    async init() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        // Ensure THREE is loaded
        if (typeof THREE === 'undefined') {
            console.error('Three.js is not loaded');
            return;
        }

        // Scene setup - Dark Premium Theme
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0a0f1c);
        this.scene.fog = new THREE.FogExp2(0x0a0f1c, 0.05);

        // Camera setup
        this.camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight, 0.1, 1000);
        this.camera.position.set(0, 2, 8);

        // Renderer setup
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(container.clientWidth, container.clientHeight);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.2;
        container.innerHTML = ''; // clear placeholder
        container.appendChild(this.renderer.domElement);

        // Lighting - Studio Setup
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambientLight);

        const mainLight = new THREE.DirectionalLight(0xffffff, 1.5);
        mainLight.position.set(5, 10, 7);
        mainLight.castShadow = true;
        mainLight.shadow.mapSize.width = 2048;
        mainLight.shadow.mapSize.height = 2048;
        this.scene.add(mainLight);

        const fillLight = new THREE.DirectionalLight(0x667eea, 0.8);
        fillLight.position.set(-5, 3, -5);
        this.scene.add(fillLight);

        const rimLight = new THREE.PointLight(0x764ba2, 2, 20);
        rimLight.position.set(0, -5, -5);
        this.scene.add(rimLight);

        // Floor / Grid
        const gridHelper = new THREE.GridHelper(20, 40, 0x667eea, 0x1a233a);
        gridHelper.position.y = -3;
        gridHelper.material.opacity = 0.2;
        gridHelper.material.transparent = true;
        this.scene.add(gridHelper);

        // Create specific sensor
        this.createModel();

        // Background Particles
        this.createBackgroundParticles();

        // Interaction
        this.addInteractivity(container);

        // Resize handler
        window.addEventListener('resize', () => this.onWindowResize(container));

        // Start animation loop
        this.animate();
    }

    createBackgroundParticles() {
        const particleGeo = new THREE.BufferGeometry();
        const particleCount = 200;
        const posArray = new Float32Array(particleCount * 3);
        
        for(let i=0; i < particleCount * 3; i++) {
            posArray[i] = (Math.random() - 0.5) * 15;
        }
        
        particleGeo.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
        const particleMat = new THREE.PointsMaterial({
            size: 0.05,
            color: 0x667eea,
            transparent: true,
            opacity: 0.6,
            blending: THREE.AdditiveBlending
        });
        
        this.bgParticles = new THREE.Points(particleGeo, particleMat);
        this.scene.add(this.bgParticles);
    }

    createModel() {
        this.model = new THREE.Group();
        
        switch(this.sensorType) {
            case 'humidity': this.buildHumiditySensor(); break;
            case 'temperature': this.buildTemperatureSensor(); break;
            case 'ph': this.buildPhSensor(); break;
            case 'npk': this.buildNpkSensor(); break;
            case 'disease': this.buildDiseaseScene(); break;
            case 'ai': this.buildAIScene(); break;
            case 'maintenance': this.buildMaintenanceScene(); break;
            default: this.buildHumiditySensor();
        }
        
        // Add floating animation wrapper
        this.floatingGroup = new THREE.Group();
        this.floatingGroup.add(this.model);
        this.scene.add(this.floatingGroup);
    }

    // --- SENSOR MODELS ---

    buildHumiditySensor() {
        // High-tech PCB based humidity sensor
        const pcbMat = new THREE.MeshStandardMaterial({ 
            color: 0x1565C0, 
            roughness: 0.4, 
            metalness: 0.8 
        });
        const goldMat = new THREE.MeshStandardMaterial({ 
            color: 0xFFD700, 
            roughness: 0.2, 
            metalness: 1.0 
        });

        // Main Board
        const boardGeo = new THREE.BoxGeometry(1.2, 3, 0.1);
        const board = new THREE.Mesh(boardGeo, pcbMat);
        board.castShadow = true;
        this.model.add(board);

        // Prongs (Capacitive traces)
        for(let i=0; i<4; i++) {
            const traceGeo = new THREE.BoxGeometry(0.1, 2.5, 0.12);
            const trace = new THREE.Mesh(traceGeo, goldMat);
            trace.position.set(-0.4 + (i * 0.26), -0.5, 0);
            this.model.add(trace);
        }

        // Top Housing
        const housingGeo = new THREE.BoxGeometry(1.5, 1.5, 0.6);
        const housingMat = new THREE.MeshStandardMaterial({ color: 0x222222, roughness: 0.7 });
        const housing = new THREE.Mesh(housingGeo, housingMat);
        housing.position.set(0, 2, 0);
        housing.castShadow = true;
        this.model.add(housing);

        // LED Indicator
        const ledGeo = new THREE.SphereGeometry(0.1, 16, 16);
        this.ledMat = new THREE.MeshStandardMaterial({ 
            color: 0x00FF00, 
            emissive: 0x00FF00, 
            emissiveIntensity: 2
        });
        const led = new THREE.Mesh(ledGeo, this.ledMat);
        led.position.set(0.5, 2.3, 0.3);
        this.model.add(led);

        // Add to interactive parts
        board.userData = { name: 'Capacitive Probes', desc: 'Measures dielectric permittivity' };
        housing.userData = { name: 'Microcontroller', desc: 'Processes analog signals' };
        this.interactiveParts.push(board, housing);

        // Water droplets animation
        this.createWaterDroplets();
    }

    buildTemperatureSensor() {
        const metalMat = new THREE.MeshStandardMaterial({
            color: 0xaaaaaa,
            metalness: 0.9,
            roughness: 0.2
        });

        const blackMat = new THREE.MeshStandardMaterial({ color: 0x111111, roughness: 0.8 });

        // Steel Probe
        const probeGeo = new THREE.CylinderGeometry(0.1, 0.05, 4, 32);
        const probe = new THREE.Mesh(probeGeo, metalMat);
        probe.castShadow = true;
        this.model.add(probe);

        // Head casing
        const headGeo = new THREE.CylinderGeometry(0.8, 0.8, 1, 32);
        const head = new THREE.Mesh(headGeo, blackMat);
        head.position.y = 2.5;
        head.rotation.x = Math.PI / 2;
        this.model.add(head);

        // Digital Screen (Glass)
        const screenGeo = new THREE.PlaneGeometry(1, 0.5);
        this.screenMat = new THREE.MeshStandardMaterial({
            color: 0x00ffff,
            emissive: 0x005555,
            transparent: true,
            opacity: 0.8
        });
        const screen = new THREE.Mesh(screenGeo, this.screenMat);
        screen.position.set(0, 2.5, 0.41);
        this.model.add(screen);

        this.interactiveParts.push(probe, head);

        // Heat waves
        this.createHeatWaves();
    }

    buildPhSensor() {
        const plasticMat = new THREE.MeshStandardMaterial({ color: 0x111111, roughness: 0.5 });
        const glassMat = new THREE.MeshStandardMaterial({
            color: 0xffffff,
            metalness: 0.1,
            roughness: 0.05,
            transparent: true,
            opacity: 0.4,
            envMapIntensity: 2
        });
        const liquidMat = new THREE.MeshStandardMaterial({
            color: 0x9C27B0,
            transparent: true,
            opacity: 0.8,
            emissive: 0x4a0072
        });

        // Main body
        const bodyGeo = new THREE.CylinderGeometry(0.4, 0.4, 3, 32);
        const body = new THREE.Mesh(bodyGeo, plasticMat);
        body.position.y = 1.5;
        this.model.add(body);

        // Glass Bulb (Bottom)
        const bulbGeo = new THREE.SphereGeometry(0.35, 32, 32);
        const bulb = new THREE.Mesh(bulbGeo, glassMat);
        bulb.position.y = -0.2;
        this.model.add(bulb);

        // Liquid inside bulb
        const innerBulbGeo = new THREE.SphereGeometry(0.28, 32, 32);
        this.liquidMesh = new THREE.Mesh(innerBulbGeo, liquidMat);
        this.liquidMesh.position.y = -0.2;
        this.model.add(this.liquidMesh);

        this.interactiveParts.push(body, bulb);
    }

    buildNpkSensor() {
        const bodyMat = new THREE.MeshStandardMaterial({ color: 0x2e7d32, roughness: 0.7 });
        const probeMat = new THREE.MeshStandardMaterial({ color: 0xeeeeee, metalness: 0.8, roughness: 0.3 });

        // Main Housing
        const housingGeo = new THREE.BoxGeometry(1.5, 1.2, 1);
        const housing = new THREE.Mesh(housingGeo, bodyMat);
        housing.position.y = 1;
        this.model.add(housing);

        // 3 Probes (N, P, K)
        for(let i=0; i<3; i++) {
            const probeGeo = new THREE.CylinderGeometry(0.08, 0.05, 2.5, 16);
            const probe = new THREE.Mesh(probeGeo, probeMat);
            probe.position.set(-0.4 + (i * 0.4), -1, 0);
            this.model.add(probe);
        }

        // LEDs (N=Red, P=Green, K=Blue)
        const colors = [0xff0000, 0x00ff00, 0x0000ff];
        this.npkLeds = [];
        for(let i=0; i<3; i++) {
            const ledGeo = new THREE.BoxGeometry(0.2, 0.1, 0.2);
            const ledMat = new THREE.MeshStandardMaterial({
                color: colors[i],
                emissive: colors[i],
                emissiveIntensity: 0
            });
            const led = new THREE.Mesh(ledGeo, ledMat);
            led.position.set(-0.4 + (i * 0.4), 1.6, 0.3);
            this.model.add(led);
            this.npkLeds.push(led);
        }

        this.interactiveParts.push(housing);
    }

    buildDiseaseScene() {
        // Leaf geometry
        const leafShape = new THREE.Shape();
        leafShape.moveTo(0, 0);
        leafShape.quadraticCurveTo(2, 2, 0, 5);
        leafShape.quadraticCurveTo(-2, 2, 0, 0);

        const extrudeSettings = { depth: 0.05, bevelEnabled: true, bevelSegments: 2, steps: 1, bevelSize: 0.02, bevelThickness: 0.02 };
        const leafGeo = new THREE.ExtrudeGeometry(leafShape, extrudeSettings);
        
        const leafMat = new THREE.MeshStandardMaterial({ color: 0x4CAF50, roughness: 0.6 });
        const leaf = new THREE.Mesh(leafGeo, leafMat);
        leaf.position.y = -2;
        this.model.add(leaf);

        // AI Scanner Plane
        const scannerGeo = new THREE.PlaneGeometry(4, 0.05);
        this.scannerMat = new THREE.MeshBasicMaterial({ 
            color: 0x00ffff, 
            transparent: true, 
            opacity: 0.8,
            side: THREE.DoubleSide
        });
        this.scanner = new THREE.Mesh(scannerGeo, this.scannerMat);
        this.scanner.rotation.x = Math.PI / 2;
        this.model.add(this.scanner);
    }

    buildAIScene() {
        // Core Brain/Network
        const coreGeo = new THREE.IcosahedronGeometry(1, 2);
        const coreMat = new THREE.MeshStandardMaterial({
            color: 0x764ba2,
            emissive: 0x3a1b63,
            wireframe: true,
            transparent: true,
            opacity: 0.8
        });
        this.aiCore = new THREE.Mesh(coreGeo, coreMat);
        this.model.add(this.aiCore);

        // Orbiting nodes
        this.aiNodes = new THREE.Group();
        for(let i=0; i<15; i++) {
            const nodeGeo = new THREE.SphereGeometry(0.1, 8, 8);
            const nodeMat = new THREE.MeshStandardMaterial({
                color: 0x00ffff,
                emissive: 0x00ffff,
                emissiveIntensity: 1
            });
            const node = new THREE.Mesh(nodeGeo, nodeMat);
            
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos((Math.random() * 2) - 1);
            const r = 2 + Math.random();
            
            node.position.x = r * Math.sin(phi) * Math.cos(theta);
            node.position.y = r * Math.sin(phi) * Math.sin(theta);
            node.position.z = r * Math.cos(phi);
            
            this.aiNodes.add(node);
        }
        this.model.add(this.aiNodes);
    }

    buildMaintenanceScene() {
        const sensorGeo = new THREE.CylinderGeometry(0.5, 0.5, 3, 32);
        const sensorMat = new THREE.MeshStandardMaterial({ color: 0x444444, metalness: 0.8 });
        const sensor = new THREE.Mesh(sensorGeo, sensorMat);
        this.model.add(sensor);

        // Wrench/Tool orbiting
        const toolGeo = new THREE.TorusGeometry(0.8, 0.1, 16, 100, Math.PI);
        const toolMat = new THREE.MeshStandardMaterial({ color: 0xffaa00, metalness: 0.9 });
        this.tool = new THREE.Mesh(toolGeo, toolMat);
        this.model.add(this.tool);
    }

    // --- ANIMATION EFFECTS ---

    createWaterDroplets() {
        const dropGeo = new THREE.SphereGeometry(0.05, 8, 8);
        const dropMat = new THREE.MeshStandardMaterial({
            color: 0x88ccff, transparent: true, opacity: 0.6, metalness: 0.1, roughness: 0
        });
        this.drops = [];
        for(let i=0; i<5; i++) {
            const drop = new THREE.Mesh(dropGeo, dropMat);
            drop.position.set((Math.random()-0.5)*0.8, Math.random()*2 - 1, (Math.random()-0.5)*0.2);
            this.model.add(drop);
            this.drops.push({ mesh: drop, speed: 0.02 + Math.random()*0.02 });
        }
    }

    createHeatWaves() {
        const ringGeo = new THREE.RingGeometry(0.2, 0.3, 32);
        const ringMat = new THREE.MeshBasicMaterial({
            color: 0xff5500, transparent: true, opacity: 0.5, side: THREE.DoubleSide
        });
        this.heatRings = [];
        for(let i=0; i<3; i++) {
            const ring = new THREE.Mesh(ringGeo, ringMat);
            ring.rotation.x = Math.PI/2;
            this.model.add(ring);
            this.heatRings.push({ mesh: ring, offset: i * 2 });
        }
    }

    // --- INTERACTION & RENDER ---

    addInteractivity(container) {
        let isDragging = false;
        let previousMousePosition = { x: 0, y: 0 };
        this.targetRotation = { x: 0, y: 0 };

        container.addEventListener('mousedown', (e) => {
            isDragging = true;
            previousMousePosition = { x: e.clientX, y: e.clientY };
        });

        container.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            this.mouse.x = ((e.clientX - rect.left) / container.clientWidth) * 2 - 1;
            this.mouse.y = -((e.clientY - rect.top) / container.clientHeight) * 2 + 1;

            if (isDragging) {
                const deltaX = e.clientX - previousMousePosition.x;
                const deltaY = e.clientY - previousMousePosition.y;
                this.targetRotation.y += deltaX * 0.01;
                this.targetRotation.x += deltaY * 0.01;
                previousMousePosition = { x: e.clientX, y: e.clientY };
            }
        });

        container.addEventListener('mouseup', () => isDragging = false);
        container.addEventListener('mouseleave', () => {
            isDragging = false;
            this.mouse.x = 0;
            this.mouse.y = 0;
        });

        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            this.camera.position.z += e.deltaY * 0.005;
            this.camera.position.z = THREE.MathUtils.clamp(this.camera.position.z, 3, 15);
        }, { passive: false });
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        const time = this.clock.getElapsedTime();

        // Smooth rotation damping
        if (this.model) {
            this.model.rotation.y += (this.targetRotation.y - this.model.rotation.y) * 0.1;
            this.model.rotation.x += (this.targetRotation.x - this.model.rotation.x) * 0.1;
            
            // Auto rotation if not interacting
            if (Math.abs(this.targetRotation.y - this.model.rotation.y) < 0.01) {
                this.targetRotation.y += 0.002;
            }
        }

        // Floating animation
        if (this.floatingGroup) {
            this.floatingGroup.position.y = Math.sin(time) * 0.2;
        }

        // Background particles
        if (this.bgParticles) {
            this.bgParticles.rotation.y = time * 0.05;
            const positions = this.bgParticles.geometry.attributes.position.array;
            for(let i=1; i<positions.length; i+=3) {
                positions[i] += 0.01;
                if (positions[i] > 10) positions[i] = -10;
            }
            this.bgParticles.geometry.attributes.position.needsUpdate = true;
        }

        // Sensor specific animations
        if (this.sensorType === 'humidity' && this.ledMat) {
            this.ledMat.emissiveIntensity = (Math.sin(time * 5) + 1) / 2 + 0.5;
            if (this.drops) {
                this.drops.forEach(d => {
                    d.mesh.position.y -= d.speed;
                    if (d.mesh.position.y < -1) d.mesh.position.y = 2;
                });
            }
        }
        
        if (this.sensorType === 'temperature' && this.heatRings) {
            this.heatRings.forEach(r => {
                let t = (time + r.offset) % 3;
                r.mesh.position.y = -2 + t * 2;
                r.mesh.scale.setScalar(1 + t);
                r.mesh.material.opacity = 1 - (t / 3);
            });
            if (this.screenMat) {
                this.screenMat.emissiveIntensity = 0.5 + Math.random() * 0.2;
            }
        }

        if (this.sensorType === 'ph' && this.liquidMesh) {
            this.liquidMesh.material.color.setHSL((Math.sin(time*0.5)+1)/2 * 0.2 + 0.6, 1, 0.5); // Color shifting
        }

        if (this.sensorType === 'npk' && this.npkLeds) {
            this.npkLeds.forEach((led, i) => {
                led.material.emissiveIntensity = Math.max(0, Math.sin(time * 3 + i * 2)) * 2;
            });
        }

        if (this.sensorType === 'disease' && this.scanner) {
            this.scanner.position.y = Math.sin(time * 2) * 2.5;
        }

        if (this.sensorType === 'ai' && this.aiCore) {
            this.aiCore.rotation.y = time * 0.5;
            this.aiCore.rotation.x = time * 0.3;
            this.aiNodes.rotation.y = -time * 0.2;
            this.aiNodes.rotation.z = time * 0.1;
        }

        if (this.sensorType === 'maintenance' && this.tool) {
            this.tool.position.y = Math.sin(time * 4) * 0.5;
            this.tool.rotation.x = time;
            this.tool.rotation.y = time * 0.5;
        }

        // Raycasting for hover effects
        if (this.interactiveParts.length > 0) {
            this.raycaster.setFromCamera(this.mouse, this.camera);
            const intersects = this.raycaster.intersectObjects(this.interactiveParts);
            
            document.body.style.cursor = intersects.length > 0 ? 'pointer' : 'default';
            
            this.interactiveParts.forEach(p => {
                if (p.material.emissive) {
                    p.material.emissiveIntensity = 0; // Reset
                }
            });

            if (intersects.length > 0) {
                const obj = intersects[0].object;
                if (obj.material.emissive !== undefined) {
                    if(!obj.userData.originalEmissive) obj.userData.originalEmissive = obj.material.emissive.getHex();
                    obj.material.emissive.setHex(0x333333);
                    obj.material.emissiveIntensity = 0.5;
                }
            }
        }

        if (this.renderer && this.scene && this.camera) {
            this.renderer.render(this.scene, this.camera);
        }
    }

    onWindowResize(container) {
        if(!this.camera || !this.renderer) return;
        const width = container.clientWidth;
        const height = container.clientHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }
}

// Global initialization
window.initializeSensorModels = function() {
    const viewer = document.getElementById('model-viewer');
    if (viewer) {
        const sensorType = viewer.dataset.sensor;
        const model = new SensorModel('model-viewer', sensorType);
        
        // Dynamic load Three.js if missing, else run immediately
        if (typeof THREE === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
            script.onload = () => model.init();
            document.head.appendChild(script);
        } else {
            model.init();
        }
    }
};

document.addEventListener('DOMContentLoaded', window.initializeSensorModels);
