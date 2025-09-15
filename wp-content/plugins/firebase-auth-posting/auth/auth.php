<?php
  $modal_path = plugin_dir_path(__FILE__) . 'auth.html';
  if (file_exists($modal_path)) 
  {
    echo file_get_contents($modal_path);
  } 
  else 
  {
    echo '<p style="color:red;">auth.html not found.</p>';
  }
?>

<!-- Modal JS Logic -->
<script>
  document.addEventListener("DOMContentLoaded", function () 
  {
    // Wait until Firebase is initialized in window.fapFirebase before running code
    function waitForFirebaseAuth(callback) 
    {
      if (window.fapFirebase?.auth && window.fapFirebase?.db) 
      {
        callback(window.fapFirebase.auth, window.fapFirebase.db);
      } 
      else 
      {
        setTimeout(() => waitForFirebaseAuth(callback), 100);
      }
    }

    waitForFirebaseAuth(function(auth, db) 
    {

        // 👇 INSERT IT RIGHT AFTER THIS LINE

  const emailInput = document.getElementById("fap-login-email");
  const passwordInput = document.getElementById("fap-login-password");
  const loginBtn = document.getElementById("fap-login-submit");

  function updateButtonState() {
    const emailFilled = emailInput.value.trim().length > 0;
    const passwordFilled = passwordInput.value.trim().length > 0;

    if (emailFilled && passwordFilled) {
      loginBtn.disabled = false;
      loginBtn.style.cursor = "pointer";
      loginBtn.style.opacity = "1";
    } else {
      loginBtn.disabled = true;
      loginBtn.style.cursor = "not-allowed";
      loginBtn.style.opacity = "0.5";
    }
  }

  emailInput.addEventListener("input", updateButtonState);
  passwordInput.addEventListener("input", updateButtonState);

      document.getElementById("fap-google-login-btn").addEventListener("click", async function () {
  const provider = new firebase.auth.GoogleAuthProvider();

  try {
    const result = await auth.signInWithPopup(provider);

    const user = result.user;
    const userDocRef = db.collection("users").doc(user.uid);

    const userDoc = await userDocRef.get();
    if (!userDoc.exists) {
      await userDocRef.set({
        name: user.displayName || "",
        email: user.email,
        avatar: user.photoURL || "https://www.gravatar.com/avatar?d=mp",
        createdAt: firebase.firestore.Timestamp.now(),
      });
    }

    alert("Logged in with Google.");
    document.getElementById("fap-login-popup").classList.remove("visible");

  } catch (error) {
    alert("Google Sign-In error: " + error.message);
    console.error("Google Sign-In Error:", error);
  }
});


      const loginForm = document.getElementById("auth-login-form"),
        registerForm = document.getElementById("auth-register-form"),
        switchToRegister = document.getElementById("switch-to-register"),
        switchToLogin = document.getElementById("switch-to-login"),
        closeAuthBtn = document.getElementById("auth-close-btn"),
        authModal = document.getElementById("fap-login-popup");

      const authModalOverlay = document.querySelector('.auth-flow-modal-overlay');

      const observer = new MutationObserver(() => 
      {
        if (authModalOverlay.classList.contains('visible')) 
        {
          authModalOverlay.style.display = 'flex';
        } 
        else 
        {
          authModalOverlay.style.display = 'none';
        }
      });



      if (authModalOverlay) 
      {
        observer.observe(authModalOverlay, { attributes: true, attributeFilter: ['class'] });

        // Set initial visibility based on existing class
        if (authModalOverlay.classList.contains('visible')) 
        {
          authModalOverlay.style.display = 'flex';
        } 
        else 
        {
          authModalOverlay.style.display = 'none';
        }
      }

      // Modal switching
      if (closeAuthBtn && authModal) 
      {
        closeAuthBtn.addEventListener("click", () => authModal.classList.remove("visible"));
        authModal.addEventListener("click", (e) => 
        {
          if (e.target === authModal) authModal.classList.remove("visible");
        });
      }

      if (switchToRegister && loginForm && registerForm) 
      {
        switchToRegister.addEventListener("click", (e) => 
        {
          e.preventDefault();
          loginForm.classList.remove("active");
          registerForm.classList.add("active");
registerForm.style.display = "flex"; // ✅ RIGHT
          loginForm.style.display = "none";
        });
      }

      if (switchToLogin && loginForm && registerForm) 
      {
        switchToLogin.addEventListener("click", (e) => 
        {
          e.preventDefault();
          registerForm.classList.remove("active");
          loginForm.classList.add("active");
          registerForm.style.display = "none";
loginForm.style.display = "flex"; // ✅ RIGHT
        });
      }

      // ✅ Registration handler
      window.fapRegister = async function () 
      {
        const name = document.getElementById('fap-register-name').value.trim();
        const email = document.getElementById('fap-register-email').value.trim();
        const password = document.getElementById('fap-register-password').value;

        if (!name || !email || !password) return alert("All fields required.");

        try 
        {
          const userCredential = await auth.createUserWithEmailAndPassword(email, password);
          await db.collection("users").doc(userCredential.user.uid).set(
          {
            name, email, avatar: "https://www.gravatar.com/avatar?d=mp", createdAt: firebase.firestore.Timestamp.now()
          });
          alert("Registered successfully.");
          document.getElementById("fap-login-popup").classList.remove("visible");
        } 
        catch (err) 
        {
          alert("Registration error: " + err.message);
        }
      };

      // ✅ Login handler
      window.fapLogin = async function () 
      {
        const email = document.getElementById('fap-login-email').value.trim();
        const password = document.getElementById('fap-login-password').value;
  
        if (!email || !password) return alert("Email and password required.");

        try 
        {
          await auth.signInWithEmailAndPassword(email, password);
          alert("Logged in.");
          document.getElementById("fap-login-popup").classList.remove("visible");
        } 
        catch (err) 
        {
          alert("Login error: " + err.message);
        }
      };

      // ✅ Logout handler
      window.fapLogout = async function () 
      {
        try 
        {
          await auth.signOut();
          alert("Logged out.");

          // Close profile bottom sheet immediately after logout
          const profileBottomSheet = document.getElementById('fap-bottom-sheet');
          if (profileBottomSheet) 
          {
            profileBottomSheet.setAttribute('aria-hidden', 'true');
          }

        } 
        catch (err) 
        {
          alert("Logout error: " + err.message);
        }
      };


      // ✅ Always bind click handler on 3-dots button (login/register bottom sheet)
      const menuDotsBtn = document.getElementById('menu-dots-btn');
      const loginRegisterBottomSheet = document.getElementById('fap-login-register-bottom-sheet');
      const loginRegisterCloseBtn = document.getElementById('fap-login-register-bottom-sheet-close');
      const backdropLoginRegister = document.querySelector('#fap-login-register-bottom-sheet .bottom-sheet-backdrop');

      if (menuDotsBtn && loginRegisterBottomSheet) 
      {
        menuDotsBtn.addEventListener('click', () => 
        {
          loginRegisterBottomSheet.setAttribute('aria-hidden', 'false');
        });
      }
      if (loginRegisterCloseBtn) 
      {
        loginRegisterCloseBtn.addEventListener('click', () => 
        {
          loginRegisterBottomSheet.setAttribute('aria-hidden', 'true');
        });
      }
      if (backdropLoginRegister) 
      {
        backdropLoginRegister.addEventListener('click', () => 
        {
          loginRegisterBottomSheet.setAttribute('aria-hidden', 'true');
        });
      }

      // ✅ Auth state listener
      function setupAuth(auth, db) 
      {
        auth.onAuthStateChanged(async (user) => 
        {
          const profileSection = document.getElementById('fap-user-profile');
          const myPostsSection = document.getElementById('fap-my-posts-section');
          const postForm = document.getElementById('fap-post-form');
          const logoutSection = document.getElementById('fap-logout-section');
          const loginButton = document.getElementById('loginButton');

          if (user) 
          {
            if (loginButton) loginButton.style.display = 'none';
            if (profileSection) profileSection.style.display = 'block';
            if (postForm) postForm.style.display = 'block';
            if (logoutSection) logoutSection.style.display = 'block';
            if (myPostsSection) myPostsSection.style.display = 'block';

            try 
            {
              const userDoc = await db.collection("users").doc(user.uid).get();
              let data = userDoc.exists ? userDoc.data() : 
              {
                name: '',
                avatar: "https://www.gravatar.com/avatar?d=mp",
                email: user.email,
                createdAt: firebase.firestore.Timestamp.now()
              };
              if (!userDoc.exists) 
              {
                await db.collection("users").doc(user.uid).set(data);
              }

              const avatar = document.getElementById('fap-profile-avatar');
              const email = document.getElementById('fap-profile-email');
              const editName = document.getElementById('fap-edit-name');
              const editAvatar = document.getElementById('fap-edit-avatar');

              if (avatar) avatar.src = data.avatar;
              if (email) email.innerText = data.email;
              if (editName) editName.value = data.name;
              if (editAvatar) editAvatar.value = data.avatar;

              if (window.fapFetchMyPosts) window.fapFetchMyPosts();

            } 
            catch (err) 
            {
              console.error("Failed to load profile:", err);
            }

          } 
          else 
          {
            if (loginButton) loginButton.style.display = 'inline-block';
            if (profileSection) profileSection.style.display = 'none';
            if (postForm) postForm.style.display = 'none';
            if (logoutSection) logoutSection.style.display = 'none';
            if (myPostsSection) myPostsSection.style.display = 'none';
          }
        });
      }

      // ✅ Bottom sheet logic with proper event binding only once
      auth.onAuthStateChanged(async (user) => 
      {
        let avatarContainer = document.getElementById('fap-user-avatar-btn');
        let menuDotsBtn = document.getElementById('menu-dots-btn');
        const plusBtn = document.getElementById('create-post');  // <-- new button
        const profileBottomSheet = document.getElementById('fap-bottom-sheet');
        const loginRegisterBottomSheet = document.getElementById('fap-login-register-bottom-sheet');
        let profileCloseBtn = document.getElementById('fap-bottom-sheet-close');
        let loginRegisterCloseBtn = document.getElementById('fap-login-register-bottom-sheet-close');
        let backdropProfile = document.querySelector('#fap-bottom-sheet .bottom-sheet-backdrop');
        let backdropLoginRegister = document.querySelector('#fap-login-register-bottom-sheet .bottom-sheet-backdrop');

        if (!avatarContainer || !menuDotsBtn || !profileBottomSheet || !loginRegisterBottomSheet) return;

        // Remove previous event listeners by cloning nodes (to avoid duplicates)
        function replaceWithClone(el) 
        {
          if (!el) return null;
          const newEl = el.cloneNode(true);
          el.parentNode.replaceChild(newEl, el);
          return newEl;
        }

        // Clone buttons to clear old listeners
        avatarContainer = replaceWithClone(avatarContainer) || avatarContainer;
        menuDotsBtn = replaceWithClone(menuDotsBtn) || menuDotsBtn;
        if(profileCloseBtn) profileCloseBtn = replaceWithClone(profileCloseBtn) || profileCloseBtn;
        if(loginRegisterCloseBtn) loginRegisterCloseBtn = replaceWithClone(loginRegisterCloseBtn) || loginRegisterCloseBtn;
        if(backdropProfile) backdropProfile = replaceWithClone(backdropProfile) || backdropProfile;
        if(backdropLoginRegister) backdropLoginRegister = replaceWithClone(backdropLoginRegister) || backdropLoginRegister;


       
        if (user) 
        {
          // Show avatar button, hide 3-dots button
          avatarContainer.style.display = 'inline-flex';
          menuDotsBtn.style.display = 'none';


          if (plusBtn) 
          {
            plusBtn.style.display = 'inline-flex';   // Show "+" button when logged in
            // Optionally add event listener here if needed
            plusBtn.onclick = () => 
           {
             // Example: open a post creation modal or sheet
             console.log("Plus button clicked!");
             // You could open a modal here, e.g.:
             // openBottomSheet('fap-create-post-sheet');
           };
          }


          try 
          {
            const userDoc = await db.collection('users').doc(user.uid).get();
            const userData = userDoc.exists ? userDoc.data() : {};
            const avatarUrl = userData.avatar || user.photoURL || 'https://www.gravatar.com/avatar?d=mp';

            let avatarImg = avatarContainer.querySelector('img');
            if (!avatarImg) 
            {
              avatarImg = document.createElement('img');
              avatarImg.style.width = '40px';
              avatarImg.style.height = '40px';
              avatarImg.style.borderRadius = '50%';
              avatarImg.style.objectFit = 'cover';
              avatarImg.style.border = '2px solid #fff';
              avatarContainer.appendChild(avatarImg);
            }
            avatarImg.src = avatarUrl;
            avatarImg.alt = user.displayName || 'User Avatar';
  
          } 
          catch (err) 
          {
            console.error('Error fetching user avatar:', err);
          }

          avatarContainer.onclick = () => 
          {
            profileBottomSheet.setAttribute('aria-hidden', 'false');
          };

          if (profileCloseBtn) 
          {
            profileCloseBtn.addEventListener('click', () => 
            {
              profileBottomSheet.setAttribute('aria-hidden', 'true');
            });
          }

          if (backdropProfile) 
          {
            backdropProfile.addEventListener('click', () => 
            {
              profileBottomSheet.setAttribute('aria-hidden', 'true');
            });
          }
  
        } 
        else 
        {
          // Show 3-dots button, hide avatar button
          avatarContainer.style.display = 'none';
          menuDotsBtn.style.display = 'inline-flex';


          if (plusBtn) 
          {
            plusBtn.style.display = 'none';   // Hide "+" button when logged out
            plusBtn.onclick = null;           // Remove click handler just in case
          }


          menuDotsBtn.addEventListener('click', () => 
          {
            loginRegisterBottomSheet.setAttribute('aria-hidden', 'false');
          });

          if (loginRegisterCloseBtn) 
          {
            loginRegisterCloseBtn.addEventListener('click', () => 
            {
              loginRegisterBottomSheet.setAttribute('aria-hidden', 'true');
            });
          }

          if (backdropLoginRegister) 
          {
            backdropLoginRegister.addEventListener('click', () => 
           {
              loginRegisterBottomSheet.setAttribute('aria-hidden', 'true');
           });
          }  
        }
      });

      
    // Initialize auth state listener
    setupAuth(auth, db);

  }); // end waitForFirebaseAuth
});
</script>