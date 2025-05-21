<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Live Webcam + Deteksi Wajah & Kendaraan</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>

  <h2>Live Webcam + Deteksi Wajah & Kendaraan</h2>
  <div class="wrapper">
    <video id="webcam" autoplay muted playsinline></video>
    <canvas id="overlay"></canvas>
  </div>

  <div class="controls">
    <button id="captureBtn">📸 Capture Gambar</button>
    <button id="startRecBtn">⏺️ Mulai Rekam</button>
    <button id="stopRecBtn" disabled>⏹️ Stop Rekam</button>
    <a id="downloadLink" style="display:none;">📥 Download Rekaman</a>
    <br>
    <label>Pilih Model YOLO: </label>
    <button class="yolo-btn" data-model="yolov8.onnx">YOLOv8</button>
    <button class="yolo-btn" data-model="yolov8n.onnx">YOLOv8n</button>
  </div>

  <div id="screenshotResult"></div>

  <!-- Face API -->
  <script src="face-api.min.js"></script>
  <!-- ONNX Runtime Web -->
  <script src="https://cdn.jsdelivr.net/npm/onnxruntime-web/dist/ort.min.js"></script>
  <script>
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('overlay');
    const ctx = canvas.getContext('2d');

    const captureBtn = document.getElementById('captureBtn');
    const startRecBtn = document.getElementById('startRecBtn');
    const stopRecBtn = document.getElementById('stopRecBtn');
    const downloadLink = document.getElementById('downloadLink');
    const screenshotResult = document.getElementById('screenshotResult');
    const yoloBtns = document.querySelectorAll('.yolo-btn');

    let mediaRecorder;
    let recordedChunks = [];
    let yoloSession = null;
    let yoloModelName = 'yolov8n.onnx'; // default
    let vehicleInterval = null;

    async function startWebcam() {
      const stream = await navigator.mediaDevices.getUserMedia({ video: true });
      video.srcObject = stream;

      mediaRecorder = new MediaRecorder(stream, { mimeType: "video/webm" });
      mediaRecorder.ondataavailable = e => recordedChunks.push(e.data);
      mediaRecorder.onstop = () => {
        const blob = new Blob(recordedChunks, { type: "video/webm" });
        const url = URL.createObjectURL(blob);
        downloadLink.href = url;
        downloadLink.download = "rekaman.webm";
        downloadLink.style.display = "inline";
      };
    }

    // --- FACE DETECTION ---
    async function startFaceDetection() {
      await faceapi.nets.tinyFaceDetector.loadFromUri('./models');
      video.addEventListener('play', () => {
        const updateCanvasSize = () => {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
        };
        updateCanvasSize();
        setInterval(async () => {
          const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions());
          const displaySize = { width: video.videoWidth, height: video.videoHeight };
          faceapi.matchDimensions(canvas, displaySize);
          const resizedDetections = faceapi.resizeResults(detections, displaySize);
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          faceapi.draw.drawDetections(canvas, resizedDetections);
        }, 100);
      });
    }

    // --- VEHICLE DETECTION (YOLOv8) ---
    const vehicleClassIds = [2, 3, 5, 7]; // car, motorcycle, bus, truck
    const classNames = [
      "person", "bicycle", "car", "motorcycle", "airplane", "bus", "train", "truck",
      // ... tambahkan sesuai model COCO jika perlu
    ];

    async function loadYoloModel(modelName) {
      return await ort.InferenceSession.create('./' + modelName);
    }

    async function detectVehicles(session) {
      if (video.videoWidth === 0 || video.videoHeight === 0) return;

      // Ambil frame dari video
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = 640;
      tempCanvas.height = 640;
      tempCanvas.getContext('2d').drawImage(video, 0, 0, 640, 640);
      const imageData = tempCanvas.getContext('2d').getImageData(0, 0, 640, 640);

      // Preprocessing: ubah imageData ke tensor float32 [1,3,640,640]
      const input = new Float32Array(1 * 3 * 640 * 640);
      for (let i = 0; i < 640 * 640; i++) {
        input[i] = imageData.data[i * 4] / 255.0;         // R
        input[i + 640 * 640] = imageData.data[i * 4 + 1] / 255.0; // G
        input[i + 2 * 640 * 640] = imageData.data[i * 4 + 2] / 255.0; // B
      }
      const tensor = new ort.Tensor('float32', input, [1, 3, 640, 640]);

      // Inference
      const feeds = { images: tensor };
      const results = await session.run(feeds);
      const output = results[Object.keys(results)[0]].data;

      // Postprocessing sederhana: ambil box kendaraan
      for (let i = 0; i < output.length; i += 6) {
        const [x, y, w, h, score, classId] = output.slice(i, i + 6);
        if (score > 0.5 && vehicleClassIds.includes(classId)) {
          ctx.strokeStyle = 'blue';
          ctx.lineWidth = 2;
          ctx.strokeRect(
            (x - w / 2) * video.videoWidth / 640,
            (y - h / 2) * video.videoHeight / 640,
            w * video.videoWidth / 640,
            h * video.videoHeight / 640
          );
          ctx.fillStyle = 'blue';
          ctx.font = '14px Arial';
          ctx.fillText(
            classNames[classId] + ' (' + (score * 100).toFixed(1) + '%)',
            (x - w / 2) * video.videoWidth / 640,
            (y - h / 2) * video.videoHeight / 640 - 5
          );
        }
      }
    }

    // --- BUTTON EVENTS ---
    captureBtn.addEventListener('click', () => {
      const tempCanvas = document.createElement('canvas');
      tempCanvas.width = video.videoWidth;
      tempCanvas.height = video.videoHeight;
      tempCanvas.getContext('2d').drawImage(video, 0, 0);
      const img = document.createElement('img');
      img.src = tempCanvas.toDataURL('image/png');
      screenshotResult.innerHTML = "";
      screenshotResult.appendChild(img);
    });

    startRecBtn.addEventListener('click', () => {
      recordedChunks = [];
      mediaRecorder.start();
      startRecBtn.disabled = true;
      stopRecBtn.disabled = false;
      downloadLink.style.display = 'none';
    });

    stopRecBtn.addEventListener('click', () => {
      mediaRecorder.stop();
      startRecBtn.disabled = false;
      stopRecBtn.disabled = true;
    });

    // --- YOLO MODEL SWITCH ---
    async function switchYoloModel(modelName) {
      yoloModelName = modelName;
      if (vehicleInterval) clearInterval(vehicleInterval);
      yoloSession = await loadYoloModel(yoloModelName);
      vehicleInterval = setInterval(() => detectVehicles(yoloSession), 500);
    }

    yoloBtns.forEach(btn => {
      btn.addEventListener('click', async () => {
        yoloBtns.forEach(b => b.disabled = false);
        btn.disabled = true;
        await switchYoloModel(btn.dataset.model);
      });
    });

    // --- MAIN ---
    window.onload = async () => {
      await startWebcam();
      await startFaceDetection();
      await switchYoloModel(yoloModelName);
      // Set tombol default disable
      yoloBtns.forEach(btn => {
        btn.disabled = (btn.dataset.model === yoloModelName);
      });
    };
  </script>
</body>

</html>
<?php
exit();