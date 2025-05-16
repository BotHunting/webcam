<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>YOLOv8 Object Detection</title>
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
  </style>
</head>
<body>

  <h2>YOLOv8 Object Detection</h2>
  <div class="wrapper">
    <video id="webcam" autoplay muted playsinline></video>
    <canvas id="overlay"></canvas>
  </div>

  <div class="controls">
    <button id="backBtn">🔙 Kembali ke Deteksi Wajah</button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/onnxruntime-web/dist/ort.min.js"></script>
  <script>
    const video = document.getElementById('webcam');
    const canvas = document.getElementById('overlay');
    const ctx = canvas.getContext('2d');
    const backBtn = document.getElementById('backBtn');

    let session;

    async function startWebcam() {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        video.addEventListener('loadeddata', () => {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
        });
        console.log('Webcam started.');
      } catch (error) {
        console.error('Error accessing webcam:', error);
        alert('Tidak dapat mengakses webcam. Pastikan Anda memberikan izin.');
      }
    }

    async function loadModel() {
      try {
        session = await ort.InferenceSession.create('./yolov8.onnx');
        console.log('YOLOv8 model loaded.');
      } catch (error) {
        console.error('Error loading YOLOv8 model:', error);
        alert('Gagal memuat model YOLOv8. Pastikan file model tersedia.');
      }
    }

    function preprocess(video) {
      const inputCanvas = document.createElement('canvas');
      inputCanvas.width = 640; // Ukuran input YOLOv8
      inputCanvas.height = 640;
      const inputCtx = inputCanvas.getContext('2d');
      inputCtx.drawImage(video, 0, 0, inputCanvas.width, inputCanvas.height);

      const imageData = inputCtx.getImageData(0, 0, inputCanvas.width, inputCanvas.height);
      const data = Float32Array.from(imageData.data).filter((_, i) => i % 4 !== 3); // Hapus channel alpha
      return new ort.Tensor('float32', data, [1, 3, 640, 640]); // Format tensor [batch, channel, height, width]
    }

    function postprocess(output) {
      const boxes = output[0].data; // Bounding box data
      const scores = output[1].data; // Confidence scores
      const classes = output[2].data; // Class indices

      const results = [];
      for (let i = 0; i < scores.length; i++) {
        if (scores[i] > 0.5) { // Confidence threshold
          results.push({
            x: boxes[i * 4],
            y: boxes[i * 4 + 1],
            width: boxes[i * 4 + 2] - boxes[i * 4],
            height: boxes[i * 4 + 3] - boxes[i * 4 + 1],
            score: scores[i],
            class: classes[i]
          });
        }
      }
      return results;
    }

    function drawBoxes(boxes) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      boxes.forEach(box => {
        ctx.strokeStyle = 'red';
        ctx.lineWidth = 2;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
        ctx.fillStyle = 'red';
        ctx.fillText(`Class: ${box.class}, Score: ${box.score.toFixed(2)}`, box.x, box.y - 5);
      });
    }

    async function detectObjects() {
      if (!session) return;

      const inputTensor = preprocess(video);
      const output = await session.run({ input: inputTensor });
      const boxes = postprocess(output);
      drawBoxes(boxes);
    }

    async function main() {
      await startWebcam();
      await loadModel();
      setInterval(detectObjects, 100); // Jalankan deteksi setiap 100ms
    }

    backBtn.addEventListener('click', () => {
      window.location.href = 'index.php'; // Kembali ke halaman deteksi wajah
    });

    main();
  </script>
</body>
</html>