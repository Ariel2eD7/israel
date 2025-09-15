<?php
/**
 * Plugin: Firebase — Single Post Display (Unified)
 */

function fap_shortcode_single_post($atts) {
    // Get the post ID from shortcode attributes or URL parameter
    $atts = shortcode_atts(['id' => ''], $atts, 'show_post');
    $post_id = !empty($atts['id'])
        ? sanitize_text_field($atts['id'])
        : (isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '');

    ob_start();
    echo do_shortcode('[firebase_layout_page]'); // Output page layout wrapper
    ?>

    <main id="fap-main-content" style="position: relative !important; top: 57px !important;">
        <div id="fap-page-container">
            <div id="fap-single-post-container" data-post-id="<?php echo esc_attr($post_id); ?>">
                <p>Loading post...</p>
            </div>
        </div>
    </main>

    <script>

function linkify(text) {
  const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
  return text.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
}

function sharePost(postId, title, content, excerpt) {
  const siteName = "Israel";
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

      // Utility: Escape HTML for safe output
      function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function(m) {
          return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
        });
      }

      // Utility: Human-readable time ago
      function timeAgo(date) {
        const now = new Date();
        const seconds = Math.floor((now - new Date(date)) / 1000);
        const intervals = [
          { label: 'yr.', seconds: 31536000 },
          { label: 'mo.', seconds: 2592000 },
          { label: 'day', seconds: 86400 },
          { label: 'hr.', seconds: 3600 },
          { label: 'min.', seconds: 60 },
          { label: 'sec.', seconds: 1 }
        ];
        for (const interval of intervals) {
          const count = Math.floor(seconds / interval.seconds);
          if (count > 0) return `${count} ${interval.label} ago`;
        }
        return 'just now';
      }

      // Add comment to post
      async function fapAddComment(postId) {
        const user = firebase.auth().currentUser;
        if (!user) return alert("Login required to comment.");

        const input = document.getElementById(`comment-input-${postId}`);
        const text = input?.value.trim();
        if (!text) return alert("Write a comment first.");

        try {
          await firebase.firestore().collection("posts").doc(postId).collection("comments").add({
            userId: user.uid,
            userEmail: user.email,
            text,
            createdAt: firebase.firestore.Timestamp.now()
          });

          // Notify post owner if commenter is not owner
          const postDoc = await firebase.firestore().collection("posts").doc(postId).get();
          const postOwnerId = postDoc.data()?.userId;
          if (postOwnerId && postOwnerId !== user.uid) {
            await firebase.firestore().collection("notifications").add({
              userId: postOwnerId,
              fromUserId: user.uid,
              postId,
              type: "comment",
              read: false,
              createdAt: firebase.firestore.Timestamp.now(),
              message: `${user.email} commented on your post`
            });
          }

          input.value = '';
          await fapLoadComments(postId);
          await fapLoadCommentCount(postId);
        } catch (err) {
          alert("Comment error: " + err.message);
        }
      }

      // Load comments for post
      async function fapLoadComments(postId) {
        const container = document.getElementById(`comment-list-${postId}`);
        if (!container) return;

        try {
          const snapshot = await firebase.firestore()
            .collection("posts").doc(postId).collection("comments")
            .orderBy("createdAt", "asc")
            .get();

          if (snapshot.empty) {
            container.innerHTML = "<p>No comments yet.</p>";
            return;
          }

          container.innerHTML = "";
for (const doc of snapshot.docs) { 
  const c = doc.data();
  const dateObj = c.createdAt?.toDate();
  const timeAgoStr = dateObj ? timeAgo(dateObj) : "Unknown";

  let username = c.userEmail.split('@')[0]; // fallback
  let avatar = "https://www.gravatar.com/avatar?d=mp";
  let userEmail = c.userEmail;

  try {
    const userDoc = await firebase.firestore().collection("users").doc(c.userId).get();
    if (userDoc.exists) {
      const user = userDoc.data();
      if (user.username) username = user.username;
      if (user.avatar) avatar = user.avatar;
    }
  } catch (err) {
    console.warn("Could not fetch user data for comment", err);
  }

  const el = document.createElement("div");
el.innerHTML = `
  <div style="display: flex; gap: 10px; margin-bottom: 12px;">
    <a href="https://indexing.co.il/profile-2?email=${encodeURIComponent(userEmail)}"
       class="no-post-click"
       style="text-decoration: none; color: inherit; display: flex; align-items: flex-start; gap: 10px;">
       
      <img src="${avatar}" alt="${escapeHtml(username)}"
           style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
       
      <div style="flex: 1;">
        <div style="font-size: 14px; color: #787c7e; display: flex; align-items: center; gap: 6px;">
          <span style="font-weight: 600;">${escapeHtml(username)}</span>
          <span>•</span>
          <span>${escapeHtml(timeAgoStr)}</span>
        </div>
        
        <div style="font-size: 15px; margin-top: 4px; word-break: break-word;">
          ${linkify(escapeHtml(c.text))}
        </div>
      </div>
    </a>
  </div>
`;

  container.appendChild(el);
}

        } catch (err) {
          container.innerHTML = `<p>Error: ${escapeHtml(err.message)}</p>`;
        }
      }

      // Load comment count
      async function fapLoadCommentCount(postId) {
        try {
          const snapshot = await firebase.firestore().collection("posts").doc(postId).collection("comments").get();
          const el = document.querySelector(`#fap-single-post-container #comment-count`);
          if (el) el.textContent = snapshot.size;
        } catch (err) {
          console.error(`Failed to load comment count for post ${postId}:`, err);
        }
      }

      // Update like count and toggle heart icon
      async function fapUpdateLikeCount(postId) {
        try {
          const likesSnapshot = await firebase.firestore().collection("likes")
            .where("postId", "==", postId)
            .get();

          const count = likesSnapshot.size;
          const likeCountEl = document.getElementById(`like-count-${postId}`);
          if (likeCountEl) likeCountEl.textContent = count;

          const user = firebase.auth().currentUser;
          const postSection = document.querySelector(`#fap-single-post-container[data-post-id="${postId}"]`);
          const heartGray = postSection?.querySelector('.heart-gray');
          const heartRed = postSection?.querySelector('.heart-red');

          if (user) {
            const liked = likesSnapshot.docs.some(doc => doc.data().userId === user.uid);
            if (heartGray && heartRed) {
              heartGray.style.display = liked ? 'none' : 'inline';
              heartRed.style.display = liked ? 'inline' : 'none';
            }
          }
        } catch (error) {
          console.error("Failed to update like count:", error);
        }
      }

      // Toggle like/unlike on post
      async function fapToggleLike(postId) {
        const user = firebase.auth().currentUser;
        if (!user) {
          alert("You must be logged in to like posts.");
          return;
        }

        try {
          const likesCollection = firebase.firestore().collection("likes");
          const querySnapshot = await likesCollection
            .where("postId", "==", postId)
            .where("userId", "==", user.uid)
            .limit(1)
            .get();

          if (!querySnapshot.empty) {
            // Unlike
            await likesCollection.doc(querySnapshot.docs[0].id).delete();
          } else {
            // Like
            await likesCollection.add({
              postId,
              userId: user.uid,
              likedAt: firebase.firestore.FieldValue.serverTimestamp()
            });

            // Notify post owner if not self
            const postDoc = await firebase.firestore().collection("posts").doc(postId).get();
            const postOwnerId = postDoc.exists ? postDoc.data().userId : null;

            if (postOwnerId && postOwnerId !== user.uid) {
              await firebase.firestore().collection("notifications").add({
                userId: postOwnerId,
                fromUserId: user.uid,
                postId,
                type: "like",
                read: false,
                createdAt: firebase.firestore.FieldValue.serverTimestamp(),
                message: `${user.email} liked your post`
              });
            }
          }

          await fapUpdateLikeCount(postId);
        } catch (error) {
          console.error("Error toggling like:", error);
        }
      }

      // Load post template HTML
      async function loadTemplate() {
        const res = await fetch('/wp-content/plugins/firebase-auth-posting/post/post.html');
        if (!res.ok) throw new Error('Failed to load template');
        return await res.text();
      }

      // Populate post and user data into template
      function populatePostData(post, userData) {
        const postId = post.id || '';
        const container = document.getElementById('fap-single-post-container');
        if (!container) return;

        container.dataset.postId = postId;

        // Set IDs for dynamic elements
        const commentInput = container.querySelector('#comment-input');
        if (commentInput) commentInput.id = `comment-input-${postId}`;

        const postCommentBtn = container.querySelector('#post-comment-btn');
        if (postCommentBtn) {
          postCommentBtn.id = `post-comment-btn-${postId}`;
          postCommentBtn.onclick = () => fapAddComment(postId);
        }

        const commentList = container.querySelector('#comment-list');
        if (commentList) commentList.id = `comment-list-${postId}`;

        const likeCountEl = container.querySelector('.like-count');
        if (likeCountEl) likeCountEl.id = `like-count-${postId}`;

        // Set user display info
        const username = (userData.email || post.userEmail || 'anonymous').split('@')[0];
        const avatarUrl = userData.avatar || 'https://www.gravatar.com/avatar?d=mp';
        const date = post.createdAt ? timeAgo(post.createdAt.toDate()) : 'Unknown';
        const title = post.title || '';
        const content = post.content || '';

        const usernameEl = container.querySelector('#username');
        if (usernameEl) usernameEl.textContent = username;

        const avatarEl = container.querySelector('#avatar');
        if (avatarEl) avatarEl.src = avatarUrl;

        const profileLink = container.querySelector('#profile-link');
        if (profileLink) {
          const userEmail = userData.email || post.userEmail || 'anonymous@example.com';
          profileLink.href = `https://indexing.co.il/profile-2?email=${encodeURIComponent(userEmail)}`;
        }

        const dateEl = container.querySelector('#post-date');
        if (dateEl) dateEl.textContent = date;

        const titleEl = container.querySelector('#post-title');
        if (titleEl) {
          titleEl.textContent = title;
          titleEl.href = `/post?id=${postId}`;
        }

        
        const contentEl = container.querySelector('#post-content');
if (contentEl) {
  const url = content.trim();

  
  function extractYouTubeID(url) {
  const match = url.match(
    /(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/
  );
  return match ? match[1] : null;
}



  function extractVimeoID(url) {
    const match = url.match(/vimeo\.com\/(\d+)/);
    return match ? match[1] : null;
  }

  const ytId = extractYouTubeID(url);
  const vimeoId = extractVimeoID(url);
  const isDirectVideo = url.match(/\.(mp4|webm|ogg)(\?.*)?$/i);

  if (ytId) {
    contentEl.innerHTML = `
      <iframe
        class="single-post-video"
        width="100%"
        height="360"
        src="https://www.youtube.com/embed/${ytId}?enablejsapi=1&autoplay=1&mute=1&playsinline=1"
        frameborder="0"
        allow="autoplay; encrypted-media"
        allowfullscreen></iframe>`;
  } else if (vimeoId) {
    contentEl.innerHTML = `
      <iframe
        class="single-post-video"
        src="https://player.vimeo.com/video/${vimeoId}?autoplay=1&muted=1&playsinline=1"
        width="100%"
        height="360"
        frameborder="0"
        allow="autoplay; fullscreen; picture-in-picture"
        allowfullscreen></iframe>`;
  } else if (isDirectVideo) {
    contentEl.innerHTML = `
      <video autoplay muted playsinline controls style="width:100%;max-height:360px;">
        <source src="${escapeHtml(url)}" type="video/mp4" />
        Your browser does not support the video tag.
      </video>`;
  } else {
    contentEl.innerHTML = linkify(escapeHtml(content));
  }
}


        const likeBtn = container.querySelector('#like-btn');
        if (likeBtn) {
          likeBtn.dataset.likeBtn = postId;
          likeBtn.onclick = () => fapToggleLike(postId);
        }

        const commentCountEl = container.querySelector('#comment-count');
        if (commentCountEl) commentCountEl.textContent = '';

        const commentBtn = container.querySelector('#comment-btn');
        if (commentBtn) commentBtn.onclick = () => {
          const commentInputFocus = document.getElementById(`comment-input-${postId}`);
          if (commentInputFocus) commentInputFocus.focus();
        };

        const backBtn = container.querySelector('#back-btn');
        if (backBtn) backBtn.onclick = () => history.back();


        
        const shareBtn = container.querySelector('#share-btn');
if (shareBtn) {
  shareBtn.onclick = () => {
const excerpt = (content || '').split(' ').slice(0, 25).join(' ');
    sharePost(postId, title, content, excerpt);
  };
}

} // <-- this closes populatePostData


      // Main initialization on DOM ready
      document.addEventListener("DOMContentLoaded", async () => {
        if (!firebase.apps.length) {
          firebase.initializeApp(fapFirebaseConfig);
        }

        const auth = firebase.auth();
        const db = firebase.firestore();

        // Load and render single post
        async function setupSinglePost() {
          const container = document.getElementById('fap-single-post-container');
          if (!container) return;

          try {
            const urlParams = new URLSearchParams(window.location.search);
            const postId = urlParams.get('id') || container.dataset.postId;
            if (!postId) {
              container.innerHTML = "<p>No post ID specified.</p>";
              return;
            }

            const doc = await db.collection("posts").doc(postId).get();
            if (!doc.exists) {
              container.innerHTML = "<p>Post not found.</p>";
              return;
            }

            const post = doc.data();
            post.id = postId;

            let userData = {};
            if (post.userId) {
              const userDoc = await db.collection('users').doc(post.userId).get();
              if (userDoc.exists) userData = userDoc.data();
            }

            const templateHTML = await loadTemplate();
            container.innerHTML = templateHTML;

            populatePostData(post, userData);

            // Load comments, comment count, and like count
            await fapLoadComments(postId);
            await fapUpdateLikeCount(postId);
            await fapLoadCommentCount(postId);
          } catch (err) {
            console.error("Error loading post:", err);
            container.innerHTML = "<p>Error loading post.</p>";
          }
        }

        document.body.addEventListener('touchstart', () => {
  const iframe = document.querySelector('.single-post-video');
  if (iframe && iframe.tagName === "IFRAME" && iframe.src.includes("youtube")) {
    iframe.contentWindow?.postMessage('{"event":"command","func":"unMute","args":""}', '*');
  }
}, { once: true });


        setupSinglePost();

        // Expose functions globally for buttons
        window.fapAddComment = fapAddComment;
        window.fapLoadComments = fapLoadComments;
        window.fapToggleLike = fapToggleLike;
        window.fapUpdateLikeCount = fapUpdateLikeCount;
        window.fapLoadCommentCount = fapLoadCommentCount;
      });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('show_post', 'fap_shortcode_single_post');