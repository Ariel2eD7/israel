<?php
function fap_shortcode_layout_wrapper() 
{
  ob_start();
  // Output HTML from external file
  include plugin_dir_path(__FILE__) . 'shortcode-layout.html';
  ?>

    <!-- ⚡ INSTANT DARK MODE FIX -->
  <script>
    (function() {
      const theme = localStorage.getItem('fap-dark-mode');
      if (theme === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'false') {
        document.documentElement.removeAttribute('data-theme');
      }
    })();
  </script>


  <script>

  

    document.addEventListener("DOMContentLoaded", function () 
    {

       // 🌓 Apply dark mode from localStorage if available
  const savedTheme = localStorage.getItem('fap-dark-mode');
  const root = document.documentElement;

  if (savedTheme === 'true') {
    root.setAttribute('data-theme', 'dark');
  } else if (savedTheme === 'false') {
    root.removeAttribute('data-theme');
  }

        if (window.fapSetupProfileCalled) {
  console.log("fapSetupProfile skipped: already initialized");
  return;
}
window.fapSetupProfileCalled = true;

console.log("fapSetupProfile called");
// Your setup code here...

window.fapAvatarURL = "https://fastly.picsum.photos/id/1020/24/24.jpg"; // fallback

function waitForFirebase() {
  return new Promise((resolve) => {
    function check() {
      if (window.fapFirebase?.auth) {
        resolve(window.fapFirebase.auth);
      } else {
        setTimeout(check, 100);
      }
    }
    check();
  });
}

if (!window.fapProfileInitialized) {
  window.fapProfileInitialized = true;

  waitForFirebase().then((auth) => {
    auth.onAuthStateChanged((user) => {
      if (!user) {
        console.log("❌ No user logged in");
        return;
      }

      const realUser = user._delegate || user;
      const uid = realUser.uid;

      const db = window.fapFirebase.db;
      if (!db) {
        console.error("❌ Firestore not available on window.fapFirebase");
        return;
      }

db.collection("users").doc(uid).get()
  .then((doc) => {
    if (!doc.exists) {
      console.warn("⚠️ No Firestore document found for UID:", uid);
      return;
    }



    const data = doc.data();

const email = realUser.email || "";
let username = email.split('@')[0]; // fallback

if (data.username) {
  username = data.username;
} else if (realUser.displayName) {
  username = realUser.displayName;
} else if (!username) {
  username = "Anonymous";
}

const nameSpan = document.getElementById("nn");
if (nameSpan) {
  nameSpan.textContent = username;
}

// Load and apply dark mode
const prefersDark = data.darkMode === true;
const root = document.documentElement;
const checkbox = document.getElementById('darkmode-switch');
const slider = checkbox ? checkbox.nextElementSibling : null;
const thumb = slider ? slider.nextElementSibling : null;

if (prefersDark) {
  root.setAttribute('data-theme', 'dark');
  if (checkbox) checkbox.checked = true;
  if (slider) slider.style.backgroundColor = '#2563eb';
  if (thumb) thumb.style.transform = 'translateX(18px)';
} else {
  root.removeAttribute('data-theme');
  if (checkbox) checkbox.checked = false;
  if (slider) slider.style.backgroundColor = '#ccc';
  if (thumb) thumb.style.transform = 'translateX(0)';
}



    const avatarUrl = data.avatar || realUser.photoURL || window.fapAvatarURL;

    window.fapAvatarURL = avatarUrl;
    console.log("✅ Avatar URL from Firestore or fallback:", avatarUrl);

    const mainImg = document.getElementById("a");

    const finalAvatarUrl = avatarUrl + (avatarUrl.includes('?') ? '&' : '?') + 't=' + Date.now();

    if (mainImg) {
      mainImg.src = finalAvatarUrl;
      console.log("✅ Updated main image to:", mainImg.src);
    }


  })
  .catch((err) => {
    console.error("❌ Error fetching Firestore user document:", err);
  });

    });
  });
}


      // Utility: Get first focusable element inside an element
      function getFirstFocusableElement(parent) {
        return parent.querySelector(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
      }

      // Reusable functions for bottom sheet open/close
      function openBottomSheet(id) 
      {
        const sheet = document.getElementById(id);
        if (!sheet) { 
          console.warn(`openBottomSheet: Element with id '${id}' not found`);
          return;
        }

        sheet.style.display = "flex";
        sheet.style.pointerEvents = "auto";
        sheet.setAttribute('aria-hidden', 'false');

        const backdrop = sheet.querySelector('.bottom-sheet-backdrop');
        if (backdrop) {
          backdrop.style.opacity = '1';
          backdrop.style.pointerEvents = 'auto';
          backdrop.style.transition = 'opacity 0.3s ease';
        } 

        const content = sheet.querySelector('.bottom-sheet-content');
        if (content) {

          

          // Reset transition and move off-screen
          content.style.transition = 'none';
          content.style.transform = 'translateY(100%)';

          // Force reflow to apply styles immediately
          void content.offsetHeight;

          // Animate to visible state
          content.style.transition = 'transform 0.3s ease';
          content.style.transform = 'translateY(0)';

          
            

              

          // Focus first focusable element inside the sheet
          const focusEl = getFirstFocusableElement(sheet);
          if (focusEl) focusEl.focus();
        }
      }

      function closeBottomSheet(id) 
      {
        const sheet = document.getElementById(id);
        if (!sheet) return;
    
        // Move focus outside before hiding
        const openerSelectorMap = 
        {
          'fap-login-register-bottom-sheet': '#menu-dots-btn',
          'fap-bottom-sheet': '#fap-user-avatar-btn'
        };


        // Move focus back to the button that opened this sheet if possible
        const openerSelector = openerSelectorMap[id];
        if (openerSelector) 
        {
          const opener = document.querySelector(openerSelector);
          if (opener) opener.focus();
        } 
        else 
        {

          // Otherwise blur active element if inside sheet
          if (document.activeElement && sheet.contains(document.activeElement)) 
          {
            document.activeElement.blur();
          }
        }

        const backdrop = sheet.querySelector('.bottom-sheet-backdrop');
        if (backdrop) 
        {
          backdrop.style.opacity = '0';
          backdrop.style.pointerEvents = 'none';
        }

        const content = sheet.querySelector('.bottom-sheet-content');
        if (content) 
        {
          content.style.transform = 'translateY(100%)';

          const onTransitionEnd = () => 
          {
            sheet.style.pointerEvents = 'none';
            sheet.setAttribute('aria-hidden', 'true');
            sheet.style.display = 'none';
            content.removeEventListener('transitionend', onTransitionEnd);
          };
          content.addEventListener('transitionend', onTransitionEnd);
        } 
        else 
        {
          sheet.style.pointerEvents = 'none';
          sheet.setAttribute('aria-hidden', 'true');
          sheet.style.display = 'none';
        }
      }

      // Expose globally
      window.openBottomSheet = openBottomSheet;
      window.closeBottomSheet = closeBottomSheet;


      // --------------------------
// DARK MODE TOGGLE
// --------------------------
window.toggleTheme = () => {
  const root = document.documentElement;
  const checkbox = document.getElementById('darkmode-switch');
  if (!checkbox) return;





  const slider = checkbox.nextElementSibling;
  const thumb = slider ? slider.nextElementSibling : null;
  const isDark = root.getAttribute('data-theme') === 'dark';

  const newTheme = isDark ? 'light' : 'dark';

  // Update UI
  if (newTheme === 'dark') {
    root.setAttribute('data-theme', 'dark');
    checkbox.checked = true;
    if (slider) slider.style.backgroundColor = '#2563eb';
    if (thumb) thumb.style.transform = 'translateX(18px)';
  } else {
    root.removeAttribute('data-theme');
    checkbox.checked = false;
    if (slider) slider.style.backgroundColor = '#ccc';
    if (thumb) thumb.style.transform = 'translateX(0)';
  }

    // ✅ Save to localStorage
  localStorage.setItem('fap-dark-mode', newTheme === 'dark');


  // 🔥 Save to Firebase if logged in
  const user = window.fapFirebase?.auth?.currentUser;
  const db = window.fapFirebase?.db;
  if (user && db) {
    const uid = user.uid || user._delegate?.uid;
    if (uid) {
      db.collection("users").doc(uid).update({
        darkMode: newTheme === 'dark'
      }).then(() => {
        console.log(`🌙 Saved dark mode setting: ${newTheme}`);
      }).catch(err => {
        console.error("❌ Failed to save dark mode preference:", err);
      });
    }
  }
};


// On DOM ready
const checkbox = document.getElementById('darkmode-switch');

if (checkbox) {
  const slider = checkbox.nextElementSibling;
  const thumb = slider ? slider.nextElementSibling : null;

  // Sync switch state on load
  if (document.documentElement.getAttribute('data-theme') === 'dark') {
    checkbox.checked = true;
    if (slider) slider.style.backgroundColor = '#2563eb';
    if (thumb) thumb.style.transform = 'translateX(18px)';
  }

  // Add event listener
  checkbox.addEventListener('change', () => {
    window.toggleTheme();
  });
}




      // Sidebar toggle
      const sidebarBtn = document.getElementById("fap-hamburger-btn"),
            sidebar = document.getElementById("fap-sidebar"),
            overlay = document.getElementById("fap-sidebar-overlay");

      const a = document.getElementById("a");

if (sidebarBtn && sidebar && overlay) {
  sidebarBtn.addEventListener("click", () => {
    const isOpen = sidebar.classList.toggle("open");
    console.log("Sidebar toggled, isOpen =", isOpen);

    if (isOpen) {
      sidebar.style.display = "block";
      overlay.style.display = "block";
    } else {
      sidebar.style.display = "none";
      overlay.style.display = "none";
    }
  });

  overlay.addEventListener("click", () => {
    sidebar.classList.remove("open");
    overlay.style.display = "none";
    sidebar.style.display = "none";
  });
}


      // Search overlay
      const searchBtn = document.getElementById("search-btn"),
            searchOverlay = document.getElementById("fap-search-overlay"),
            backBtn = document.getElementById("fap-search-back"),
            searchInput = document.getElementById("fap-search-input"),
            resultsContainer = document.getElementById("fap-search-results");

      if (searchBtn && searchOverlay && backBtn && searchInput && resultsContainer) {
        searchBtn.addEventListener("click", () => {
          searchOverlay.style.display = "flex";
          searchInput.focus();
        });

        backBtn.addEventListener("click", () => {
          searchOverlay.style.display = "none";
          searchInput.value = "";
          resultsContainer.innerHTML = "";
        });

        searchInput.addEventListener("input", function () {
          const query = this.value.trim().toLowerCase();
          resultsContainer.innerHTML = query
            ? `<p>Results for "<strong>${query}</strong>":</p>
               <ul style="list-style: none; padding: 0; margin: 0;">
                 <li style="margin-bottom: 12px;">
                   <a href="#" style="text-decoration: none; color: #ff4500; font-weight: bold;">
                     Post about ${query}
                   </a>
                 </li>
               </ul>`
            : "";
        });
      }

      // Modal openers
      const loginBtn = document.querySelector(".auth-login-btn"),
            openLoginModal = document.getElementById("open-login-modal"),
            openRegisterModal = document.getElementById("open-register-modal"),
            authModal = document.getElementById("fap-login-popup");

      if (loginBtn && authModal) {
        loginBtn.addEventListener("click", (e) => {
          e.preventDefault();
          authModal.classList.add("visible");
        });
      }

      if (openLoginModal && authModal) {
        openLoginModal.addEventListener("click", (e) => {
          e.preventDefault();
          authModal.classList.add("visible");
          document.getElementById("auth-login-form").classList.add("active");
          document.getElementById("auth-register-form").classList.remove("active");
          document.getElementById("fap-login-register-bottom-sheet").setAttribute("aria-hidden", "true");
        });
      }

      if (openRegisterModal && authModal) {
        openRegisterModal.addEventListener("click", (e) => {
          e.preventDefault();
          authModal.classList.add("visible");
          document.getElementById("auth-login-form").classList.remove("active");
          document.getElementById("auth-register-form").classList.add("active");
          document.getElementById("fap-login-register-bottom-sheet").setAttribute("aria-hidden", "true");
        });
      }

      document.addEventListener("click", function (event) 
      {
        const profileBackdrop = event.target.closest("#fap-bottom-sheet .bottom-sheet-backdrop");
        const profileBtn = event.target.closest("#fap-user-avatar-btn");
        const closeBtn = event.target.closest("#fap-bottom-sheet-close");
  
        if (profileBtn) 
        {
          openBottomSheet("fap-bottom-sheet");
        
        }
 
        if (profileBackdrop || closeBtn) 
        {
          closeBottomSheet("fap-bottom-sheet");
        }
      });


      document.addEventListener("click", function(event) 
      {
        const loginOpenBtn = event.target.closest("#menu-dots-btn");
        const loginCloseBtn = event.target.closest("#fap-login-register-bottom-sheet-close");
        const loginBackdrop = event.target.closest("#fap-login-register-bottom-sheet .bottom-sheet-backdrop");
 
        if (loginOpenBtn) 
        {
          openBottomSheet("fap-login-register-bottom-sheet");
        }

        if (loginCloseBtn || loginBackdrop) 
        {
          closeBottomSheet("fap-login-register-bottom-sheet");
        }
      });


    });
  </script>

  <?php
  return ob_get_clean();
}
add_shortcode('firebase_layout_page', 'fap_shortcode_layout_wrapper');