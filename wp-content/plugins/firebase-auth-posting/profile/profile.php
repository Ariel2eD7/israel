<?php
function fap_shortcode_user_profile() {
    ob_start();

    // Output the common page layout/header (adjust shortcode name if needed)
    echo do_shortcode('[firebase_layout_page]');

    // Load only the mobile profile HTML file
    $html_file = 'profile-mobile.html'; 
    $html_path = __DIR__ . '/' . $html_file; 

    $content = '<p>Error: Profile HTML not found.</p>';

    if (file_exists($html_path)) {
        $htmlContent = file_get_contents($html_path);
        $htmlContent = str_replace('</script>', '<\\/script>', $htmlContent);
        $content = $htmlContent;
    }

    // JSON-encode the content to safely insert into JS
    $jsSafeHtml = json_encode($content);

    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const main = document.querySelector('#fap-main-content');
        if (main) {
            main.innerHTML = $jsSafeHtml;
        }
    });
    </script>";



    ?>

    <script>
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    // Expose setup function globally so main.js can call it
window.fapSetupProfile = function(auth, db) {
  console.log('fapSetupProfile called');

  const profileSection = document.getElementById('fap-user-profile');
  if (!profileSection) {
    console.error('Profile section #fap-user-profile not found');
    return;
  }
  console.log('Profile section found');

  auth.onAuthStateChanged(async (currentUser) => {
    console.log('Auth state changed', currentUser);

    const urlParams = new URLSearchParams(window.location.search);
    const profileUser = urlParams.get('email') || urlParams.get('user') || '';

    const emailToShow = profileUser || (currentUser ? currentUser.email : null);
    console.log('Email to show:', emailToShow);

    if (!emailToShow) {
      profileSection.innerHTML = '<p style="color: red; text-align: center; font-weight: bold;">Please log in or specify a user to view a profile.</p>';
      return;
    }

    const querySnapshot = await db.collection('users')
      .where('email', '==', emailToShow)
      .limit(1)
      .get();

    console.log('Query snapshot:', querySnapshot);
if (querySnapshot.empty) {
  console.log("❌ No user found in Firestore");
  profileSection.innerHTML = `<p>Profile for "${emailToShow}" not found.</p>`;
  return;
}
console.log("✅ User found");




            const doc = querySnapshot.docs[0];
            const data = doc.data();
            const joinDate = data.createdAt ? data.createdAt.toDate() : new Date();

            // Set banner background image or none
            const banner = document.getElementById('profile-banner');
            if (banner) {
                banner.style.backgroundImage = data.banner ? `url(${data.banner})` : 'none';
            }

            // Set avatar
            const avatarElem = document.getElementById('profile-avatar');
            const rawAvatar = data.avatar || '';
            const defaultAvatar = 'https://www.gravatar.com/avatar?d=mp';
            const avatarUrl = rawAvatar && isValidUrl(rawAvatar) ? rawAvatar : defaultAvatar;
            if (avatarElem) {
                avatarElem.src = avatarUrl;
            }

            // Set name, join date, and bio
            const nameElem = document.getElementById('profile-name');
            if (nameElem) nameElem.innerText = data.name || '';

            const joinedElem = document.getElementById('profile-joined');
            if (joinedElem) {
                joinedElem.innerText = `Joined ${joinDate.toLocaleString('default', { month: 'long', year: 'numeric' })}`;
            }

            const bioElem = document.getElementById('profile-bio');
            if (bioElem) bioElem.innerText = data.bio || '';

            const editForm = document.getElementById('edit-profile-form');
            const isOwner = currentUser && currentUser.email === emailToShow;

            if (editForm) {
                if (isOwner) {
                    editForm.style.display = 'block';
                    document.getElementById('edit-name').value = data.name || '';
                    document.getElementById('edit-bio').value = data.bio || '';

                    editForm.onsubmit = async (e) => {
                        e.preventDefault();
                        const updates = {
                            name: document.getElementById('edit-name').value.trim(),
                            bio: document.getElementById('edit-bio').value.trim(),
                            updatedAt: firebase.firestore.Timestamp.now()
                        };
                        await db.collection('users').doc(currentUser.uid).set(updates, { merge: true });
                        alert('Profile updated');
                        location.reload();
                    };
                } else {
                    editForm.style.display = 'none';
                }
            }

            // Tabs logic
            const tabs = document.querySelectorAll('#profile-tabs button');
            const panels = [
                document.getElementById('overview'),
                document.getElementById('posts'),
                document.getElementById('comments')
            ];

tabs.forEach(btn => {
    btn.onclick = () => {
        tabs.forEach(b => {
            const isActive = b === btn;
            b.style.fontWeight = isActive ? 'bold' : 'normal';
            b.style.backgroundColor = isActive ? 'var(--button-bg-color)' : 'transparent';
        });

        panels.forEach(p => {
            if (!p) return;
            p.style.display = (p.id === btn.dataset.tab) ? 'block' : 'none';
        });

        if (btn.dataset.tab === 'posts') loadPosts();
        else if (btn.dataset.tab === 'comments') loadComments();
        else if (btn.dataset.tab === 'overview') loadOverview();
    };


    profileSection.style.display = 'block'; // ✅ <-- Add this here

});


function timeAgo(date) {
  const seconds = Math.floor((new Date() - date) / 1000);

  const intervals = [
    { label: 'year', seconds: 31536000 },
    { label: 'month', seconds: 2592000 },
    { label: 'week', seconds: 604800 },
    { label: 'day', seconds: 86400 },
    { label: 'hour', seconds: 3600 },
    { label: 'minute', seconds: 60 },
    { label: 'second', seconds: 1 }
  ];

  for (const interval of intervals) {
    const count = Math.floor(seconds / interval.seconds);
    if (count >= 1) {
      return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
    }
  }

  return 'just now';
}


async function loadPosts() 
{
  const out = document.getElementById('posts');
  if (!out) return;
  out.innerHTML = '<p>Loading posts...</p>';

  const snapshot = await db.collection('posts')
    .where('userId', '==', doc.id)
    .orderBy('createdAt', 'desc')
    .get();

  if (snapshot.empty) 
  {
    out.innerHTML = '<p>No posts yet.</p>';
    return;
  }

  const userEmail = data.email || '';



  const userName = userEmail.split('@')[0];
  const avatarUrl = data.avatar && isValidUrl(data.avatar)
    ? data.avatar
    : 'https://www.gravatar.com/avatar?d=mp';

  let template;
  try 
  {
    template = await fetch('/wp-content/plugins/firebase-auth-posting/posts/posts.html').then(r => r.text());
  } 
  catch (err) 
  {
    console.error('❌ Failed to load posts.html:', err);
    out.innerHTML = '<p>Error loading posts template.</p>';
    return;
  }

  const postsHTML = snapshot.docs.map(d => 
  {
    const post = d.data();
    const postId = d.id;
    const title = escapeHtml(post.title || '');
    const content = renderPostForProfile(post);
    const excerpt = content.length > 100 ? content.substring(0, 100) + '...' : content;
const date = post.createdAt ? timeAgo(post.createdAt.toDate()) : 'Unknown date';

    return template
      .replace(/{{POST_ID}}/g, postId)
      .replace(/{{USER_EMAIL}}/g, encodeURIComponent(userEmail))
      .replace(/{{USERNAME}}/g, escapeHtml(userName))
      .replace(/{{AVATAR_URL}}/g, avatarUrl)
      .replace(/{{DATE}}/g, date)
      .replace(/{{TITLE}}/g, title)
      .replace(/{{CONTENT}}/g, content)
      .replace(/{{EXCERPT}}/g, excerpt)
      .replace(/{{DATA_CONTENT}}/g, content.replace(/"/g, '&quot;')); // For data-content attr
  });

  out.innerHTML = postsHTML.join('');


  document.querySelectorAll('.share-button').forEach(btn => 
  {
   const postId = btn.getAttribute('data-post-id');
   const title = btn.getAttribute('data-title');
   const content = btn.getAttribute('data-content');
   const excerpt = btn.getAttribute('data-excerpt');

   btn.addEventListener('click', event => 
   { 
     event.stopPropagation(); // prevent accidental navigation
     sharePost(postId, title, content, excerpt);
   });
  });



  snapshot.docs.forEach(doc => 
  {
    const postId = doc.id;
  
    function loadYouTubeAPI() 
    {
      return new Promise((resolve) => 
      {
        if (window.YT && window.YT.Player) 
        {
          resolve();
        } 
        else 
        {
          const tag = document.createElement('script');
          tag.src = "https://www.youtube.com/iframe_api";
          document.body.appendChild(tag);
          window.onYouTubeIframeAPIReady = () => resolve();
        }
      });
 }



async function setupVideoAutoPlayObserver() 
{
  const posts = document.querySelectorAll('.fap-post');
  const players = new Map();

  await loadYouTubeAPI();

  posts.forEach(post => {
    const iframe = post.querySelector('iframe');
    if (!iframe) return;

    let src = iframe.getAttribute('src');

    // Add required params
    if (!src.includes('enablejsapi=1')) {
      const sep = src.includes('?') ? '&' : '?';
      src += sep + 'enablejsapi=1';
    }
    if (!src.includes('origin=')) {
      const sep = src.includes('?') ? '&' : '?';
      src += sep + 'origin=' + encodeURIComponent(window.location.origin);
    }
    iframe.setAttribute('src', src);

    const ytIdMatch = src.match(/(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (!ytIdMatch) return;

    const player = new YT.Player(iframe, {
      playerVars: {
        autoplay: 0,
        mute: 1,
        playsinline: 1,
        controls: 1,
        rel: 0,
      },
      events: {
        onReady: (event) => {
          event.target.mute(); 
        }
      }
    });

    players.set(post, player);
  });

  let currentVisiblePost = null;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const post = entry.target;
      const player = players.get(post);
      if (!player) return;

     if (entry.isIntersecting) {
  if (currentVisiblePost && currentVisiblePost !== post) {
    const previousPlayer = players.get(currentVisiblePost);
    if (previousPlayer && typeof previousPlayer.pauseVideo === 'function') {
      previousPlayer.pauseVideo();
    }
  }

  currentVisiblePost = post;
  if (typeof player.playVideo === 'function') {
    player.playVideo();
  }
} else {
  if (currentVisiblePost === post) {
    currentVisiblePost = null;
  }
  if (typeof player.pauseVideo === 'function') {
    player.pauseVideo();
  }
}

    });
   }, { threshold: 0.6 }); // Adjust threshold to control how much of post needs to be visible

    posts.forEach(post => observer.observe(post));

    function unmuteVisibleVideos() 
    {
    if (currentVisiblePost) {
      const player = players.get(currentVisiblePost);
      if (player) player.unMute();
    }

    document.body.removeEventListener('touchstart', unmuteVisibleVideos);
    }

  document.body.addEventListener('touchstart', unmuteVisibleVideos, { once: true });
}

setupVideoAutoPlayObserver();






  setupLikeButton(postId);
  fapLoadCommentCount(postId);
});


}

function fapLoadCommentCount(postId) {
  firebase.firestore().collection("posts")
    .doc(postId)
    .collection("comments")
    .get()
    .then(snapshot => {
      const el = document.getElementById('comment-count-' + postId);
      if (el) el.textContent = snapshot.size;
    })
    .catch(err => {
      console.error('Failed to load comment count:', err);
    });
}

function displayLikeCount(postId) {
  const likeCountEl = document.getElementById('like-count-' + postId);
  if (!likeCountEl) return;

  firebase.firestore().collection("likes")
    .where("postId", "==", postId)
    .get()
    .then(snapshot => {
      likeCountEl.textContent = snapshot.size;
    })
    .catch(err => {
      console.error('Error fetching likes count:', err);
    });
}

function setupLikeButton(postId) {
  const user = firebase.auth().currentUser;

  displayLikeCount(postId);

  const likeBtn = document.querySelector(`[data-post-id="${postId}"] .like-button`);
  const heartGray = document.querySelector(`[data-post-id="${postId}"] .heart-gray`);
  const heartRed = document.querySelector(`[data-post-id="${postId}"] .heart-red`);
  const likeCountEl = document.getElementById('like-count-' + postId);

  if (!likeBtn || !likeCountEl) return;

  if (!user) {
    likeBtn.addEventListener('click', e => {
      e.stopPropagation();
      alert('You must be logged in to like a post.');
    });
    return;
  }

  firebase.firestore().collection("likes")
    .where("postId", "==", postId)
    .onSnapshot(snapshot => {
      const liked = snapshot.docs.some(doc => doc.data().userId === user.uid);
      likeCountEl.textContent = snapshot.size;
      if (heartGray && heartRed) {
        heartGray.style.display = liked ? 'none' : 'inline';
        heartRed.style.display = liked ? 'inline' : 'none';
      }
    });

  likeBtn.addEventListener('click', async e => {
    e.stopPropagation();

    const query = firebase.firestore().collection("likes")
      .where("postId", "==", postId)
      .where("userId", "==", user.uid)
      .limit(1);

    const existing = await query.get();

    if (!existing.empty) {
      await firebase.firestore().collection("likes").doc(existing.docs[0].id).delete();
    } else {
      await firebase.firestore().collection("likes").add({
        postId,
        userId: user.uid,
        likedAt: firebase.firestore.FieldValue.serverTimestamp()
      });

      // Optional: Notification logic here
    }
  });
}




            async function loadComments() {
                const out = document.getElementById('comments');
                if (!out) return;
                out.innerHTML = '<p>Loading comments...</p>';
                const commentsSnap = await db.collectionGroup('comments')
                    .where('userId', '==', doc.id)
                    .orderBy('createdAt', 'desc')
                    .get();
                out.innerHTML = commentsSnap.empty
                    ? '<p>No comments yet.</p>'
                    : commentsSnap.docs.map(d => {
                        const c = d.data();
                        return `
                        <div style="background:#fafafa;padding:15px;margin-bottom:12px;border-radius:4px;">
                            <p>${escapeHtml(c.text)}</p>
                            <small>on post ${escapeHtml(c.postId)}</small>
                        </div>
                        `;
                    }).join('');
            }

            async function loadOverview() {
                const out = document.getElementById('overview');
                if (!out) return;
                out.innerHTML = '<p>Loading overview...</p>';
                const [ps, cs] = await Promise.all([
                    db.collection('posts').where('userId', '==', doc.id).orderBy('createdAt', 'desc').limit(5).get(),
                    db.collectionGroup('comments').where('userId', '==', doc.id).orderBy('createdAt', 'desc').limit(5).get()
                ]);
                const combined = [
                    ...ps.docs.map(d => ({ ...d.data(), type: 'post' })),
                    ...cs.docs.map(d => ({ ...d.data(), type: 'comment' }))
                ].sort((a, b) => b.createdAt.seconds - a.createdAt.seconds);
                out.innerHTML = combined.map(item =>
                    item.type === 'post'
                    ? `<div style="background:#fafafa;padding:15px;margin-bottom:12px;border-radius:4px;">
                        <strong>${escapeHtml(item.title)}</strong> 
                        <p>${escapeHtml(item.content)}</p>
                    </div>`
                    : `<div style="background:#fafafa;padding:15px;margin-bottom:12px;border-radius:4px;">
                        <p>${escapeHtml(item.text)}</p>
                        <small>Comment on post ${escapeHtml(item.postId)}</small>
                    </div>`
                ).join('') || '<p>No activity yet.</p>';
            }

            function escapeHtml(txt) {
                return txt.replace(/[&<>"']/g, m => ({
                    '&':'&amp;',
                    '<':'&lt;',
                    '>':'&gt;',
                    '"':'&quot;',
                    "'":'&#039;'
                }[m]));
            }

            // Show overview tab by default
            if (tabs.length > 0) {
                tabs[0].click();
            }
        });
    };

    
function trySetupProfile(attempts = 0) {
  if (window.firebase && firebase.apps.length) {
    window.fapSetupProfile(firebase.auth(), firebase.firestore());
  } else if (attempts < 20) { // retry max 20 times every 100ms = 2 seconds max wait
    setTimeout(() => trySetupProfile(attempts + 1), 100);
  } else {
    console.warn("⚠️ Firebase app not initialized after multiple attempts.");
  }
}

document.addEventListener('DOMContentLoaded', () => {
  trySetupProfile();
});


function sharePost(postId, title, content, excerpt) {
  const siteName = "Israel"; // Optional branding
  const shareUrl = `${window.location.origin}/post?id=${postId}`;

  const shareText = `${title}
${shareUrl}

———
${siteName}
${title.toUpperCase()}
${excerpt || ''}`;

  if (navigator.share) {
    navigator.share({
      title: title,
      text: shareText,
      url: shareUrl
    }).catch(error => {
      console.error('Error sharing:', error);
      fallbackCopy(shareText);
    });
  } else {
    fallbackCopy(shareText);
  }
}

function fallbackCopy(text) {
  navigator.clipboard.writeText(text)
    .then(() => alert('Link copied to clipboard!'))
    .catch(() => prompt('Copy this link manually:', text));
}




    





function renderPostForProfile(postData) {
  // You can copy the entire renderPostTemplate function here,
  // or just the media detection and content rendering part.

  // Example (simplified):
  const { content } = postData;

  function isValidUrl(url) {
    try { new URL(url); return true; } catch { return false; }
  }
  function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
  }
  function extractYouTubeID(url) {
    const regExp = /(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|embed|shorts)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
    const match = url.match(regExp);
    return match ? match[1] : null;
  }
  function extractVimeoID(url) {
    const regExp = /vimeo\.com\/(?:video\/)?(\d+)/;
    const match = url.match(regExp);
    return match ? match[1] : null;
  }

  let contentHtml = '';
  if (isValidUrl(content)) {
    const ytId = extractYouTubeID(content);
    const vimeoId = extractVimeoID(content);

    if (ytId) {

       contentHtml = `<div style="background-color:black; border-radius:20px; padding:10px; margin-bottom:12px;">
                <iframe width="100%" height="315" src="https://www.youtube.com/embed/${ytId}" 
                          frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                          allowfullscreen></iframe>
            </div>`;


     // contentHtml = `<iframe width="100%" height="315" src="https://www.youtube.com/embed/${ytId}" frameborder="0" allowfullscreen></iframe>`;
    } else if (vimeoId) {
      contentHtml = `<iframe src="https://player.vimeo.com/video/${vimeoId}" width="100%" height="315" frameborder="0" allowfullscreen></iframe>`;
    } else if (content.match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
      contentHtml = `<video width="100%" height="315" controls><source src="${escapeHtml(content)}" type="video/mp4">Your browser does not support the video tag.</video>`;
    } else if (content.match(/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i)) {
      contentHtml = `<img src="${escapeHtml(content)}" style="max-width:100%; height:auto;" alt="Post image"/>`;
    } else {
      contentHtml = `<p>${escapeHtml(content)}</p>`;
    }
  } else {
    contentHtml = `<p>${escapeHtml(content)}</p>`;
  }

  return contentHtml;
}

    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('firebase_user_profile', 'fap_shortcode_user_profile');