document.addEventListener('DOMContentLoaded', () => {

    const toastEl = document.getElementById('toast');
    function toast(msg, ms = 2200) {
      if (!toastEl) return;
      toastEl.textContent = msg;
      toastEl.classList.add('show');
      clearTimeout(toastEl._t);
      toastEl._t = setTimeout(() => toastEl.classList.remove('show'), ms);
    }
  
    // حذف لودر صفحه
    window.addEventListener('load', () => {
      document.getElementById('page-loader')?.classList.add('hide');
    });
    setTimeout(() => document.getElementById('page-loader')?.classList.add('hide'), 1200);
  
    // ثبت بازدید پروفایل
    fetch('api/visit.php').then(r => r.json()).then(d => {
      const el = document.getElementById('visits-num');
      if (el && d.total !== undefined) el.textContent = d.total;
    }).catch(() => {});
  
    // ---------- افکت اسپات‌لایت و تیلت آواتار ----------
    try {
      const spotlight = document.getElementById('spotlight');
      const avatarRing = document.getElementById('avatar-ring');
      document.addEventListener('mousemove', e => {
        if (spotlight) {
          spotlight.style.setProperty('--mx', e.clientX + 'px');
          spotlight.style.setProperty('--my', e.clientY + 'px');
        }
        if (avatarRing) {
          const r = avatarRing.getBoundingClientRect();
          const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
          const dx = (e.clientX - cx) / r.width, dy = (e.clientY - cy) / r.height;
          if (Math.abs(dx) < 4 && Math.abs(dy) < 4) {
            avatarRing.style.transform = `rotateY(${dx * 12}deg) rotateX(${-dy * 12}deg)`;
          }
        }
      });
      avatarRing?.addEventListener('mouseleave', () => { avatarRing.style.transform = ''; });
    } catch (e) {}
  
    // ---------- انیمیشن ظاهرشدن هنگام اسکرول ----------
    try {
      const revealEls = document.querySelectorAll('.reveal');
      const io2 = new IntersectionObserver(entries => {
        entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in-view'); io2.unobserve(en.target); } });
      }, { threshold: 0.12 });
      revealEls.forEach(el => io2.observe(el));
    } catch (e) {}
  
    // ---------- دکمهٔ اسکرول به بالا ----------
    try {
      const scrollBtn = document.getElementById('scroll-top-btn');
      window.addEventListener('scroll', () => {
        scrollBtn?.classList.toggle('show', window.scrollY > 400);
      });
      scrollBtn?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    } catch (e) {}
  
    // ---------- کپی یوزرنیم ----------
    try {
      document.getElementById('username-btn')?.addEventListener('click', async () => {
        await navigator.clipboard.writeText('@' + (window.PROFILE_USERNAME || ''));
        toast('یوزرنیم کپی شد');
      });
    } catch (e) {}
  
    // ---------- تب‌ها ----------
    try {
      const tabBtns = document.querySelectorAll('.tab-btn');
      tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          tabBtns.forEach(b => b.classList.remove('active'));
          document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
          btn.classList.add('active');
          document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
        });
      });
    } catch (e) {}
  
    // ---------- افزودن مخاطب (vCard) ----------
    try {
      document.getElementById('vcard-btn')?.addEventListener('click', () => {
        const name = window.PROFILE_NAME || 'مخاطب';
        const phone = window.PROFILE_PHONE || '';
        if (!phone) { toast('شماره تماس ثبت نشده'); return; }
        const vcard = `BEGIN:VCARD\nVERSION:3.0\nFN:${name}\nTEL;TYPE=CELL:${phone}\nEND:VCARD`;
        const blob = new Blob([vcard], { type: 'text/vcard' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = name + '.vcf';
        document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
        toast('فایل مخاطب دانلود شد');
      });
    } catch (e) {}
  
    // ---------- گالری + لایت‌باکس ----------
    try {
      const gallery = window.GALLERY || [];
      const lightbox = document.getElementById('lightbox-modal');
      const lbImg = document.getElementById('lightbox-img');
      let lbIndex = 0;
      function openLightbox(i) {
        lbIndex = i;
        lbImg.src = gallery[i].image;
        lightbox.classList.add('active');
      }
      function lbNav(dir) {
        lbIndex = (lbIndex + dir + gallery.length) % gallery.length;
        lbImg.src = gallery[lbIndex].image;
      }
      document.querySelectorAll('.gallery-item').forEach(el => {
        el.addEventListener('click', () => openLightbox(+el.dataset.index));
      });
      document.getElementById('lightbox-close')?.addEventListener('click', () => lightbox.classList.remove('active'));
      document.getElementById('lightbox-next')?.addEventListener('click', () => lbNav(1));
      document.getElementById('lightbox-prev')?.addEventListener('click', () => lbNav(-1));
      lightbox?.addEventListener('click', e => { if (e.target === lightbox) lightbox.classList.remove('active'); });
      document.addEventListener('keydown', e => {
        if (!lightbox?.classList.contains('active')) return;
        if (e.key === 'Escape') lightbox.classList.remove('active');
        if (e.key === 'ArrowLeft') lbNav(1);
        if (e.key === 'ArrowRight') lbNav(-1);
      });
    } catch (e) { console.error('خطا در گالری:', e); }
  
    // ---------- دفترچه یادگاری ----------
    try {
      const form = document.getElementById('guestbook-form');
      const list = document.getElementById('guestbook-list');
      function timeAgoShort(ts) {
        const diff = Math.floor(Date.now() / 1000) - ts;
        if (diff < 60) return 'همین الان';
        if (diff < 3600) return Math.floor(diff / 60) + ' دقیقه پیش';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ساعت پیش';
        return Math.floor(diff / 86400) + ' روز پیش';
      }
      document.querySelectorAll('.gb-time').forEach(el => {
        el.textContent = timeAgoShort(+el.dataset.time);
      });
      form?.addEventListener('submit', async e => {
        e.preventDefault();
        const name = document.getElementById('gb-name').value;
        const message = document.getElementById('gb-message').value;
        const website = document.getElementById('gb-honeypot').value;
        if (!message.trim()) return;
        const res = await fetch('api/guestbook.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add', name, message, website })
        }).then(r => r.json()).catch(() => null);
        if (res?.ok) {
          toast('پیامت ثبت شد ✅');
          document.querySelector('.empty-msg')?.remove();
          const item = document.createElement('div');
          item.className = 'guestbook-item';
          item.dataset.id = res.entry.id;
          item.innerHTML = `<div class="gb-avatar">${res.entry.name.charAt(0)}</div>
            <div class="gb-body"><p class="gb-name">${res.entry.name} <span class="gb-time">همین الان</span></p>
            <p class="gb-message">${res.entry.message.replace(/\n/g,'<br>')}</p></div>`;
          list.insertBefore(item, list.firstChild);
          form.reset();
        } else {
          toast(res?.error || 'خطا در ارسال پیام');
        }
      });
      list?.addEventListener('click', async e => {
        const btn = e.target.closest('.gb-delete');
        if (!btn) return;
        const item = btn.closest('.guestbook-item');
        if (!confirm('این پیام حذف شود؟')) return;
        const res = await fetch('api/guestbook.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', id: item.dataset.id, csrf: window.CSRF })
        }).then(r => r.json()).catch(() => null);
        if (res?.ok) { item.remove(); toast('پیام حذف شد'); }
        else toast(res?.error || 'خطا در حذف');
      });
    } catch (e) { console.error('خطا در دفترچه یادگاری:', e); }
  
    // ---------- بخش استوری‌ها ----------
    try {
      const stories = window.STORIES || [];
      const isAdmin = window.IS_ADMIN === true;
      const seen = JSON.parse(localStorage.getItem('seenStories') || '[]');
  
      document.querySelectorAll('.story-circle').forEach(el => {
        const idx = +el.dataset.index;
        if (seen.includes(stories[idx]?.id)) el.classList.add('seen');
        el.addEventListener('click', () => openViewer(idx));
      });
  
      const viewer = document.getElementById('story-viewer');
      const progressBar = document.getElementById('story-progress-bar');
      const content = document.getElementById('story-content');
      const captionEl = document.getElementById('story-caption');
      const likeBtn = document.getElementById('like-btn');
      const likeCount = document.getElementById('like-count');
      const viewNum = document.getElementById('view-num');
      const heartBurst = document.getElementById('heart-burst');
      const viewerTime = document.getElementById('viewer-time');
      const muteBtn = document.getElementById('mute-toggle');
      const deleteBtn = document.getElementById('delete-story-btn');
      const shareBtn = document.getElementById('story-share-btn');
      const closeBtn = document.getElementById('close-viewer');
  
      let current = 0, timer = null, paused = false, muted = true;
      let holdTimeout = null, holdActive = false, startX = 0, startY = 0, startedAt = 0;
      let segDuration = window.STORY_DEFAULT_DURATION || 5000;
  
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
  
      function openViewer(index, pushState = true) {
        if (!stories.length) return;
        current = index;
        buildSegments();
        viewer.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (pushState) history.pushState({ story: stories[current].id }, '', '?story=' + stories[current].id);
        loadStory();
      }
      function closeViewer(popState = false) {
        viewer.classList.remove('active');
        document.body.style.overflow = '';
        clearTimeout(timer);
        content.innerHTML = '';
        if (!popState && location.search) history.pushState({}, '', location.pathname);
      }
      function markSeen(id) {
        const arr = JSON.parse(localStorage.getItem('seenStories') || '[]');
        if (!arr.includes(id)) {
          arr.push(id);
          localStorage.setItem('seenStories', JSON.stringify(arr));
          document.querySelector(`.story-circle[data-index="${stories.findIndex(s=>s.id===id)}"]`)?.classList.add('seen');
        }
      }
      function preload(idx) {
        const s = stories[idx];
        if (s && s.type === 'image') { const img = new Image(); img.src = s.url; }
      }
  
      function loadStory() {
        const story = stories[current];
        if (!story) { closeViewer(); return; }
        content.innerHTML = '';
        viewerTime.textContent = timeAgo(story.time);
        captionEl.textContent = story.caption || '';
        captionEl.style.display = story.caption ? 'block' : 'none';
  
        let el;
        segDuration = story.duration || (window.STORY_DEFAULT_DURATION || 5000);
  
        if (story.type === 'video') {
          el = document.createElement('video');
          el.src = story.url; el.playsInline = true; el.muted = muted;
          el.addEventListener('loadedmetadata', () => {
            const real = Math.min(el.duration * 1000, window.STORY_MAX_VIDEO_DURATION || 15000);
            if (real > 0) { segDuration = real; runSegment(current); }
          });
          el.play().catch(() => {});
        } else {
          el = document.createElement('img');
          el.src = story.url;
        }
        content.appendChild(el);
        likeCount.textContent = story.likes;
        viewNum.textContent = story.views;
        likeBtn.classList.remove('liked');
        likeBtn.querySelector('i').className = 'fa-regular fa-heart';
        updateMuteIcon();
  
        markSeen(story.id);
        track('view', story.id).then(res => { if (res) viewNum.textContent = res.views; });
  
        resetSegments();
        runSegment(current);
        preload(current + 1);
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
        fill.style.transition = 'none';
        fill.style.width = '0%';
        requestAnimationFrame(() => {
          fill.style.transition = `width ${segDuration}ms linear`;
          fill.style.width = '100%';
        });
        timer = setTimeout(next, segDuration);
      }
      function pauseSegment() {
        if (paused) return;
        paused = true;
        clearTimeout(timer);
        const fill = document.querySelectorAll('.seg .fill')[current];
        if (fill) {
          const computed = getComputedStyle(fill).width;
          fill.style.transition = 'none';
          fill.style.width = computed;
        }
        content.querySelector('video')?.pause();
      }
      function resumeSegment() {
        if (!paused) return;
        paused = false;
        const fill = document.querySelectorAll('.seg .fill')[current];
        content.querySelector('video')?.play().catch(() => {});
        if (fill) {
          const currentWidth = parseFloat(getComputedStyle(fill).width);
          const trackWidth = fill.parentElement.getBoundingClientRect().width || 1;
          const remainRatio = 1 - (currentWidth / trackWidth);
          const remainMs = Math.max(200, segDuration * remainRatio);
          fill.style.transition = `width ${remainMs}ms linear`;
          fill.style.width = '100%';
          timer = setTimeout(next, remainMs);
        }
      }
      function next() { current < stories.length - 1 ? (current++, loadStory()) : closeViewer(); }
      function prev() { current > 0 ? (current--, loadStory()) : resetSegments(); }
  
      function onPointerDown(e) {
        startedAt = Date.now();
        const p = e.touches ? e.touches[0] : e;
        startX = p.clientX; startY = p.clientY;
        holdTimeout = setTimeout(() => { holdActive = true; pauseSegment(); }, 180);
      }
      function onPointerUp(e) {
        clearTimeout(holdTimeout);
        const p = e.changedTouches ? e.changedTouches[0] : e;
        const dx = (p.clientX || startX) - startX;
        const dy = (p.clientY || startY) - startY;
        const dt = Date.now() - startedAt;
  
        if (holdActive) { holdActive = false; resumeSegment(); return; }
  
        if (dy > 90 && Math.abs(dy) > Math.abs(dx)) { closeViewer(); return; }
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? next() : prev(); return; }
  
        if (dt < 250) {
          const nowT = Date.now();
          if (viewer._lastTap && nowT - viewer._lastTap < 300) {
            spawnHeart(p.clientX, p.clientY);
            doLike(true);
            viewer._lastTap = 0;
            return;
          }
          viewer._lastTap = nowT;
          setTimeout(() => {
            if (viewer._lastTap) {
              const half = window.innerWidth / 2;
              (startX < half) ? prev() : next();
            }
          }, 260);
        }
      }
      content.addEventListener('mousedown', onPointerDown);
      content.addEventListener('mouseup', onPointerUp);
      content.addEventListener('touchstart', onPointerDown, { passive: true });
      content.addEventListener('touchend', onPointerUp);
  
      closeBtn?.addEventListener('click', () => closeViewer());
      document.addEventListener('keydown', e => {
        if (!viewer?.classList.contains('active')) return;
        if (e.key === 'ArrowLeft') next();
        if (e.key === 'ArrowRight') prev();
        if (e.key === 'Escape') closeViewer();
      });
      window.addEventListener('popstate', () => {
        const params = new URLSearchParams(location.search);
        const sid = params.get('story');
        if (sid) {
          const idx = stories.findIndex(s => s.id === sid);
          if (idx >= 0) return openViewer(idx, false);
        }
        closeViewer(true);
      });
  
      function updateMuteIcon() {
        if (!muteBtn) return;
        muteBtn.querySelector('i').className = muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
      }
      muteBtn?.addEventListener('click', () => {
        muted = !muted;
        const video = content.querySelector('video');
        if (video) video.muted = muted;
        updateMuteIcon();
      });
  
      async function doLike(forceLike) {
        const story = stories[current];
        const res = await track('like', story.id);
        if (res) {
          likeCount.textContent = res.likes;
          story.likes = res.likes;
          const isLiked = forceLike ? true : res.liked;
          likeBtn.classList.toggle('liked', !!isLiked);
          likeBtn.querySelector('i').className = isLiked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }
      }
      likeBtn?.addEventListener('click', () => doLike());
      function spawnHeart(x, y) {
        const h = document.createElement('i');
        h.className = 'fa-solid fa-heart heart-pop';
        const rect = heartBurst.getBoundingClientRect();
        h.style.left = ((x - rect.left) - 30) + 'px';
        h.style.top = ((y - rect.top) - 30) + 'px';
        h.style.bottom = 'auto';
        heartBurst.appendChild(h);
        setTimeout(() => h.remove(), 800);
      }
  
      shareBtn?.addEventListener('click', async () => {
        const story = stories[current];
        const url = location.origin + location.pathname + '?story=' + story.id;
        if (navigator.share) {
          navigator.share({ title: 'استوری', url }).catch(() => {});
        } else {
          await navigator.clipboard.writeText(url);
          toast('لینک استوری کپی شد');
        }
      });
  
      deleteBtn?.addEventListener('click', async () => {
        const story = stories[current];
        if (!confirm('این استوری حذف شود؟')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', story.id);
        fd.append('csrf', window.CSRF);
        const res = await fetch('api/manage_story.php', { method: 'POST', body: fd }).then(r => r.json());
        if (res.ok) {
          stories.splice(current, 1);
          document.querySelector(`.story-circle[data-index="${current}"]`)?.remove();
          toast('استوری حذف شد');
          if (!stories.length) closeViewer(); else loadStory();
        } else {
          toast(res.error || 'خطا در حذف');
        }
      });
  
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
  
      const params = new URLSearchParams(location.search);
      const deepId = params.get('story');
      if (deepId) {
        const idx = stories.findIndex(s => s.id === deepId);
        if (idx >= 0) openViewer(idx, false);
      }
  
      if (isAdmin) {
        const addBtn = document.getElementById('add-story-btn');
        const modal = document.getElementById('add-story-modal');
        const fileInput = document.getElementById('story-file-input');
        const captionInput = document.getElementById('story-caption-input');
        const pinInput = document.getElementById('story-pin-input');
        const submitBtn = document.getElementById('story-upload-submit');
        const cancelBtn = document.getElementById('story-upload-cancel');
        const progressWrap = document.getElementById('upload-progress');
        const progressFill = document.getElementById('upload-progress-fill');
  
        addBtn?.addEventListener('click', () => modal.classList.add('active'));
        cancelBtn?.addEventListener('click', () => modal.classList.remove('active'));
  
        submitBtn?.addEventListener('click', () => {
          const file = fileInput.files[0];
          if (!file) { toast('یک فایل انتخاب کن'); return; }
          const fd = new FormData();
          fd.append('action', 'upload');
          fd.append('story', file);
          fd.append('caption', captionInput.value);
          fd.append('pinned', pinInput.checked ? '1' : '');
          fd.append('csrf', window.CSRF);
  
          const xhr = new XMLHttpRequest();
          xhr.open('POST', 'api/manage_story.php');
          xhr.upload.onprogress = (e) => {
            progressWrap.classList.add('active');
            progressFill.style.width = (e.loaded / e.total * 100) + '%';
          };
          xhr.onload = () => {
            progressWrap.classList.remove('active');
            try {
              const res = JSON.parse(xhr.responseText);
              if (res.ok) { toast('استوری اضافه شد ✅'); setTimeout(() => location.reload(), 700); }
              else toast(res.error || 'خطا در آپلود');
            } catch { toast('خطا در آپلود'); }
          };
          xhr.send(fd);
        });
      }
  
    } catch (err) {
      console.error('خطا در بخش استوری‌ها:', err);
    }
  
    // ---------- پلی‌لیست موزیک ----------
    try {
      const playlist = window.PLAYLIST || [];
      const audio = document.getElementById('audio-player');
      const playBtn = document.getElementById('play-btn');
      const prevBtn = document.getElementById('prev-btn');
      const nextBtn = document.getElementById('next-btn');
      const vinyl = document.getElementById('vinyl');
      const vinylCover = document.getElementById('vinyl-cover');
      const titleEl = document.getElementById('song-title');
      const artistEl = document.getElementById('song-artist');
      const fill = document.getElementById('progress-fill');
      const progressBarEl = document.getElementById('music-progress');
      let trackIndex = 0;
  
      function loadTrack(i, autoplay) {
        if (!playlist[i]) return;
        trackIndex = i;
        const t = playlist[i];
        audio.src = t.file;
        titleEl.textContent = t.title;
        artistEl.textContent = t.artist;
        vinylCover.src = t.cover || '';
        if (autoplay) audio.play().catch(() => {});
      }
  
      if (audio && playBtn) {
        playBtn.addEventListener('click', () => {
          if (audio.paused) {
            audio.play().then(() => {
              vinyl?.classList.add('playing');
              playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            }).catch(() => toast('فایل آهنگ پیدا نشد یا قابل پخش نیست'));
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
          if (playlist.length > 1) { loadTrack((trackIndex + 1) % playlist.length, true); }
          else { vinyl?.classList.remove('playing'); playBtn.innerHTML = '<i class="fa-solid fa-play"></i>'; }
        });
        progressBarEl?.addEventListener('click', e => {
          if (!audio.duration) return;
          const rect = progressBarEl.getBoundingClientRect();
          const ratio = (e.clientX - rect.left) / rect.width;
          audio.currentTime = ratio * audio.duration;
        });
        nextBtn?.addEventListener('click', () => loadTrack((trackIndex + 1) % playlist.length, true));
        prevBtn?.addEventListener('click', () => loadTrack((trackIndex - 1 + playlist.length) % playlist.length, true));
      }
    } catch (err) { console.error('خطا در بخش پلیر آهنگ:', err); }
  
    // ---------- تغییر تم ----------
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
    } catch (err) { console.error('خطا در بخش تغییر تم:', err); }
  
    // ---------- پارتیکل پس‌زمینه با اتصال ذرات ----------
    try {
      const canvas = document.getElementById('bg-canvas');
      if (canvas) {
        const ctx = canvas.getContext('2d');
        function resize() { canvas.width = innerWidth; canvas.height = innerHeight; }
        resize();
        window.addEventListener('resize', resize);
        const particles = Array.from({length: 55}, () => ({
          x: Math.random()*innerWidth, y: Math.random()*innerHeight,
          r: Math.random()*2 + 1, dx: (Math.random()-0.5)*0.3, dy: (Math.random()-0.5)*0.3,
        }));
        function animate() {
          ctx.clearRect(0,0,canvas.width,canvas.height);
          particles.forEach((p, i) => {
            p.x += p.dx; p.y += p.dy;
            if (p.x < 0 || p.x > canvas.width) p.dx *= -1;
            if (p.y < 0 || p.y > canvas.height) p.dy *= -1;
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
            ctx.fillStyle = 'rgba(255,255,255,0.3)'; ctx.fill();
            for (let j = i + 1; j < particles.length; j++) {
              const q = particles[j];
              const dist = Math.hypot(p.x - q.x, p.y - q.y);
              if (dist < 120) {
                ctx.beginPath();
                ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y);
                ctx.strokeStyle = `rgba(124,92,255,${0.12 * (1 - dist/120)})`;
                ctx.lineWidth = 1; ctx.stroke();
              }
            }
          });
          requestAnimationFrame(animate);
        }
        animate();
      }
    } catch (err) { console.error('خطا در بخش پارتیکل:', err); }
  
    // ---------- انیمیشن نوار مهارت‌ها ----------
    try {
      const bars = document.querySelectorAll('.skill-fill');
      if (bars.length) {
        const io = new IntersectionObserver(entries => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.style.width = entry.target.dataset.level + '%';
              io.unobserve(entry.target);
            }
          });
        }, { threshold: 0.3 });
        bars.forEach(b => io.observe(b));
      }
    } catch (err) { console.error('خطا در بخش مهارت‌ها:', err); }
  
    // ---------- اشتراک‌گذاری پروفایل / QR ----------
    try {
      const shareBtn2 = document.getElementById('share-btn');
      const shareModal = document.getElementById('share-modal');
      const shareModalClose = document.getElementById('share-modal-close');
      const copyLinkBtn = document.getElementById('copy-link-btn');
      const nativeShareBtn = document.getElementById('native-share-btn');
      const qrCanvas = document.getElementById('qr-canvas');
  
      shareBtn2?.addEventListener('click', () => {
        shareModal.classList.add('active');
        if (window.QRCode && qrCanvas) {
          QRCode.toCanvas(qrCanvas, location.href, { width: 200, margin: 1 }, () => {});
        }
      });
      shareModalClose?.addEventListener('click', () => shareModal.classList.remove('active'));
      copyLinkBtn?.addEventListener('click', async () => {
        await navigator.clipboard.writeText(location.href);
        toast('لینک کپی شد');
      });
      nativeShareBtn?.addEventListener('click', () => {
        if (navigator.share) navigator.share({ title: document.title, url: location.href }).catch(() => {});
        else toast('این قابلیت روی این مرورگر پشتیبانی نمی‌شود');
      });
    } catch (err) { console.error('خطا در بخش اشتراک‌گذاری:', err); }
  
  });
  