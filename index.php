<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Live Webcam + Deteksi Wajah</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 30px;
      background-color: #f2f2f2;
    }
    .wrapper {
      position: relative;
      display: inline-block;
    }
    #webcam, #overlay {
      width: 100%;
      max-width: 640px;
      border-radius: 10px;
    }
    #webcam {
      border: 4px solid #4CAF50;
      box-shadow: 0px 0px 10px rgba(0,0,0,0.3);
    }
    #overlay {
      position: absolute;
      top: 0;
      left: 0;
    }
    .controls {
      margin-top: 20px;
    }
    button {
      margin: 5px;
      padding: 10px 20px;
      font-size: 16px;
    }
    img {
      margin-top: 10px;
      border: 2px solid #333;
      max-width: 300px;
    }
  </style>
</head>
<body>

  <h2>Live Webcam + Deteksi Wajah</h2>
  <div class="wrapper">
    <video id="webcam" autoplay muted playsinline></video>
    <canvas id="overlay"></canvas>
  </div>

  <div class="controls">
    <button id="captureBtn" disabled>📸 Capture Gambar</button>
    <button id="startRecBtn" disabled>⏺️ Mulai Rekam</button>
    <button id="stopRecBtn" disabled>⏹️ Stop Rekam</button>
    <a id="downloadLink" style="display:none;">📥 Download Rekaman</a>
  </div>

  <div id="screenshotResult"></div>

  <!-- Face API -->
  <script src="face-api.min.js" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      const video = document.getElementById('webcam');
      const canvas = document.getElementById('overlay');
      const ctx = canvas.getContext('2d');

      const captureBtn = document.getElementById('captureBtn');
      const startRecBtn = document.getElementById('startRecBtn');
      const stopRecBtn = document.getElementById('stopRecBtn');
      const downloadLink = document.getElementById('downloadLink');
      const screenshotResult = document.getElementById('screenshotResult');

      let mediaRecorder;
      let recordedChunks = [];

      async function startWebcam() {
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ video: true });
          video.srcObject = stream;
          console.log('Webcam stream started');
          captureBtn.disabled = false;
          startRecBtn.disabled = false;
        } catch (error) {
          console.error('Error accessing webcam:', error);
          alert('Tidak dapat mengakses webcam. Pastikan Anda memberikan izin.');
        }
      }

      async function loadModels() {
        try {
          console.log('Loading face detection models...');
          await faceapi.nets.tinyFaceDetector.loadFromUri('./models');
          console.log('Face detection models loaded successfully.');
        } catch (error) {
          console.error('Error loading face detection models:', error);
          alert('Gagal memuat model deteksi wajah. Pastikan folder /models berisi file yang benar.');
        }
      }

      async function startFaceDetection() {
        video.addEventListener('play', () => {
          const updateCanvasSize = () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
          };

          updateCanvasSize();

          setInterval(async () => {
            try {
              const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions());
              const displaySize = { width: video.videoWidth, height: video.videoHeight };
              faceapi.matchDimensions(canvas, displaySize);
              const resizedDetections = faceapi.resizeResults(detections, displaySize);

              ctx.clearRect(0, 0, canvas.width, canvas.height);
              faceapi.draw.drawDetections(canvas, resizedDetections);
            } catch (error) {
              console.error('Error during face detection:', error);
            }
          }, 100);
        });
      }

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

      window.onload = async () => {
        await startWebcam();
        await loadModels();
        await startFaceDetection();
      };
    });
  </script>
</body>
</html>