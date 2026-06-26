(function (global) {
  'use strict';

  function normalizeText(value) {
    return String(value || '').toLowerCase().trim();
  }

  function speak(text) {
    if (!window.speechSynthesis || !text) {
      return;
    }

    window.speechSynthesis.cancel();
    var u = new SpeechSynthesisUtterance(text);
    u.lang = 'fr-FR';
    u.rate = 1;
    window.speechSynthesis.speak(u);
  }

  function loadScript(url) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[data-gesture-src="' + url + '"]');
      if (existing && existing.dataset.loaded === '1') {
        resolve();
        return;
      }

      if (existing) {
        existing.addEventListener('load', function () { resolve(); }, { once: true });
        existing.addEventListener('error', function () { reject(new Error('load failed')); }, { once: true });
        return;
      }

      var script = document.createElement('script');
      script.src = url;
      script.async = true;
      script.dataset.gestureSrc = url;
      script.addEventListener('load', function () {
        script.dataset.loaded = '1';
        resolve();
      }, { once: true });
      script.addEventListener('error', function () {
        reject(new Error('load failed'));
      }, { once: true });
      document.head.appendChild(script);
    });
  }

  function GestureAssistant(config) {
    this.config = config || {};
    this.id = this.config.id || 'gesture';
    this.onGesture = typeof this.config.onGesture === 'function' ? this.config.onGesture : function () { return null; };
    this.cooldownMs = Number(this.config.cooldownMs || 2600);
    this.minStableFrames = Number(this.config.minStableFrames || 5);
    this.autoSpeak = !!this.config.autoSpeak;
    this.camera = null;
    this.hands = null;
    this.isRunning = false;
    this.lastActionAt = 0;
    this.lastGesture = '';
    this.stableFrames = 0;
    this.video = null;
    this.statusEl = null;
    this.helpEl = null;
  }

  GestureAssistant.prototype.renderUi = function () {
    if (document.getElementById('gesture-fab-' + this.id)) {
      this.fab = document.getElementById('gesture-fab-' + this.id);
      this.panel = document.getElementById('gesture-panel-' + this.id);
      this.video = document.getElementById('gesture-video-' + this.id);
      this.statusEl = document.getElementById('gesture-status-' + this.id);
      return;
    }

    var fab = document.createElement('button');
    fab.id = 'gesture-fab-' + this.id;
    fab.type = 'button';
    fab.title = 'Assistant gestes';
    fab.style.cssText = [
      'position: fixed',
      'right: 22px',
      'bottom: 22px',
      'width: 58px',
      'height: 58px',
      'border-radius: 50%',
      'border: none',
      'background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%)',
      'color: #fff',
      'z-index: 1400',
      'box-shadow: 0 12px 22px rgba(15,118,110,.35)',
      'cursor: pointer'
    ].join(';');
    fab.innerHTML = '<span class="material-symbols-outlined">videocam</span>';

    var panel = document.createElement('div');
    panel.id = 'gesture-panel-' + this.id;
    panel.style.cssText = [
      'position: fixed',
      'right: 22px',
      'bottom: 92px',
      'width: min(90vw, 300px)',
      'background: rgba(2,6,23,.92)',
      'border: 1px solid rgba(148,163,184,.25)',
      'border-radius: 12px',
      'padding: 10px',
      'z-index: 1400',
      'display: none'
    ].join(';');

    var video = document.createElement('video');
    video.id = 'gesture-video-' + this.id;
    video.autoplay = true;
    video.playsInline = true;
    video.muted = true;
    video.style.cssText = 'width:100%;border-radius:8px;transform:scaleX(-1);background:#000;';

    var status = document.createElement('div');
    status.id = 'gesture-status-' + this.id;
    status.style.cssText = 'color:#e2e8f0;font-size:12px;line-height:1.35;margin-top:8px;min-height:34px;';
    status.textContent = 'Assistant gestes inactif.';

    var help = document.createElement('div');
    help.id = 'gesture-help-' + this.id;
    help.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid rgba(148,163,184,.25);color:#cbd5e1;font-size:12px;line-height:1.4;';
    help.innerHTML = '<div style="opacity:.9;">Chargement aide gestes...</div>';

    panel.appendChild(video);
    panel.appendChild(status);
    panel.appendChild(help);
    document.body.appendChild(panel);
    document.body.appendChild(fab);

    this.fab = fab;
    this.panel = panel;
    this.video = video;
    this.statusEl = status;
    this.helpEl = help;

    var self = this;
    fab.addEventListener('click', function () {
      if (self.isRunning) {
        self.stop();
      } else {
        self.start();
      }
    });
  };

  GestureAssistant.prototype.setStatus = function (text, withSpeech) {
    if (this.statusEl) {
      this.statusEl.textContent = text;
    }
    if (withSpeech && this.autoSpeak) {
      speak(text);
    }
  };

  GestureAssistant.prototype.loadGestureGuide = async function () {
    if (!this.helpEl) {
      return;
    }

    try {
      var response = await fetch('/api/ai/gesture-help?context=' + encodeURIComponent(this.id));
      var data = await response.json();
      if (!response.ok || !data.ok || !Array.isArray(data.items) || data.items.length === 0) {
        this.helpEl.innerHTML = '<div style="opacity:.9;">Aide gestes indisponible.</div>';
        return;
      }

      var html = '<div style="font-weight:700;margin-bottom:6px;">Gestes disponibles</div>';
      for (var i = 0; i < data.items.length; i += 1) {
        var item = data.items[i] || {};
        var title = String(item.title || item.gesture || 'Geste');
        var desc = String(item.description || '');
        html += '<div style="margin-bottom:4px;"><strong>' + title + ':</strong> ' + desc + '</div>';
      }
      this.helpEl.innerHTML = html;
    } catch (_e) {
      this.helpEl.innerHTML = '<div style="opacity:.9;">Aide gestes indisponible.</div>';
    }
  };

  GestureAssistant.prototype.ensureDeps = function () {
    return typeof window.Hands !== 'undefined' && typeof window.Camera !== 'undefined';
  };

  GestureAssistant.prototype.loadFirstAvailable = async function (urls) {
    for (var i = 0; i < urls.length; i += 1) {
      try {
        await loadScript(urls[i]);
        return true;
      } catch (_e) {
        // try next url
      }
    }
    return false;
  };

  GestureAssistant.prototype.ensureDepsLoaded = async function () {
    if (this.ensureDeps()) {
      return true;
    }

    var cameraUrls = [
      'https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js',
      'https://unpkg.com/@mediapipe/camera_utils/camera_utils.js'
    ];

    var handsUrls = [
      'https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js',
      'https://unpkg.com/@mediapipe/hands/hands.js'
    ];

    if (typeof window.Camera === 'undefined') {
      await this.loadFirstAvailable(cameraUrls);
    }

    if (typeof window.Hands === 'undefined') {
      await this.loadFirstAvailable(handsUrls);
    }

    return this.ensureDeps();
  };

  GestureAssistant.prototype.start = async function () {
    this.renderUi();
    await this.loadGestureGuide();

    var depsLoaded = await this.ensureDepsLoaded();
    if (!depsLoaded) {
      this.setStatus('Moteur gestes non charge. Verifiez internet.', true);
      return;
    }

    this.panel.style.display = 'block';
    this.fab.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';

    try {
      var self = this;
      this.hands = new window.Hands({
        locateFile: function (file) {
          return 'https://cdn.jsdelivr.net/npm/@mediapipe/hands/' + file;
        }
      });

      this.hands.setOptions({
        maxNumHands: 2,
        modelComplexity: 1,
        minDetectionConfidence: 0.7,
        minTrackingConfidence: 0.6
      });

      this.hands.onResults(function (results) {
        self.onResults(results);
      });

      this.camera = new window.Camera(this.video, {
        onFrame: async function () {
          if (self.hands && self.video) {
            await self.hands.send({ image: self.video });
          }
        },
        width: 640,
        height: 480
      });

      await this.camera.start();
      this.isRunning = true;
      this.setStatus('Camera active. Faites un geste.', true);
    } catch (_err) {
      this.setStatus('Impossible de demarrer la camera gestes.', true);
      this.stop();
    }
  };

  GestureAssistant.prototype.stop = function () {
    this.isRunning = false;
    this.lastGesture = '';
    this.stableFrames = 0;

    try {
      if (this.video && this.video.srcObject) {
        var tracks = this.video.srcObject.getTracks();
        tracks.forEach(function (t) { t.stop(); });
        this.video.srcObject = null;
      }
    } catch (_e) {
      // ignore
    }

    this.camera = null;
    this.hands = null;

    if (this.panel) {
      this.panel.style.display = 'none';
    }
    if (this.fab) {
      this.fab.style.background = 'linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%)';
    }
    this.setStatus('Assistant gestes arrete.', false);
  };

  GestureAssistant.prototype.onResults = function (results) {
    if (!this.isRunning) {
      return;
    }

    var allLandmarks = results && results.multiHandLandmarks;
    if (!Array.isArray(allLandmarks) || allLandmarks.length === 0) {
      this.lastGesture = '';
      this.stableFrames = 0;
      return;
    }

    var gesture = null;
    var handednessList = Array.isArray(results.multiHandedness) ? results.multiHandedness : [];
    var perHandGestures = [];

    for (var i = 0; i < allLandmarks.length; i += 1) {
      var handLandmarks = allLandmarks[i];
      var handLabel = (handednessList[i] && handednessList[i].label) || 'Right';
      var handGesture = this.classify(handLandmarks, normalizeText(handLabel));
      if (handGesture) {
        perHandGestures.push(handGesture);
      }
    }

    if (perHandGestures.length >= 2 && perHandGestures[0] === 'open_palm' && perHandGestures[1] === 'open_palm') {
      gesture = 'double_open_palm';
    } else if (perHandGestures.length > 0) {
      gesture = perHandGestures[0];
    }

    if (!gesture) {
      this.lastGesture = '';
      this.stableFrames = 0;
      return;
    }

    if (gesture === this.lastGesture) {
      this.stableFrames += 1;
    } else {
      this.lastGesture = gesture;
      this.stableFrames = 1;
    }

    this.setStatus('Geste detecte: ' + this.gestureLabel(gesture), false);

    if (this.stableFrames < this.minStableFrames) {
      return;
    }

    var now = Date.now();
    if (now - this.lastActionAt < this.cooldownMs) {
      return;
    }

    this.lastActionAt = now;
    var message = this.onGesture(gesture);
    if (typeof message === 'string' && message !== '') {
      this.setStatus(message, true);
    }
  };

  GestureAssistant.prototype.classify = function (lm, handedness) {
    var finger = function (tip, pip) {
      return lm[tip].y < lm[pip].y;
    };

    var indexUp = finger(8, 6);
    var middleUp = finger(12, 10);
    var ringUp = finger(16, 14);
    var pinkyUp = finger(20, 18);

    var thumbUp = lm[4].y < lm[2].y;
    var thumbDown = lm[4].y > lm[2].y + 0.04;
    var thumbSide = handedness === 'right' ? (lm[4].x < lm[3].x) : (lm[4].x > lm[3].x);
    var thumbExtended = thumbUp || thumbSide;

    var count = 0;
    if (thumbExtended) count += 1;
    if (indexUp) count += 1;
    if (middleUp) count += 1;
    if (ringUp) count += 1;
    if (pinkyUp) count += 1;

    if (count === 0) return 'fist';
    if (count >= 5) return 'open_palm';
    if (count === 1) return 'one_finger';
    if (count === 2) return 'two_fingers';
    if (count === 3) return 'three_fingers';
    if (count === 4) return 'four_fingers';
    if (thumbExtended && !indexUp && !middleUp && !ringUp && !pinkyUp) {
      if (thumbDown) return 'thumb_down';
      return 'thumb_up';
    }

    return null;
  };

  GestureAssistant.prototype.gestureLabel = function (gesture) {
    var map = {
      double_open_palm: '2 mains ouvertes',
      open_palm: 'Main ouverte',
      fist: 'Poing',
      one_finger: '1 doigt',
      two_fingers: '2 doigts',
      three_fingers: '3 doigts',
      four_fingers: '4 doigts',
      five_fingers: '5 doigts',
      thumb_up: 'Pouce haut',
      thumb_down: 'Pouce bas'
    };
    return map[gesture] || gesture;
  };

  global.GestureAssistant = {
    create: function (config) {
      var instance = new GestureAssistant(config || {});
      instance.renderUi();
      return instance;
    }
  };
})(window);
