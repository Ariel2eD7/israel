<?php
/**
 * Injects Firebase Notifications logic into the page as a script tag.
 */

defined('ABSPATH') || exit;

  function fap_output_notifications_script() 
  {
    // Adjust the path to your actual notifications.html location
    include __DIR__ . '/notifications.html';

    ?>
    <script type="text/javascript">
      function setupNotifications(auth, db) 
      {
        const bellBtn = document.getElementById("fap-notification-btn");
        const badge = document.getElementById("fap-notification-count");
        const overlay = document.getElementById("fap-notification-overlay");
        const closeBtn = document.getElementById("fap-notification-close-btn");
        const list = document.getElementById("fap-notification-list");
        console.log({ bellBtn, badge, overlay, closeBtn, list });

        if (!bellBtn || !badge || !overlay || !closeBtn || !list) return;

        let currentUserId = null;

        auth.onAuthStateChanged(async (user) => 
        {
          if (!user) 
          {
            badge.textContent = "0";
            currentUserId = null;
            clearNotificationsList();
            overlay.style.display = "none";
            bellBtn.style.display = "none";
          } 
          else 
          {
            currentUserId = user.uid;
            updateUnreadCount();
            bellBtn.style.display = "inline-flex";
          }
        });

        async function updateUnreadCount() 
        {
          if (!currentUserId) return;
          try 
          {
            const snapshot = await db.collection("notifications")
              .where("userId", "==", currentUserId)
              .where("read", "==", false)
              .get();

            badge.textContent = snapshot.size > 0 ? snapshot.size : "0";
          } 
          catch (err) 
          {
            console.error("Failed to fetch notifications count:", err);
          }
        }

        function clearNotificationsList() 
        {
          list.innerHTML = "";
        }

        async function loadNotifications() 
        {
          if (!currentUserId) 
          {
            list.innerHTML = "<li>Please log in to see notifications.</li>";
            return;
          }

          list.innerHTML = "<li class='fap-notification-loading'>Loading...</li>";

          try 
          {
            const snapshot = await db.collection("notifications")
              .where("userId", "==", currentUserId)
              .orderBy("createdAt", "desc")
              .limit(20)
              .get();

            if (snapshot.empty) 
            {
              list.innerHTML = "<li>No notifications.</li>";
              return;
            }

            list.innerHTML = "";

            snapshot.forEach((doc, index) => 
            {
              const data = doc.data();
              const message = data.message || 'Untitled';
              const postId = data.postId || "";
              const url = postId ? "/post?id=" + encodeURIComponent(postId) : "#";
              const date = data.createdAt ? data.createdAt.toDate().toLocaleString() : '';
              let postTitle = "Untitled";

              if (postId) 
              {
                db.collection("posts").doc(postId).get()
                  .then(postDoc => 
                  {
                    if (postDoc.exists) 
                    {
                      postTitle = postDoc.data().title || "Untitled";
                    }

                    appendNotification(doc.id, message, postTitle, date, url, index, snapshot.size);
                  })
                  .catch(error => 
                  {
                    console.error("Error fetching post title:", error);
                    appendNotification(doc.id, message, null, date, url, index, snapshot.size);
                  });
              } 
              else 
              {
                appendNotification(doc.id, message, null, date, url, index, snapshot.size);
              }
            });

            await markNotificationsRead();
            updateUnreadCount();

          } 
          catch (err) 
          {
            console.error("Failed to load notifications:", err);
            list.innerHTML = `<li class='fap-notification-error'>Error loading notifications: ${escapeHtml(err.message)}</li>`;
          }
        }

        function appendNotification(id, message, title, date, url, index, total) 
        {
          const li = document.createElement('li');
          li.setAttribute('data-id', id);
          const titlePart = title ? ` - ${escapeHtml(title)}` : `Your post: Not Available`;

          li.innerHTML = `
            <a href="${escapeHtml(url)}" class="fap-notification-link" style="text-decoration: none; padding: 16px 24px; border-bottom: 1px solid #eee; display: flex; flex-direction: column; transition: background-color 0.2s ease;"
               onmouseover="this.style.backgroundColor='#f6f7f8'" onmouseout="this.style.backgroundColor='transparent'">
              <span style="font-weight: 600; font-size: 14px; color: var(--text-color); margin-bottom: 6px; line-height: 1.3;">${escapeHtml(message)}${titlePart}</span>
              <span style="font-size: 12px; color: var(--text-color);">${date}</span>
            </a>
          `;

          if (index === total - 1) 
          {
            const a = li.querySelector('a');
            if (a) a.style.borderBottom = 'none';
          }

          list.appendChild(li);
        }

        async function markNotificationsRead() 
        {
          if (!currentUserId) return;
          try 
          {
            const snapshot = await db.collection("notifications")
              .where("userId", "==", currentUserId)
              .where("read", "==", false)
              .get();

            const batch = db.batch();
            snapshot.forEach(doc => {
              batch.update(doc.ref, { read: true });
            });
            await batch.commit();
          } 
          catch (err) 
          {
            console.error("Failed to mark notifications read:", err);
          }
        }

        bellBtn.addEventListener("click", async () => 
        {
          console.log("Bell button clicked");
          if (overlay.style.display === "block") 
          {
            overlay.style.display = "none";
          } 
          else 
          {
            overlay.style.display = "block";
            await loadNotifications();
          }
        });

        closeBtn.addEventListener("click", () => 
        {
          overlay.style.display = "none";
        });

        overlay.addEventListener("click", (e) => 
        {
          if (e.target === overlay) {
            overlay.style.display = "none";
          }
        });
      }

      function escapeHtml(text) 
      {
        const map = 
        {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;',
        };
        return text.replace(/[&<>"']/g, (m) => map[m]);
      }
    </script>
    <?php
}
