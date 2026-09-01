document.addEventListener('DOMContentLoaded', () => {

    // ---------- بخش استوری‌ها ----------
    try {
      const stories = window.STORIES || [];
      const seen = JSON.parse(localStorage.getItem('seenStories') || '[]');
  
      document.querySelectorAll('.story-circle').forEach(el => {
        const idx = +el.dataset.index;
        if (seen.includes(stories[idx]?.id)) el.classList.add('seen');
        el.addEventListener('click', () => openViewer(idx));
      });
  
      const viewer = document.getElementById('story-viewer');
      const progressBar = document.getElementById('story-progress-bar');
      const content = document.getElementById('story-content');
      const likeBtn = document.getElementById('like-btn');
      const likeCount = document.getElementById('like-count');
      const viewNum = document.getElementById('view-num');
      const heartBurst = document.getElementById('heart-burst');
      const viewerTime = document.getElementById('viewer-time');
  
      let current = 0, timer = null;
      const DURATION = 5000;
  
      function buildSegments() {
        progressBar.innerHTML = stories.map(() => '<div class="seg"><div class="fill"></div></div>').join('');
      }
      function timeAgo(ts) {
        const diff = Math.floor(Date.now()/1000) - ts;
        if (diff < 60) return 'همین الان';
        if (diff < 3600) return Math.floor(diff/60) + ' دقیقه پیش';
        if (diff < 86400) return Math.floor(diff/3600) + ' ساعت پیش';
        return Math.floor(diff/86400) + ' روز پیش';
      }
      function openViewer(index) {
        if (!stories.length) return;
        current = index;
        buildSegments();
        viewer.classList.add('active');
        document.body.style.overflow = 'hidden';
        loadStory();
      }
      function closeViewer() {
        viewer.classList.remove('active');
        document.body.style.overflow = '';
        clearTimeout(timer);
        content.innerHTML = '';
      }
      function markSeen(id) {
        const arr = JSON.parse(localStorage.getItem('seenStories') || '[]');
        if (!arr.includes(id)) {
          arr.push(id);
          localStorage.setItem('seenStories', JSON.stringify(arr));
          document.querySelector(`.story-circle[data-index="${stories.findIndex(s=>s.id===id)}"]`)?.classList.add('seen');
        }
      }
      async function loadStory() {
        const story = stories[current];
        if (!story) { closeViewer(); return; }
        content.innerHTML = '';
        viewerTime.textContent = timeAgo(story.time);
        let el;
        if (story.type === 'video') {
          el = document.createElement('video');
          el.src = story.url; el.autoplay = true; el.playsInline = true;
        } else {
          el = document.createElement('img');
          el.src = story.url;
        }
        content.appendChild(el);
        likeCount.textContent = story.likes;
        viewNum.textContent = story.views;
        likeBtn.classList.remove('liked');
        likeBtn.querySelector('i').className = 'fa-regular fa-heart';
        markSeen(story.id);
        track('view', story.id).then(res => { if (res) viewNum.textContent = res.views; });
        resetSegments();
        runSegment(current);
      }
      function resetSegments() {
        document.querySelectorAll('.seg .fill').forEach((f, i) => {
          f.style.transition = 'none';
          f.style.width = i < current ? '100%' : '0%';
        });
      }
      function runSegment(idx) {
        clearTimeout(timer);
        const fill = document.querySelectorAll('.seg .fill')[idx];
        if (!fill) return;
        requestAnimationFrame(() => {
          fill.style.transition = `width ${DURATION}ms linear`;
          fill.style.width = '100%';
        });
        timer = setTimeout(next, DURATION);
      }
      function next() { current < stories.length - 1 ? (current++, loadStory()) : closeViewer(); }
      function prev() { current > 0 ? (current--, loadStory()) : resetSegments(); }
  
      document.getElementById('nav-next')?.addEventListener('click', next);
      document.getElementById('nav-prev')?.addEventListener('click', prev);
      document.getElementById('close-viewer')?.addEventListener('click', closeViewer);
  
      let touchX = 0;
      viewer?.addEventListener('touchstart', e => touchX = e.touches[0].clientX);
      viewer?.addEventListener('touchend', e => {
        const diff = e.changedTouches[0].clientX - touchX;
        if (diff > 60) prev(); else if (diff < -60) next();
      });
      document.addEventListener('keydown', e => {
        if (!viewer?.classList.contains('active')) return;
        if (e.key === 'ArrowLeft') next();
        if (e.key === 'ArrowRight') prev();
        if (e.key === 'Escape') closeViewer();
      });
      likeBtn?.addEventListener('click', async () => {
        const story = stories[current];
        const res = await track('like', story.id);
        if (res) {
          likeCount.textContent = res.likes;
          if (res.liked) {
            likeBtn.classList.add('liked');
            likeBtn.querySelector('i').className = 'fa-solid fa-heart';
            spawnHeart();
          } else {
            likeBtn.classList.remove('liked');
            likeBtn.querySelector('i').className = 'fa-regular fa-heart';
          }
        }
      });
      function spawnHeart() {
        const h = document.createElement('i');
        h.className = 'fa-solid fa-heart heart-pop';
        h.style.left = (40 + Math.random()*20) + '%';
        h.style.bottom = '80px';
        heartBurst.appendChild(h);
        setTimeout(() => h.remove(), 800);
      }
      async function track(action, id) {
        try {
          const res = await fetch('api/track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, id })
          });
          return await res.json();
        } catch (e) { return null; }
      }
    } catch (err) {
      console.error('خطا در بخش استوری‌ها:', err);
    }
  
    // ---------- بخش پلیر آهنگ ----------
    try {
      const audio = document.getElementById('audio-player');
      const playBtn = document.getElementById('play-btn');
      const vinyl = document.getElementById('vinyl');
      const fill = document.getElementById('progress-fill');
  
      if (audio && playBtn) {
        playBtn.addEventListener('click', () => {
          if (audio.paused) {
            audio.play().then(() => {
              vinyl?.classList.add('playing');
              playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            }).catch(err => {
              console.error('پخش آهنگ با خطا مواجه شد:', err);
              alert('فایل آهنگ پیدا نشد یا فرمتش قابل پخش نیست. مسیر assets/audio/song.mp3 رو چک کن.');
            });
          } else {
            audio.pause();
            vinyl?.classList.remove('playing');
            playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
          }
        });
        audio.addEventListener('timeupdate', () => {
          if (audio.duration && fill) fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
        });
        audio.addEventListener('ended', () => {
          vinyl?.classList.remove('playing');
          playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
        });
        audio.addEventListener('error', () => {
          console.error('فایل صوتی لود نشد. مسیر src:', audio.src);
        });
      } else {
        console.warn('المنت پلیر آهنگ در صفحه پیدا نشد.');
      }
    } catch (err) {
      console.error('خطا در بخش پلیر آهنگ:', err);
    }
  
    // ---------- بخش تغییر تم (شب/روز) ----------
    try {
      const themeBtn = document.getElementById('theme-toggle');
      if (themeBtn) {
        const saved = localStorage.getItem('theme');
        if (saved === 'light') {
          document.body.classList.add('light-mode');
          themeBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
        }
        themeBtn.addEventListener('click', () => {
          document.body.classList.toggle('light-mode');
          const isLight = document.body.classList.contains('light-mode');
          localStorage.setItem('theme', isLight ? 'light' : 'dark');
          themeBtn.innerHTML = isLight ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
        });
      }
    } catch (err) {
      console.error('خطا در بخش تغییر تم:', err);
    }
  
    // ---------- بخش پارتیکل پس‌زمینه ----------
    try {
      const canvas = document.getElementById('bg-canvas');
      if (canvas) {
        const ctx = canvas.getContext('2d');
        function resize() { canvas.width = innerWidth; canvas.height = innerHeight; }
        resize();
        window.addEventListener('resize', resize);
        const particles = Array.from({length: 40}, () => ({
          x: Math.random()*innerWidth, y: Math.random()*innerHeight,
          r: Math.random()*2 + 1, dx: (Math.random()-0.5)*0.3, dy: (Math.random()-0.5)*0.3,
        }));
        function animate() {
          ctx.clearRect(0,0,canvas.width,canvas.height);
          particles.forEach(p => {
            p.x += p.dx; p.y += p.dy;
            if (p.x < 0 || p.x > canvas.width) p.dx *= -1;
            if (p.y < 0 || p.y > canvas.height) p.dy *= -1;
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
            ctx.fillStyle = 'rgba(255,255,255,0.25)'; ctx.fill();
          });
          requestAnimationFrame(animate);
        }
        animate();
      }
    } catch (err) {
      console.error('خطا در بخش پارتیکل:', err);
    }
  });
  