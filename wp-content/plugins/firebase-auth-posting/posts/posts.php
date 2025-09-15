<?php
/**
 * Plugin: Firebase — Public Feed with Create Post (Unified, Firebase SDK v8 style)
 */

function fap_shortcode_all_posts_with_create() {
    ob_start();

    // Assuming you have a [firebase_layout_page] shortcode or replace this with your layout
    echo do_shortcode('[firebase_layout_page]'); 

    ?>
    <script>

        function sharePost(postId, title, content, excerpt) {
  const siteName = "Israel"; // You can adjust branding
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

    // -------------------------------
    // Inline JavaScript from posts.js adapted to Firebase v8 style
    // -------------------------------

    let postTemplate = '';
 
    async function loadPostTemplate() {
        const res = await fetch('/wp-content/plugins/firebase-auth-posting/posts/posts.html');
        if (!res.ok) throw new Error('Failed to load post template');
        return await res.text();
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            '"': '&quot;', "'": '&#039;'
        })[m]);
    }

    function timeAgo(date) {
        const now = new Date();
        const seconds = Math.floor((now - new Date(date)) / 1000);
        const intervals = [
            { label: 'yr.', seconds: 31536000 },
            { label: 'mo.', seconds: 2592000 },
            { label: 'days', seconds: 86400 },
            { label: 'hr.', seconds: 3600 },
            { label: 'min.', seconds: 60 },
            { label: 'sec.', seconds: 1 }
        ];
        for (const interval of intervals) {
            const count = Math.floor(seconds / interval.seconds);
            if (count > 0) return count + ' ' + interval.label + ' ago';
        }
        return 'just now';
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }


    
function renderPostTemplate(postData) {
    const { id, title, content, userEmail, avatarUrl, username, date, postUrl } = postData;

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

    let videoHtml = '';
    let isMedia = false;

    if (isValidUrl(content)) {
        const ytId = extractYouTubeID(content);
        const vimeoId = extractVimeoID(content);

        if (ytId) {
            isMedia = true;
            videoHtml = `<iframe width="100%" height="315" src="https://www.youtube.com/embed/${ytId}" 
                          frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                          allowfullscreen></iframe>`;
        } else if (vimeoId) {
            isMedia = true;
            videoHtml = `<iframe src="https://player.vimeo.com/video/${vimeoId}" width="100%" height="315" 
                          frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
        } else if (content.match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
            isMedia = true;
            videoHtml = `<video controls autoplay style="width: 100%; max-height: 360px;">
                            <source src="${escapeHtml(content)}" type="video/mp4">
                            Your browser does not support the video tag.
                         </video>`;
        } else if (content.match(/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i)) {
            isMedia = true;
            videoHtml = `<img src="${escapeHtml(content)}" style="width:100%; height:auto;" alt="Post image" />`;
        }
    }

    let contentHtml = '';

    if (isMedia) {
        contentHtml = `
            <div style="background-color:black; border-radius:20px; padding:10px; margin-bottom:12px;">
                ${videoHtml}
            </div>`;
    } else {
        contentHtml = `<p>${escapeHtml(content)}</p>`;
    }

    const excerpt = (typeof content === 'string') ? content.slice(0, 140) : '';

    return postTemplate
        .replace(/{{POST_ID}}/g, escapeHtml(id))
        .replace(/{{TITLE}}/g, escapeHtml(title))
        .replace(/{{CONTENT}}/g, contentHtml)
        .replace(/{{DATA_CONTENT}}/g, escapeHtml(content))
        .replace(/{{EXCERPT2}}/g, escapeHtml(excerpt))
        .replace(/{{USER_EMAIL}}/g, escapeHtml(userEmail))
        .replace(/{{AVATAR_URL}}/g, escapeHtml(avatarUrl))
        .replace(/{{USERNAME}}/g, escapeHtml(username))
        .replace(/{{DATE}}/g, escapeHtml(date))
        .replace(/{{POST_URL}}/g, escapeHtml(postUrl));
}






    async function fapFetchPosts() {
        const container = document.getElementById('fap-posts-container');
        if (!container) return;
        container.innerHTML = "<p>Loading posts...</p>";

        while (!postTemplate) {
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        try {
            const snapshot = await firebase.firestore().collection("posts").orderBy("createdAt", "desc").get();
            if (snapshot.empty) {
                container.innerHTML = "<p>No posts yet.</p>";
                return;
            }

            const posts = [];
            const userIdsSet = new Set();

            snapshot.forEach(doc => {
                const post = doc.data();
                post.id = doc.id;
                posts.push(post);
                if (post.userId) userIdsSet.add(post.userId);
            });

            const userIds = Array.from(userIdsSet);
            let usersMap = {};
            if (userIds.length) {
                const userDocs = await firebase.firestore().collection('users')
                    .where(firebase.firestore.FieldPath.documentId(), 'in', userIds)
                    .get();
                userDocs.forEach(doc => usersMap[doc.id] = doc.data());
            }

            container.innerHTML = '';

            for (const post of posts) {
                const userData = usersMap[post.userId] || {};
                const rawAvatar = userData.avatar || '';
                let avatarUrl = 'https://www.gravatar.com/avatar?d=mp';
                if (rawAvatar && isValidUrl(rawAvatar)) {
                    avatarUrl = rawAvatar.includes('i.pravatar.cc')
                        ? rawAvatar.replace(/\/\d+(\?u=.+)?$/, '/100$1')
                        : rawAvatar;
                }

                const username = (userData.email || post.userEmail || 'unknown').split('@')[0];
                const createdAtDate = post.createdAt ? post.createdAt.toDate() : null;
                const dateStr = createdAtDate ? timeAgo(createdAtDate) : 'Unknown';
                const postUrl = window.location.origin + '/post?id=' + encodeURIComponent(post.id);

                const postHtml = renderPostTemplate({
                    id: post.id,
                    title: post.title,
                    content: post.content,
                    userEmail: userData.email || post.userEmail || '',
                    avatarUrl,
                    username,
                    date: dateStr,
                    postUrl
                });

                container.insertAdjacentHTML('beforeend', postHtml);




                

const shareBtn = document.querySelector(`[data-post-id="${post.id}"] .share-button`);
if (shareBtn) {
  shareBtn.addEventListener('click', () => {
    const postId = shareBtn.getAttribute('data-post-id');
    const title = shareBtn.getAttribute('data-title');
    const content = shareBtn.getAttribute('data-content');
    const excerpt = shareBtn.getAttribute('data-excerpt');
    sharePost(postId, title, content, excerpt);
  });
}


function loadYouTubeAPI() {
  return new Promise((resolve) => {
    if (window.YT && window.YT.Player) {
      resolve();
    } else {
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
          if (previousPlayer) previousPlayer.pauseVideo();
        }

        currentVisiblePost = post;
        player.playVideo();
      } else {
        if (currentVisiblePost === post) {
          currentVisiblePost = null;
        }
        player.pauseVideo();
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






                setupLikeButton(post.id);
                fapLoadCommentCount(post.id);
            }
        } catch (err) {
            container.innerHTML = '<p>Error loading posts: ' + escapeHtml(err.message) + '</p>';
        }
    }

    async function fapLoadCommentCount(postId) {
        try {
            const snapshot = await firebase.firestore().collection("posts").doc(postId).collection("comments").get();
            const el = document.getElementById('comment-count-' + postId);
            if (el) el.textContent = snapshot.size;
        } catch (err) {
            console.error('Failed to load comment count for post ' + postId + ':', err);
        }
    }


    async function displayLikeCount(postId) {
    const likeCountEl = document.getElementById('like-count-' + postId);
    if (!likeCountEl) return;
    try {
        const snapshot = await firebase.firestore().collection("likes")
            .where("postId", "==", postId).get();
        likeCountEl.textContent = snapshot.size;
    } catch (err) {
        console.error('Error fetching likes count:', err);
    }
}


function setupLikeButton(postId) {
    const user = firebase.auth().currentUser;

    // Always show total like count:
    displayLikeCount(postId);

    const likeBtn = document.querySelector(`[data-post-id="${postId}"] .like-button`);
    if (!likeBtn) return;

    if (!user) {
        // User not logged in: show alert on like button click
        likeBtn.addEventListener('click', e => {
            e.stopPropagation();
            alert('You must be logged in to like a post.');
        });
        return;
    }

    // If user is logged in, continue with existing logic
    const heartGray = document.querySelector(`[data-post-id="${postId}"] .heart-gray`);
    const heartRed = document.querySelector(`[data-post-id="${postId}"] .heart-red`);
    const likeCountEl = document.getElementById('like-count-' + postId);
    if (!likeCountEl) return;

    function updateLikeUI(likesSnapshot) {
        const count = likesSnapshot.size;
        likeCountEl.textContent = count;
        const liked = likesSnapshot.docs.some(doc => doc.data().userId === user.uid);
        if (heartGray && heartRed) {
            heartGray.style.display = liked ? 'none' : 'inline';
            heartRed.style.display = liked ? 'inline' : 'none';
        }
    }

    firebase.firestore().collection("likes")
        .where("postId", "==", postId)
        .onSnapshot(snapshot => updateLikeUI(snapshot));

    likeBtn.addEventListener('click', async e => {
        e.stopPropagation();
        const query = firebase.firestore().collection("likes")
            .where("postId", "==", postId)
            .where("userId", "==", user.uid)
            .limit(1);
        const existing = await query.get();
        if (!existing.empty) {
            // Unlike the post
            await firebase.firestore().collection("likes").doc(existing.docs[0].id).delete();
        } else {
            // Like the post
            await firebase.firestore().collection("likes").add({
                postId,
                userId: user.uid,
                likedAt: firebase.firestore.FieldValue.serverTimestamp()
            });

            // 🔔 Create a notification
            try {
                const postDoc = await firebase.firestore().collection("posts").doc(postId).get();
                const postOwnerId = postDoc.exists ? postDoc.data().userId : null;

                if (postOwnerId && postOwnerId !== user.uid) {
                    await firebase.firestore().collection("notifications").add({
                        userId: postOwnerId, // recipient
                        fromUserId: user.uid,
                        postId,
                        type: "like",
                        message: `${user.email} liked your post`,
                        read: false,
                        createdAt: firebase.firestore.FieldValue.serverTimestamp()
                    });
                }
            } catch (err) {
                console.error('Failed to send like notification:', err);
            }
        }
    });
}





  

    async function initFapPosts() { 
        try {
            postTemplate = await loadPostTemplate();
            fapFetchPosts();
        } catch (err) {
            console.error('Post system failed to initialize:', err);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const main = document.querySelector('#fap-main-content');
        if (main) {
            // Render the content safely, escaping ' and newlines for JS embedding
            main.innerHTML = `<?php echo fap_all_posts_with_create_content(); ?>`;
            initFapPosts();
        }
    });





    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('firebase_all_posts_with_create', 'fap_shortcode_all_posts_with_create');

function fap_all_posts_with_create_content() {
    ob_start(); ?>
    <div id="fap-page-container">
      <div id="fap-posts-list">
        <div id="fap-posts-container" style="background-color: var(--bg-color);"><p>Loading...</p></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}