(function () {
  'use strict';

  function initHeroModel() {
    var container = document.getElementById('three-pose-container');
    if (!container || !window.THREE) {
      return;
    }

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var THREE = window.THREE;
    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.set(3.1, 2.0, 4.3);

    var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    container.appendChild(renderer.domElement);

    var controls = window.THREE.OrbitControls ? new window.THREE.OrbitControls(camera, renderer.domElement) : null;
    if (controls) {
      controls.enableDamping = true;
      controls.enablePan = false;
      controls.enableZoom = false;
      controls.autoRotate = !reduceMotion;
      controls.autoRotateSpeed = 0.8;
      controls.target.set(0, 0.95, 0);
    }

    scene.add(new THREE.HemisphereLight(0xffffff, 0xded8ff, 1.7));

    var keyLight = new THREE.DirectionalLight(0xffffff, 1.15);
    keyLight.position.set(2.5, 4, 3);
    scene.add(keyLight);

    var primary = 0x7c3aed;
    var high = 0xdc2626;
    var medium = 0xd97706;
    var low = 0x16a34a;
    var bone = 0x5b21b6;

    var materials = {
      safe: new THREE.MeshStandardMaterial({ color: low, roughness: 0.45, metalness: 0.08 }),
      medium: new THREE.MeshStandardMaterial({ color: medium, roughness: 0.42, metalness: 0.08 }),
      high: new THREE.MeshStandardMaterial({ color: high, roughness: 0.38, metalness: 0.08 }),
      primary: new THREE.MeshStandardMaterial({ color: primary, roughness: 0.35, metalness: 0.12 }),
      box: new THREE.MeshStandardMaterial({ color: 0xf8b84e, roughness: 0.6, metalness: 0.04 }),
      floor: new THREE.MeshStandardMaterial({ color: 0xede9fe, transparent: true, opacity: 0.34 })
    };

    var group = new THREE.Group();
    scene.add(group);

    var floor = new THREE.Mesh(new THREE.CircleGeometry(2.05, 72), materials.floor);
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -0.02;
    group.add(floor);

    var grid = new THREE.GridHelper(4.4, 16, 0xc4b5fd, 0xe9d5ff);
    grid.position.y = 0.005;
    group.add(grid);

    var box = new THREE.Mesh(new THREE.BoxGeometry(0.72, 0.46, 0.52), materials.box);
    box.position.set(0, 0.34, 0.86);
    box.rotation.x = -0.12;
    group.add(box);

    var joints = {};
    var beforePositions = {
      head: [0, 1.95, 0.38],
      neck: [0, 1.68, 0.22],
      chest: [0, 1.42, 0.06],
      pelvis: [0, 0.9, -0.28],
      lumbar: [0, 1.12, -0.18],
      shoulderL: [-0.38, 1.52, 0.12],
      shoulderR: [0.38, 1.52, 0.12],
      elbowL: [-0.48, 1.03, 0.42],
      elbowR: [0.48, 1.03, 0.42],
      wristL: [-0.28, 0.67, 0.8],
      wristR: [0.28, 0.67, 0.8],
      hipL: [-0.28, 0.86, -0.24],
      hipR: [0.28, 0.86, -0.24],
      kneeL: [-0.36, 0.42, 0.18],
      kneeR: [0.36, 0.42, 0.18],
      ankleL: [-0.38, 0.06, -0.16],
      ankleR: [0.38, 0.06, -0.16]
    };

    var afterPositions = {
      head: [0, 1.96, 0.02],
      neck: [0, 1.7, 0.01],
      chest: [0, 1.46, 0],
      pelvis: [0, 0.94, -0.04],
      lumbar: [0, 1.18, -0.02],
      shoulderL: [-0.38, 1.54, 0],
      shoulderR: [0.38, 1.54, 0],
      elbowL: [-0.42, 1.18, 0.28],
      elbowR: [0.42, 1.18, 0.28],
      wristL: [-0.24, 0.92, 0.56],
      wristR: [0.24, 0.92, 0.56],
      hipL: [-0.28, 0.9, -0.04],
      hipR: [0.28, 0.9, -0.04],
      kneeL: [-0.34, 0.48, 0.08],
      kneeR: [0.34, 0.48, 0.08],
      ankleL: [-0.38, 0.06, -0.1],
      ankleR: [0.38, 0.06, -0.1]
    };

    function makeJoint(name, material, size) {
      var position = beforePositions[name];
      var mesh = new THREE.Mesh(new THREE.SphereGeometry(size || 0.065, 24, 24), material);
      mesh.position.set(position[0], position[1], position[2]);
      joints[name] = mesh;
      group.add(mesh);
      return mesh;
    }

    Object.keys(beforePositions).forEach(function (name) {
      var material = materials.safe;
      var size = 0.065;
      if (name === 'lumbar') {
        material = materials.high;
        size = 0.11;
      } else if (name === 'chest' || name === 'shoulderL' || name === 'shoulderR') {
        material = materials.medium;
        size = 0.078;
      } else if (name === 'head') {
        material = materials.primary;
        size = 0.13;
      }
      makeJoint(name, material, size);
    });

    var boneMaterial = new THREE.LineBasicMaterial({ color: bone, transparent: true, opacity: 0.9 });
    var highBoneMaterial = new THREE.LineBasicMaterial({ color: high, transparent: true, opacity: 0.95 });
    var bones = [
      ['head', 'neck'], ['neck', 'chest'], ['chest', 'lumbar'], ['lumbar', 'pelvis'],
      ['chest', 'shoulderL'], ['chest', 'shoulderR'], ['shoulderL', 'elbowL'], ['shoulderR', 'elbowR'],
      ['elbowL', 'wristL'], ['elbowR', 'wristR'], ['pelvis', 'hipL'], ['pelvis', 'hipR'],
      ['hipL', 'kneeL'], ['hipR', 'kneeR'], ['kneeL', 'ankleL'], ['kneeR', 'ankleR']
    ];

    var boneLines = [];
    bones.forEach(function (pair) {
      var geometry = new THREE.BufferGeometry().setFromPoints([joints[pair[0]].position, joints[pair[1]].position]);
      var line = new THREE.Line(geometry, pair[0] === 'chest' || pair[0] === 'lumbar' ? highBoneMaterial : boneMaterial);
      line.userData.pair = pair;
      boneLines.push(line);
      group.add(line);
    });

    var ring = new THREE.Mesh(
      new THREE.TorusGeometry(0.2, 0.012, 12, 48),
      new THREE.MeshBasicMaterial({ color: high, transparent: true, opacity: 0.75 })
    );
    ring.position.copy(joints.lumbar.position);
    ring.rotation.x = Math.PI / 2.6;
    group.add(ring);

    var clock = new THREE.Clock();
    var stateLabel = document.getElementById('hero-state-label');
    var stateLabelWrap = stateLabel ? stateLabel.closest('.hero-state-label') : null;
    var scoreLabel = document.getElementById('hero-score-label');
    var stateNote = document.getElementById('hero-state-note');
    var currentState = '';

    function setHeroState(isAfter) {
      var nextState = isAfter ? 'after' : 'before';
      if (currentState === nextState) {
        return;
      }
      currentState = nextState;
      if (stateLabel) {
        stateLabel.textContent = isAfter
          ? (stateLabelWrap ? stateLabelWrap.getAttribute('data-after-label') : 'After adjustment')
          : (stateLabelWrap ? stateLabelWrap.getAttribute('data-before-label') : 'Before intervention');
      }
      if (scoreLabel) {
        scoreLabel.textContent = isAfter ? 'REBA score: 3.4' : 'REBA score: 8';
      }
      if (stateNote) {
        stateNote.textContent = isAfter ? 'Reviewer confirmation required' : 'High risk — investigation and change needed soon';
      }
    }

    function interpolatePosition(name, amount) {
      var before = beforePositions[name];
      var after = afterPositions[name];
      joints[name].position.set(
        before[0] + (after[0] - before[0]) * amount,
        before[1] + (after[1] - before[1]) * amount,
        before[2] + (after[2] - before[2]) * amount
      );
    }

    function updateBoneLines() {
      boneLines.forEach(function (line) {
        var pair = line.userData.pair;
        var positions = line.geometry.attributes.position.array;
        positions[0] = joints[pair[0]].position.x;
        positions[1] = joints[pair[0]].position.y;
        positions[2] = joints[pair[0]].position.z;
        positions[3] = joints[pair[1]].position.x;
        positions[4] = joints[pair[1]].position.y;
        positions[5] = joints[pair[1]].position.z;
        line.geometry.attributes.position.needsUpdate = true;
      });
    }

    function resize() {
      var rect = container.getBoundingClientRect();
      var width = Math.max(1, rect.width);
      var height = Math.max(1, rect.height);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height, false);
    }

    function animate() {
      var elapsed = clock.getElapsedTime();
      var cycle = reduceMotion ? 0 : (Math.sin(elapsed * 0.55) + 1) / 2;
      var smoothCycle = cycle * cycle * (3 - 2 * cycle);
      var isAfter = smoothCycle > 0.55;
      Object.keys(joints).forEach(function (name) {
        interpolatePosition(name, smoothCycle);
      });
      updateBoneLines();
      ring.position.copy(joints.lumbar.position);
      box.position.y = 0.34 + smoothCycle * 0.24;
      box.position.z = 0.86 - smoothCycle * 0.18;
      materials.high.color.setHex(isAfter ? low : high);
      highBoneMaterial.color.setHex(isAfter ? low : high);
      materials.medium.color.setHex(isAfter ? low : medium);
      setHeroState(isAfter);
      if (!reduceMotion) {
        group.rotation.y = Math.sin(elapsed * 0.35) * 0.18;
        joints.lumbar.scale.setScalar(1 + Math.sin(elapsed * 3) * 0.08);
        ring.scale.setScalar(1 + Math.sin(elapsed * 2.4) * 0.14);
        ring.material.opacity = 0.55 + Math.sin(elapsed * 2.4) * 0.2;
      }
      if (controls) {
        controls.update();
      }
      renderer.render(scene, camera);
      window.requestAnimationFrame(animate);
    }

    resize();
    window.addEventListener('resize', resize);
    animate();
  }

  function initFeedbackForm() {
    var form = document.getElementById('feedbackForm');
    if (!form) {
      return;
    }

    var button = document.getElementById('feedbackSubmitBtn');
    var text = document.getElementById('feedbackBtnText');
    var spinner = document.getElementById('feedbackBtnSpinner');
    var alertBox = document.getElementById('feedbackAlert');

    function setLoading(isLoading) {
      if (button) {
        button.disabled = isLoading;
      }
      if (text) {
        text.classList.toggle('d-none', isLoading);
      }
      if (spinner) {
        spinner.classList.toggle('d-none', !isLoading);
      }
    }

    function showAlert(type, message) {
      if (!alertBox) {
        return;
      }
      alertBox.className = 'mb-3 alert alert-' + type + ' py-2 small';
      alertBox.textContent = message;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      setLoading(true);

      if (alertBox) {
        alertBox.className = 'd-none mb-3';
        alertBox.textContent = '';
      }

      fetch('/api/v1/feedback', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: document.getElementById('feedbackName').value.trim() || null,
          email: document.getElementById('feedbackEmail').value.trim() || null,
          type: document.getElementById('feedbackType').value,
          message: document.getElementById('feedbackMessage').value.trim()
        })
      })
        .then(function (response) {
          if (response.ok) {
            return response.json().catch(function () {
              return {};
            });
          }
          return response.json()
            .catch(function () {
              return {};
            })
            .then(function (payload) {
              throw new Error(payload.error || payload.message || 'Submission failed.');
            });
        })
        .then(function () {
          showAlert('success', 'Thank you. Your feedback was submitted successfully.');
          form.reset();
          window.setTimeout(function () {
            var modalElement = document.getElementById('feedbackFormModal');
            var modal = modalElement ? bootstrap.Modal.getInstance(modalElement) : null;
            if (modal) {
              modal.hide();
            }
          }, 1600);
        })
        .catch(function (error) {
          showAlert('danger', error.message || 'Something went wrong. Please try again.');
        })
        .finally(function () {
          setLoading(false);
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    try {
      initHeroModel();
    } catch (error) {
      var container = document.getElementById('three-pose-container');
      if (container) {
        container.innerHTML = '<div class="hero-3d-fallback"><i class="bi bi-person-video3"></i><span>3D posture preview unavailable</span></div>';
      }
    }
    initFeedbackForm();
  });
})();
